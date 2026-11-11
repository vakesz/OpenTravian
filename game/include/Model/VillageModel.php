<?php

declare(strict_types=1);

namespace Model;

use Game\FarmList\FarmListInbox;
use Core\Database\DB;
use Game\Buildings\BuildingAction;
use Game\Formulas;
use Game\Map\Map;
use Game\ResourcesHelper;
use function logError;

class VillageModel
{
    /**
     * @return array{cp: int|float, pop: int|float}
     */
    public static function calculateVillageCulturePointsAndPopulation(int $kid, ?bool $isWW = null): array
    {
        $buildings = (new self())->getBuildingsAssoc($kid);
        $db = DB::getInstance();
        if ($isWW === null) {
            $isWW = (int) $db->fetchScalar("SELECT isWW FROM vdata WHERE kid=$kid") === 1;
        }
        $total_cp = $total_pop = 0;
        foreach ($buildings as $index => $building) {
            $item_id = $building['item_id'];
            $level = $building['level'];
            if ($item_id === 0) {
                continue;
            }
            list($pop, $cp) = Formulas::buildingCpPop($item_id, 0, $level, $isWW || Formulas::isGrayArea($kid));
            $total_cp += $cp;
            $total_pop += $pop;
        }
        return [
            'cp' => $total_cp,
            'pop' => $total_pop,
        ];
    }

    public function punishPlayer(int $uid, int $kid, int|float $resources, int|float $Troops, int|float $punishResourcesBuildings, int|float $punishBuildings): bool
    {
        $resources = min(abs($resources), 100);
        $Troops = min(abs($Troops), 100);
        $punishBuildings = min(abs($punishBuildings), 20);
        ignore_user_abort(TRUE);
        set_time_limit(600);
        $db = DB::getInstance();
        if ($resources) {
            if ($kid > 0) {
                $result = $db->query("SELECT kid FROM vdata WHERE owner=$uid AND kid=$kid");
            } else {
                $result = $db->query("SELECT kid FROM vdata WHERE owner=$uid");
            }
            while ($row = $result->fetch_assoc()) {
                ResourcesHelper::updateVillageResources((int) $row['kid']);
            }
            if ($kid > 0) {
                $result = $db->query("SELECT kid, wood, clay, iron, crop FROM vdata WHERE owner=$uid AND kid=$kid");
            } else {
                $result = $db->query("SELECT kid, wood, clay, iron, crop FROM vdata WHERE owner=$uid");
            }
            while ($row = $result->fetch_assoc()) {
                $wood = max($row['wood'] - ceil($row['wood'] * $resources / 100), 0);
                $clay = max($row['clay'] - ceil($row['clay'] * $resources / 100), 0);
                $iron = max($row['iron'] - ceil($row['iron'] * $resources / 100), 0);
                $crop = max($row['crop'] - ceil($row['crop'] * $resources / 100), 0);
                $db->query("UPDATE vdata SET wood=$wood, clay=$clay, iron=$iron, crop=$crop WHERE kid={$row['kid']}");
            }
        }
        if ($Troops) {
            //Starvation
            $result = $db->query("SELECT u.* FROM units u, vdata v WHERE u.kid=v.kid AND v.owner={$uid}");
            while ($row = $result->fetch_assoc()) {
                $modify = [];
                for ($i = 1; $i <= 10; ++$i) {
                    $num = $row['u' . $i] - ceil($row['u' . $i] * $Troops / 100);
                    $modify[] = "u{$i}=$num";
                }
                $db->query("UPDATE units SET " . implode(",", $modify) . " WHERE kid={$row['kid']}");
                ResourcesHelper::updateVillageResources((int) $row['kid'], false);
            }
            $result = $db->query("SELECT e.* FROM enforcement e, vdata v WHERE e.kid=v.kid AND v.owner={$uid}");
            while ($row = $result->fetch_assoc()) {
                $modify = [];
                $total = $row['u11'];
                for ($i = 1; $i <= 10; ++$i) {
                    $num = $row['u' . $i] - ceil($row['u' . $i] * $Troops / 100);
                    $modify[] = "u{$i}=$num";
                    $total += $num;
                }
                if ($total) {
                    $db->query("UPDATE enforcement SET " . implode(",", $modify) . " WHERE id={$row['id']}");
                } else {
                    $db->query("DELETE FROM enforcement WHERE id={$row['id']}");
                }
                //TODO: update upkeep
            }
            $result = $db->query("SELECT t.* FROM trapped t, vdata v WHERE t.kid=v.kid AND v.owner={$uid}");
            while ($row = $result->fetch_assoc()) {
                $modify = [];
                $total = $row['u11'];
                for ($i = 1; $i <= 10; ++$i) {
                    $num = $row['u' . $i] - ceil($row['u' . $i] * $Troops / 100);
                    $modify[] = "u{$i}=$num";
                    $total += $num;
                }
                if ($total) {
                    $db->query("UPDATE trapped SET " . implode(",", $modify) . " WHERE id={$row['id']}");
                } else {
                    $db->query("DELETE FROM trapped WHERE id={$row['id']}");
                }
                //TODO: update upkeep
            }
        }
        if (($punishBuildings + $punishResourcesBuildings) > 0) {
            if ($kid > 0) {
                $result = $db->query("SELECT owner, kid, isWW, capital FROM vdata WHERE owner=$uid AND kid=$kid");
            } else {
                $result = $db->query("SELECT owner, kid, isWW, capital FROM vdata WHERE owner=$uid");
            }
            $accountDeleter = new AccountDeleter();
            while ($row = $result->fetch_assoc()) {
                $buildings = $this->getBuildingsAssoc((int) $row['kid']);
                foreach ($buildings as $index => $build) {
                    $level = $index <= 18 ? $punishResourcesBuildings : $punishBuildings;
                    if ($level > 0 && $build['item_id'] && $build['level']) {
                        BuildingAction::downgrade($row['kid'], $index, $level);
                    }
                }
                $rowKid = (int) $row['kid'];
                $cpPop = self::calculateVillageCulturePointsAndPopulation($rowKid, (int) $row['isWW'] === 1);
                if ($cpPop['pop'] <= 0 && true === $accountDeleter->isVillageDestroyAble(0, $rowKid, (int) $row['owner'])) {
                    $accountDeleter->deleteVillage($rowKid);
                } else {
                    ResourcesHelper::updateVillageResources($rowKid, false);
                }
            }
        }
        return true;
    }

