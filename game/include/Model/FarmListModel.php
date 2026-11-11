<?php

declare(strict_types=1);

namespace Model;

use Core\Database\DB;
use Game\Formulas;
use mysqli_result;

/**
 * Farm lists as other parts of the game use them: the list a village target is on, and lists the game fills itself.
 */
class FarmListModel
{
    /**
     * Copies up to $count unclaimed Natar farms into a new list of the village; returns how many were found.
     */
    public function addUniqueFarms(int $uid, int $kid, int $count): int
    {
        $db = DB::getInstance();
        $farms = $db->query("SELECT v.* FROM vdata v WHERE v.owner=1 AND v.isWW=0 AND (SELECT COUNT(a.id) FROM artefacts a WHERE a.kid=v.kid)=0 AND (SELECT COUNT(r.id) FROM raidlist r WHERE r.kid=v.kid AND (SELECT f.owner FROM farmlist f WHERE f.id=r.lid AND f.kid=$kid)=$uid)=0 LIMIT $count");
        if (!$farms instanceof mysqli_result || !$farms->num_rows) {
            return 0;
        }
        $lid = $this->addFarmList($uid, $kid, 'Auto Added farms');
        $batch = [];
        while ($row = $farms->fetch_assoc()) {
            $distance = (float) Formulas::getDistance($kid, (int) $row['kid']);
            $batch[] = '(' . $lid . ', ' . (int) $row['kid'] . ', ' . $distance . ', 50000,0,0,0,0,0,0,0,0,0)';
        }
        $db->query('INSERT INTO raidlist(lid, kid, distance, u1, u2, u3, u4, u5, u6, u7, u8, u9, u10) VALUES ' . implode(',', $batch));

        return $farms->num_rows;
    }

    /**
     * The player's farm list that holds $kid, with the slot id; false when none does.
     *
     * @return array<string, mixed>|false
     */
    public function getWhereIsKidInFarmList(int $kid, int $uid): array|false
    {
        return $this->fetchRow("SELECT farmlist.id, farmlist.name, raidlist.id slotId FROM farmlist, raidlist WHERE farmlist.owner=$uid AND raidlist.kid=$kid AND raidlist.lid=farmlist.id LIMIT 1") ?? false;
    }

    /** Id of the village's farm list, false when it has none. */
    public function getVillageFarmList(int $kid, int $uid): int|false
    {
        $id = DB::getInstance()->fetchScalar("SELECT id FROM farmlist WHERE kid=$kid AND owner=$uid");

        return $id === false || $id === null ? false : (int) $id;
    }

    public function addFarmList(int $uid, int $kid, string $name): int
    {
        $db = DB::getInstance();
        $db->run('INSERT INTO farmlist (kid, owner, name) VALUES (?, ?, ?)', [$kid, $uid, $name]);

        return (int) $db->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchRow(string $query): ?array
    {
        $result = DB::getInstance()->query($query);
        $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;

        return is_array($row) ? $row : null;
    }
}
