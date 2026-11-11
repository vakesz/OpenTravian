<?php

namespace Core\Database;

use Core\Config;
use Core\ErrorHandler;
use const BACKUP_PATH;
use function logError;
use function miliseconds;

class DB
{
    /** @var \mysqli|null */
    public $mysqli;
    /** @var int|null */
    private $lastPing;
    /** @var \Core\Database\DB|null */
    private static $_self;
    /** @var mixed */
    private $details;
    /** @var int */
    private $transactionDepth = 0;
    /** Request profiling for the dev footer (Core\Dev\RequestTiming): off unless a developer opted in. */
    public static bool $profile = false;
    public static int $queryCount = 0;
    public static float $queryTime = 0.0;

    /**
     * @param list<mixed>|list<mixed> $details
     */
    public function setDatabaseDetails($details): void
    {
        $this->details = $details;
    }

    /**
     * @return \Core\Database\DB
     */
    public static function getInstance()
    {
        if (!(self::$_self instanceof self)) {
            self::$_self = new self();
        }
        return self::$_self;
    }

    /**
     * @param bool $connect
     */
    public function __construct($connect = true)
    {
        if (!$this->isCLI()) {
            if ($connect) {
                $this->doConnect();
            }
        }
    }

    public function isCLI(): bool
    {
        return php_sapi_name() == 'cli' && !defined("IS_INSTALLER");
    }

    public function doConnect(): void
    {
        $config = Config::getInstance();
        $this->details = [
            $config->db->hostname,
            $config->db->username,
            $config->db->password,
            $config->db->database,
            $config->db->charset,
        ];
        if ($this->isCLI()) {
            $this->real_connect("p:" . $this->details[0],
                $this->details[1],
                $this->details[2],
                $this->details[3],
                3306,
                NULL);
        } else {
            $this->real_connect($this->details[0], $this->details[1], $this->details[2], $this->details[3], 3306, NULL);
        }
        if ($this->mysqli->connect_error) {
            trigger_error("Failed to connect to mysql server thought socket.");
            echo 'Failed to connect to mysql server thought socket.';
            exit(1);
        }
        $this->set_charset($this->details[4]);
        $this->lastPing = time();
    }

    public function reconnect(): void
    {
        if (!$this->details) {
            $config = Config::getInstance();
            $this->details = [
                $config->db->hostname,
                $config->db->username,
                $config->db->password,
                $config->db->database,
                $config->db->charset,
            ];
        }
        if ($this->isCLI()) {
            $this->real_connect("p:" . $this->details[0],
                $this->details[1],
                $this->details[2],
                $this->details[3],
                3306,
                NULL);
        } else {
            $this->real_connect($this->details[0], $this->details[1], $this->details[2], $this->details[3], 3306, NULL);
        }
        if ($this->mysqli->connect_error) {
            trigger_error("Failed to connect to mysql server thought socket.");
            echo 'Failed to connect to mysql server thought socket.';
            exit(1);
        }
        $this->set_charset($this->details[4]);
        $this->lastPing = time();
    }

    /**
     * @return mixed
     */
    public function forceNewDatabase()
    {
        $this->reconnect();
        return DB::getInstance();
    }

    public function more_results(): bool
    {
        return $this->mysqli->more_results();
    }

    /**
     * @param mixed $query
     * @return bool|null
     */
    public function multi_query($query)
    {
        try {
            if (!$this->mysqli->multi_query($query)) {
                trigger_error("Mysqli multi query error: " . $this->mysqli->error, E_USER_WARNING);

                return false;
            }
            do {
                $result = $this->mysqli->store_result();
                if ($result instanceof \mysqli_result) {
                    $result->free();
                }
                if (!$this->more_results()) {
                    return true;
                }
                if (!$this->next_result()) {
                    trigger_error("Mysqli multi query error: " . $this->mysqli->error, E_USER_WARNING);

                    return false;
                }
            } while (true);
        } catch (\mysqli_sql_exception $exception) {
            trigger_error("Mysqli multi query error: " . $exception->getMessage(), E_USER_WARNING);

            return false;
        }
    }