    /** Horse Drinking Trough level (Romans only), 0 when the village has none. */
    public static function getHDP(int $kid, int|string|null $race = null): int
    {
        if ($race !== null && (int) $race !== 1) {
            return 0;
        }

        return self::findBuildingLevel($kid, 18, 41);
    }

    /**
     * @return array<int, array{item_id: int, level: int}> slot => building, empty when the village has no buildings
     */
    public function getBuildingsAssoc(int $kid): array
    {
        $db = DB::getInstance();
        $buildings = $db->query("SELECT * FROM fdata WHERE kid=$kid");
        if (!$buildings || !$buildings->num_rows) {
            logError("No buildings found in getBuildingsAssoc VillageModel for kid $kid");
            return [];
        }
        $buildings = $buildings->fetch_assoc();
        $assoc = [];
        for ($i = 1; $i <= 40; ++$i) {
            $assoc[$i] = [
                "item_id" => (int)$buildings['f' . $i . 't'],
                'level' => (int)$buildings['f' . $i],
            ];
        }
        $assoc[99] = [
            "item_id" => (int)$buildings['f99t'],
            'level' => (int)$buildings['f99'],
        ];
        return $assoc;
    }

    public function updateUserVillageResources(int $uid, bool $simple = true): void
    {
        $db = DB::getInstance();
        $stmt = $db->query("SELECT kid FROM vdata WHERE owner=$uid");
        while ($row = $stmt->fetch_assoc()) {
            ResourcesHelper::updateVillageResources((int) $row['kid'], $simple);
        }
    }

    /** Brewery level of the capital while its festival runs, 0 otherwise. */
    public function getCapBrewery(int $uid): int
    {
        if ($uid === 1) {
            return 0;
        }
        $result = DB::getInstance()->query("SELECT kid, festival FROM vdata WHERE owner=$uid AND capital=1");
        $capVillage = $result ? $result->fetch_assoc() : null;
        if (!is_array($capVillage) || !((int) $capVillage['festival'] > time())) {
            return 0;
        }

        return self::findBuildingLevel((int) $capVillage['kid'], 19, 35);
    }

