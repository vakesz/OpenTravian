<?php

namespace Game\Buildings;

use Core\Config;
use Core\Database\DB;
use Game\Formulas;
use Model\ArtefactsModel;
use Model\WonderOfTheWorldModel;
use function getCustom;

class BuildingHelper
{
    // @return:
    //  1: can be build
    //  0: available soon
    // -1: can not be build
    /** A village is a harbor village for good once settled on an abandoned shore (wdata.shore). */
    public function isShoreVillage(int $kid): bool
    {
        return (int)DB::getInstance()->fetchScalar("SELECT shore FROM wdata WHERE id=?", $kid) === 1;
    }

    /**
     * @param mixed $capital
     * @param mixed $tribe
     * @param mixed $item_id
     * @param mixed $buildings
     * @param bool $isMaster
     * @param bool $shore
     */
    public function canCreateNewBuild($capital, $tribe, $item_id, $buildings, $isMaster = FALSE, $shore = FALSE): int
    {
        $buildingMetaData = Formulas::$data['buildings'];
        if (!isset($buildingMetaData[$item_id - 1])) {
            return -1;
        }
        $buildMetadata = $buildingMetaData[$item_id - 1];
        //capital and non capital
        if (isset($buildMetadata['req']['capital'])) {
            if ($buildMetadata['req']['capital'] == -1 && $capital) {
                return -1;
            } else if ($buildMetadata['req']['capital'] == 1 && !$capital) {
                return -1;
            }
        }
        if (!empty($buildMetadata['req']['shore']) && !$shore) {
            return -1;
        }
        if (isset($buildMetadata['req']['races']) && !in_array((int)$tribe, $buildMetadata['req']['races'], true)) {
            return -1;
        }
        if (isset($buildMetadata['req']['race'])) {
            if ($tribe <> $buildMetadata['req']['race'] && !($item_id == 31 && $tribe == 5)) {
                return -1;
            }
        }
        // check for support multiple
        $alreadyBuilt = FALSE;
        $alreadyBuiltWithMaxLevel = FALSE;
        foreach ($buildings as $villageBuild) {
            $villageBuild['item_id'] = (int)$villageBuild['item_id'];
            $villageBuild['level'] = (int)$villageBuild['level'];
            if ($villageBuild['item_id'] == $item_id) {
                $alreadyBuilt = $isMaster ? $villageBuild['level'] > 0 : TRUE;
                if ($villageBuild['level'] == Formulas::buildingMaxLvl($item_id, $capital)) {
                    $alreadyBuiltWithMaxLevel = TRUE;
                    break;
                }
            }
        }
        if ($alreadyBuilt) {
            $multi = FALSE;
            if (isset($buildMetadata['req']) && isset($buildMetadata['req']['multi'])) {
                $multi = $buildMetadata['req']['multi'] == 'true';
            }
            if (!$multi) {
                return -1;
            } else {
                if (!$alreadyBuiltWithMaxLevel) {
                    return -1;
                }
                if ($item_id == 36 && Config::getProperty("game", "maxTrapperCount") > 0) {
                    $count = 0;
                    foreach ($buildings as $villageBuild) {
                        if ($villageBuild['item_id'] == $item_id) {
                            $count++;
                        }
                    }
                    if ($count >= Config::getProperty("game", "maxTrapperCount")) {
                        return -1;
                    }
                }
            }
        }
        if (isset($buildMetadata['breq'])) {
            // check for none pre-request
            foreach ($buildMetadata['breq'] as $req_item_id => $level) {
                if ($level == -1) {
                    foreach ($buildings as $villageBuild) {
                        $villageBuild['item_id'] = (int)$villageBuild['item_id'];
                        if ($villageBuild['item_id'] == $req_item_id) {
                            return -1;
                        }
                    }
                }
            }
            // check for pre-request
            foreach ($buildMetadata['breq'] as $req_item_id => $level) {
                if ($level == -1) {
                    continue;
                }
                $result = FALSE;
                foreach ($buildings as $villageBuild) {
                    if (($villageBuild['item_id'] == $req_item_id && $villageBuild['level'] >= $level) || $req_item_id == 40) {
                        $result = TRUE;
                        break;
                    }
                }
                if (!$result) {
                    return 0;
                }
            }
        }

        return 1;
    }

