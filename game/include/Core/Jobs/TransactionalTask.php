<?php

namespace Core\Jobs;

use Core\Database\DB;

/**
 * Runs the effect of one scheduled row (building, movement, ...) inside a single transaction.
 *
 * Lock-wait timeouts (1205) and deadlocks (1213) are transient: the transaction is rolled back and re-run a few times
 * with a short backoff and they never count as task failures. Because a retry re-runs the effect, effects must only
 * write to the database inside the transaction (cache invalidation is idempotent and allowed).
 *
 * Any other failure is recorded in scheduled_task_failures. After MAX_ATTEMPTS the row is quarantined: its full
 * contents are stored as JSON in scheduled_task_failures.payload (with quarantined_at set) and it is removed from the
 * hot table so it stops blocking the queue. Nothing is lost; an admin can restore it with
 * \Core\Jobs\TransactionalTask::requeue('<table>', <id>), which re-inserts the row and clears the ledger entry.
 * Quarantined rows can be listed with:
 *   SELECT task_table, task_id, last_error, quarantined_at FROM scheduled_task_failures WHERE quarantined_at IS NOT NULL
 */
final class TransactionalTask
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_RETRY_BACKOFF_US = [50000, 150000, 400000];
    private const LOCK_ERRORS = [1205, 1213];
    private static ?bool $failureLedgerAvailable = null;
    private static ?bool $quarantineColumnAvailable = null;
    private const TABLES = [
        'building_upgrade',
        'demolition',
        'movement',
        'research',
        'send',
    ];

    public static function consume(string $table, int $id, callable $effect, ?callable $beforeLock = null): bool
    {
        return self::executeWithRetry($table, $id, $effect, true, $beforeLock);
    }

    /**
     * Like consume() but the effect decides whether the row is removed (the row is only locked and re-read).
     */
    public static function process(string $table, int $id, callable $effect, ?callable $beforeLock = null): bool
    {
        return self::executeWithRetry($table, $id, $effect, false, $beforeLock);
    }

    /**
     * Restores a quarantined row into its table and clears its ledger entry.
     */
    public static function requeue(string $table, int $id): bool
    {
        self::assertTable($table);
        if (!self::hasFailureLedger() || !self::hasQuarantineColumn()) {
            throw new \RuntimeException('The scheduled task failure ledger has no quarantine support.');
        }

        $db = DB::getInstance();
        if (!$db->begin_transaction()) {
            throw new \RuntimeException('Unable to begin requeue transaction.');
        }
        try {
            // Use the same lock order as completion and failure handling: hot row, then ledger. The hot row is
            // normally absent for a quarantined task, but this protects the gap from a concurrent transition.
            $hotRow = $db->query("SELECT id FROM $table WHERE id=$id FOR UPDATE");
            if ($hotRow === false) {
                throw new \RuntimeException("Unable to lock quarantined task $table #$id.");
            }
            if ($hotRow->num_rows) {
                $db->rollback();

                return false;
            }
            $escapedTable = $db->real_escape_string($table);
            $payload = $db->fetchScalar(
                "SELECT payload FROM scheduled_task_failures
                 WHERE task_table='$escapedTable' AND task_id=$id AND quarantined_at IS NOT NULL FOR UPDATE"
            );
            if ($payload === false) {
                $db->rollback();

                return false;
            }
            $row = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($row) || !$row || (int)($row['id'] ?? 0) !== $id) {
                throw new \RuntimeException("Quarantined payload for $table #$id is not restorable.");
            }
            $columns = [];
            $values = [];
            foreach ($row as $column => $value) {
                if (!preg_match('/^[A-Za-z0-9_]+$/', (string)$column)) {
                    throw new \RuntimeException("Invalid column name in quarantined payload for $table #$id.");
                }
                $columns[] = "`$column`";
                $values[] = $value === null ? 'NULL' : "'" . $db->real_escape_string((string)$value) . "'";
            }
            if (!$db->query("INSERT INTO $table (" . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ')')
                || $db->affectedRows() !== 1) {
                throw new \RuntimeException("Unable to restore quarantined task $table #$id.");
            }
            if (!$db->query("DELETE FROM scheduled_task_failures WHERE task_table='$escapedTable' AND task_id=$id")
                || $db->affectedRows() !== 1) {
                throw new \RuntimeException("Unable to clear the quarantine ledger for $table #$id.");
            }
            if (!$db->commit()) {
                throw new \RuntimeException('Unable to commit requeue.');
            }

            return true;
        } catch (\Throwable $exception) {
            self::safeRollback();

            throw $exception;
        }
    }

    private static function assertTable(string $table): void
    {
        if (!in_array($table, self::TABLES, true)) {
            throw new \InvalidArgumentException('Unsupported transactional task table.');
        }
    }

    private static function executeWithRetry(
        string $table,
        int $id,
        callable $effect,
        bool $consume,
        ?callable $beforeLock
    ): bool {
        self::assertTable($table);
        $retry = 0;
        while (true) {
            try {
                return self::execute($table, $id, $effect, $consume, $beforeLock);
            } catch (\Throwable $exception) {
                if (!self::isLockError($exception)) {
                    self::recordFailure($table, $id, $exception);

                    throw $exception;
                }
                if (!isset(self::LOCK_RETRY_BACKOFF_US[$retry])) {
                    // Contention is not a task failure; the row stays queued and is picked up on the next pass.
                    throw $exception;
                }
                usleep(self::LOCK_RETRY_BACKOFF_US[$retry++]);
            }
        }
    }

    private static function execute(
        string $table,
        int $id,
        callable $effect,
        bool $consume,
        ?callable $beforeLock
    ): bool {
        $db = DB::getInstance();
        if (!$db->begin_transaction()) {
            throw new \RuntimeException('Unable to begin scheduled task transaction.');
        }

        try {
            if ($beforeLock !== null) {
                $beforeLock();
            }
            $result = $db->query("SELECT * FROM $table WHERE id=$id FOR UPDATE");
            if (!$result || !$result->num_rows) {
                $db->rollback();

                return false;
            }
            $row = $result->fetch_assoc();
            $effect($row);
            $completed = $consume;
            if ($consume) {
                // The effect may already remove the hot row inside this same
                // transaction (BuildingAction::downgrade deletes demolition by
                // kid+field). That is completion, not a race — only fail when
                // a delete of a still-present row does not apply.
                $remaining = $db->query("SELECT id FROM $table WHERE id=$id FOR UPDATE");
                if ($remaining === false) {
                    throw new \RuntimeException('Unable to verify scheduled task completion.');
                }
                if ($remaining->num_rows) {
                    $db->query("DELETE FROM $table WHERE id=$id");
                    if ($db->affectedRows() !== 1) {
                        throw new \RuntimeException('Scheduled task disappeared before completion.');
                    }
                }
            } else {
                // Master-build rows stay in the queue while workers/resources are unavailable. A no-op pass is
                // not proof that a previous failure has recovered, so preserve its ledger until the effect removes
                // the hot row itself.
                $remaining = $db->query("SELECT id FROM $table WHERE id=$id");
                if ($remaining === false) {
                    throw new \RuntimeException('Unable to verify scheduled task completion.');
                }
                $completed = !$remaining->num_rows;
            }
            if ($completed && self::hasFailureLedger()) {
                if (!$db->query("DELETE FROM scheduled_task_failures WHERE task_table='" .
                    $db->real_escape_string($table) . "' AND task_id=$id")) {
                    throw new \RuntimeException('Unable to clear the scheduled task failure ledger.');
                }
            }
            if (!$db->commit()) {
                throw new \RuntimeException('Unable to commit scheduled task.');
            }

            return true;
        } catch (\Throwable $exception) {
            self::safeRollback();

            throw $exception;
        }
    }

    private static function isLockError(\Throwable $exception): bool
    {
        if ($exception instanceof \mysqli_sql_exception && in_array((int)$exception->getCode(), self::LOCK_ERRORS, true)) {
            return true;
        }

        // Non-strict mysqli reporting surfaces the error text through trigger_error / ErrorException.
        return (bool)preg_match('/Deadlock found|Lock wait timeout exceeded/i', $exception->getMessage());
    }

    private static function safeRollback(): void
    {
        try {
            DB::getInstance()->rollback();
        } catch (\Throwable $rollbackError) {
            \logError('Scheduled task rollback failed: ' . $rollbackError->getMessage());
        }
    }

    private static function recordFailure(string $table, int $id, \Throwable $error): void
    {
        try {
            self::writeFailure($table, $id, $error);
        } catch (\Throwable $ledgerError) {
            \logError("Unable to record scheduled task failure for $table #$id: " . $ledgerError->getMessage());
        }
    }

    private static function writeFailure(string $table, int $id, \Throwable $error): void
    {
        $db = DB::getInstance();
        if (!self::hasFailureLedger()) {
            \logError('Scheduled task failure ledger is unavailable:' . $error->getMessage());

            return;
        }
        if (!$db->begin_transaction()) {
            throw new \RuntimeException('Unable to begin scheduled task failure transaction.');
        }
        $escapedTable = $db->real_escape_string($table);
        $message = $db->real_escape_string(substr($error->getMessage(), 0, 1000));
        $now = time();
        $quarantined = false;
        try {
            // Lock task then ledger, matching execute() and requeue(). If another worker already completed it,
            // there is no failure state to create or quarantine.
            $result = $db->query("SELECT * FROM $table WHERE id=$id FOR UPDATE");
            if (!$result || !$result->num_rows) {
                if (!$db->commit()) {
                    throw new \RuntimeException('Unable to commit an empty scheduled task failure transition.');
                }

                return;
            }
            $row = $result->fetch_assoc();
            $ledger = $db->query(
                "SELECT attempts, quarantined_at FROM scheduled_task_failures
                 WHERE task_table='$escapedTable' AND task_id=$id FOR UPDATE"
            );
            if ($ledger === false) {
                throw new \RuntimeException('Unable to lock the scheduled task failure ledger.');
            }
            $payload = $db->real_escape_string(json_encode($row, JSON_THROW_ON_ERROR));
            if (!$ledger->num_rows) {
                if (!$db->query(
                    "INSERT INTO scheduled_task_failures
                        (task_table, task_id, attempts, payload, last_error, first_failed_at, last_failed_at)
                     VALUES ('$escapedTable', $id, 1, '$payload', '$message', $now, $now)"
                ) || $db->affectedRows() !== 1) {
                    throw new \RuntimeException('Unable to create the scheduled task failure ledger entry.');
                }
                $attempts = 1;
            } else {
                $failure = $ledger->fetch_assoc();
                if ($failure['quarantined_at'] !== null) {
                    if (!$db->commit()) {
                        throw new \RuntimeException('Unable to commit an unchanged scheduled task failure transition.');
                    }

                    return;
                }
                if (!$db->query(
                    "UPDATE scheduled_task_failures SET attempts=attempts+1, payload='$payload', last_error='$message',
                        last_failed_at=$now WHERE task_table='$escapedTable' AND task_id=$id AND quarantined_at IS NULL"
                ) || $db->affectedRows() !== 1) {
                    throw new \RuntimeException('Unable to update the scheduled task failure ledger entry.');
                }
                $attempts = (int)$failure['attempts'] + 1;
            }
            if ($attempts >= self::MAX_ATTEMPTS && self::hasQuarantineColumn()) {
                if (!$db->query(
                    "UPDATE scheduled_task_failures SET quarantined_at=$now
                     WHERE task_table='$escapedTable' AND task_id=$id AND attempts>=" . self::MAX_ATTEMPTS .
                    " AND quarantined_at IS NULL"
                ) || $db->affectedRows() !== 1) {
                    throw new \RuntimeException('Scheduled task quarantine ledger transition was not applied.');
                }
                if (!$db->query("DELETE FROM $table WHERE id=$id") || $db->affectedRows() !== 1) {
                    throw new \RuntimeException('Scheduled task disappeared before quarantine completion.');
                }
                $quarantined = true;
            }
            if (!$db->commit()) {
                throw new \RuntimeException('Unable to commit scheduled task failure transition.');
            }
        } catch (\Throwable $exception) {
            self::safeRollback();

            throw $exception;
        }
        if ($quarantined) {
            \logError("Scheduled task $table #$id quarantined after repeated failures.");
        } elseif ($attempts >= self::MAX_ATTEMPTS && !self::hasQuarantineColumn()) {
            \logError("Scheduled task $table #$id keeps failing but the ledger has no quarantine column; leaving it queued.");
        }
    }

    private static function hasFailureLedger(): bool
    {
        if (self::$failureLedgerAvailable !== null) {
            return self::$failureLedgerAvailable;
        }

        $result = DB::getInstance()->query("SHOW TABLES LIKE 'scheduled_task_failures'");

        return self::$failureLedgerAvailable = $result && $result->num_rows === 1;
    }

    private static function hasQuarantineColumn(): bool
    {
        if (self::$quarantineColumnAvailable !== null) {
            return self::$quarantineColumnAvailable;
        }

        $result = DB::getInstance()->query("SHOW COLUMNS FROM scheduled_task_failures LIKE 'quarantined_at'");

        return self::$quarantineColumnAvailable = $result && $result->num_rows === 1;
    }
}
