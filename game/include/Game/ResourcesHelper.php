<?php
/**
 * Created by PhpStorm.
 * User: Amirhossein
 * Date: 11/4/2018
 * Time: 11:08
 */

namespace Game;

use Core\Config;
use Core\Database\DB;
use Core\Time;
use Model\ArtefactsModel;
use Model\MasterBuilder;
use Model\MovementsModel;
use Model\VillageModel;

class ResourcesHelper
{
    /**
     * A village benefits from at most three oases. Keep the three with the
     * largest total bonus; ties break on type then oasis id so the choice does
     * not depend on row order.
     *
     * @param array<int, array{kid: int|string, type: int|string}> $oases
     * @return array<int, array{kid: int|string, type: int|string}>
     */
    public static function topBonusOases(array $oases): array
    {
        usort($oases, function (array $a, array $b): int {
            $bonus = fn(array $o): float => array_sum(Formulas::getOasisEffect($o['type']));
            return [$bonus($b), (int)$a['type'], (int)$a['kid']] <=> [$bonus($a), (int)$b['type'], (int)$b['kid']];
        });

        return array_slice($oases, 0, 3);
    }

    /**
     * Oasis production bonus of a village per resource (wood, clay, iron, crop): how many of its three best oases
     * give the resource and the total percentage they add.
     *
     * @return array{count: array<int, int>, bonus: array<int, int>}
     */
    public static function oasisBonus(int $kid): array
    {
        $result = ['count' => [0, 0, 0, 0], 'bonus' => [0, 0, 0, 0]];
        $oases = DB::getInstance()->query("SELECT kid, type FROM odata WHERE did=$kid");
        foreach (self::topBonusOases($oases->fetch_all(MYSQLI_ASSOC)) as $oasis) {
            foreach (Formulas::getOasisEffect($oasis['type']) as $resource => $multiplier) {
                $result['count'][$resource - 1]++;
                $result['bonus'][$resource - 1] += $multiplier * 25;
            }
        }

        return $result;
    }

    /**
     * @param mixed $uid
     * @param mixed $kid
     * @param mixed $upkeep
     * @param int $mode
     */
    public static function modifyUpkeep($uid, $kid, $upkeep, $mode = 0): void
    {
        if ($upkeep <= 0) return;
        self::updateVillageResources($kid, true);
        $db = DB::getInstance();
        $isWW = $db->fetchScalar("SELECT isWW FROM vdata WHERE kid=$kid");
        if ($isWW && ArtefactsModel::wwPlansReleased()) {
            $upkeep /= 2;
        }
        if (ArtefactsModel::artifactsReleased()) {
            $upkeep *= ArtefactsModel::getArtifactEffectByType($uid, $kid, ArtefactsModel::ARTIFACT_DIET);
        }
        $upkeep = ceil($upkeep);
        if ($mode == 0) {
            $db->query("UPDATE vdata SET upkeep=upkeep+$upkeep WHERE kid=$kid");
            return;
        }
        $db->query("UPDATE vdata SET upkeep=upkeep-$upkeep WHERE kid=$kid");
    }

