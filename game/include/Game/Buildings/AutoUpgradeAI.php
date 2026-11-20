<?php

namespace Game\Buildings;

use Core\Config;
use Core\Database\DB;
use Core\Time;
use Game\Formulas;
use Game\Gid;
use Game\Slot;
use Game\Tribes;
use const SORT_ASC;
use function in_array;

class AutoUpgradeAI
{
    /** @var array<mixed> */
    private $buildings = [];
    /** @var array<mixed> */
    private $resources = [];
    /** @var mixed */
    private $capital = false;
    /** @var mixed */
    private $hasPlus = false;
    /** @var mixed */
    private $isWW = false;
    /** @var bool */
    private $skipWorkers = false;
    /** @var mixed */
    private $kid;
    /** @var mixed */
    private $race = 5;
    /** @var int */
    private $owner = 0;
    /** @var mixed */
    private $main_building_lvl = 0;
    /** @var mixed */
    private $creationTime = 0;
    /** @var mixed */
    private $maxstore = 0;
    /** @var mixed */
    private $maxcrop = 0;
    /** @var mixed */
    private $cropProduction = 0;
    /** @var mixed */
    private $upkeep = 0;
    /** @var array<string, int> */
    private $workers = ['buildsNum' => 0, 'fieldsNum' => 0, 'master' => 0, 'WW' => 0];
    public function skipWorkers(): void{
        $this->skipWorkers = true;
    }
    /**
     * @param mixed $kid
     * @param mixed $resources
     * @param array<int, array<string, int>> $buildings
     * @param bool $hasPlus
     * @param mixed $race
     * @param mixed $village
     */
    public function __construct($kid, &$resources, &$buildings, $hasPlus, $race, $village)
    {
        $this->kid = $kid;
        $this->race = $race;
        $this->resources = &$resources;
        $this->buildings = &$buildings;
        $this->capital = $village['capital'];
        $this->hasPlus = $hasPlus;
        $this->creationTime = $village['created'];
        $this->maxstore = $village['maxstore'];
        $this->maxcrop = $village['maxcrop'];
        $this->isWW = $village['isWW'];
        $this->cropProduction = $village['cropp'];
        $this->upkeep = $village['upkeep'];
        $this->owner = (int) ($village['owner'] ?? 0);
        for ($i = Slot::FIRST_BUILDING; $i <= Slot::LAST_BUILDING; ++$i) {
            if ($this->buildings[$i]['item_id'] == Gid::MAIN_BUILDING) {
                $this->main_building_lvl = $this->buildings[$i]['level'];
            }
        }
        $db = DB::getInstance();
        $this->workers = [
            'fieldsNum' => (int)$db->fetchScalar("SELECT COUNT(id) FROM building_upgrade WHERE kid=$kid AND building_field <= " . Slot::LAST_FIELD . " AND isMaster=0"),
            'buildsNum' => (int)$db->fetchScalar("SELECT COUNT(id) FROM building_upgrade WHERE kid=$kid AND building_field > " . Slot::LAST_FIELD . " AND isMaster=0"),
            'master' => (int)$db->fetchScalar("SELECT COUNT(id) FROM building_upgrade WHERE kid=$kid AND isMaster=1"),
            'WW' => (int)$db->fetchScalar("SELECT COUNT(id) FROM building_upgrade WHERE kid=$kid AND building_field=99"),
        ];
    }

    /**
     * @param bool $isField
     * @param bool $isWWQueue
     * @return array<string, bool>
     */
    private function isWorkersBusy($isField, $isWWQueue = FALSE)
    {
        $config = Config::getInstance();
        $hasPlus = $this->hasPlus;
        $maxTasks = $hasPlus ? 2 : 1; //its with wonder
        $fieldsNum = $this->workers['fieldsNum'];
        $buildsNum = $this->workers['buildsNum'];
        $master = $this->workers['master'];
        if($isWWQueue){
            $maxTasks = 2;
        }
        if ($this->race == Tribes::ROMANS) {
            return [
                'isMasterBusy' => $master >= ($this->isWW ? $config->masterBuilder->maxTasksInWonder : $config->masterBuilder->maxTasksInNoneWonder),
                'isBusy' => (($isField) ? ($fieldsNum >= $maxTasks) : ($buildsNum >= $maxTasks)) || ($fieldsNum + $buildsNum) >= 3,
                'isPlusUsed' => ($hasPlus ? ($isField ? ($fieldsNum > 0) : ($buildsNum > 0)) : FALSE),
            ];
        }
        return [
            'isMasterBusy' => $master >= ($this->isWW ? $config->masterBuilder->maxTasksInWonder : $config->masterBuilder->maxTasksInNoneWonder),
            'isBusy' => ($buildsNum + $fieldsNum) >= $maxTasks,
            'isPlusUsed' => ($hasPlus ? (($buildsNum + $fieldsNum) > 0) : FALSE),
        ];
    }

