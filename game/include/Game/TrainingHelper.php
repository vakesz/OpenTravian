<?php

namespace Game;

use Core\Caching\Caching;
use Core\Database\DB;
use Core\Session;
use Core\Village;
use Model\Quest;
use Model\VillageOverviewModel;

class TrainingHelper
{
    /**
     * @return (float|int)
     */
    private function getMaxTraps()
    {
        $village = Village::getInstance();
        $db = DB::getInstance();
        $totalTraps = 0;
        for ($i = 19; $i <= 38; $i++) {
            if ($village->getField($i)['item_id'] == 36) {
                $totalTraps += Formulas::getTrapperValueByLevel($village->getField($i)['level']);
            }
        }
        $totalTraps -= $db->fetchScalar("SELECT u99 FROM units WHERE kid={$village->getKid()} LIMIT 1");
        $totalTraps -= $db->fetchScalar("SELECT SUM(num) FROM training WHERE kid={$village->getKid()} AND item_id=36");
        $cost = Formulas::uTrainingCost(99);
        $can = [];
        foreach ($village->getCurrentResources(-1, TRUE) as $r => $v) {
            $can[$r] = floor($v / $cost[$r]);
            if ($can[$r] > $totalTraps) {
                $can[$r] = $totalTraps;
            }
        }
        return min($can);
    }

    /**
     * @return (float|int)
     */
    private function getMaxTraps2()
    {
        $village = Village::getInstance();
        $db = DB::getInstance();
        $totalTraps = 0;
        for ($i = 19; $i <= 38; $i++) {
            if ($village->getField($i)['item_id'] == 36) {
                $totalTraps += Formulas::getTrapperValueByLevel($village->getField($i)['level']);
            }
        }
        $totalTraps -= $db->fetchScalar("SELECT u99 FROM units WHERE kid={$village->getKid()} LIMIT 1");
        $totalTraps -= $db->fetchScalar("SELECT SUM(num) FROM training WHERE kid={$village->getKid()} AND item_id=36");
        $cost = Formulas::uTrainingCost(99);
        $can = floor(array_sum($village->getCurrentResources(-1, TRUE)) / array_sum($cost));
        return min($can, $totalTraps);
    }

    /**
     * @param float|int|string|null $kid
     * @return array<string, (array<mixed>|float|int)>
     */
    public function getExpansionUnits($kid)
    {
        $db = DB::getInstance();

        list($chiefs, $settlers) = $db->query("SELECT u9, u10 FROM units WHERE kid=$kid")->fetch_row();

        $onTheWay = $db->query("SELECT SUM(u10) AS `settlers`, SUM(u9) AS `chiefs` FROM movement WHERE (kid={$kid} AND mode=0)")->fetch_assoc();
        $settlers += $onTheWay['settlers'];
        $chiefs += $onTheWay['chiefs'];

        $onTheWay = $db->query("SELECT SUM(u10) AS `settlers`, SUM(u9) AS `chiefs` FROM movement WHERE (to_kid=$kid AND mode=1)")->fetch_assoc();
        $settlers += $onTheWay['settlers'];
        $chiefs += $onTheWay['chiefs'];


        $trapped = $db->query("SELECT SUM(u10) AS `settlers`, SUM(u9) AS `chiefs` FROM trapped WHERE kid=$kid")->fetch_assoc();
        $settlers += $trapped['settlers'];
        $chiefs += $trapped['chiefs'];
        $enforcement = $db->query("SELECT SUM(u10) AS `settlers`, SUM(u9) AS `chiefs` FROM enforcement WHERE kid=$kid")->fetch_assoc();
        $settlers += $enforcement['settlers'];
        $chiefs += $enforcement['chiefs'];
        $onTrain = self::settlersAndChiefsInTraining((int)$kid);
        $settlers += $onTrain['settlers'];
        $chiefs += $onTrain['chiefs'];

        return ['settlers' => $settlers, 'chiefs' => $chiefs];
    }

    /** Levels at which each expansion slot of a Residence, Palace or Command Center unlocks. */
    private const SLOT_LEVELS = [25 => [10, 20], 26 => [10, 15, 20], 44 => [10, 15, 20]];

