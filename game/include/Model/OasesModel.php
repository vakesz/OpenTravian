<?php

declare(strict_types=1);

namespace Model;

use Game\FarmList\FarmListInbox;
use Core\Config;
use Core\Database\DB;
use Game\Formulas;
use Game\Map\Map;
use Game\ResourcesHelper;

use function miliseconds;

class OasesModel
{
    /**
     * Nature unit ids (1 Rat, 2 Spider, 3 Snake, 4 Bat, 5 Wild Boar, 6 Wolf, 7 Bear, 8 Crocodile, 9 Tiger, 10 Elephant)
     * that spawn in each oasis type, keyed by oasis type (see the oasis effect table in Formulas).
     * Sources: Travian support, "Early game oasis farming" (wood: Boar, Wolf, Bear; clay: Rat, Spider, Boar;
     * iron: Rat, Spider, Bat; crop: Rat, Snake, Bear, Crocodile, Tiger); Travian Wiki "Nature" (Elephants are most
     * common in 25% wood/wheat and 50% wheat oases); the 50% wood, clay and iron types add one stronger animal.
     *
     * @var array<int, list<int>>
     */
    public const ANIMALS_BY_TYPE = [
        2 => [5, 6, 7],
        3 => [5, 6, 7, 10],
        4 => [5, 6, 7, 8],
        6 => [1, 2, 5],
        7 => [1, 2, 5],
        8 => [1, 2, 5, 8],
        10 => [1, 2, 4],
        11 => [1, 2, 4],
        12 => [1, 2, 4, 7],
        14 => [1, 3, 7, 8, 9],
        15 => [1, 3, 7, 8, 9, 10],
    ];

    /**
     * Neutral-oasis population is a named world-profile value. Keeping the
     * cap separate from economy speed prevents a very fast world from gaining
     * an accidental, unbounded animal army.
     */
    public static function getOasisAnimalCap(): int
    {
        return max(15, (int)Config::getProperty('game', 'oasisAnimalCap'));
    }

    public static function getOasisRespawnInterval(): int
    {
        return max(1, (int)Config::getProperty('game', 'oasisRespawnInterval'));
    }

    public static function getInitialProtectionEndTime(): int
    {
        return (int)Config::getProperty('game', 'start_time')
            + max(0, (int)Config::getProperty('game', 'oasisInitialProtectionTime'));
    }

    public static function oasesTrainV3(int $kid): bool
    {
        if ($kid <= 0) {
            return false;
        }
        $now = time();
        if ($now < self::getInitialProtectionEndTime()) {
            return false;
        }
        $db = DB::getInstance();
        if (!$db->begin_transaction()) {
            return false;
        }
        try {
            $oasesResult = $db->query("SELECT type, lasttrain, lastfarmed, owner FROM odata WHERE kid=$kid FOR UPDATE");
            $oases = $oasesResult ? $oasesResult->fetch_assoc() : false;
            if (!$oases || (int)$oases['owner'] !== 0) {
                $db->rollback();
                return false;
            }

            $cycleInterval = self::getOasisRespawnInterval();
            if (($now - (int)$oases['lastfarmed']) < $cycleInterval) {
                $db->rollback();
                return false;
            }
            $unitsResult = $db->query("SELECT * FROM units WHERE kid=$kid FOR UPDATE");
            if (!$unitsResult || !$unitsResult->num_rows) {
                $db->rollback();
                return false;
            }
            $units = $unitsResult->fetch_assoc();

            $maximum = self::getOasisAnimalCap();
            $sum = 0;
            for ($i = 1; $i <= 10; ++$i) {
                $sum += (int) $units['u' . $i];
            }
            if ($sum >= $maximum) {
                $db->rollback();
                return false;
            }
            $troops = self::ANIMALS_BY_TYPE[(int) $oases['type']] ?? [];
            if (!$troops) {
                $db->rollback();
                return false;
            }

            if ((int)$oases['lasttrain'] === (int)Config::getProperty('game', 'start_time')) {
                $cycles = 7;
            } else {
                $lastActivity = max((int)$oases['lasttrain'], (int)$oases['lastfarmed']);
                $cycles = (int)floor(($now - $lastActivity) / $cycleInterval);
            }
            if ($cycles <= 0) {
                $db->rollback();
                return false;
            }

            $gains = array_fill(1, 10, 0);
            for ($i = 0; $i < $cycles && $sum < $maximum; ++$i) {
                $unitId = $troops[mt_rand(0, count($troops) - 1)];
                $amount = min(mt_rand(1, 3), $maximum - $sum);
                $gains[$unitId] += $amount;
                $sum += $amount;
            }
            $updates = [];
            foreach ($gains as $unitId => $amount) {
                if ($amount > 0) {
                    $updates[] = "u{$unitId}=u{$unitId}+$amount";
                }
            }
            if (!$updates || !$db->query('UPDATE units SET ' . implode(',', $updates) . " WHERE kid=$kid")) {
                throw new \RuntimeException('Could not update neutral-oasis units.');
            }
            if (!$db->query("UPDATE odata SET lasttrain=$now WHERE kid=$kid")) {
                throw new \RuntimeException('Could not update neutral-oasis training time.');
            }
            if (!$db->commit()) {
                throw new \RuntimeException('Could not commit neutral-oasis training.');
            }
            return true;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            return false;
        }
    }

