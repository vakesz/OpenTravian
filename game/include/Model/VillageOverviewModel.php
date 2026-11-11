<?php

declare(strict_types=1);

namespace Model;

use Core\Database\DB;
use mysqli_result;

class VillageOverviewModel
{
    /**
     * @return array<string, mixed>|null
     */
    public function getUnits(int $kid): ?array
    {
        return $this->fetchRow('SELECT * FROM units WHERE kid=?', [$kid]);
    }

    public function getGoingUnits(int $kid): mysqli_result|false
    {
        return $this->fetchAll('SELECT * FROM movement WHERE kid=? AND mode=0', [$kid]);
    }

    public function getVillageEnforcements(int $kid): mysqli_result|false
    {
        return $this->fetchAll('SELECT * FROM enforcement WHERE to_kid=?', [$kid]);
    }

    public function getVillageName(int $kid): string
    {
        return (string) DB::getInstance()->fetchScalar('SELECT name FROM vdata WHERE kid=?', [$kid]);
    }

    public function getOutEnforcement(int $kid): mysqli_result|false
    {
        return $this->fetchAll('SELECT * FROM enforcement WHERE kid=?', [$kid]);
    }

    public function getOutTrappedUnits(int $kid): mysqli_result|false
    {
        return $this->fetchAll('SELECT * FROM trapped WHERE kid=?', [$kid]);
    }

    public function getReturningUnits(int $kid): mysqli_result|false
    {
        return $this->fetchAll('SELECT * FROM movement WHERE to_kid=? AND mode=1', [$kid]);
    }

    public function getVillageUpgradeBuildings(int $kid): mysqli_result|false
    {
        return $this->fetchAll('SELECT building_field FROM building_upgrade WHERE kid=? AND isMaster=0', [$kid]);
    }

    public function getFieldItemId(int $kid, int $field): int
    {
        return (int) DB::getInstance()->fetchScalar("SELECT f{$field}t FROM fdata WHERE kid=?", [$kid]);
    }

    public function getPlayerVillagesCulturePointsData(int $uid): mysqli_result|false
    {
        return $this->fetchAll('SELECT kid, name, cp, celebration, type FROM vdata WHERE owner=? ORDER BY name', [$uid]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getArmory(int $kid): ?array
    {
        return $this->fetchRow('SELECT * FROM smithy WHERE kid=?', [$kid]);
    }

    public function getArmoryProgress(int $kid): mysqli_result|false
    {
        return $this->fetchAll('SELECT * FROM research WHERE kid=? AND mode=0', [$kid]);
    }

    public function getPlayerVillageResources(int $uid): mysqli_result|false
    {
        return $this->fetchAll('SELECT kid, name, wood, clay, iron, crop FROM vdata WHERE owner=? ORDER BY name', [$uid]);
    }

    /** Stock, net production and capacity of every village the player owns. */
    public function getPlayerVillageEconomy(int $uid): mysqli_result|false
    {
        return $this->fetchAll(
            'SELECT kid, name, wood, clay, iron, crop, woodp, clayp, ironp, cropp - pop - upkeep AS cropnet, maxstore, maxcrop FROM vdata WHERE owner=? ORDER BY name',
            [$uid]
        );
    }

    /**
     * Units queued per village and training building: [kid => [item_id => units]].
     * The Hospital and Asclepeion queues hold healing orders.
     *
     * @return array<int, array<int, int>>
     */
    public function getPlayerTrainingQueues(int $uid): array
    {
        $result = $this->fetchAll(
            'SELECT t.kid, t.item_id, SUM(t.num) AS units FROM training t JOIN vdata v ON v.kid = t.kid WHERE v.owner=? AND t.item_id!=98 GROUP BY t.kid, t.item_id',
            [$uid]
        );
        $queues = [];
        while ($result && ($row = $result->fetch_assoc())) {
            $queues[(int) $row['kid']][(int) $row['item_id']] = (int) $row['units'];
        }

        return $queues;
    }

    /**
     * @return array<int, array<int, array{num: int, item_id: int, end_time: int}>> [item_id => [nr => batch]], end_time of the batch that finishes last
     */
    public function getVillageTroopsTraining(int $kid): array
    {
        $training = [];
        $result = $this->fetchAll('SELECT * FROM training WHERE kid=? AND item_id!=98 ORDER BY nr', [$kid]);
        while ($result && ($row = $result->fetch_assoc())) {
            $itemId = (int) $row['item_id'];
            $nr = (int) $row['nr'];
            $training[$itemId][$nr] ??= ['num' => 0, 'item_id' => $itemId, 'end_time' => 0];
            $training[$itemId][$nr]['num'] += (int) $row['num'];
            $training[$itemId][$nr]['end_time'] = max($training[$itemId][$nr]['end_time'], (int) $row['end_time']);
        }

        return $training;
    }

    /**
     * @return array{item_id: int, level: int}|false false when the village has no Residence or Palace
     */
    public function getResidencePalaceLevel(int $capWref): array|false
    {
        $building = $this->findBuilding($capWref, [25, 26]);

        return $building === null ? false : ['item_id' => $building['item_id'], 'level' => $building['level']];
    }

    /** Town Hall level, 0 when the village has none. */
    public function getTHLevel(int $capWref): int
    {
        return $this->findBuilding($capWref, [24])['level'] ?? 0;
    }

    /**
     * First building slot (18-38) whose type is one of $itemIds.
     *
     * @param list<int> $itemIds
     * @return array{item_id: int, level: int}|null
     */
    private function findBuilding(int $kid, array $itemIds): ?array
    {
        $row = $this->fetchRow('SELECT `f18t`, `f19`, `f19t`, `f20`, `f20t`, `f21`, `f21t`, `f22`, `f22t`, `f23`, `f23t`, `f24`, `f24t`, `f25`, `f25t`, `f26`, `f26t`, `f27`, `f27t`, `f28`, `f28t`, `f29`, `f29t`, `f30`, `f30t`, `f31`, `f31t`, `f32`, `f32t`, `f33`, `f33t`, `f34`, `f34t`, `f35`, `f35t`, `f36`, `f36t`, `f37`, `f37t`, `f38`, `f38t` FROM fdata WHERE kid=?', [$kid]);
        if ($row === null) {
            return null;
        }
        for ($i = 18; $i <= 38; ++$i) {
            $type = (int) $row['f' . $i . 't'];
            if (in_array($type, $itemIds, true)) {
                return ['item_id' => $type, 'level' => (int) $row['f' . $i]];
            }
        }

        return null;
    }

    /**
     * Reads over the text protocol so columns stay strings, as the callers expect (prepared statements would return
     * native ints). Every placeholder is an integer; they are substituted with %d.
     *
     * @param list<int> $parameters
     */
    private function fetchAll(string $query, array $parameters): mysqli_result|false
    {
        return DB::getInstance()->query(vsprintf(str_replace('?', '%d', $query), $parameters));
    }

    /**
     * @param list<int> $parameters
     * @return array<string, mixed>|null
     */
    private function fetchRow(string $query, array $parameters): ?array
    {
        $result = $this->fetchAll($query, $parameters);
        $row = $result === false ? null : $result->fetch_assoc();

        return is_array($row) ? $row : null;
    }
}