    /**
     * @param mixed $gid
     * @param (float|int)|int $lvl
     */
    private function isBuildingResourcesAvailable($gid, $lvl): bool
    {
        $costs = Formulas::buildingUpgradeCosts($gid, $lvl);
        $cur_res = $this->resources;
        for ($i = 0; $i < 4; ++$i) {
            if ($cur_res[$i] < $costs[$i]) {
                return FALSE;
            }
        }
        return TRUE;
    }

    /**
     * @param mixed $rand_field
     * @param mixed $gid
     */
    public function newBuilding($rand_field, $gid = false): bool
    {
        if (!$this->skipWorkers && $this->isWorkersBusy($rand_field <= Slot::LAST_FIELD)['isBusy']) return false;
        $db = DB::getInstance();
        $helper = new BuildingHelper();
        if ($rand_field == Slot::RALLY_POINT) {
            $hasResources = $this->isBuildingResourcesAvailable(Gid::RALLY_POINT, 1);
            if ($hasResources && $helper->canCreateNewBuild($this->capital, $this->race, Gid::RALLY_POINT, $this->buildings) == 1) {
                if (!$this->buildings[$rand_field]['item_id']) {
                    $this->buildings[$rand_field]['item_id'] = Gid::RALLY_POINT;
                    $db->query("UPDATE fdata SET f{$rand_field}t=16 WHERE kid=" . $this->kid);
                    $this->addUpgrade($rand_field);
                    return true;
                }
                return false;
            }
            return false;
        }
        if ($rand_field == Slot::WALL) {
            $buildingGID = Formulas::getWallID($this->race);
            $hasResources = $this->isBuildingResourcesAvailable($buildingGID, 1);
            if ($hasResources && $helper->canCreateNewBuild($this->capital, $this->race, $buildingGID, $this->buildings) == 1) {
                if (!$this->buildings[$rand_field]['item_id']) {
                    $this->buildings[$rand_field]['item_id'] = $buildingGID;
                    $db->query("UPDATE fdata SET f{$rand_field}t=$buildingGID WHERE kid=" . $this->kid);
                    $this->addUpgrade($rand_field);
                    return true;
                }
                return false;
            }
            return false;
        }
        $rand_arr = [
            Gid::SAWMILL, Gid::BRICKYARD, Gid::IRON_FOUNDRY, Gid::GRAIN_MILL, Gid::BAKERY, Gid::WAREHOUSE, Gid::GRANARY, Gid::SMITHY, Gid::TOURNAMENT_SQUARE, Gid::MAIN_BUILDING, Gid::RALLY_POINT, Gid::MARKETPLACE, Gid::EMBASSY, Gid::BARRACKS, Gid::STABLE, Gid::WORKSHOP, Gid::ACADEMY, Gid::CRANNY, /*more crannies*/
            Gid::CRANNY, Gid::CRANNY, Gid::CRANNY, Gid::CRANNY, Gid::CRANNY, Gid::CRANNY,/*more crannies*/
            Gid::TOWN_HALL, Gid::RESIDENCE, /*26, */Gid::TREASURY, Gid::TRADE_OFFICE,/*29, 30,*/
            Gid::STONEMASON, Gid::BREWERY, Gid::TRAPPER, Gid::HEROS_MANSION, /*38, 39,*/ Gid::COMMAND_CENTER, Gid::WATERWORKS
        ];
        if ($gid) {
            $rand_arr = [$gid];
        }
        shuffle($rand_arr);
        foreach ($rand_arr as $gid) {
            if ($gid == Gid::RALLY_POINT && $rand_field != Slot::RALLY_POINT) {
                continue;
            }
            $hasResources = $this->isBuildingResourcesAvailable($gid, 1);
            if ($hasResources && $helper->checkArtifactDependencies(0, $this->owner, $this->kid, $gid, false, 0) == 0 && $helper->canCreateNewBuild($this->capital, $this->race, $gid, $this->buildings) == 1) {
                if (!$this->buildings[$rand_field]['item_id']) {
                    $this->buildings[$rand_field]['item_id'] = $gid;
                    $db->query("UPDATE fdata SET f{$rand_field}t=$gid WHERE kid=" . $this->kid);
                    $this->addUpgrade($rand_field);
                    return true;
                }
                return false;
            }
        }
        return false;
    }

