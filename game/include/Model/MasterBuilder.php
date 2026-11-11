<?php

declare(strict_types=1);

namespace Model;

use Core\Config;
use Core\Database\DB;
use Game\Buildings\BuildingHelper;
use Game\Formulas;
use Game\GoldHelper;
use Game\ResourcesHelper;

use function logError;

class MasterBuilder
{
    /**
     * Starts a queued master-builder order once its worker and resources are free.
     *
     * @param array<string, mixed> $row building_upgrade row
     */
    public function process(array $row): void
    {
        $db = DB::getInstance();
        ResourcesHelper::updateVillageResources((int) $row['kid'], true);
        $village = $db->query("SELECT capital, upkeep, isWW, pop, owner, kid, wood, woodp, clay, clayp, iron, ironp, crop, cropp, maxstore, maxcrop, lastmupdate FROM vdata WHERE kid={$row['kid']}");
        if (!$village->num_rows) {
            $db->query("DELETE FROM building_upgrade WHERE kid={$row['kid']}");
            //logError("MasterBuilder: Village does not exist.");
            return;
        }
        $village = $village->fetch_assoc();
        $player = $db->query("SELECT aid, race, gift_gold, bought_gold, plus FROM users WHERE id={$village['owner']} LIMIT 1");
        if (!$player->num_rows) {
            $db->query("DELETE FROM building_upgrade WHERE kid={$row['kid']}");
            //logError("MasterBuilder: Player does not exist.");
            return;
        }
        $player = $player->fetch_assoc();
        $buildings = $this->sortBuildings((int) $row['kid']);
        $item_id = $buildings['buildings'][$row['building_field']]['item_id'];
        $level = $buildings['buildings'][$row['building_field']]['level'] + 1;
        $level += (int)$db->fetchScalar("SELECT COUNT(id) FROM building_upgrade WHERE isMaster=0 AND building_field={$row['building_field']} AND kid={$row['kid']}");
        $costs = Formulas::buildingUpgradeCosts($item_id, $level);
        $workers = $this->isWorkersBusy((int) $player['race'],
            $player['plus'] >= time(),
            (int) $row['kid'],
            $row['building_field'] <= 18,
            $item_id == 40);
        if ($workers['isBusy']) {
            //logError("MasterBuilder: Workers were busy.");
            return;
        }
        //ignore  ww here
        if ($item_id <> 40) {
            if (!$this->isResourcesAvailable($village, $costs)) {
                //maybe a server lag!
                //logError("MasterBuilder: Resources not available.");
                return;
            }
        }
        if (!$db->begin_transaction()) {
            logError('MasterBuilder: Unable to start scheduled build transaction.');
            return;
        }

        $gold = 0;
        if ($item_id <> 40) {
            $gold = Config::getInstance()->gold->masterBuilderGold;
            if (($player['gift_gold'] + $player['bought_gold']) < $gold) {
                $db->rollback();
                $db->query("DELETE FROM building_upgrade WHERE isMaster=1 AND kid={$row['kid']}");
                logError('MasterBuilder: Not enough gold.');
                return;
            }
        }

        try {
            $changed = 'The queued build or its Gold debit changed before it could be processed.';
            if ($item_id <> 40 && $gold > 0 && !GoldHelper::decreaseGold($village['owner'], $gold, true)) {
                throw new \RuntimeException($changed);
            }
            $dequeued = $db->query("DELETE FROM building_upgrade WHERE id={$row['id']} AND isMaster=1");
            $dequeuedRows = $db->affectedRows();
            if ($dequeued === false || $dequeuedRows !== 1) {
                throw new \RuntimeException($changed);
            }

            $kid = (int) $row['kid'];
            $commence = $this->getLastCommence($kid, (int)$row['building_field'], (int)$player['race']);
            $commence += Formulas::buildingUpgradeTime($item_id, $level, $buildings['mainBuildingLevel'], $village['isWW']);
            if ($db->query("INSERT INTO building_upgrade (`kid`, `building_field`, `isMaster`, start_time, `commence`) VALUES ({$row['kid']},{$row['building_field']},0," . time() . ",$commence)") === false) {
                throw new \RuntimeException('Could not create the scheduled build.');
            }
            if ($item_id <> 40) {
                $deducted = $db->query(
                    "UPDATE vdata SET wood=wood-{$costs[0]}, clay=clay-{$costs[1]}, iron=iron-{$costs[2]}, crop=crop-{$costs[3]}"
                    . " WHERE kid={$row['kid']} AND wood>={$costs[0]} AND clay>={$costs[1]} AND iron>={$costs[2]} AND crop>={$costs[3]}"
                );
                if ($deducted === false || $db->affectedRows() !== 1) {
                    throw new \RuntimeException('The required resources changed before the scheduled build could start.');
                }
            }
            if (!$db->commit()) {
                throw new \RuntimeException('Could not commit the scheduled build.');
            }
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            logError('MasterBuilder: ' . $exception->getMessage());
        }
    }