    // @return:
    // 0: no dependencies needed
    // 1: need to increase crop production
    // 2: need to increase warehouse level
    // 22: create warehouse
    // 3: need to increase granny level
    // 33: create granny
    //crop production contains free crop! (CROP + $upkeep - $cropLoading - $cropLoadingMaster)
    /**
     * @param mixed $item_id
     * @param mixed $level
     * @param mixed $natar
     * @param (float|int) $cropProduction
     * @param mixed $maxstore
     * @param mixed $maxcrop
     */
    public function checkDependencies($item_id, $level, $natar, $cropProduction, $maxstore, $maxcrop): int
    {
        $resRequiredPop = Formulas::buildingCropConsumption($item_id, $level);
        //ignore WW here.
        if (getGame("starvation")) {
            if (($cropProduction - $resRequiredPop) <= 0 && $item_id <> 4) {
                return 1;
            }
        }
        $costs = Formulas::buildingUpgradeCosts($item_id, $level);
        if ($costs[0] > $maxstore || $costs[1] > $maxstore || $costs[2] > $maxstore) {
            if ($maxstore <= Formulas::storeCAP(0)) {
                return 22;
            }
            return 2;
        }
        if ($costs[3] > $maxcrop) {
            if ($maxcrop <= Formulas::storeCAP(0)) {
                return 33;
            }

            return 3;
        }

        return 0;
    }
    // @return:
    // 0: nothing needed
    // 1: Great Warehouse Artifact Needed
    // 2: WW Building Plan 1 Needed
    // 3: WW Building Plan 2 Needed (in Alliance)
    /**
     * @param mixed $aid
     * @param mixed $uid
     * @param mixed $kid
     * @param mixed $item_id
     * @param mixed $isWW
     * @param mixed $wwLevel
     */
    public function checkArtifactDependencies($aid, $uid, $kid, $item_id, $isWW, $wwLevel): int
    {
        if($uid == 1){
            return 0;
        }
        if ($item_id == 38 || $item_id == 39) {
            //check great ware house plan
            //artifact type 9 is for Great Warehouse or Granny
            if (!$isWW && ArtefactsModel::getArtifactEffectByType($uid, $kid, ArtefactsModel::ARTIFACT_GREAT_STORE) <= 0) {
                return 1;
            }
        } //great warehouse
        if ($item_id == 40) {
            $m = new WonderOfTheWorldModel();
            if (getCustom("wwPlansEnabled")) {
                $artEffBP = $this->checkIfPlayerHasWWBuildingPlan($uid);
                if (!$artEffBP) return 2;
                if (Config::getProperty("custom", "needAllianceWWPlan")) {
                    $wwLevel = (int)$wwLevel;
                    if ($wwLevel >= 50) {
                        $artEffAllyBP = $aid == 0 ? FALSE : $this->checkIfPlayerAllianceHasWWBuildingPlan($aid, $uid);
                        if ($artEffAllyBP <= 0) {
                            return 3;
                        }
                    }
                }
                return 0;
            } else {
                return ArtefactsModel::wwPlansReleased() ? 0 : 2;
            }
        } // WW
        return 0;
    }

    /**
     * @param mixed $uid
     */
    public function checkIfPlayerHasWWBuildingPlan($uid): bool
    {
        $db = DB::getInstance();
        return 0 < $db->fetchScalar("SELECT COUNT(id) FROM artefacts WHERE uid=$uid AND active=1 AND type=12 LIMIT 1");
    }

    // Alliance configuration may also affect this value.
    /**
     * @param mixed $aid
     * @param mixed $uid
     */
    public function checkIfPlayerAllianceHasWWBuildingPlan($aid, $uid): bool
    {
        if (!$aid) {
            return FALSE;
        }
        $db = DB::getInstance();
        $diplos = $db->query("SELECT aid1, aid2 FROM diplomacy WHERE (aid1=$aid OR aid2=$aid) AND accepted<>0");
        $al = [];
        $al[] = $aid;
        while ($row = $diplos->fetch_assoc()) {
            $al[] = $row['aid1'];
            $al[] = $row['aid2'];
        }
        $members = [];
        $aidArray = implode(",", array_unique($al));
        $find = $db->query("SELECT id FROM users WHERE aid IN ($aidArray) AND id <> $uid");
        while ($row = $find->fetch_assoc()) {
            $members[] = $row['id'];
        }
        if (!sizeof($members)) {
            return FALSE;
        }
        $members = implode(",", $members);
        return 0 < $db->fetchScalar("SELECT COUNT(id) FROM artefacts WHERE uid IN($members) AND type=12 AND active=1 LIMIT 1");
    }


    /**
     * @param mixed $uid
     */
    public function hasPalaceAnywhere($uid): bool
    {
        $db = DB::getInstance();
        $fields = $db->query("SELECT f.* FROM fdata f, vdata v WHERE f.kid=v.kid AND v.owner=$uid");
        while ($row = $fields->fetch_assoc()) {
            for ($i = 19; $i <= 38; ++$i) {
                if ($row['f' . $i . 't'] == 26) {
                    return TRUE;
                }
            }
        }
        return FALSE;
    }

    /**
     * @param mixed $max_warehouse
     * @param mixed $max_granny
     * @param mixed $cost
     */
    public function checkWarehouseDependencies($max_warehouse, $max_granny, $cost): int
    {
        if ($cost[0] > $max_warehouse || $cost[1] > $max_warehouse || $cost[2] > $max_warehouse) {
            if ($max_warehouse <= Formulas::storeCAP(0))
                return 22;
            return 2;
        }
        if ($cost[3] > $max_granny) {
            if ($max_granny <= Formulas::storeCAP(0))
                return 33;
            return 3;
        }
        return 0;
    }
}