    /**
     * @param mixed $host
     * @param mixed $username
     * @param mixed $passwd
     * @param mixed $dbname
     * @param int|null $port
     * @param null $socket
     */
    public function real_connect($host = NULL, $username = NULL, $passwd = NULL, $dbname = NULL, $port = NULL, $socket = NULL): bool
    {
        $this->mysqli = new \mysqli($host, $username, $passwd, $dbname, $port, $socket);
        $this->transactionDepth = 0;
        if ($this->mysqli->connect_errno) {
            return false;
        }
        $this->set_charset("utf8");
        $this->lastPing = time();
        return true;
    }

    /**
     * @param mixed $charset
     */
    public function set_charset($charset): bool
    {
        return $this->mysqli->set_charset($charset);
    }

    public function ping(): bool
    {
        if (!($this->mysqli instanceof \mysqli)) {
            return false;
        }
        try {
            $result = $this->mysqli->query('SELECT 1');
            if ($result instanceof \mysqli_result) {
                $result->free();
                return true;
            }
        } catch (\mysqli_sql_exception $exception) {
            return false;
        }
        return false;
    }

    /**
     * @param bool $force
     * @return mixed
     */
    public function checkConnection($force = false)
    {
        if (!($this->mysqli instanceof \mysqli)) {
            $this->forceNewDatabase();
            sleep(2);
        }
        $ping = TRUE;
        if ((time() - $this->lastPing) > 100 || $force) {
            $ping = $this->ping();
            $try = 0;
            while (!$ping && $try <= 20) {
                ++$try;
                $this->forceNewDatabase();
                $ping = $this->ping();
                logError("Could not ping MySQL");
                sleep(1);
            }
            if ($ping) $this->lastPing = time();
        }
        return $ping;
    }

    /**
     * @param mixed $host
     * @param mixed $user
     * @param mixed $password
     * @param mixed $database
     * @param mixed $port
     * @param mixed $socket
     */
    public function connect($host, $user, $password, $database, $port, $socket): void
    {
        $this->mysqli->connect($host, $user, $password, $database, $port, $socket);
        $this->lastPing = time();
    }

    public function begin_transaction(): bool
    {
        if ($this->transactionDepth === 0) {
            if (!$this->mysqli->begin_transaction()) {
                return false;
            }
            $this->transactionDepth = 1;

            return true;
        }

        $savepoint = $this->savepointName($this->transactionDepth);
        if (!$this->mysqli->query("SAVEPOINT $savepoint")) {
            return false;
        }
        ++$this->transactionDepth;

        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transactionDepth > 0;
    }

    public function commit(): bool
    {
        if ($this->transactionDepth <= 1) {
            $committed = $this->mysqli->commit();
            if ($committed) {
                $this->transactionDepth = 0;
            }

            return $committed;
        }

        $savepoint = $this->savepointName($this->transactionDepth - 1);
        if (!$this->mysqli->query("RELEASE SAVEPOINT $savepoint")) {
            return false;
        }
        --$this->transactionDepth;

        return true;
    }

    public function rollback(): bool
    {
        if ($this->transactionDepth <= 1) {
            $rolledBack = $this->mysqli->rollback();
            if ($rolledBack) {
                $this->transactionDepth = 0;
            }

            return $rolledBack;
        }

        $savepoint = $this->savepointName($this->transactionDepth - 1);
        if (!$this->mysqli->query("ROLLBACK TO SAVEPOINT $savepoint")) {
            return false;
        }
        if (!$this->mysqli->query("RELEASE SAVEPOINT $savepoint")) {
            return false;
        }
        --$this->transactionDepth;

        return true;
    }

    private function savepointName(int $depth): string
    {
        return 'travian_transaction_' . $depth;
    }

    public function next_result(): bool
    {
        return $this->mysqli->next_result();
    }

    /**
     * @param mixed $query
     * @param mixed $resultmode
     * @return \mysqli_result|bool
     */
    public function query($query, $resultmode = MYSQLI_STORE_RESULT)
    {
        if ($this->isCLI() && !$this->checkConnection()) {
            trigger_error("GONE AWAY | Mysqli Error: " . $this->mysqli->error . ' in Query: ' . $query, E_USER_WARNING);
            return false;
        }
        $began = self::$profile ? microtime(true) : 0.0;
        $status = $this->mysqli->query($query, $resultmode);
        if (self::$profile) {
            ++self::$queryCount;
            self::$queryTime += microtime(true) - $began;
        }
        if (!empty($this->mysqli->error)) {
            trigger_error("Mysqli Error: " . $this->mysqli->error . ' in Query: ' . $query, E_USER_WARNING);
        }
        return $status;
    }