    private function getLastCommence(int $kid, int $field, int $race): int
    {
        [$min, $max] = $race == 1 ? ($field <= 18 ? [1, 18] : [19, 99]) : [0, 99];
        $commence = (int)DB::getInstance()->fetchScalar("SELECT MAX(commence) FROM building_upgrade WHERE kid=$kid AND isMaster=0 AND building_field BETWEEN $min AND $max");

        return max(time(), $commence);
    }

    /**
     * @return array{buildings: array<int, array{item_id: int, level: int}>, mainBuildingLevel: int}
     */
    private function sortBuildings(int $kid): array
    {
        $db = DB::getInstance();
        $buildings_db = $db->query("SELECT * FROM fdata WHERE kid={$kid}")->fetch_assoc() ?? [];
        $whole_village_buildings = [];
        $mainBuildingLevel = 0;
        for ($i = 1; $i <= 40; $i++) {
            $whole_village_buildings[$i] = [
                'item_id' => (int) ($buildings_db['f' . $i . 't'] ?? 0),
                'level'   => (int) ($buildings_db['f' . $i] ?? 0),
            ];
            if ($whole_village_buildings[$i]['item_id'] == 15) {
                $mainBuildingLevel = $whole_village_buildings[$i]['level'];
            }
        }
        $whole_village_buildings[99] = [
            'item_id' => (int) ($buildings_db['f99t'] ?? 0),
            'level'   => (int) ($buildings_db['f99'] ?? 0),
        ];
        return [
            'buildings'         => $whole_village_buildings,
            'mainBuildingLevel' => $mainBuildingLevel,
        ];
    }

    /**
     * @return array{fieldsNum: int, buildsNum: int, isBusy: bool, isPlusUsed: bool}
     */
    private function isWorkersBusy(int $race, bool $hasPlus, int $kid, bool $isField, bool $isWW): array
    {
        $db = DB::getInstance();
        $workers = ['fieldsNum' => 0, 'buildsNum' => 0];
        $stmt = $db->query("SELECT building_field FROM building_upgrade WHERE isMaster=0 AND kid=$kid");
        while ($row = $stmt->fetch_assoc()) {
            ++$workers[$row['building_field'] <= 18 ? 'fieldsNum' : 'buildsNum'];
        }
        $maxTasks = $hasPlus ? 2 : 1; //its with wonder
        if ($isWW) {
            $maxTasks = 2;
        }
        if ($race == 1) {
            return [
                'fieldsNum'  => $workers['fieldsNum'],
                'buildsNum'  => $workers['buildsNum'],
                'isBusy'     => (($isField) ? ($workers['fieldsNum'] >= $maxTasks) : ($workers['buildsNum'] >= $maxTasks)) || ($workers['fieldsNum'] + $workers['buildsNum']) >= 3,
                'isPlusUsed' => $hasPlus && ($isField ? ($workers['fieldsNum'] > 0) : ($workers['buildsNum'] > 0)),
            ];
        }
        return [
            'fieldsNum'  => $workers['fieldsNum'],
            'buildsNum'  => $workers['buildsNum'],
            'isBusy'     => ($workers['buildsNum'] + $workers['fieldsNum']) >= $maxTasks,
            'isPlusUsed' => $hasPlus && ($workers['buildsNum'] + $workers['fieldsNum']) > 0,
        ];
    }

    /**
     * @param array<string, mixed> $current_resources vdata row
     * @param array<int, int|float> $costs wood, clay, iron, crop
     */
    private function isResourcesAvailable(array $current_resources, array $costs): bool
    {
        $current_resources = [
            (float) $current_resources['wood'],
            (float) $current_resources['clay'],
            (float) $current_resources['iron'],
            (float) $current_resources['crop'],
        ];
        foreach ($costs as $k => $v) {
            if ($v > floor($current_resources[$k])) {
                return false;
            }
        }

        return true;
    }

