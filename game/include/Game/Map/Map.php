<?php

namespace Game\Map;

use Core\Database\DB;
use Model\MapPageModel;
use Model\MapModel;

class Map
{
    /**
     * @param mixed $kid
     */
    public static function OccupyOrLeaveOasisCacheRemove($kid): void
    {
        $mapModel = new MapModel();
        $mapModel->updateMapBlockVersionWhereKidIsIn($kid, 2);
        $mapModel->updateMapMarkVersionWhereKidIsIn($kid);
    }

    /**
     * @param mixed $kid
     * @param bool $updateMarks
     */
    public static function clearCacheForKid($kid, $updateMarks = true): void
    {
        $mapModel = new MapModel();
        $mapModel->updateMapBlockVersionWhereKidIsIn($kid);
        if ($updateMarks)
            $mapModel->updateMapMarkVersionWhereKidIsIn($kid);
    }


    /**
     * @param mixed $kid
     * @param mixed $oldPop
     * @param (array<mixed>|float|int)|(float|int) $nextPop
     */
    public static function popChangeMapUpdate($kid, $oldPop, $nextPop): void
    {
        if ($oldPop == $nextPop) {
            return;
        }
        $update = FALSE;
        //village updated
        if ($oldPop <= 100 && $nextPop > 100) {
            $update = TRUE;
        } else if ($oldPop <= 249 && $nextPop >= 250) {
            $update = TRUE;
        } else if ($oldPop <= 499 && $nextPop >= 500) {
            $update = TRUE;
        }
        if ($nextPop <= 100 && $oldPop > 100) {
            $update = TRUE;
        } else if ($nextPop <= 249 && $oldPop >= 250) {
            $update = TRUE;
        } else if ($nextPop <= 499 && $oldPop >= 500) {
            $update = TRUE;
        }
        if ($update) {
            $mapModel = new MapModel();
            $mapModel->updateMapBlockVersionWhereKidIsIn($kid);
        }
    }

    /**
     * @param mixed $kid
     */
    public static function villageDestroyOrCaptureOrNewVillageUpdate($kid): void
    {
        $mapModel = new MapModel();
        $mapModel->updateMapBlockVersionWhereKidIsIn($kid);
        $mapModel->updateMapMarkVersionWhereKidIsIn($kid);
    }

    /**
     * @param mixed $uid
     * @param mixed $aid
     * @param int $mode
     */
    public static function switchOnOffMarks($uid, $aid, $mode): void
    {
        $db = DB::getInstance();
        $m = new MapPageModel();
        $marks = $m->getWholeMapMarks($uid, $aid, $mode, TRUE);
        $mapModel = new MapModel();
        while ($mark = $marks->fetch_assoc()) {
            if ($mark['aid'] && !$mark['uid']) {
                //alliances
                $villages = $db->query("SELECT vdata.kid FROM vdata, users, alidata WHERE alidata.id={$mark['targetId']} AND users.aid=alidata.id AND vdata.owner=users.id");
            } else {
                //players
                $villages = $db->query("SELECT kid FROM vdata WHERE owner={$mark['targetId']}");
            }
            while ($row = $villages->fetch_assoc()) {
                $oases = $db->query("SELECT kid FROM odata WHERE did={$row['kid']}");
                while ($o = $oases->fetch_assoc()) {
                    $mapModel->updateMapMarkVersionWhereKidAndUidIsIn($uid, $o['kid']);
                }
                $mapModel->updateMapMarkVersionWhereKidAndUidIsIn($uid, $row['kid']);
            }
        }
    }

    /**
     * @param int $uid
     * @param int $aid
     */
    public static function allianceJoinOrLeaveCacheUpdate($uid, $aid): void
    {
        self::switchOnOffMarks($uid, $aid, 1);
        self::allianceDiplomacyCacheUpdateByUidAndAid($uid, $aid);
    }

    /**
     * @param int $aid
     */
    public static function allianceDeleteDiplomacyUpdate($aid): void
    {
        $db = DB::getInstance();
        $find = $db->query("SELECT aid1, aid2 FROM diplomacy WHERE aid1=$aid OR aid2=$aid");
        $ali = [];
        while ($row = $find->fetch_assoc()) {
            $ali[] = $row['aid1'];
            $ali[] = $row['aid2'];
        }
        $ali = array_unique($ali);
        if (!sizeof($ali)) {
            return;
        }
        $ali = implode(",", $ali);
        $players = [];
        $find = $db->query("SELECT id FROM users WHERE aid IN($ali)");
        while ($row = $find->fetch_assoc()) {
            $players[] = $row['id'];
        }
        $players = implode(",", $players);
        $villages = $db->query("SELECT vdata.kid FROM vdata, users, alidata WHERE (alidata.id IN($ali)) AND users.aid=alidata.id AND vdata.owner=users.id");
        $mapModel = new MapModel();
        while ($village = $villages->fetch_assoc()) {
            $oases = $db->query("SELECT kid FROM odata WHERE did={$village['kid']}");
            while ($o = $oases->fetch_assoc()) {
                $mapModel->updateBatchAllianceMarks($o['kid'], $players);
            }
            $mapModel->updateBatchAllianceMarks($village['kid'], $players);
        }
    }