    /**
     * Settlers and chiefs queued for training in the Residence, Palace or Command Center of the village.
     *
     * @return array<string, mixed>
     */
    private static function settlersAndChiefsInTraining(int $kid): array
    {
        $buildings = implode(',', array_keys(self::SLOT_LEVELS));

        return DB::getInstance()->query("
        SELECT
        (SELECT SUM(num) FROM training WHERE kid=$kid AND nr=10 AND item_id IN($buildings)) AS settlers,
        (SELECT SUM(num) FROM training WHERE kid=$kid AND nr=9 AND item_id IN($buildings)) AS chiefs
        ")->fetch_assoc();
    }

    /**
     * @return list<int>
     */
    public static function slotLevels(int $gid): array
    {
        return self::SLOT_LEVELS[$gid] ?? [];
    }

    public static function unlockedSlots(int $gid, int $level): int
    {
        return count(array_filter(self::slotLevels($gid), static fn (int $needed): bool => $level >= $needed));
    }

    /**
     * The building whose level sets the expansion slots of the active village: Residence first, then Command
     * Center, then Palace.
     *
     * @return array{0: int, 1: int} building id and level
     */
    public static function expansionBuilding(): array
    {
        $village = Village::getInstance();
        foreach ([25, 44] as $gid) {
            $level = max($village->getTypeLevel($gid));
            if ($level) {
                return [$gid, (int)$level];
            }
        }

        return [26, (int)max($village->getTypeLevel(26))];
    }

    /**
     * @param float|int|string|null $kid
     * @return array<string, mixed>
     */
    public function getSlots($kid)
    {
        $m = new VillageOverviewModel();
        $residencePalace = $m->getResidencePalaceLevel($kid);
        $db = DB::getInstance();
        $filledSlots = $db->fetchScalar("SELECT COUNT(kid) FROM vdata WHERE expandedfrom=$kid LIMIT 3");
        $maxslots = self::unlockedSlots((int)($residencePalace['item_id'] ?? 0), (int)($residencePalace['level'] ?? 0));
        return [
            'maxSlots'    => $maxslots,
            'filledSlots' => $filledSlots,
            'freeSlots'   => $maxslots - $filledSlots,
        ];
    }

    /**
     * @param mixed $nr
     * @param bool $calc
     * @return (float|int)|float|list<(float|int)>
     */
    public function getAvailableExpansionTraining($nr, $calc = FALSE)
    {
        $village = Village::getInstance();
        $db = DB::getInstance();
        $filledSlots = $db->fetchScalar("SELECT COUNT(kid) FROM vdata WHERE expandedfrom={$village->getKid()} LIMIT 3");
        $maxslots = self::unlockedSlots(...self::expansionBuilding()) - $filledSlots;
        list($chiefs, $settlers) = $db->query("SELECT u9, u10 FROM units WHERE kid=" . $village->getKid())->fetch_row();
        $onTheWay = $db->query("SELECT SUM(u10) AS `settlers`, SUM(u9) AS `chiefs` FROM movement WHERE (mode=1 AND to_kid={$village->getKid()}) OR (mode=0 AND kid={$village->getKid()})")->fetch_assoc();
        $settlers += $onTheWay['settlers'];
        $chiefs += $onTheWay['chiefs'];
        $trapped = $db->query("SELECT SUM(u10) AS `settlers`, SUM(u9) AS `chiefs` FROM trapped WHERE kid={$village->getKid()}")->fetch_assoc();
        $settlers += $trapped['settlers'];
        $chiefs += $trapped['chiefs'];
        $enforcement = $db->query("SELECT SUM(u10) AS `settlers`, SUM(u9) AS `chiefs` FROM enforcement WHERE kid={$village->getKid()}")->fetch_assoc();
        $settlers += $enforcement['settlers'];
        $chiefs += $enforcement['chiefs'];
        $onTrain = self::settlersAndChiefsInTraining($village->getKid());
        $settlers += $onTrain['settlers'];
        $chiefs += $onTrain['chiefs'];
        if ($settlers >= 3) {
            Quest::getInstance()->setQuestBitwise('world', 15, 1);
        }
        // trapped settlers/chiefs calculation required
        $settlerslots = ($maxslots * 3) - $settlers - ($chiefs * 3);
        $chiefslots = $maxslots - $chiefs - floor(($settlers + 2) / 3);
        $cost = Formulas::uTrainingCost(nrToUnitId(10, Session::getInstance()->getRace()));
        $can = [];
        foreach ($village->getCurrentResources(-1, TRUE) as $r => $v) {
            $can[$r] = floor($v / $cost[$r]);
            if ($can[$r] > $settlerslots) {
                $can[$r] = $settlerslots;
            }
        }
        if ($nr == 10) {
            return $calc ? $settlerslots : min($can);
        }
        $cost = Formulas::uTrainingCost(nrToUnitId(9, Session::getInstance()->getRace()));
        $can = [];
        foreach ($village->getCurrentResources(-1, TRUE) as $r => $v) {
            $can[$r] = floor($v / $cost[$r]);
            if ($can[$r] > $chiefslots) {
                $can[$r] = $chiefslots;
            }
        }
        if ($nr == 9) {
            return $calc ? $chiefslots : min($can);
        }
        if ($nr == 0) {
            return [$settlerslots, $chiefslots];
        }

        return $chiefslots;
    }

    /**
     * @param mixed $nr
     * @param bool $calc
     * @return (float|int)|float|list<(float|int)>
     */
    public function getAvailableExpansionTraining2($nr, $calc = FALSE)
    {
        $village = Village::getInstance();
        $db = DB::getInstance();
        $filledSlots = $db->fetchScalar("SELECT COUNT(kid) FROM vdata WHERE expandedfrom={$village->getKid()}");
        $maxslots = self::unlockedSlots(...self::expansionBuilding()) - $filledSlots;
        list($chiefs, $settlers) = $db->query("SELECT u9, u10 FROM units WHERE kid=" . $village->getKid())->fetch_row();
        $onTheWay = $db->query("SELECT SUM(u10) AS `settlers`, SUM(u9) AS `chiefs` FROM movement WHERE (mode=1 AND to_kid={$village->getKid()}) OR (mode=0 AND kid={$village->getKid()})")->fetch_assoc();
        $settlers += $onTheWay['settlers'];
        $chiefs += $onTheWay['chiefs'];
        $trapped = $db->query("SELECT SUM(u10) AS `settlers`, SUM(u9) AS `chiefs` FROM trapped WHERE kid={$village->getKid()}")->fetch_assoc();
        $settlers += $trapped['settlers'];
        $chiefs += $trapped['chiefs'];
        $enforcement = $db->query("SELECT SUM(u10) AS `settlers`, SUM(u9) AS `chiefs` FROM enforcement WHERE kid={$village->getKid()}")->fetch_assoc();
        $settlers += $enforcement['settlers'];
        $chiefs += $enforcement['chiefs'];
        $onTrain = self::settlersAndChiefsInTraining($village->getKid());
        $settlers += $onTrain['settlers'];
        $chiefs += $onTrain['chiefs'];
        if ($settlers >= 3) {
            Quest::getInstance()->setQuestBitwise('world', 15, 1);
        }
        // trapped settlers/chiefs calculation required
        $settlerslots = ($maxslots * 3) - $settlers - ($chiefs * 3);
        $chiefslots = $maxslots - $chiefs - floor(($settlers + 2) / 3);
        $cost = Formulas::uTrainingCost(nrToUnitId(10, Session::getInstance()->getRace()));
        $can = min(floor(array_sum($village->getCurrentResources(-1, TRUE)) / array_sum($cost)), $settlerslots);
        if ($nr == 10) {
            return $calc ? $settlerslots : $can;
        }
        $cost = Formulas::uTrainingCost(nrToUnitId(9, Session::getInstance()->getRace()));
        $can = min(floor(array_sum($village->getCurrentResources()) / array_sum($cost)), $chiefslots);
        if ($nr == 9) {
            return $calc ? $chiefslots : $can;
        }
        if ($nr == 0) {
            return [$settlerslots, $chiefslots];
        }
        return $chiefslots;
    }

    /**
     * @param mixed $nr
     * @param mixed $great
     */
    private function maxUnits2($nr, $great): float
    {
        $session = Session::getInstance();
        $village = Village::getInstance();
        $cost = Formulas::uTrainingCost(nrToUnitId($nr, $session->getRace()), $great);
        return floor(array_sum($village->getCurrentResources()) / array_sum($cost));
    }

    /**
     * @param mixed $nr
     * @param mixed $great
     */
    private function maxUnits($nr, $great): float
    {
        $session = Session::getInstance();
        $village = Village::getInstance();
        $cost = Formulas::uTrainingCost(nrToUnitId($nr, $session->getRace()), $great);
        $can = [];
        foreach ($village->getCurrentResources(-1, TRUE) as $r => $v) {
            $can[$r] = floor($v / $cost[$r]);
        }
        return min($can);
    }

    /**
     * @param mixed $nr
     * @param bool $great
     * @return mixed
     */
    public function getMaxUnitByNr($nr, $great = FALSE)
    {
        if ($nr == 99) {
            return $this->getMaxTraps();
        }
        if ($nr >= 9) {
            return $this->getAvailableExpansionTraining($nr, FALSE);
        }
        return $this->maxUnits($nr, $great);
    }

    /**
     * @param mixed $nr
     * @param mixed $great
     * @return mixed
     */
    public function getMaxUnitByNr2($nr, $great = FALSE)
    {
        if ($nr == 99) {
            return $this->getMaxTraps2();
        }
        if ($nr >= 9) {
            return $this->getAvailableExpansionTraining2($nr, FALSE);
        }
        return $this->maxUnits2($nr, $great);
    }
}