    public function updateCommence(int $kid, bool $update = true, bool $simple = false): bool
    {
        $maxTime = time() + 100 * 86400;
        $helper = new BuildingHelper();
        $db = DB::getInstance();
        $masterBuilders = $db->query("SELECT * FROM building_upgrade WHERE isMaster=1 AND kid={$kid} ORDER BY id");
        if (!$masterBuilders->num_rows) {
            return false;
        }
        $village = $db->query("SELECT capital, upkeep, isWW, pop, owner, kid, wood, woodp, clay, clayp, iron, ironp, crop, cropp, maxstore, maxcrop, lastmupdate FROM vdata WHERE kid=$kid");
        if (!$village->num_rows) {
            return false;
        }
        $village = $village->fetch_assoc();
        $player = $db->query("SELECT aid, race, plus FROM users WHERE id={$village['owner']} LIMIT 1");
        if (!$player->num_rows) {
            return false;
        }
        if ($update) {
            ResourcesHelper::updateVillageResources($kid, $simple);
        }
        $player = $player->fetch_assoc();
        $buildings = $this->sortBuildings($kid);
        if (!$village['isWW']) {
            $wwLevel = -1;
        } else {
            $onLoadLevels = (int)$db->fetchScalar("SELECT COUNT(id) FROM building_upgrade WHERE isMaster=0 AND building_field=99 AND kid=$kid");
            $wwLevel = $buildings['buildings'][99]['level'] + (int)$onLoadLevels;
        }
        $previous_commence = 0;
        $queryBatch = [];
        while ($row = $masterBuilders->fetch_assoc()) {
            $item_id = $buildings['buildings'][$row['building_field']]['item_id'];
            $level = $buildings['buildings'][$row['building_field']]['level'] + 1;
            $level += (int)$db->fetchScalar("SELECT COUNT(id) FROM building_upgrade WHERE isMaster=0 AND building_field={$row['building_field']} AND kid=$kid");
            $commence = time() + $previous_commence;
            if ($level > Formulas::buildingMaxLvl($item_id, $village['capital'])) {
                $this->deleteProcess($row['id'], $kid, $row['building_field'], $level);
                continue;
            }
            if ($row['building_field'] > 18 && $level == 1 && $helper->canCreateNewBuild($village['capital'],
                    $player['race'],
                    $item_id,
                    $buildings['buildings'],
                    true,
                    $item_id == 49 && $helper->isShoreVillage((int)$kid)) <> 1) {
                $this->deleteProcess($row['id'], $kid, $row['building_field'], $level);
                continue;
            }
            if ($helper->checkArtifactDependencies($player['aid'],
                    $village['owner'],
                    $kid,
                    $item_id,
                    $village['isWW'],
                    $wwLevel) <> 0) {
                $this->deleteProcess($row['id'], $kid, $row['building_field'], $level);
                continue;
            }
            $freeCrop = $village['cropp'] + $village['upkeep'] - $this->getCropLoading($row['kid'], $village['isWW'], $buildings['buildings']);
            if ($helper->checkDependencies($item_id,
                    $level,
                    $village['isWW'],
                    $freeCrop,
                    $village['maxstore'],
                    $village['maxcrop']) <> 0) {
                $this->deleteProcess($row['id'], $kid, $row['building_field'], $level);
                continue;
            }
            $workers = $this->isWorkersBusy((int) $player['race'],
                $player['plus'] >= time(),
                (int) $row['kid'],
                $item_id <= 4,
                $item_id == 40);
            if ($workers['isBusy']) {
                $end_time = $db->fetchScalar("SELECT commence FROM building_upgrade WHERE isMaster=0 AND kid={$row['kid']} ORDER BY commence ASC LIMIT 1");
                if ($end_time > $commence) {
                    $commence += (int)$end_time - time();
                }
            }
            //ignore WW here cuz it's not actually master and we got the resources first.
            if ($item_id <> 40) {
                $cost = Formulas::buildingUpgradeCosts($item_id, $level);
                if (!$this->isResourcesAvailable($village, $cost)) {
                    $commence += $this->calcualteAvailable($village, $cost);
                }
            }
            if ($commence <> $row['commence']) {
                // check outrange
                if($commence > $maxTime)
                    $commence = $maxTime;
                $queryBatch[] = "UPDATE building_upgrade SET commence={$commence} WHERE id={$row['id']}";
            }
            $previous_commence += $commence - time();
        }
        foreach ($queryBatch as $query) {
            $db->query($query);
        }

        return true;
    }

    private function deleteProcess(int|string $processId, int $wid, int|string $building_field, int $level): mixed
    {
        $db = DB::getInstance();
        if ($level == 1 && $building_field > 18 && $building_field < 99) {
            $db->query("UPDATE fdata SET f{$building_field}t=0 WHERE kid=" . $wid);
        }

        return $db->query("DELETE FROM building_upgrade WHERE id=" . $processId);
    }

    /**
     * @param array<int, array{item_id: int, level: int}> $buildings
     */
    private function getCropLoading(int|string $kid, mixed $isWW, array $buildings): int|float
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
     * @param array<string, mixed> $data vdata row
     * @param array<int, int|float> $costs
     */
    private function calcualteAvailable(array $data, array $costs): float
    {
        //TODO: i don't know how they calculate minus crop in master builder but no problem we set to update every day
        // resources are not valid! calculate resources until end of plus and again further!
        $timeNeeded = 0;
        $cur_res = [(float) $data['wood'], (float) $data['clay'], (float) $data['iron'], (float) $data['crop']];
        $cur_prod = [
            (float) $data['woodp'],
            (float) $data['clayp'],
            (float) $data['ironp'],
            (float) $data['cropp'],
        ];
        for ($i = 0; $i < 4; ++$i) {
            if (floor($cur_res[$i]) < $costs[$i]) {
                if ($i == 3 && $cur_prod[$i] <= 0) {
                    $neededTime = 86400;
                } else {
                    $neededTime = ($costs[$i] - floor($cur_res[$i])) / $cur_prod[$i] * 3600000;
                }
                if ($neededTime > $timeNeeded) {
                    $timeNeeded = $neededTime;
                }
            }
        }

        return floor($timeNeeded / 1000);
    }
}
