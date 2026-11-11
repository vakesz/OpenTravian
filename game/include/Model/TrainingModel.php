<?php

declare(strict_types=1);

namespace Model;

use Core\Config;
use Core\Database\DB;
use Game\Formulas;
use Game\ResourcesHelper;
use Game\Ships;
use mysqli_result;

use function nanoseconds;

class TrainingModel
{
    public function getTraining(int $kid, int $item_id): mysqli_result|bool
    {
        return DB::getInstance()->query("SELECT * FROM training WHERE item_id=$item_id AND kid=$kid");
    }

    public function addTraining(int $kid, int $item_id, int $nr, int $num, int|float $training_time): void
    {
        $this->enqueue($kid, $item_id, $nr, $num, $training_time);
        $ownerId = (int) DB::getInstance()->fetchScalar('SELECT owner FROM vdata WHERE kid=?', [$kid]);
        if ($ownerId > 2) {
            (new SeasonPassModel())->onMissionEvent($ownerId, 'trainTroops');
        }
    }

    /** Appends a batch to a building's queue: wounded healing uses this too and is not training. */
    public function enqueue(int $kid, int $item_id, int $nr, int $num, int|float $training_time): void
    {
        $time = $this->now();
        $db = DB::getInstance();
        $commence = (int) $db->fetchScalar(
            'SELECT end_time FROM training WHERE item_id=? AND kid=? ORDER BY end_time DESC LIMIT 1',
            [$item_id, $kid]
        );
        $commence = ($commence > $time ? $commence : $time) + $training_time;
        $end_time = $commence + (($num - 1) * $training_time);
        $db->run(
            'INSERT INTO training(`kid`, `nr`, `num`, `item_id`, `training_time`, `commence`, `end_time`) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$kid, $nr, $num, $item_id, $training_time, $commence, $end_time]
        );
    }