    /**
     * @param mixed $gid
     * @param (float|int) $lvl
     */
    private function takeResources($gid, $lvl): void
    {
        $kid = $this->kid;
        $resources = Formulas::buildingUpgradeCosts($gid, $lvl);
        for ($i = 0; $i < 4; ++$i) {
            $this->resources[$i] = max($this->resources[$i] - $resources[$i], 0);
        }
        DB::getInstance()->query("UPDATE vdata SET wood=IF(wood-{$resources[0]} > 0, wood-{$resources[0]}, 0), clay=IF(clay-{$resources[1]} > 0, clay-{$resources[1]}, 0), iron=IF(iron-{$resources[2]} > 0, iron-{$resources[2]}, 0), crop=IF(crop-{$resources[3]} > 0, crop-{$resources[3]}, 0) WHERE kid=$kid");
    }

    /**
     * @param mixed $field
     */
    private function addUpgrade($field, ?int $nextLevel = null): void
    {
        $this->workers[$field <= Slot::LAST_FIELD ? 'fieldsNum' : 'buildsNum']++;
        if($this->buildings[$field]['item_id'] == Gid::WONDER_OF_THE_WORLD){
            $this->workers['WW']++;
        }
        // Charge the level this order will finish, including rows already queued.
        // The in-memory level stays the built level; the next pass reads the new row.
        $nextLevel ??= (int) $this->buildings[$field]['level'] + $this->getUpgradeState($field) + 1;
        $commence = $this->getLastCommence($field);
        $this->takeResources($this->buildings[$field]['item_id'], $nextLevel);
        $commence += Formulas::buildingUpgradeTime($this->buildings[$field]['item_id'], $nextLevel, $this->main_building_lvl, $this->isWW);
        $db = DB::getInstance();
        $db->query("INSERT INTO building_upgrade (kid, building_field, isMaster, start_time, commence) VALUES ({$this->kid}, {$field}, 0, ".time().", {$commence})");
    }

    /**
     * End of the queue the field belongs to, or now. Romans have separate field
     * (1-18) and building (19-99) queues; other tribes share one. Bounds are
     * inclusive so fields 1/18 and slot 19 chain correctly.
     *
     * @param mixed $field
     */
    private function getLastCommence($field): int
    {
        [$min, $max] = $this->race == Tribes::ROMANS ? ($field <= Slot::LAST_FIELD ? [Slot::FIRST_FIELD, Slot::LAST_FIELD] : [Slot::FIRST_BUILDING, Slot::WONDER]) : [0, Slot::WONDER];
        $commence = (int)DB::getInstance()->fetchScalar("SELECT MAX(commence) FROM building_upgrade WHERE kid={$this->kid} AND isMaster=0 AND building_field BETWEEN $min AND $max");

        return max(time(), $commence);
    }

    /**
     * @param mixed $building_field
     */
    private function getUpgradeState($building_field): int
    {
        $db = DB::getInstance();
        $kid = $this->kid;
        return (int)$db->fetchScalar("SELECT COUNT(id) FROM building_upgrade WHERE kid=$kid AND building_field=$building_field");
    }

    /**
     * @param mixed $dependency
     */
    private function resolveDependencies($dependency): bool
    {
        if ($dependency == 1) {
            //increase crop production
        } else if ($dependency == 2) {
            $warehouses = [
                10 => [],
                38 => [],
            ];
            for ($i = Slot::FIRST_BUILDING; $i <= Slot::LAST_BUILDING; ++$i) {
                if ($this->buildings[$i]['item_id'] == Gid::WAREHOUSE || $this->buildings[$i]['item_id'] == Gid::GREAT_WAREHOUSE) {
                    $warehouses[$this->buildings[$i]['item_id']][] = $i;
                }
            }
            $flag = false;
            foreach ($warehouses[10] as $index) {
                if ($this->upgradeIndex($index, false)) {
                    $flag = true;
                    break;
                }
            }
            if (!$flag) {
                foreach ($warehouses[38] as $index) {
                    if ($this->upgradeIndex($index, false)) {
                        break;
                    }
                }
            }
            //need to increase warehouse level
        } else if ($dependency == 22) {
            //create warehouse
            for ($i = Slot::FIRST_BUILDING; $i <= Slot::LAST_BUILDING; ++$i) {
                if ($this->buildings[$i]['item_id'] == 0) {
                    if (!$this->newBuildingAtIndex($i, Gid::WAREHOUSE)) {
                        $this->newBuildingAtIndex($i, Gid::GREAT_WAREHOUSE);
                    }
                    break;
                }
            }
        } else if ($dependency == 3) {
            //need to increase granny level
            $warehouses = [
                11 => [],
                39 => [],
            ];
            for ($i = Slot::FIRST_BUILDING; $i <= Slot::LAST_BUILDING; ++$i) {
                if ($this->buildings[$i]['item_id'] == Gid::GRANARY || $this->buildings[$i]['item_id'] == Gid::GREAT_GRANARY) {
                    $warehouses[$this->buildings[$i]['item_id']][] = $i;
                }
            }
            $flag = false;
            foreach ($warehouses[11] as $index) {
                if ($this->upgradeIndex($index, false)) {
                    $flag = true;
                    break;
                }
            }
            if (!$flag) {
                foreach ($warehouses[39] as $index) {
                    if ($this->upgradeIndex($index, false)) {
                        break;
                    }
                }
            }
        } else if ($dependency == 33) {
            //create granny
            for ($i = Slot::FIRST_BUILDING; $i <= Slot::LAST_BUILDING; ++$i) {
                if ($this->buildings[$i]['item_id'] == 0) {
                    if (!$this->newBuildingAtIndex($i, Gid::GRANARY)) {
                        $this->newBuildingAtIndex($i, Gid::GREAT_GRANARY);
                    }
                    break;
                }
            }
        }
        return false;
    }