    /**
     * @param mixed $query
     * @param array<mixed> $parameters
     * @return \mysqli_stmt|bool
     */
    public function run($query, array $parameters = [])
    {
        $began = self::$profile ? microtime(true) : 0.0;
        $statement = $this->mysqli->prepare($query);
        if ($statement === false) {
            trigger_error("Mysqli prepare error: " . $this->mysqli->error, E_USER_WARNING);
            return false;
        }
        if (!$statement->execute($parameters)) {
            trigger_error("Mysqli execute error: " . $statement->error, E_USER_WARNING);
            return false;
        }
        if (self::$profile) {
            ++self::$queryCount;
            self::$queryTime += microtime(true) - $began;
        }

        return $statement;
    }

    /**
     * @param string $query
     * @param array<mixed>|bool|int|string|null $parameters
     * @param bool|int $default
     * @return mixed
     */
    public function fetchScalar($query, $parameters = [], $default = false)
    {
        if ($parameters !== [] && $parameters !== null) {
            $statement = $this->run($query, is_array($parameters) ? $parameters : [$parameters]);
            if ($statement === false) {
                return $default;
            }

            $result = $statement->get_result();
            if ($result && $result->num_rows) {
                return $result->fetch_row()[0];
            }

            return $default;
        }

        $result = $this->query($query);
        if ($result && $result->num_rows) {
            return $result->fetch_row()[0];
        }

        return $default;
    }

    /**
     * @return int|string
     */
    public function lastInsertId()
    {
        return $this->mysqli->insert_id;
    }

    /**
     * @return int|string
     * @phpstan-impure
     */
    public function affectedRows()
    {
        return $this->mysqli->affected_rows;
    }

    /**
     * @param string $escapestr
     */
    public function real_escape_string($escapestr): string
    {
        return $this->mysqli->real_escape_string((string)$escapestr);
    }

    public function close(): void
    {
        if (!($this->mysqli instanceof \mysqli)) {
            return;
        }
        $this->mysqli->close();
    }