    public function changeCapital(int $uid, int $new_capital_kid): bool
    {
        $db = DB::getInstance();
        $capital_kid = (int) $db->fetchScalar("SELECT kid FROM vdata WHERE owner=$uid AND capital=1");
        if (!$capital_kid) {
            return false;
        }
        $buildings = $this->getBuildingsAssoc($new_capital_kid);
        for ($i = 19; $i <= 40; ++$i) {
            if (in_array($buildings[$i]['item_id'], [29, 30])) {
                BuildingAction::downgrade($new_capital_kid, $i, 0, true);
            }
        }
        ResourcesHelper::updateVillageResources($new_capital_kid, FALSE);
        $buildings = $this->getBuildingsAssoc($capital_kid);
        for ($i = 19; $i <= 40; ++$i) {
            if (in_array($buildings[$i]['item_id'], [34, 35])) {
                BuildingAction::downgrade($capital_kid, $i, 0, true);
            }
        }
        for ($i = 1; $i <= 18; ++$i) {
            if ($buildings[$i]['level'] > 10) {
                BuildingAction::downgrade($capital_kid, $i, $buildings[$i]['level'] - 10);
            }
        }
        $db->query("UPDATE vdata SET capital=0 WHERE kid=$capital_kid");
        $db->query("UPDATE vdata SET capital=1 WHERE kid=$new_capital_kid");
        ResourcesHelper::updateVillageResources($capital_kid, FALSE);
        ResourcesHelper::updateVillageResources($new_capital_kid, FALSE);
        Map::villageDestroyOrCaptureOrNewVillageUpdate($capital_kid);
        Map::villageDestroyOrCaptureOrNewVillageUpdate($new_capital_kid);
        $db->query("UPDATE users SET profileCacheVersion=profileCacheVersion+1 WHERE id=$uid");
        return true;
    }

    public function removeTribeSpecificBuildings(int $kid): void
    {
        $buildings = $this->getBuildingsAssoc($kid);
        if (sizeof($buildings) == 0) {
            logError("No building. removeTribeSpecificBuildings");
            return;
        }
        $tribeSpecificArray = \Game\Formulas::TRIBE_SPECIFIC_BUILDINGS;
        for ($i = 19; $i <= 40; ++$i) {
            if (isset($buildings[$i]['item_id']) && in_array($buildings[$i]['item_id'], $tribeSpecificArray)) {
                BuildingAction::downgrade($kid, $i, 0, true);
            }
        }
    }