    /**
     * Runs independently from map rendering and combat so a neutral oasis
     * regains animals even when nobody opens it. A bounded batch keeps the
     * world worker responsive on larger maps.
     */
    public static function trainDueOases(int $limit = 250): void
    {
        $limit = min(max($limit, 1), 1000);
        if (time() < self::getInitialProtectionEndTime()) {
            return;
        }
        $cutoff = time() - self::getOasisRespawnInterval();
        $db = DB::getInstance();
        $result = $db->query(
            "SELECT kid FROM odata WHERE owner=0 AND lasttrain <= $cutoff AND lastfarmed <= $cutoff ORDER BY lasttrain ASC LIMIT $limit"
        );
        if (!$result) {
            return;
        }
        while ($row = $result->fetch_assoc()) {
            self::oasesTrainV3((int)$row['kid']);
        }
    }

    public static function oasesTrain(int $kid): bool
    {
        self::oasesTrainV3($kid);

        return true;
    }

    public static function getNearByFieldCropPercent(int $kid, float $distance = 4.9497474683058326708059105347339): int|float
    {
        $db = DB::getInstance();
        $xy = Formulas::kid2xy($kid);
        $ruler_x = $ruler_y = 7;
        $i = $xy['y'] + floor($ruler_y / 2);
        $percent = 0;
        $percents = [];
        while ($i >= $xy['y'] - floor($ruler_y / 2)) {
            $current_y = Formulas::coordinateFixer($i);
            $j = $xy['x'] - floor($ruler_x / 2);
            while ($j <= $xy['x'] + floor($ruler_x / 2)) {
                $current_x = Formulas::coordinateFixer($j);
                if (Formulas::getDistance(['x' => $current_x, 'y' => $current_y], $xy) <= $distance) {
                    $current_kid = Formulas::xy2kid($current_x, $current_y);
                    $oasisType = (int) $db->fetchScalar("SELECT oasistype FROM wdata WHERE id=$current_kid");
                    if ($oasisType > 0) {
                        $effect = Formulas::getOasisEffect($oasisType);
                        if (is_array($effect) && array_key_exists(4, $effect)) {
                            $percents[] = 25 * $effect[4];
                        }
                    }
                }
                $j++;
            }
            --$i;
        }
        rsort($percents, SORT_NUMERIC);
        $x = 0;
        foreach ($percents as $p) {
            $x++;
            if ($x <= 3) {
                $percent += $p;
            }
        }
        return min($percent, 150);
    }

    public static function captureOasis(int $kid, int $uid, int $did): void
    {
        $db = DB::getInstance();
        FarmListInbox::targetChangedOwner($kid);
        $db->query("UPDATE odata SET did=$did, owner=$uid, loyalty=100, conquered_time=" . time() . ", last_loyalty_update=" . time() . " WHERE kid=$kid");
        $db->query("UPDATE wdata SET occupied=1 WHERE id=$kid");
        Map::OccupyOrLeaveOasisCacheRemove($kid);
        ResourcesHelper::updateVillageResources($did, false);
    }

    public static function releaseOasis(int $kid, int $did): void
    {
        $db = DB::getInstance();
        $db->query("UPDATE odata SET owner=0, did=0, lasttrain=" . time() . ", last_loyalty_update=" . time() . " WHERE kid=$kid");
        $db->query("UPDATE wdata SET occupied=0 WHERE id=$kid");
        Map::OccupyOrLeaveOasisCacheRemove($kid);
        ResourcesHelper::updateVillageResources($did, false);
    }

    public function updateResources(int $kid): bool
    {
        $db = DB::getInstance();
        $result = $db->query("SELECT lastmupdate, kid, type, wood, clay, iron, crop, did FROM odata WHERE kid=$kid");
        $row = $result ? $result->fetch_assoc() : null;
        if (!is_array($row)) {
            return false;
        }
        if ($row['did']) {
            return false;
        }
        $cur_res = [(float) $row['wood'], (float) $row['clay'], (float) $row['iron'], (float) $row['crop']];
        $production = Formulas::getOasisProduction((int) $row['type']);
        $lastmupdate = (int) miliseconds();
        $diff_time = $lastmupdate - (int) $row['lastmupdate'];
        $resources = [
            ($production[0] / 3600000) * $diff_time,
            ($production[1] / 3600000) * $diff_time,
            ($production[2] / 3600000) * $diff_time,
            ($production[3] / 3600000) * $diff_time,
        ];
        $maxStore = Formulas::getOasisStorage((int) $row['type']);
        foreach ($resources as $key => $res) {
            $nextValue = $res + $cur_res[$key];
            if ($nextValue >= $maxStore) {
                $resources[$key] = $maxStore - $cur_res[$key];
            }
            $resources[$key] = round($resources[$key], 4);
        }
        $db->query("UPDATE odata SET wood=wood+{$resources[0]}, clay=clay+{$resources[1]}, iron=iron+{$resources[2]}, crop=crop+{$resources[3]}, lastmupdate=$lastmupdate WHERE kid={$row['kid']}");
        return true;
    }

    public function isOasis(int $kid): bool
    {
        return (int) DB::getInstance()->fetchScalar("SELECT oasistype FROM wdata WHERE id=$kid") > 0;
    }

    public function isOasisConqured(int $kid): bool
    {
        return $this->getOasisCaptureKid($kid) > 0;
    }

    /** Village that owns the oasis, 0 when it is unoccupied or does not exist. */
    public function getOasisCaptureKid(int $kid): int
    {
        return (int) DB::getInstance()->fetchScalar("SELECT did FROM odata WHERE kid=$kid");
    }
}