    /**
     * @param mixed $uid
     * @param mixed $kid
     * @param mixed $isWW
     * @param (float|int)|int $hdp
     * @return (float|int)
     */
    public static function updateVillageUpkeep($uid, $kid, $isWW = -1, $hdp = -1)
    {
        $db = DB::getInstance();
        if ($uid == -1 || $isWW == -1) {
            $village = $db->query("SELECT owner, isWW FROM vdata WHERE kid=$kid");
            if ($village->num_rows) {
                $village = $village->fetch_assoc();
                $uid = $village['owner'];
                $isWW = $village['isWW'] == 1;
            }
        }
        if ($hdp < 0) {
            $hdp = VillageModel::getHDP($kid);
        }
        $upkeep = 0;
        if (getGame("starvation")) {
            $units = $db->query("SELECT * FROM units WHERE kid=$kid")->fetch_assoc();
            $heroHealth = (int)$db->fetchScalar("SELECT health FROM hero WHERE uid=$uid AND kid=$kid");
            for ($i = 1; $i <= Unit::LAST; ++$i) {
                // No units row while a village is being captured or deleted: no own troops to feed.
                if (!$units || !$units['u' . $i]) {
                    continue;
                }
                // A dead hero keeps the flat 6 crop production and does not eat.
                if ($i === Unit::HERO && $heroHealth <= 0) {
                    continue;
                }
                $upkeep += Formulas::uUpkeep(nrToUnitId($i, $units['race']), $hdp) * $units['u' . $i];
            }
            $oasesString = trim((string)$db->fetchScalar("SELECT GROUP_CONCAT(kid) FROM odata WHERE did=$kid"));
            $oasesArray = [];
            if (!empty($oasesString)) {
                $oasesArray = explode(",", $oasesString);
            }
            $oasesArray[] = $kid;
            $toMeReinforcements = $db->query("SELECT * FROM enforcement WHERE to_kid IN(" . implode(",", $oasesArray) . ")");
            while ($un = $toMeReinforcements->fetch_assoc()) {
                for ($i = 1; $i <= Unit::LAST; ++$i) {
                    if (!$un['u' . $i]) {
                        continue;
                    }
                    $upkeep += Formulas::uUpkeep(nrToUnitId($i, $un['race']), $hdp) * $un['u' . $i];
                }
            }
            //own trapped units
            $trapped = $db->query($q = "SELECT * FROM trapped WHERE kid=$kid");
            while ($un = $trapped->fetch_assoc()) {
                for ($i = 1; $i <= Unit::LAST; ++$i) {
                    if (!$un['u' . $i]) {
                        continue;
                    }
                    $upkeep += Formulas::uUpkeep(nrToUnitId($i, $un['race']), $hdp) * $un['u' . $i];
                }
            }
            $movements = $db->query("SELECT race, u1, u2, u3, u4, u5, u6, u7, u8, u9, u10, u11 FROM movement WHERE (kid=$kid AND mode=" . MovementsModel::MODE_GOING . ") OR (to_kid=$kid AND mode=" . MovementsModel::MODE_RETURN . ")");
            while ($un = $movements->fetch_assoc()) {
                for ($i = 1; $i <= Unit::LAST; ++$i) {
                    if (!$un['u' . $i]) {
                        continue;
                    }
                    $upkeep += Formulas::uUpkeep(nrToUnitId($i, $un['race']), $hdp) * $un['u' . $i];
                }
            }
            if (ArtefactsModel::artifactsReleased()) {
                $upkeep *= ArtefactsModel::getArtifactEffectByType($uid, $kid, ArtefactsModel::ARTIFACT_DIET);
            }
            if ($isWW && ArtefactsModel::wwPlansReleased()) {
                //halved production in ww villages once building plans released
                $upkeep /= 2;
            }
        }
        $db->query("UPDATE vdata SET upkeep=$upkeep WHERE kid=$kid");
        return $upkeep;
    }

    /**
     * Crop figure used by the building gate. Base crop is fields, oases, the
     * mill and the bakery. Free crop subtracts population and queued building
     * upkeep. Hero production and the +25% crop bonus are left out.
     *
     * @return array{base: float, free: float}
     */
    public static function legendsCropGate(int $kid, float $queuedBuildingCrop = 0): array
    {
        $db = DB::getInstance();
        $row = $db->query("SELECT owner, cropp, pop FROM vdata WHERE kid=$kid")->fetch_assoc();
        if (!$row) {
            return ['base' => 0.0, 'free' => 0.0];
        }
        $owner = (int)$row['owner'];
        $bonusOn = (int)$db->fetchScalar("SELECT b4 FROM users WHERE id=$owner") > time();
        $beforeBonus = $bonusOn ? ((float)$row['cropp'] / 1.25) : (float)$row['cropp'];
        $hero = $db->query("SELECT production, productionType, health FROM hero WHERE uid=$owner AND kid=$kid")->fetch_assoc();
        $heroCrop = 0.0;
        if ($hero) {
            $heroCrop = 6.0;
            if ((int)$hero['health'] > 0) {
                $tribe = (int)$db->fetchScalar("SELECT race FROM users WHERE id=$owner");
                if ((int)$hero['productionType'] === 0) {
                    $heroCrop += (float)$hero['production'] * Formulas::heroProductionPerPoint($tribe, false);
                } elseif ((int)$hero['productionType'] === 4) {
                    $heroCrop += (float)$hero['production'] * Formulas::heroProductionPerPoint($tribe, true);
                }
            }
        }
        $base = $beforeBonus - $heroCrop;
        $free = $base - (float)$row['pop'] - $queuedBuildingCrop;

        return ['base' => $base, 'free' => $free];
    }

