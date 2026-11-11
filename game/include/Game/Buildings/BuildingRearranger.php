<?php

declare(strict_types=1);

namespace Game\Buildings;

use Core\Database\DB;

/**
 * Main Building "Rearrange your buildings": the player permutes the buildings of
 * the dorf2 slots in one confirmed step, paid with gold (config gold.rearrangeBuildingsGold,
 * default 20).
 *
 * Rules (every slot 19-38
 * except the Main Building is `.movable`, empty sites included; the Main Building,
 * Rally Point and Wall carry the tooltip "This building cannot be moved."):
 *  - Slots 19-38 are movable unless they hold the Main Building, the Rally Point or a
 *    wall. The Rally Point site (39) and the Wall ring (40) are always fixed, and a
 *    Wonder of the World village keeps its fixed layout.
 *  - The submitted layout must be a permutation of the movable slots; a slot
 *    is identified by its opaque building id (BUILDING_ID_PREFIX . original slot).
 *  - A slot with a construction, master-builder order or demolition in
 *    progress cannot take part in a move. Such slots must stay where they are.
 *  - Levels travel with the building; resources, culture points and
 *    population are unchanged because only the slot a building stands on moves.
 */
final class BuildingRearranger
{
    public const FIRST_MOVABLE_SLOT = 19;
    public const LAST_MOVABLE_SLOT = 38;
    /** Opaque id prefix of dorf2 `data-building-id`; the slot number follows. */
    public const BUILDING_ID_PREFIX = '2504';

    /** Main Building, Rally Point, the tribe walls and the World Wonder cannot be moved. */
    public const FIXED_GIDS = [15, 16, 31, 32, 33, 40, 42, 43, 47, 50];

    /**
     * @param array<int,int> $gids slot => building type; slots that hold a fixed building are left out
     * @return int[]
     */
    public static function movableSlots(array $gids = []): array
    {
        return array_values(array_filter(
            range(self::FIRST_MOVABLE_SLOT, self::LAST_MOVABLE_SLOT),
            static fn(int $slot): bool => !in_array((int)($gids[$slot] ?? 0), self::FIXED_GIDS, true)
        ));
    }

    /** @return array<int,int> slot => building type for the movable range of a village */
    public static function slotTypes(int $kid): array
    {
        $columns = [];
        for ($slot = self::FIRST_MOVABLE_SLOT; $slot <= self::LAST_MOVABLE_SLOT; ++$slot) {
            $columns[] = "f{$slot}t AS t{$slot}";
        }
        $statement = DB::getInstance()->run('SELECT ' . implode(', ', $columns) . ' FROM fdata WHERE kid=?', [$kid]);
        $row = $statement ? $statement->get_result()->fetch_assoc() : null;
        $types = [];
        for ($slot = self::FIRST_MOVABLE_SLOT; $slot <= self::LAST_MOVABLE_SLOT && $row; ++$slot) {
            $types[$slot] = (int)$row["t{$slot}"];
        }

        return $types;
    }

    public static function buildingId(int $slot): string
    {
        return self::BUILDING_ID_PREFIX . $slot;
    }

    /**
     * Validate a submitted layout and work out the moves.
     *
     * @param array<mixed> $positions  aid => building id, as posted by the client
     * @param int[]        $busySlots  slots with a queued construction or demolition
     * @param array<int,int> $gids     slot => building type (see movableSlots())
     * @return array{error: string|null, target: array<int,int>} target maps the
     *         slot that receives a building to the slot the building comes from
     */
    public static function plan(array $positions, array $busySlots, array $gids = []): array
    {
        $movable = self::movableSlots($gids);
        $target = [];
        $seenSources = [];
        foreach ($positions as $aid => $buildingId) {
            $slot = filter_var($aid, FILTER_VALIDATE_INT);
            if ($slot === false || !in_array($slot, $movable, true)) {
                // Fixed buildings and slots (Main Building, 39, 40) are sent along but must stay put.
                $source = self::sourceSlot($buildingId);
                if ($slot !== false && $source !== null && $source !== $slot) {
                    return ['error' => 'fixedSlot', 'target' => []];
                }
                continue;
            }
            $source = self::sourceSlot($buildingId);
            if ($source === null || !in_array($source, $movable, true) || isset($seenSources[$source])) {
                return ['error' => 'invalidLayout', 'target' => []];
            }
            $seenSources[$source] = true;
            $target[$slot] = $source;
        }
        if (count($target) !== count($movable)) {
            return ['error' => 'invalidLayout', 'target' => []];
        }
        $moves = [];
        foreach ($target as $slot => $source) {
            if ($slot !== $source) {
                $moves[$slot] = $source;
            }
        }
        if ($moves === []) {
            return ['error' => 'nothingToMove', 'target' => []];
        }
        foreach ($moves as $slot => $source) {
            if (in_array($slot, $busySlots, true) || in_array($source, $busySlots, true)) {
                return ['error' => 'slotBusy', 'target' => []];
            }
        }

        return ['error' => null, 'target' => $moves];
    }

    private static function sourceSlot(mixed $buildingId): ?int
    {
        if (!is_scalar($buildingId)) {
            return null;
        }
        $id = (string)$buildingId;
        if (!str_starts_with($id, self::BUILDING_ID_PREFIX)) {
            return null;
        }
        $slot = substr($id, strlen(self::BUILDING_ID_PREFIX));

        return ctype_digit($slot) ? (int)$slot : null;
    }

    /** @return int[] Slots of the village that have a construction or demolition pending. */
    public static function busySlots(int $kid): array
    {
        $db = DB::getInstance();
        $busy = [];
        foreach (['building_upgrade', 'demolition'] as $table) {
            $statement = $db->run("SELECT DISTINCT building_field FROM $table WHERE kid=?", [$kid]);
            $result = $statement ? $statement->get_result() : null;
            while ($result && ($row = $result->fetch_row())) {
                $busy[] = (int)$row[0];
            }
        }

        return $busy;
    }

    /**
     * Apply a validated plan: every moved slot takes the level and type of its
     * source slot, all in one statement so the village is never half moved.
     *
     * @param array<int,int> $moves slot => source slot
     */
    public static function apply(int $kid, array $moves): bool
    {
        if ($moves === []) {
            return false;
        }
        $db = DB::getInstance();
        $select = [];
        foreach (array_unique(array_merge(array_keys($moves), array_values($moves))) as $slot) {
            $select[] = "f" . (int)$slot . " AS l" . (int)$slot . ", f" . (int)$slot . "t AS t" . (int)$slot;
        }
        $row = $db->run("SELECT " . implode(', ', $select) . " FROM fdata WHERE kid=?", [$kid]);
        $row = $row ? $row->get_result()->fetch_assoc() : null;
        if (!$row) {
            return false;
        }
        $assignments = [];
        foreach ($moves as $slot => $source) {
            $assignments[] = "f" . (int)$slot . "=" . (int)$row['l' . $source];
            $assignments[] = "f" . (int)$slot . "t=" . (int)$row['t' . $source];
        }
        $db->begin_transaction();
        $ok = $db->run("UPDATE fdata SET " . implode(', ', $assignments) . " WHERE kid=?", [$kid]);
        if ($ok === false) {
            $db->rollback();

            return false;
        }

        return (bool)$db->commit();
    }
}
