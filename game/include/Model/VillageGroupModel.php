<?php

declare(strict_types=1);

namespace Model;

use Core\Database\DB;
use RuntimeException;

/**
 * Owns the sidebar village-group state. Group membership stays separate from
 * vdata, with the capture and deletion paths removing the old owner's row.
 */
class VillageGroupModel
{
    public const MAX_GROUPS = 20;

    private const COLORS = ['grey', 'yellow', 'green', 'turquoise', 'blue', 'violet', 'magenta', 'red', 'orange'];

    /**
     * @return list<array{id: int, name: string, color: string, villageIds: list<int>}>
     */
    public function getGroups(int $uid): array
    {
        $statement = DB::getInstance()->run(
            'SELECT g.id, g.name, g.color, g.sort_order, m.kid
             FROM village_groups g
             LEFT JOIN village_group_villages m ON m.group_id=g.id AND m.uid=g.uid
             WHERE g.uid=?
             ORDER BY g.sort_order ASC, g.id ASC, m.sort_order ASC, m.kid ASC',
            [$uid]
        );
        if ($statement === false) {
            return [];
        }

        $groups = [];
        foreach ($statement->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $id = (int)$row['id'];
            if (!isset($groups[$id])) {
                $groups[$id] = [
                    'id' => $id,
                    'name' => (string)$row['name'],
                    'color' => $this->normaliseColor((string)$row['color']),
                    'villageIds' => [],
                ];
            }
            if ($row['kid'] !== null) {
                $groups[$id]['villageIds'][] = (int)$row['kid'];
            }
        }

        return array_values($groups);
    }

    public function create(int $uid, string $name = 'New group', string $color = 'grey'): int
    {
        $db = DB::getInstance();
        $this->begin($db, $uid);
        try {
            $count = (int)$db->fetchScalar('SELECT COUNT(*) FROM village_groups WHERE uid=?', [$uid], 0);
            if ($count >= self::MAX_GROUPS) {
                throw new RuntimeException('You can only create up to 20 village groups.');
            }
            $sortOrder = (int)$db->fetchScalar('SELECT COALESCE(MAX(sort_order), 0)+1 FROM village_groups WHERE uid=?', [$uid], 1);
            $created = $db->run(
                'INSERT INTO village_groups (uid, name, color, sort_order, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$uid, $this->normaliseName($name), $this->normaliseColor($color), $sortOrder, time(), time()]
            );
            if ($created === false) {
                throw new RuntimeException('Could not create the village group.');
            }
            $id = (int)$db->lastInsertId();
            if (!$db->commit()) {
                throw new RuntimeException('Could not save the village group.');
            }

            return $id;
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            throw $exception;
        }
    }

    public function update(int $uid, int $groupId, string $name, string $color): void
    {
        $db = DB::getInstance();
        if ($db->fetchScalar('SELECT id FROM village_groups WHERE id=? AND uid=?', [$groupId, $uid], false) === false) {
            throw new RuntimeException('Village group not found.');
        }
        $statement = $db->run(
            'UPDATE village_groups SET name=?, color=?, updated_at=? WHERE id=? AND uid=?',
            [$this->normaliseName($name), $this->normaliseColor($color), time(), $groupId, $uid]
        );
        if ($statement === false) {
            throw new RuntimeException('Could not update the village group.');
        }
    }

    public function delete(int $uid, int $groupId): bool
    {
        $db = DB::getInstance();
        $this->begin($db, $uid);
        try {
            $deleted = $db->run('DELETE FROM village_groups WHERE id=? AND uid=?', [$groupId, $uid]);
            if ($deleted === false || $db->affectedRows() !== 1) {
                throw new RuntimeException('Village group not found.');
            }
            $memberships = $db->run('DELETE FROM village_group_villages WHERE uid=? AND group_id=?', [$uid, $groupId]);
            if ($memberships === false) {
                throw new RuntimeException('Could not clear village-group memberships.');
            }
            if (!$db->commit()) {
                throw new RuntimeException('Could not delete the village group.');
            }

            return true;
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            throw $exception;
        }
    }

    /**
     * Stores the whole village list the way the reference sends it: one entry per
     * group (id plus its village ids, in order) and one per ungrouped village
     * (villages null). Group order, memberships and village order follow it.
     *
     * @param list<array{id: int, villages: list<int>|null}> $entries
     */
    public function setLayout(int $uid, array $entries): void
    {
        $db = DB::getInstance();
        $this->begin($db, $uid);
        try {
            $groupIds = $this->ids($db, 'SELECT id FROM village_groups WHERE uid=?', [$uid]);
            $villageIds = $this->ids($db, 'SELECT kid FROM vdata WHERE owner=?', [$uid]);
            $groupOrder = 0;
            $seq = 0;
            $seen = [];
            $memberships = [];
            $groupSort = [];
            $villageSeq = [];
            foreach ($entries as $entry) {
                $id = $entry['id'];
                if ($entry['villages'] === null) {
                    $members = [$id];
                } else {
                    if (!isset($groupIds[$id])) {
                        throw new RuntimeException('Village group not found.');
                    }
                    $groupSort[$id] = ++$groupOrder;
                    $members = $entry['villages'];
                }
                foreach ($members as $position => $kid) {
                    if (!isset($villageIds[$kid]) || isset($seen[$kid])) {
                        throw new RuntimeException('Village not found.');
                    }
                    $seen[$kid] = true;
                    $villageSeq[$kid] = ++$seq;
                    if ($entry['villages'] !== null) {
                        $memberships[] = [$kid, $id, $position + 1];
                    }
                }
            }

            $db->run('DELETE FROM village_group_villages WHERE uid=?', [$uid]);
            foreach ($memberships as [$kid, $groupId, $sortOrder]) {
                $db->run(
                    'INSERT INTO village_group_villages (uid, kid, group_id, sort_order) VALUES (?, ?, ?, ?)',
                    [$uid, $kid, $groupId, $sortOrder]
                );
            }
            foreach ($groupSort as $groupId => $sortOrder) {
                $db->run('UPDATE village_groups SET sort_order=?, updated_at=? WHERE id=? AND uid=?', [$sortOrder, time(), $groupId, $uid]);
            }
            foreach ($villageSeq as $kid => $position) {
                $db->run('UPDATE vdata SET seq=? WHERE kid=? AND owner=?', [$position, $kid, $uid]);
            }
            if (!$db->commit()) {
                throw new RuntimeException('Could not save the village list.');
            }
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            throw $exception;
        }
    }

    /**
     * @param list<int|string> $parameters
     * @return array<int, true>
     */
    private function ids(DB $db, string $query, array $parameters): array
    {
        $statement = $db->run($query, $parameters);
        $ids = [];
        if ($statement !== false) {
            foreach ($statement->get_result()->fetch_all(MYSQLI_NUM) as $row) {
                $ids[(int)$row[0]] = true;
            }
        }

        return $ids;
    }

    private function begin(DB $db, int $uid): void
    {
        if (!$db->begin_transaction()) {
            throw new RuntimeException('Could not start the village-group update.');
        }
        $user = $db->run('SELECT id FROM users WHERE id=? FOR UPDATE', [$uid]);
        if ($user === false || $user->get_result()->fetch_row() === null) {
            throw new RuntimeException('Player not found.');
        }
    }

    private function normaliseName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 20) {
            throw new RuntimeException('Village-group names must contain 1 to 20 characters.');
        }

        return $name;
    }

    private function normaliseColor(string $color): string
    {
        return in_array($color, self::COLORS, true) ? $color : 'grey';
    }
}