    /**
     * @param mixed $aid1
     * @param mixed $aid2
     */
    public static function allianceDiplomacyCacheUpdate($aid1, $aid2): void
    {
        $db = DB::getInstance();
        $players = [];
        $find = $db->query("SELECT id FROM users WHERE (aid=$aid1 OR aid=$aid2)");
        while ($row = $find->fetch_assoc()) {
            $players[] = $row['id'];
        }
        $mapModel = new MapModel();
        $players = implode(",", $players);
        //just updating target alliance if us
        $villages = $db->query("SELECT vdata.kid FROM vdata, users, alidata WHERE (alidata.id=$aid1 OR alidata.id=$aid2) AND users.aid=alidata.id AND vdata.owner=users.id");
        while ($village = $villages->fetch_assoc()) {
            $oases = $db->query("SELECT kid FROM odata WHERE did={$village['kid']}");
            while ($o = $oases->fetch_assoc()) {
                $mapModel->updateBatchAllianceMarks($o['kid'], $players);
            }
            $mapModel->updateBatchAllianceMarks($village['kid'], $players);
        }
    }

    /**
     * @param mixed $uid
     * @param mixed $aid
     */
    public static function allianceDiplomacyCacheUpdateByUidAndAid($uid, $aid): void
    {
        $db = DB::getInstance();
        //just updating target alliance if us
        $mapModel = new MapModel();
        $diplomacy = $db->query("SELECT aid1, aid2 FROM diplomacy WHERE accepted=1 AND (aid1=$aid OR aid2=$aid)");
        while ($row = $diplomacy->fetch_assoc()) {
            $allianceId = $row['aid1'] == $aid ? $row['aid2'] : $row['aid1'];
            $villages = $db->query("SELECT vdata.kid FROM vdata, users, alidata WHERE alidata.id=$allianceId AND users.aid=alidata.id AND vdata.owner=users.id");
            while ($village = $villages->fetch_assoc()) {
                $oases = $db->query("SELECT kid FROM odata WHERE did={$village['kid']}");
                while ($o = $oases->fetch_assoc()) {
                    $mapModel->updateMapMarkVersionWhereKidAndUidIsIn($uid, $o['kid']);
                }
                $mapModel->updateMapMarkVersionWhereKidAndUidIsIn($uid, $village['kid']);
            }
        }
        $diplomacy->free();
    }

    /**
     * @param mixed $kid
     * @param mixed $uid
     * @param mixed $aid
     * @param mixed $mode
     */
    public static function markAddORDeleteUpdate($kid, $uid, $aid, $mode): void
    {
        $db = DB::getInstance();
        $m = new MapPageModel();
        $mapModel = new MapModel();
        $marks = $m->getWholeMapMarksForKid($kid, $uid, $aid, $mode, TRUE);
        while ($mark = $marks->fetch_assoc()) {
            if ($marks['aid'] && !$marks['uid']) {
                //alliances
                $villages = $db->query("SELECT vdata.kid FROM vdata, users, alidata WHERE alidata.id={$mark['targetId']} AND users.aid=alidata.id AND vdata.owner=users.id");
            } else {
                //players
                $villages = $db->query("SELECT kid FROM vdata WHERE owner={$mark['targetId']}");
            }
            while ($row = $villages->fetch_assoc()) {
                $oases = $db->query("SELECT kid FROM odata WHERE did={$row['kid']}");
                while ($o = $oases->fetch_assoc()) {
                    $mapModel->updateMapMarkVersionWhereKidAndUidIsIn($uid, $o['kid']);
                }
                $mapModel->updateMapMarkVersionWhereKidAndUidIsIn($uid, $row['kid']);
            }
        }
    }

    /**
     * @param mixed $uid
     * @param mixed $type
     * @param mixed $targetId
     */
    public static function removeMapCacheForPlayer($uid, $type, $targetId): void
    {
        $db = DB::getInstance();
        if ($type == 'alliance') {
            $villages = $db->query("SELECT vdata.kid FROM vdata, users, alidata WHERE alidata.id=$targetId AND users.aid=alidata.id AND vdata.owner=users.id");
        } else {
            //players
            $villages = $db->query("SELECT kid FROM vdata WHERE owner=$targetId");
        }
        $mapModel = new MapModel();
        while ($row = $villages->fetch_assoc()) {
            $oases = $db->query("SELECT kid FROM odata WHERE did={$row['kid']}");
            while ($o = $oases->fetch_assoc()) {
                $mapModel->updateMapMarkVersionWhereKidAndUidIsIn($uid, $o['kid']);
            }
            $mapModel->updateMapMarkVersionWhereKidAndUidIsIn($uid, $row['kid']);
        }
    }

    /**
     * @param mixed $aid
     * @param mixed $type
     * @param mixed $targetId
     */
    public static function removeMapCacheForAlliance($aid, $type, $targetId): void
    {
        $db = DB::getInstance();
        if ($type == 'alliance') {
            $villages = $db->query("SELECT vdata.kid FROM vdata, users, alidata WHERE alidata.id=$targetId AND users.aid=alidata.id AND vdata.owner=users.id");
        } else {
            //players
            $villages = $db->query("SELECT kid FROM vdata WHERE owner=$targetId");
        }
        $mapModel = new MapModel();
        $allianceMembers = [];
        $alliance = $db->query("SELECT id FROM users WHERE aid=$aid");
        while ($id = $alliance->fetch_row()) {
            $id = $id[0];
            $allianceMembers[] = $id;
        }
        $allianceMembers = implode(",", $allianceMembers);
        while ($row = $villages->fetch_assoc()) {
            $oases = $db->query("SELECT kid FROM odata WHERE did={$row['kid']}");
            while ($o = $oases->fetch_assoc()) {
                $mapModel->updateBatchAllianceMarks($o['kid'], $allianceMembers);
            }
            $mapModel->updateBatchAllianceMarks($row['kid'], $allianceMembers);
        }
    }
}