    /**
     * @param int $field
     * @param int $gid
     * @return mixed
     */
    private function newBuildingAtIndex($field, $gid)
    {
        return $this->newBuilding($field, $gid);
    }

    /**
     * @param mixed $field
     * @param bool $resolveDependencies
     * @return mixed
     */
    private function upgradeIndex($field, $resolveDependencies = true)
    {
        if (!$this->skipWorkers && $this->isWorkersBusy($field <= Slot::LAST_FIELD)['isBusy']) return false;
        $helper = new BuildingHelper();
        $max = Formulas::buildingMaxLvl($this->buildings[$field]['item_id'], $this->capital, false);
        $level = $this->buildings[$field]['level'];
        $level += $this->getUpgradeState($field);
        if ($level >= $max) return false;
        $freeCrop = $this->cropProduction + $this->upkeep - $this->getCropLoading($this->kid, $this->isWW, $this->buildings);
        $dependencies = $helper->checkDependencies($this->buildings[$field]['item_id'], $level, $this->isWW, $freeCrop, $this->maxstore, $this->maxcrop);
        if ($dependencies <> 0) {
            if ($resolveDependencies) {
                return $this->resolveDependencies($dependencies);
            }
            return false;
        } else {
            $hasResources = $this->isBuildingResourcesAvailable($this->buildings[$field]['item_id'], $level + 1);
            if ($hasResources) {
                $this->addUpgrade($field, $level + 1);
                return true;
            }
        }
        return false;
    }

    /**
     * @param mixed $kid
     * @param mixed $isWW
     * @param mixed $buildings
     * @return (float|int)|int
     */
    private function getCropLoading($kid, $isWW, $buildings)
    {
        $db = DB::getInstance();
        $upgrades = $db->query("SELECT building_field FROM building_upgrade WHERE isMaster=0 AND kid=" . $kid);
        if (!$upgrades->num_rows) {
            return 0;
        }
        $cu = 0;
        $tmp = [];
        while ($row = $upgrades->fetch_assoc()) {
            if (!isset($tmp[$row['building_field']])) {
                $tmp[$row['building_field']] = 0;
            }
            ++$tmp[$row['building_field']];
            $level = $buildings[$row['building_field']]['level'] + $tmp[$row['building_field']];
            $cu += Formulas::buildingCropConsumption($buildings[$row['building_field']]['item_id'], $level, $isWW);
        }
        return $cu;
    }

    /**
     * @return mixed
     */
    public function upgrade()
    {
        if ((time()-$this->creationTime) < 90 * Time::MINUTE) {
            $rand_field = mt_rand(Slot::FIRST_FIELD, Slot::LAST_FIELD);
        } else {
            $rand_field = mt_rand(1, 5) <= 3 ? mt_rand(Slot::FIRST_FIELD, Slot::LAST_FIELD) : mt_rand(Slot::FIRST_BUILDING, Slot::LAST);
        }
        if ($this->isWW && in_array($rand_field, \Model\WonderOfTheWorldModel::RESERVED_SLOTS)) return false;
        if ($this->buildings[$rand_field]['item_id'] <= 0) {
            return $this->newBuilding($rand_field);
        }
        $levels = [];
        foreach($this->buildings as $index => $build){
            if($build['item_id'] == 0 || ($build['level'] == 0 && $index > Slot::LAST_FIELD)) continue;
            if(!isset($levels[$build['level']])){
                $levels[$build['level']] = [];
            }
            $levels[$build['level']][] = $index;
        }
        ksort($levels);
        if(sizeof($levels)){
            foreach($levels as $level => $childs){
                foreach($childs as $field){
                    if($this->upgradeIndex($field, true)){
                        return true;
                    }
                }
            }
        }
        return false;
    }
}