    /**
     * Dumps this database to $filename (gzip). The password is passed through a 0600
     * defaults file, never on the command line. The file only appears when complete.
     */
    public function dumpTo(string $filename, ?callable $heartbeat = null): bool
    {
        $directory = dirname($filename);
        $dumpBinary = null;
        foreach (['mariadb-dump', 'mysqldump'] as $candidate) {
            if (trim((string)shell_exec('command -v ' . $candidate . ' 2>/dev/null')) !== '') {
                $dumpBinary = $candidate;
                break;
            }
        }
        if ($dumpBinary === null) {
            logError("Backup failed: mariadb-dump/mysqldump is not installed.");
            return false;
        }
        $temporaryFile = tempnam($directory, '.backup-');
        if ($temporaryFile === false) {
            logError('Backup failed: cannot create a temporary output file.');

            return false;
        }
        chmod($temporaryFile, 0600);
        $defaultsFile = tempnam($directory, '.cnf');
        if ($defaultsFile === false) {
            @unlink($temporaryFile);
            logError("Backup failed: cannot create the credentials file.");
            return false;
        }
        chmod($defaultsFile, 0600);
        $quote = function ($value) {
            return '"' . addcslashes((string)$value, "\\\"") . '"';
        };
        if (file_put_contents($defaultsFile, "[client]\nhost=" . $quote($this->details[0]) . "\nuser=" . $quote($this->details[1]) . "\npassword=" . $quote($this->details[2]) . "\n") === false) {
            @unlink($defaultsFile);
            @unlink($temporaryFile);
            logError('Backup failed: cannot write the credentials file.');

            return false;
        }
        $databaseName = $this->details[3];
        $arguments = [
            '--defaults-extra-file=' . $defaultsFile,
            '--skip-set-charset',
            '--default-character-set=utf8mb4',
            // One consistent snapshot (a single transaction), rows in primary-key order and binary
            // columns as hex, so a dump is repeatable and survives any character set on restore.
            '--single-transaction',
            '--order-by-primary',
            '--hex-blob',
            '--no-tablespaces',
            '--triggers',
        ];
        $arguments[] = $databaseName;
        $command = 'set -o pipefail; ' . $dumpBinary . ' ' . implode(' ', array_map('escapeshellarg', $arguments))
            . ' | gzip --best > ' . escapeshellarg($temporaryFile);
        $stderr = '';
        $ok = false;
        $process = proc_open(['bash', '-c', $command], [1 => ['file', '/dev/null', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (is_resource($process)) {
            stream_set_blocking($pipes[2], false);
            $exitCode = null;
            $leaseLost = false;
            $lastHeartbeat = 0;
            do {
                $stderr .= stream_get_contents($pipes[2]);
                $status = proc_get_status($process);
                if ($heartbeat !== null && time() - $lastHeartbeat >= 30) {
                    try {
                        $stillOwnsLease = $heartbeat();
                    } catch (\Throwable $exception) {
                        $stillOwnsLease = false;
                        $stderr .= "\nBackup lock renewal failed: " . $exception->getMessage();
                    }
                    if (!$stillOwnsLease) {
                        $leaseLost = true;
                        proc_terminate($process);
                    }
                    $lastHeartbeat = time();
                }
                if (!$status['running']) {
                    $exitCode = $status['exitcode'];
                    break;
                }
                usleep(250000);
            } while (!$leaseLost);
            $stderr .= stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $closeCode = proc_close($process);
            $fileSize = is_file($temporaryFile) ? filesize($temporaryFile) : false;
            $ok = !$leaseLost && $fileSize !== false && $fileSize > 0
                && ($exitCode === 0 || ($exitCode === -1 && $closeCode === 0));
        }
        @unlink($defaultsFile);
        if (!$ok) {
            @unlink($temporaryFile);
            logError("Backup failed: " . trim($stderr));
            return false;
        }
        if (!rename($temporaryFile, $filename)) {
            @unlink($temporaryFile);
            logError('Backup failed: cannot publish the completed backup.');

            return false;
        }
        chmod($filename, 0640);

        return true;
    }

    /**
     * Dumps the world database and the account database to BACKUP_PATH as
     * backup-<ms>-<random>.sql.gz and backup-<ms>-<random>-account.sql.gz, optionally
     * copies them to TRAVIAN_BACKUP_EXPORT_DIR, then applies the retention policy.
     * $keepNew also exempts this backup itself from that pass, for a restore point someone must be
     * able to read before any later backup's retention can drop it.
     */
    public function backup_tables(?callable $heartbeat = null, array $protect = [], bool $keepNew = false): bool
    {
        $directory = rtrim(BACKUP_PATH, '/');
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            logError("Backup failed: cannot create $directory");
            return false;
        }
        try {
            $stamp = miliseconds() . '-' . bin2hex(random_bytes(8));
        } catch (\Throwable $exception) {
            logError('Backup failed: unable to create a unique filename: ' . $exception->getMessage());

            return false;
        }
        if ($keepNew) {
            $protect[] = $stamp;
        }
        $targets = [
            "$directory/backup-$stamp.sql.gz" => $this,
            "$directory/backup-$stamp-account.sql.gz" => GlobalDB::getInstance(),
        ];
        foreach ($targets as $file => $database) {
            if (!$database->dumpTo($file, $heartbeat)) {
                array_map('unlink', array_filter(array_keys($targets), 'is_file'));

                return false;
            }
        }
        $passphrase = StateArchive::passphrase();
        if ($passphrase !== null) {
            // Opt-in: the generated state of the operator, sealed with the passphrase from the environment.
            $stateFile = "$directory/backup-$stamp-state.enc";
            $targets[$stateFile] = null;
            if (!StateArchive::write($stateFile, $passphrase, rtrim(STATE_DIR, '/'))) {
                logError('Backup failed: the encrypted state archive could not be written.');
                array_map('unlink', array_filter(array_keys($targets), 'is_file'));

                return false;
            }
        }
        if (!$this->query("UPDATE config SET lastBackup=" . time())) {
            logError('Backup completed but could not update its schedule timestamp.');

            return false;
        }
        $export = rtrim((string)getenv('TRAVIAN_BACKUP_EXPORT_DIR'), '/');
        if ($export !== '') {
            if (!self::exportBackup(array_reverse(array_keys($targets)), $export)) {
                logError("Backup completed but could not be copied to $export.");
            }
            self::pruneBackups($export, $protect);
        }
        self::pruneBackups($directory, $protect);

        return true;
    }

    /**
     * Copies a finished backup into the export directory. Each file is written under a temporary
     * name and renamed once complete, so a partial copy never looks like a backup; the files go in
     * the order given (the world dump last, because it is what makes a backup visible), and a
     * failure removes what this call copied, so the export never holds half a backup.
     *
     * @param list<string> $files
     */
    public static function exportBackup(array $files, string $export): bool
    {
        $copied = [];
        foreach ($files as $file) {
            $target = $export . '/' . basename($file);
            $partial = $export . '/.' . basename($file) . '.partial';
            $free = is_dir($export) ? @disk_free_space($export) : false;
            if (!is_dir($export) || ($free !== false && $free < (int)@filesize($file)) || !@copy($file, $partial) || !@rename($partial, $target)) {
                @unlink($partial);
                foreach ($copied as $done) {
                    @unlink($done);
                }

                return false;
            }
            $copied[] = $target;
        }

        return true;
    }

    /** Whether the engine runs its scheduled backups (Operations settings, default on). */
    public static function scheduledBackupsEnabled(): bool
    {
        return \Game\OperationsSettings::get('backupsEnabled') === 1;
    }

    /**
     * Retention: keep the newest TRAVIAN_BACKUP_KEEP_LAST backups (default 3) plus the newest
     * one of each of the last TRAVIAN_BACKUP_KEEP_DAILY calendar days (default 7). A backup
     * is the world dump and its -account sibling; both are kept or removed together.
     * $protect lists backup stamps (the part after "backup-") that must survive, such as the
     * backup a restore is about to read: the safety backup taken first would otherwise push it
     * out of the newest-N and daily windows and delete it.
     *
     * @param list<string> $protect
     */
    public static function pruneBackups(string $directory, array $protect = []): void
    {
        $keepLast = \Game\OperationsSettings::get('backupKeepLast');
        $keepDaily = \Game\OperationsSettings::get('backupKeepDaily');
        $sets = [];
        foreach (glob($directory . '/backup-*') ?: [] as $file) {
            if (preg_match('/\Abackup-([0-9]+(?:-[a-f0-9]+)?)(?:-account|-state)?\.(?:sql(?:\.gz)?|enc)\z/', basename($file), $m)) {
                $sets[$m[1]][] = $file;
            }
        }
        krsort($sets, SORT_NATURAL);
        $keep = array_slice(array_keys($sets), 0, $keepLast);
        $days = [];
        foreach (array_keys($sets) as $stamp) {
            $day = gmdate('Y-m-d', intdiv((int)$stamp, 1000));
            if (!isset($days[$day]) && count($days) < $keepDaily) {
                $days[$day] = $stamp;
            }
        }
        $keep = array_map('strval', array_merge($keep, array_values($days), $protect));
        foreach ($sets as $stamp => $files) {
            if (in_array((string)$stamp, $keep, true)) {
                continue;
            }
            foreach ($files as $file) {
                if (!@unlink($file)) {
                    logError("Backup completed but could not prune $file.");
                }
            }
        }
    }

    /** Replaces this database with a gzip dump made by dumpTo(). The caller must have stopped the engine. */
    public function restoreFrom(string $filename): bool
    {
        $defaultsFile = tempnam(sys_get_temp_dir(), '.cnf');
        if ($defaultsFile === false) {
            return false;
        }
        chmod($defaultsFile, 0600);
        $quote = fn ($value) => '"' . addcslashes((string)$value, "\\\"") . '"';
        file_put_contents($defaultsFile, "[client]\nhost=" . $quote($this->details[0]) . "\nuser=" . $quote($this->details[1]) . "\npassword=" . $quote($this->details[2]) . "\n");
        $client = trim((string)shell_exec('command -v mariadb 2>/dev/null')) !== '' ? 'mariadb' : 'mysql';
        $command = 'set -o pipefail; gzip -dc -- ' . escapeshellarg($filename) . ' | ' . $client
            . ' --defaults-extra-file=' . escapeshellarg($defaultsFile) . ' --default-character-set=utf8mb4 ' . escapeshellarg($this->details[3]);
        exec('bash -c ' . escapeshellarg($command) . ' 2>&1', $output, $code);
        @unlink($defaultsFile);
        if ($code !== 0) {
            logError('Restore failed: ' . trim(implode("\n", $output)));
        }

        return $code === 0;
    }
}
