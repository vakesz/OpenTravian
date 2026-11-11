<?php

declare(strict_types=1);

namespace Model;

use Core\Database\DB;

class MapModel
{
    /**
     * @param list<string> $kidBatchAdd "(kid, map_id)" value tuples
     */
    public function addTileBlocks(array $kidBatchAdd): void
    {
        $this->insertTiles('blocks', $kidBatchAdd);
    }

    /**
     * @param list<string> $kidBatchAdd "(kid, map_id)" value tuples
     */
    public function addTileMarks(array $kidBatchAdd): void
    {
        $this->insertTiles('marks', $kidBatchAdd);
    }

    /**
     * @return array{0: int|string, 1: int|string, 2: bool}|false id, version and whether it was just created
     */
    public function getMapMark(int $tx0, int $ty0, int $tx1, int $ty1, int $uid, int $zoomLevel = 1, bool $insert = true): array|false
    {
        $db = DB::getInstance();
        $found = $db->query("SELECT id, version FROM map_mark WHERE uid=$uid AND tx0=$tx0 AND ty0=$ty0 AND tx1=$tx1 AND ty1=$ty1")->fetch_row();
        if ($found !== null) {
            return [$found[0], $found[1], false];
        }
        if (!$insert) {
            return false;
        }
        $db->query("INSERT IGNORE INTO map_mark (uid, tx0, ty0, tx1, ty1, zoomLevel) VALUES ($uid, $tx0, $ty0, $tx1, $ty1, $zoomLevel)");
        if ($db->affectedRows() === 1) {
            return [$db->lastInsertId(), 0, true];
        }

        // A concurrent request created the tile first; the unique key kept the second insert out.
        $found = $db->query("SELECT id, version FROM map_mark WHERE uid=$uid AND tx0=$tx0 AND ty0=$ty0 AND tx1=$tx1 AND ty1=$ty1")->fetch_row();

        return $found === null ? false : [$found[0], $found[1], false];
    }

    /**
     * @return array{0: int|string, 1: int|string, 2: bool}|false id, version and whether it was just created
     */
    public function getMapBlock(int $tx0, int $ty0, int $tx1, int $ty1, int $zoomLevel = 1, bool $insert = true): array|false
    {
        $db = DB::getInstance();
        $found = $db->query("SELECT id, version FROM map_block WHERE tx0=$tx0 AND ty0=$ty0 AND tx1=$tx1 AND ty1=$ty1")->fetch_row();
        if ($found !== null) {
            return [$found[0], $found[1], false];
        }
        if (!$insert) {
            return false;
        }
        $db->query("INSERT IGNORE INTO map_block (tx0, ty0, tx1, ty1, zoomLevel) VALUES ($tx0, $ty0, $tx1, $ty1, $zoomLevel)");
        if ($db->affectedRows() === 1) {
            return [$db->lastInsertId(), 0, true];
        }

        // A concurrent request created the tile first; the unique key kept the second insert out.
        $found = $db->query("SELECT id, version FROM map_block WHERE tx0=$tx0 AND ty0=$ty0 AND tx1=$tx1 AND ty1=$ty1")->fetch_row();

        return $found === null ? false : [$found[0], $found[1], false];
    }

    public function getNearMapBlocksWithVersion(int $x, int $y, int $zoomLevel = 1): \mysqli_result|bool
    {
        $rate = match ($zoomLevel) {
            1 => 9,
            2 => 19,
            default => 99,
        };

        return DB::getInstance()->query("SELECT * FROM map_block WHERE zoomLevel=$zoomLevel AND tx0 >= $x-(3*$rate) AND ty0 >= $y - (3*$rate) AND tx1 <= $x + (3*$rate) AND ty1 <= $y + (3*$rate)");
    }

    public function updateMapBlockVersionWhereKidIsIn(int $kid, int $zoomLevel = 0): void
    {
        DB::getInstance()->query("UPDATE map_block m JOIN blocks b ON b.map_id=m.id SET m.version=m.version+1 WHERE b.kid=$kid" . ($zoomLevel === 0 ? '' : " AND m.zoomLevel<=$zoomLevel"));
    }

    public function updateMapMarkVersionWhereKidIsIn(int $kid, int $zoomLevel = 0): void
    {
        $this->bumpMarkVersions($kid, '', $zoomLevel);
    }

    /**
     * @param string $allianceMembers comma-separated player ids
     */
    public function updateBatchAllianceMarks(int $kid, string $allianceMembers): void
    {
        if ($allianceMembers === '') {
            return;
        }
        $this->bumpMarkVersions($kid, " AND m.uid IN($allianceMembers)", 0);
    }

    public function updateMapMarkVersionWhereKidAndUidIsIn(int $uid, int $kid, int $zoomLevel = 0): void
    {
        $this->bumpMarkVersions($kid, " AND m.uid=$uid", $zoomLevel);
    }

    /**
     * @param list<string> $kidBatchAdd
     */
    private function insertTiles(string $table, array $kidBatchAdd): void
    {
        if ($kidBatchAdd === []) {
            return;
        }
        DB::getInstance()->query("INSERT IGNORE INTO $table (kid, map_id) VALUES " . implode(',', $kidBatchAdd));
    }

    private function bumpMarkVersions(int $kid, string $extraCondition, int $zoomLevel): void
    {
        DB::getInstance()->query("UPDATE map_mark m JOIN marks k ON k.map_id=m.id SET m.version=m.version+1 WHERE k.kid=$kid$extraCondition" . ($zoomLevel === 0 ? '' : " AND m.zoomLevel<=$zoomLevel"));
    }
}