    public function getTotalTrainingTime(int $kid, int $item_id): int|float
    {
        $total = DB::getInstance()->fetchScalar(
            'SELECT SUM(training_time*num) FROM training WHERE item_id=? AND kid=?',
            [$item_id, $kid]
        );

        return is_numeric($total) ? $total + 0 : 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getTechnology(int $kid): ?array
    {
        return $this->fetchRow("SELECT * FROM tdata WHERE kid=$kid");
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getUnits(int $kid): ?array
    {
        return $this->fetchRow("SELECT * FROM units WHERE kid=$kid");
    }

    public function getFilledTrapCount(int $kid): int
    {
        return (int) DB::getInstance()->fetchScalar(
            'SELECT SUM(u1)+SUM(u2)+SUM(u3)+SUM(u4)+SUM(u5)+SUM(u6)+SUM(u7)+SUM(u8)+SUM(u9)+SUM(u10)+SUM(u11) FROM trapped WHERE to_kid=?',
            [$kid]
        );
    }

    public function calculatePriceForInstantTraining(int $kid): int|float
    {
        $rate = 1;
        if (Config::getProperty('game', 'useNanoseconds')) {
            $rate = 1e9;
        } elseif (Config::getProperty('game', 'useMilSeconds')) {
            $rate = 1000;
        }
        $totalTrainingTime = (int) DB::getInstance()->fetchScalar(
            'SELECT SUM(training_time*num) FROM training WHERE kid=?',
            [$kid]
        ) / $rate;
        $threshold = Config::getProperty('extraSettings', 'generalOptions', 'finishTraining', 'threshold');

        return max(1, ceil($totalTrainingTime / $threshold));
    }

    /**
     * Credits finished units and updates/removes the training row atomically; the row is re-read under a lock so a
     * concurrent pass cannot credit the same batch twice, and a failure leaves the row and the units untouched.
     *
     * @param array<string, mixed> $row
     */
    public function handleTrainingCompleteResult(array $row): void
    {
        $db = DB::getInstance();
        if (!$db->begin_transaction()) {
            throw new \RuntimeException('Unable to begin training transaction.');
        }
        try {
            $locked = $db->run('SELECT * FROM training WHERE id=? FOR UPDATE', [(int) $row['id']]);
            $lockedRow = $locked === false ? null : $locked->get_result()->fetch_assoc();
            if (is_array($lockedRow)) {
                $this->creditTrainingResult($lockedRow);
            }
            if (!$db->commit()) {
                throw new \RuntimeException('Unable to commit training transaction.');
            }
        } catch (\Throwable $exception) {
            $db->rollback();

            throw $exception;
        }
    }

    /**
     * Instantly credits every training queue row for a village inside an already
     * open purchase transaction (finishTraining gold spend). Returns how many
     * rows were claimed. Does not begin/commit its own transaction.
     */
    public function completeAllTrainingInOpenTransaction(int $kid): int
    {
        $statement = DB::getInstance()->run('SELECT * FROM training WHERE kid=? ORDER BY id ASC FOR UPDATE', [$kid]);
        if ($statement === false) {
            throw new \RuntimeException('Unable to lock training queue for instant completion.');
        }
        $count = 0;
        $result = $statement->get_result();
        while ($row = $result->fetch_assoc()) {
            // Same full-batch signal the old end_time=0 / commence=0 path used,
            // but credit units here instead of waiting for Automation.
            $row['commence'] = 0;
            $row['end_time'] = 0;
            $this->creditTrainingResult($row);
            ++$count;
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function creditTrainingResult(array $row): void
    {
        $db = DB::getInstance();
        $id = (int) $row['id'];
        $kid = (int) $row['kid'];
        $nr = (int) $row['nr'];
        $num = (int) $row['num'];
        $trainingTime = (int) $row['training_time'];
        $commence = (int) $row['commence'];
        $endTime = (int) $row['end_time'];
        $current = $this->now();
        if ($commence > $current) {
            return;
        }
        if ($nr === 11) {
            $trained = 1;
        } else {
            if ($trainingTime === 0 || $endTime === 0 || $commence === 0) {
                $trained = $num;
            } else {
                $trained = (int) floor(($current - ($commence - $trainingTime)) / $trainingTime);
            }
            if ($trained >= $num) {
                $trained = $num;
            }
        }
        $trained = abs($trained);
        if ($trained >= $num) {
            $db->run('DELETE FROM training WHERE id=?', [$id]);
        } else {
            $leftOnTraining = abs($num - $trained);
            $newCommence = $endTime - (($leftOnTraining - 1) * $trainingTime);
            $db->run('UPDATE training SET num=?, commence=? WHERE id=?', [$leftOnTraining, $newCommence, $id]);
        }
        if ($nr === 11) {
            $this->regenerateHero(-1, $kid);
            return;
        }
        if ($nr > Ships::TRAINING_NR_OFFSET) {
            // Ships are not troops: they have no upkeep and live in units.ship1..ship4.
            $column = Ships::column($nr - Ships::TRAINING_NR_OFFSET);
            $db->run("UPDATE units SET $column=$column+? WHERE kid=?", [$trained, $kid]);
            return;
        }
        $uid = (int) $db->fetchScalar('SELECT owner FROM vdata WHERE kid=?', [$kid]);
        $db->run("UPDATE units SET u$nr=u$nr+? WHERE kid=?", [$trained, $kid]);
        $race = (int) $db->fetchScalar('SELECT race FROM units WHERE kid=?', [$kid]);
        $upkeep = Formulas::uUpkeep(nrToUnitId($nr, $race), VillageModel::getHDP($kid, $race)) * $trained;
        ResourcesHelper::modifyUpkeep($uid, $kid, $upkeep);
    }

    public function regenerateHero(int $uid, int $kid): void
    {
        $db = DB::getInstance();
        if ($uid < 0) {
            $uid = (int) $db->fetchScalar('SELECT owner FROM vdata WHERE kid=?', [$kid]);
        }
        // Settle the dead hero's old rate first. Production and upkeep begin
        // together when the hero is revived, never retroactively.
        ResourcesHelper::updateVillageResources($kid, true);
        $db->run('UPDATE units SET u11=1 WHERE kid=?', [$kid]);
        $db->run('UPDATE hero SET kid=?, health=100 WHERE uid=?', [$kid, $uid]);
        ResourcesHelper::updateVillageUpkeep($uid, $kid);
        ResourcesHelper::updateVillageResources($kid, false, false);
    }

    private function rowExists(int $id): bool
    {
        return (int) DB::getInstance()->fetchScalar('SELECT COUNT(*) FROM training WHERE id=?', [$id]) > 0;
    }

    /** The clock of the queue timestamps (overridden by the runtime tests). */
    protected function now(): int
    {
        return (int) (getGame('useNanoseconds') ? nanoseconds() : (getGame('useMilSeconds') ? miliseconds() : time()));
    }

    /**
     * Text-protocol read: callers expect string column values, which prepared statements would turn into native ints.
     * Only integers are interpolated into $query.
     *
     * @return array<string, mixed>|null
     */
    private function fetchRow(string $query): ?array
    {
        $result = DB::getInstance()->query($query);
        $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;

        return is_array($row) ? $row : null;
    }
}