    /**
     * @param mixed $race
     * @param array<int, int> $units
     * @param int $hdp
     * @return (float|int)|int
     */
    public static function getTotalCropConsumption($race, $units, $hdp)
    {
        if ($race == Tribes::NATURE) {
            return 0;
        }
        $total = 0;
        foreach ($units as $nr => $amount) {
            if ($nr <= 10) {
                $unitId = nrToUnitId($nr, $race);
            } else {
                $unitId = Unit::HERO_ID;
            }
            $total += $amount * Formulas::uUpkeep($unitId, $hdp);
        }
        return $total;
    }

    /**
     * @param mixed $kid
     * @param bool $simple
     * @param bool $accrueAtExistingRate
     */
    public static function updateVillageResources($kid, $simple = TRUE, $accrueAtExistingRate = TRUE): void
    {
        if (!is_numeric($kid)) {
            return;
        }
        $config = Config::getInstance();
        if (!$simple && $accrueAtExistingRate) {
            self::updateVillageResources($kid, true);
        }
        $db = DB::getInstance();
        $find = $db->query("SELECT isWW, pop, owner, kid, wood, woodp, clay, clayp, iron, ironp, crop, cropp, maxstore, maxcrop, lastmupdate, upkeep FROM vdata WHERE kid=$kid");
        if (!$find->num_rows) {
            return;
        }
        $row = $find->fetch_assoc();
        unset($find);
        if ($simple) {
            $lastmupdate = miliseconds();
            $diff_time = ($lastmupdate - $row['lastmupdate']);
            $production = [$row['woodp'], $row['clayp'], $row['ironp'], $row['cropp'] - $row['upkeep'] - $row['pop']];
            $cur_res = [$row['wood'], $row['clay'], $row['iron'], $row['crop']];
            $resources = [
                (($production[0] / Time::HOUR_MS) * $diff_time),
                (($production[1] / Time::HOUR_MS) * $diff_time),
                (($production[2] / Time::HOUR_MS) * $diff_time),
                (($production[3] / Time::HOUR_MS) * $diff_time),
            ];
            foreach ($resources as $key => $res) {
                $nextValue = $res + $cur_res[$key];
                $storage = $row[$key < 3 ? 'maxstore' : 'maxcrop'];
                if ($nextValue >= $storage) {
                    $resources[$key] = $row[$key < 3 ? 'maxstore' : 'maxcrop'] - $cur_res[$key];
                }
                $resources[$key] = round($resources[$key], 4);
            }
            $absoluteCrop = false;
            if ($row['owner'] == UserId::NATARS && $row['crop'] + $resources[3] < 0) {
                $resources[3] = $row['maxcrop'] * 2 / 3;
                $absoluteCrop = true;
            }
            if (!getGame("starvation") && ($row['crop'] + $resources[3]) <= 0) {
                $resources[3] = -($row['crop'] + $resources[3]) + 1000;
                $absoluteCrop = true;
            }
            // Relative credits, so a debit committed after our read is kept. The lastmupdate guard makes sure
            // only one writer credits a given interval: when it matches nothing, another writer has already
            // accrued up to its own lastmupdate and there is nothing left to add.
            $cropSql = $absoluteCrop ? "crop={$resources[3]}" : "crop=LEAST(maxcrop, crop+{$resources[3]})";
            $db->query("UPDATE vdata SET wood=LEAST(maxstore, wood+{$resources[0]}), clay=LEAST(maxstore, clay+{$resources[1]}), iron=LEAST(maxstore, iron+{$resources[2]}), $cropSql, lastmupdate=$lastmupdate WHERE kid={$row['kid']} AND lastmupdate=" . (int)$row['lastmupdate']);
            $master = new MasterBuilder();
            $master->updateCommence($row['kid'], FALSE);
            return;
        }
        $fields = $db->query("SELECT * FROM fdata WHERE kid={$row['kid']}")->fetch_assoc();
        if (!$fields) {
            // The village is being created or removed; there are no fields to produce from yet.
            return;
        }
        $production = [0, 0, 0, 0];
        $bonusPercent = [0, 0, 0, 0];
        $plusBonusPercent = [0, 0, 0, 0];
        $waterWorks = 0;
        $productionFields = [];
        for ($i = Slot::FIRST_FIELD; $i <= Slot::LAST_BUILDING; ++$i) {
            if ((!$fields['f' . $i . 't'] || !$fields['f' . $i]) && $i > Slot::LAST_FIELD) {
                continue;
            }
            if ($fields['f' . $i . 't'] <= 4) {
                $productionFields[] = [
                    "item_id" => $fields['f' . $i . 't'],
                    "level" => $fields['f' . $i],
                ];
            } else if ($fields['f' . $i . 't'] <= 9) {
                $bonusPercent[$fields['f' . $i . 't'] == 9 ? 3 : ($fields['f' . $i . 't'] - 5)] += $fields['f' . $i] * 5;
            } else if ($fields['f' . $i . 't'] == 45) {
                $waterWorks = $fields['f' . $i];
            }
        }
        unset($fields);
        $oasisBonusPercent = self::oasisBonus((int)$row['kid'])['bonus'];
        $user = $db->query("SELECT race, b1, b2, b3, b4 FROM users WHERE id={$row['owner']} LIMIT 1");
        if (!$user->num_rows) {
            return;
        }
        $user = $user->fetch_assoc();
        $playerTribe = $user['race'];
        if ($user['b1'] > time()) {
            $plusBonusPercent[0] += 25;
        }
        if ($user['b2'] > time()) {
            $plusBonusPercent[1] += 25;
        }
        if ($user['b3'] > time()) {
            $plusBonusPercent[2] += 25;
        }
        if ($user['b4'] > time()) {
            $plusBonusPercent[3] += 25;
        }
        $totalProd = [0, 0, 0, 0];
        $waterWorksEffect = (1 + (($waterWorks * 5) / 100));
        foreach ($productionFields as $field) {
            if (!isset($totalProd[$field['item_id'] - 1])) {
                continue;
            }
            $i = $field['item_id'] - 1;
            $bonusMultiplier = (($bonusPercent[$i] + ($oasisBonusPercent[$i] * $waterWorksEffect)) / 100);
            $prod = Formulas::fieldProduction($field['level']);
            if ($row['owner'] == UserId::NATARS && $field['item_id'] == Gid::CROPLAND) {
                $prod *= 2;
            }
            $totalProd[$i] += $prod + round5($prod * $bonusMultiplier);
        }
        unset($productionFields, $prod);
        $heroData = $db->query("SELECT production, productionType, health FROM hero WHERE kid={$row['kid']} AND uid={$row['owner']}");
        if ($heroData->num_rows) {
            $heroData = $heroData->fetch_assoc();
            // A dead home hero no longer contributes allocated production, but
            // its fixed six crop per hour remains active.
            $production[3] += 6;
            if ((int)$heroData['health'] > 0) {
                switch ($heroData['productionType']) {
                    case 0:
                        for ($i = 0; $i <= 3; ++$i) {
                            $production[$i] += $heroData['production'] * Formulas::heroProductionPerPoint((int)$playerTribe, false);
                        }
                        break;
                    default:
                        $production[$heroData['productionType'] - 1] += $heroData['production'] * Formulas::heroProductionPerPoint((int)$playerTribe, true);
                        break;
                }
            }
        }
        unset($heroData);
        foreach ($production as $k => $v) {
            $prod = $totalProd[$k] + $production[$k];
            $production[$k] = $prod * ((100 + $plusBonusPercent[$k]) / 100);
        }
        unset($prod);
        $upkeep = $row['upkeep'];
        $lastmupdate = miliseconds();
        $diff_time = ($lastmupdate - $row['lastmupdate']);
        $cur_res = [$row['wood'], $row['clay'], $row['iron'], $row['crop']];
        $resources = [
            (($production[0] / Time::HOUR_MS) * $diff_time),
            (($production[1] / Time::HOUR_MS) * $diff_time),
            (($production[2] / Time::HOUR_MS) * $diff_time),
            ((($production[3] - $row['pop'] - $upkeep) / Time::HOUR_MS) * $diff_time),
        ];
        foreach ($resources as $key => $res) {
            $nextValue = $res + $cur_res[$key];
            if ($nextValue >= $row[$key < 3 ? 'maxstore' : 'maxcrop']) {
                $resources[$key] = $row[$key < 3 ? 'maxstore' : 'maxcrop'] - $cur_res[$key];
            }
            $resources[$key] = round($resources[$key], 4);
        }
        $updateWithMaster = FALSE;
        if (array_sum($production) <> array_sum([
                $row['woodp'],
                $row['clayp'],
                $row['ironp'],
                $row['cropp'] - $row['pop'] - $upkeep
            ])) {
            $updateWithMaster = TRUE;
        }
        $absoluteCrop = false;
        if ($row['owner'] == UserId::NATARS && $row['crop'] + $resources[3] < 0) {
            $resources[3] = $row['maxcrop'] * 2 / 3;
            $absoluteCrop = true;
        }
        if (!getGame("starvation") && ($row['crop'] + $resources[3]) <= 0) {
            $resources[3] = -($row['crop'] + $resources[3]) + 1000;
            $absoluteCrop = true;
        }
//        else if (getGame("starvation") && ($row['crop'] + $resources[3]) <= 0 && $upkeep <= 0) {
//            $resources[3] = -($row['crop'] + $resources[3]) + 1000;
//            $crop = 0;
//        }

        // Same relative, guarded credit as the simple mode above. The new production must be stored even when the
        // guard misses (another writer accrued first, at the old rate, up to its own lastmupdate): otherwise the
        // village would keep a stale rate after a building, hero or item change.
        // affected_rows counts changed rows, so it is also 0 when the guard matched but nothing changed (same
        // millisecond, same production). The production-only write is then a no-op, so that case is harmless.
        $cropSql = $absoluteCrop ? "crop={$resources[3]}" : "crop=LEAST(maxcrop, crop+{$resources[3]})";
        $db->query("UPDATE vdata SET
                                            wood=LEAST(maxstore, wood+{$resources[0]}), woodp={$production[0]},
                                            clay=LEAST(maxstore, clay+{$resources[1]}), clayp={$production[1]},
                                            iron=LEAST(maxstore, iron+{$resources[2]}), ironp={$production[2]},
                                            $cropSql, cropp={$production[3]},
                                            lastmupdate=$lastmupdate WHERE kid=$kid AND lastmupdate=" . (int)$row['lastmupdate']);
        if ((int)$db->affectedRows() === 0) {
            $db->query("UPDATE vdata SET woodp={$production[0]}, clayp={$production[1]}, ironp={$production[2]}, cropp={$production[3]} WHERE kid=$kid");
        }
        if ($updateWithMaster) {
            $master = new MasterBuilder();
            $master->updateCommence($kid, FALSE);
        }
    }

    /**
     * Rebuild a village's hourly production from its fields and modifiers
     * without first crediting elapsed time using a stale cached rate.
     *
     * This is the safe operation after changing a world-speed profile. Normal
     * gameplay mutations should keep using updateVillageResources($kid, false)
     * so resources are settled at the rate that applied before the mutation.
     *
     * @param mixed $kid
     */
    public static function recalculateVillageProduction($kid): void
    {
        self::updateVillageResources($kid, false, false);
    }
}