    public function captureVillage(int $uid, int $kid, int|float $pop, int $newUid, int|float $newUidPop, int $newRace, int $expandedFrom): bool
    {
        $db = DB::getInstance();
        FarmListInbox::targetChangedOwner($kid);
        $helper = new AccountDeleter();
        $db->query("UPDATE vdata SET loyalty=0, last_loyalty_update=" . time() . " WHERE kid=$kid");
        $db->query("DELETE FROM training WHERE kid=$kid");
        \Game\Wounded::purge($kid);
        $db->query("DELETE FROM traderoutes WHERE (kid=$kid OR to_kid=$kid)");
        $db->query("DELETE FROM movement WHERE ((kid=$kid AND mode=0) OR (to_kid=$kid AND mode=1)) OR (race=4 AND to_kid={$kid})");
        $db->query("DELETE FROM enforcement WHERE kid=$kid");
        $db->query("DELETE FROM trapped WHERE kid=$kid");
        $db->query("DELETE FROM research WHERE kid=$kid");
        $db->query("DELETE FROM smithy WHERE kid=$kid");
        $db->query("DELETE FROM market WHERE kid=$kid");
        $db->query("DELETE FROM units WHERE kid=$kid");
        $db->query("DELETE FROM tdata WHERE kid=$kid");
        $db->query("DELETE FROM building_upgrade WHERE kid=$kid");
        $db->query("DELETE FROM demolition WHERE kid=$kid");
        $db->run('DELETE FROM village_group_villages WHERE uid=? AND kid=?', [$uid, $kid]);
        $db->query("DELETE FROM send WHERE kid=$kid OR (to_kid=$kid AND mode=1)");
        $db->query("UPDATE odata SET owner=$newUid WHERE did=$kid");
        $traps = $db->query("SELECT * FROM trapped WHERE to_kid=$kid");
        while ($trapped = $traps->fetch_assoc()) {
            $helper->returnTrappedOrEnforcementRow($trapped, FALSE);
        }
        //removing tribe specific buildings.
        $buildings = $this->getBuildingsAssoc($kid);
        if (sizeof($buildings) == 0) {
            logError("No building. while capture village");
        }
        $tribeSpecificArray = \Game\Formulas::TRIBE_SPECIFIC_BUILDINGS;
        for ($i = 19; $i <= 40; ++$i) {
            if (!isset($buildings[$i])) {
                continue;
            }
            if (in_array($buildings[$i]['item_id'], $tribeSpecificArray)) {
                BuildingAction::downgrade($kid, $i, 0, true);
                $buildings[$i]['item_id'] = 0;
                $buildings[$i]['level'] = 0;
            }
        }
        if ($newUidPop > $pop) {
            foreach ($buildings as $index => $build) {
                if ($build['item_id'] && $build['level']) {
                    if ($build['item_id'] > 4 && ($build['level'] - 1) == 0) {
                        $build['item_id'] = 0;
                    }
                    $build['level']--;
                    BuildingAction::downgrade($kid, $index, 1);
                }
            }
        }
        BuildingAction::fixUnUpgradedBuildings($kid);
        $db->query("UPDATE hero SET health=0 WHERE kid=$kid");
        if ($db->affectedRows()) {
            $heroNewKid = (int)$db->fetchScalar("SELECT kid FROM vdata WHERE capital=1 AND owner=$uid");
            if ($heroNewKid > 0) {
                $db->query("UPDATE hero SET kid=$heroNewKid WHERE kid=$kid");
            }
        }
        $village = $db->query("SELECT maxstore, maxcrop, extraMaxstore, extraMaxcrop, pop, cp FROM vdata WHERE kid=$kid")->fetch_assoc();
        $maxstore = $village['maxstore'];
        $maxcrop = $village['maxcrop'];
        if ($village['extraMaxstore']) {
            $maxstore -= $village['extraMaxstore'] * Formulas::storeCAP(20);
        }
        if ($village['extraMaxcrop']) {
            $maxcrop -= $village['extraMaxcrop'] * Formulas::storeCAP(20);
        }
        $db->query("UPDATE users SET total_pop=total_pop-{$village['pop']}, cp_prod=cp_prod-{$village['cp']}, total_villages=total_villages-1 WHERE id=$uid");
        $db->query("UPDATE users SET total_pop=total_pop+{$village['pop']}, cp_prod=cp_prod+{$village['cp']}, total_villages=total_villages+1 WHERE id=$newUid");
        $db->query("UPDATE vdata SET allowLoyaltyDecrease=0, isFarm=0, extraMaxstore=0, extraMaxcrop=0, maxstore=$maxstore, maxcrop=$maxcrop, owner=$newUid, expandedfrom=$expandedFrom WHERE kid=$kid");


        $aid = $db->fetchScalar("SELECT aid FROM users WHERE id=$uid");
        if ($aid) {
            $db->query("UPDATE alidata SET week_pop_changes=week_pop_changes-{$village['pop']} WHERE id=$aid");
        }

        $aid = $db->fetchScalar("SELECT aid FROM users WHERE id=$newUid");
        if ($aid) {
            $db->query("UPDATE alidata SET week_pop_changes=week_pop_changes+{$village['pop']} WHERE id=$aid");
        }


        $register = new RegisterModel();
        $register->addUnits($kid, $newRace);
        $register->addSmithy($kid);
        $register->addTech($kid);
        Map::villageDestroyOrCaptureOrNewVillageUpdate($kid);
        $find = $db->query("SELECT * FROM artefacts WHERE kid={$kid}");
        $m = new ArtefactsModel();
        while ($row = $find->fetch_assoc()) {
            $m->arteIsMine($row['id'], $row['kid'], $newUid);
        }
        //restoring village id to capital
        $user = $db->query("SELECT id FROM users WHERE kid=$kid");
        if ($user->num_rows) {
            $user = $user->fetch_assoc();
            $capital = $db->query("SELECT kid FROM vdata WHERE capital=1 AND owner={$user['id']}");
            if ($capital->num_rows) {
                $capital = $capital->fetch_assoc();
                $db->query("UPDATE users SET kid={$capital['kid']} WHERE id={$user['id']}");
            }
        }
        (new AccountDeleter())->rematchExpands($kid);
        $db->query("UPDATE users SET profileCacheVersion=profileCacheVersion+1 WHERE id=$uid");
        $db->query("UPDATE users SET profileCacheVersion=profileCacheVersion+1 WHERE id=$newUid");
        return true;
    }

    public function removeUnavailableCapitalBuildings(int $owner, int $kid): bool
    {
        $result = DB::getInstance()->query("SELECT * FROM fdata WHERE kid=$kid");
        $buildings = $result ? $result->fetch_assoc() : null;
        if (!is_array($buildings)) {
            return false;
        }
        $tribeSpecificArray = [34, 29, 30, 35];
        for ($i = 19; $i <= 40; $i++) {
            if (in_array((int) $buildings['f' . $i . 't'], $tribeSpecificArray, true)) {
                BuildingAction::downgrade($kid, $i, 0, true);
            }
        }

        return true;
    }

    public static function getTournamentSquireLevel(int $kid): int
    {
        return self::findBuildingLevel($kid, 19, 14);
    }

    /**
     * Level of the first building of type $itemId in slots $firstSlot..38 of the village, 0 when absent.
     */
    private static function findBuildingLevel(int $kid, int $firstSlot, int $itemId): int
    {
        $result = DB::getInstance()->query("SELECT * FROM fdata WHERE kid=$kid");
        $buildings = $result ? $result->fetch_assoc() : null;
        if (!is_array($buildings)) {
            return 0;
        }
        for ($i = $firstSlot; $i <= 38; ++$i) {
            if ((int) $buildings['f' . $i . 't'] === $itemId) {
                return (int) $buildings['f' . $i];
            }
        }

        return 0;
    }
}
