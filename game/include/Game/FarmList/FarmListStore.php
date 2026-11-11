<?php

declare(strict_types=1);

namespace Game\FarmList;

use Core\Database\DB;
use Core\Session;
use Game\Formulas;
use Game\Ships;

use function T;

use const MAP_SIZE;

/**
 * Every change a player makes to farm lists and their slots. Methods validate, write and return the ids of the
 * lists whose state changed, so the controllers answer with those lists in the read model's shape. A refusal
 * is a \DomainException whose code is the HTTP status.
 */
final class FarmListStore
{
    private const MAX_NAME_LENGTH = 30;

    private DB $db;
    private int $uid;

    public function __construct()
    {
        $this->db = DB::getInstance();
        $this->uid = Session::getInstance()->getPlayerId();
    }

    /**
     * @param array<mixed> $data name, villageId, defaultUnits {t1..t10}, useShip, onlyLosses
     * @return list<int>
     */
    public function createList(array $data): array
    {
        $kid = $this->ownedVillage($data['villageId'] ?? null);
        $name = $this->name($data['name'] ?? null);
        if ($this->scalar('SELECT COUNT(*) FROM farmlist WHERE owner=?', [$this->uid]) >= FarmListData::LIST_LIMIT) {
            throw new \DomainException(self::text('error_limitReached'));
        }
        $this->assertNameFree($kid, $name, 0);
        $units = $this->troops($data['defaultUnits'] ?? [], false);
        $this->write(
            'INSERT INTO farmlist (kid, owner, name, sortIndex, useShip, onlyLosses, u1, u2, u3, u4, u5, u6, u7, u8, u9, u10) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array_merge([$kid, $this->uid, $name, $this->scalar('SELECT COALESCE(MAX(sortIndex), 0) + 1 FROM farmlist WHERE owner=? AND kid=?', [$this->uid, $kid]), $this->useShip($kid, $data), $this->flag($data['onlyLosses'] ?? null)], $units)
        );

        return [(int)$this->db->lastInsertId()];
    }

    /**
     * @param array<mixed> $data as for createList
     * @return list<int>
     */
    public function updateList(int $listId, array $data): array
    {
        $list = $this->list($listId);
        $kid = array_key_exists('villageId', $data) ? $this->ownedVillage($data['villageId']) : (int)$list['kid'];
        $name = $this->name($data['name'] ?? null);
        $this->assertNameFree($kid, $name, $listId);
        $units = $this->troops($data['defaultUnits'] ?? [], false);
        $moved = $kid !== (int)$list['kid'];
        $this->write(
            'UPDATE farmlist SET name=?, kid=?, sortIndex=?, useShip=?, onlyLosses=?, u1=?, u2=?, u3=?, u4=?, u5=?, u6=?, u7=?, u8=?, u9=?, u10=? WHERE id=? AND owner=?',
            array_merge(
                [$name, $kid, $moved ? $this->scalar('SELECT COALESCE(MAX(sortIndex), 0) + 1 FROM farmlist WHERE owner=? AND kid=?', [$this->uid, $kid]) : (int)$list['sortIndex'], $this->useShip($kid, $data), $this->flag($data['onlyLosses'] ?? null)],
                $units,
                [$listId, $this->uid]
            )
        );
        if (!$moved) {
            return [$listId];
        }
        foreach ($this->rows('SELECT id, kid FROM raidlist WHERE lid=?', [$listId]) as $slot) {
            $this->write('UPDATE raidlist SET distance=? WHERE id=?', [Formulas::getDistance($kid, (int)$slot['kid']), (int)$slot['id']]);
        }

        return array_merge([$listId], $this->renumber((int)$list['kid']));
    }

    /**
     * @return list<int> the lists of the same village, renumbered
     */
    public function deleteList(int $listId): array
    {
        $list = $this->list($listId);
        $this->db->run('DELETE FROM raidlist WHERE lid=?', [$listId]);
        $this->write('DELETE FROM farmlist WHERE id=? AND owner=?', [$listId, $this->uid]);

        return $this->renumber((int)$list['kid']);
    }

    /**
     * @param array<mixed> $listIds
     * @return list<int>
     */
    public function setExpanded(array $listIds, mixed $expanded): array
    {
        $ids = $this->ids($listIds);
        $flag = filter_var($expanded, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($flag === null) {
            throw new \DomainException(self::text('errorDataChange'));
        }
        foreach ($ids as $id) {
            $this->list($id);
            $this->write('UPDATE farmlist SET isExpanded=? WHERE id=? AND owner=?', [(int)$flag, $id, $this->uid]);
        }

        return $ids;
    }

    /** @return list<int> */
    public function setSorting(int $listId, mixed $field, mixed $direction): array
    {
        $this->list($listId);
        if (!in_array($field, FarmListData::SORT_FIELDS, true) || !in_array($direction, ['asc', 'desc'], true)) {
            throw new \DomainException(self::text('errorDataChange'));
        }
        $this->write('UPDATE farmlist SET sortField=?, sortDirection=? WHERE id=? AND owner=?', [$field, $direction, $listId, $this->uid]);

        return [$listId];
    }

    /**
     * @return list<int> the lists of the village, renumbered
     */
    public function moveList(int $listId, mixed $newIndex): array
    {
        $list = $this->list($listId);
        if (!is_numeric($newIndex) || (int)$newIndex < 1) {
            throw new \DomainException(self::text('errorDataChange'));
        }
        $ids = array_map('intval', array_column($this->rows('SELECT id FROM farmlist WHERE owner=? AND kid=? ORDER BY sortIndex, id', [$this->uid, (int)$list['kid']]), 'id'));
        array_splice($ids, (int)array_search($listId, $ids, true), 1);
        array_splice($ids, max(0, min(count($ids), (int)$newIndex - 1)), 0, [$listId]);

        return $this->renumber((int)$list['kid'], $ids);
    }

    /**
     * Adds targets (POST) or changes them (PUT). A target already in the list is refused with 409 unless $force
     * overwrites that entry.
     *
     * @param array<mixed> $slots [{id?, listId, x, y, units {t1..t10}, active}]
     * @return list<int>
     */
    public function saveSlots(array $slots, bool $update, bool $force): array
    {
        if ($slots === [] || count($slots) > FarmListData::SLOT_LIMIT) {
            throw new \DomainException(self::text('errorDataChange'));
        }
        $changed = [];
        $this->db->begin_transaction();
        try {
            foreach ($slots as $slot) {
                if (!is_array($slot)) {
                    throw new \DomainException(self::text('errorDataChange'));
                }
                $changed = array_merge($changed, $this->saveSlot($slot, $update, $force));
            }
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }

        return array_values(array_unique($changed));
    }

    /**
     * @param array<mixed> $slotIds
     * @return list<int>
     */
    public function deleteSlots(array $slotIds): array
    {
        $changed = [];
        foreach ($this->ids($slotIds) as $slotId) {
            $changed[] = (int)$this->slot($slotId)['lid'];
            $this->write('DELETE FROM raidlist WHERE id=?', [$slotId]);
        }

        return array_values(array_unique($changed));
    }

    /**
     * @param array<mixed> $slot
     */
    /** @return list<int> the lists the change touched: the target list, and the one a moved entry left */
    private function saveSlot(array $slot, bool $update, bool $force): array
    {
        $list = $this->list($this->id($slot['listId'] ?? null));
        $listId = (int)$list['id'];
        $slotId = $update ? $this->id($slot['id'] ?? null) : 0;
        $existing = $update ? $this->slot($slotId) : [];
        // A change may leave out what it does not touch: the target, single troop amounts or the state.
        $target = $update && !isset($slot['x'], $slot['y']) ? (int)$existing['kid'] : $this->target($slot['x'] ?? null, $slot['y'] ?? null);
        $units = $this->troops($slot['units'] ?? [], true, $existing);
        $active = isset($slot['active']) ? $this->flag($slot['active']) : (int)($existing['isActive'] ?? 1);
        $clash = (int)$this->scalar('SELECT id FROM raidlist WHERE lid=? AND kid=? AND id<>? LIMIT 1', [$listId, $target, $slotId]);
        if ($clash > 0) {
            if (!$force) {
                throw new \DomainException(self::text('targetExists'), 409);
            }
            $slotId = $slotId > 0 ? $slotId : $clash;
            if ($slotId !== $clash) {
                $this->write('DELETE FROM raidlist WHERE id=?', [$clash]);
            }
        }
        $distance = Formulas::getDistance((int)$list['kid'], $target);
        if ($slotId > 0) {
            $this->write(
                'UPDATE raidlist SET lid=?, kid=?, distance=?, isActive=?, u1=?, u2=?, u3=?, u4=?, u5=?, u6=?, u7=?, u8=?, u9=?, u10=? WHERE id=?',
                array_merge([$listId, $target, $distance, $active], $units, [$slotId])
            );

            return array_values(array_unique([$listId, (int)($existing['lid'] ?? $listId)]));
        }
        if ($this->scalar('SELECT COUNT(*) FROM raidlist WHERE lid=?', [$listId]) >= FarmListData::SLOT_LIMIT) {
            throw new \DomainException(self::text('errorRaidListSlotLimit'));
        }
        $this->write(
            'INSERT INTO raidlist (lid, kid, distance, isActive, u1, u2, u3, u4, u5, u6, u7, u8, u9, u10) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array_merge([$listId, $target, $distance, $active], $units)
        );

        return [$listId];
    }

    /** A refusal's text: the reference's own wording. */
    private static function text(string $key): string
    {
        return (string)T('FarmList', $key);
    }

    /** @return array<string, mixed> */
    private function list(int $listId): array
    {
        $row = $this->rows('SELECT * FROM farmlist WHERE id=? AND owner=? LIMIT 1', [$listId, $this->uid])[0] ?? null;
        if ($row === null) {
            throw new \DomainException(self::text('farmListNotFound'), 404);
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function slot(int $slotId): array
    {
        $row = $this->rows('SELECT r.* FROM raidlist r INNER JOIN farmlist f ON f.id=r.lid WHERE r.id=? AND f.owner=? LIMIT 1', [$slotId, $this->uid])[0] ?? null;
        if ($row === null) {
            throw new \DomainException(self::text('errorDataChange'), 404);
        }

        return $row;
    }

    private function ownedVillage(mixed $value): int
    {
        $kid = $this->id($value, self::text('error_villageNotFromUser'));
        if ($this->scalar('SELECT COUNT(*) FROM vdata WHERE kid=? AND owner=?', [$kid, $this->uid]) !== 1) {
            throw new \DomainException(self::text('error_villageNotFromUser'));
        }

        return $kid;
    }

    private function target(mixed $x, mixed $y): int
    {
        foreach ([$x, $y] as $coordinate) {
            if (!is_scalar($coordinate) || !preg_match('/^-?[0-9]{1,4}$/', (string)$coordinate)) {
                throw new \DomainException(self::text('error_no_village'));
            }
            if (abs((int)$coordinate) > MAP_SIZE) {
                throw new \DomainException(self::text('error_no_village'));
            }
        }
        $kid = (int)Formulas::xy2kid((int)$x, (int)$y);
        if ($this->scalar('SELECT COUNT(*) FROM wdata WHERE id=? AND (oasistype>0 OR (occupied=1 AND fieldtype>0))', [$kid]) !== 1) {
            throw new \DomainException(self::text('error_no_village'), 404);
        }

        return $kid;
    }

    /**
     * @param array<mixed> $units {t1..t10}
     * @param array<string, mixed> $fallback a row whose u1..u10 fill in the amounts left out
     * @return list<int> u1..u10
     */
    private function troops(mixed $units, bool $required, array $fallback = []): array
    {
        $units = is_array($units) ? $units : [];
        $amounts = [];
        for ($unit = 1; $unit <= 10; $unit++) {
            $value = $units['t' . $unit] ?? $fallback['u' . $unit] ?? 0;
            if ($value === '' || $value === null) {
                $value = 0;
            }
            if (!is_scalar($value) || !preg_match('/^[0-9]{1,7}$/', (string)$value) || (int)$value > FarmListData::MAX_UNITS) {
                throw new \DomainException(self::text('error_no_troops'));
            }
            $amounts[] = (int)$value;
        }
        if ($required && array_sum($amounts) < 1) {
            throw new \DomainException(self::text('error_no_troops'));
        }

        return $amounts;
    }

    private function name(mixed $name): string
    {
        if (!is_string($name) || trim($name) === '' || mb_strlen(trim($name)) > self::MAX_NAME_LENGTH) {
            throw new \DomainException(self::text('nameNotEmpty'));
        }

        return trim($name);
    }

    private function assertNameFree(int $kid, string $name, int $exceptId): void
    {
        if ($this->scalar('SELECT COUNT(*) FROM farmlist WHERE owner=? AND kid=? AND name=? AND id<>?', [$this->uid, $kid, $name, $exceptId]) > 0) {
            throw new \DomainException(self::text('farmListNameExistsForVillage'));
        }
    }

    /** A ship is only used from a village that has a Harbor. */
    private function useShip(int $kid, array $data): int
    {
        return $this->flag($data['useShip'] ?? null) === 1 && Ships::hasHarbor($kid) ? 1 : 0;
    }

    private function flag(mixed $value): int
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    private function id(mixed $value, ?string $message = null): int
    {
        if (!is_scalar($value) || !preg_match('/^[1-9][0-9]{0,9}$/', (string)$value)) {
            throw new \DomainException($message ?? self::text('errorDataChange'));
        }

        return (int)$value;
    }

    /**
     * @param array<mixed> $values
     * @return list<int>
     */
    private function ids(array $values): array
    {
        if ($values === [] || count($values) > FarmListData::SLOT_LIMIT) {
            throw new \DomainException(self::text('errorDataChange'));
        }

        return array_values(array_unique(array_map(fn (mixed $value): int => $this->id($value), $values)));
    }

    /**
     * Numbers the lists of a village 1..n in their current order (or the given order).
     *
     * @param list<int>|null $order list ids
     * @return list<int>
     */
    private function renumber(int $kid, ?array $order = null): array
    {
        $order ??= array_map('intval', array_column($this->rows('SELECT id FROM farmlist WHERE owner=? AND kid=? ORDER BY sortIndex, id', [$this->uid, $kid]), 'id'));
        foreach ($order as $index => $id) {
            $this->write('UPDATE farmlist SET sortIndex=? WHERE id=? AND owner=?', [$index + 1, $id, $this->uid]);
        }

        return $order;
    }

    /** @param list<mixed> $parameters */
    private function write(string $query, array $parameters): void
    {
        if ($this->db->run($query, $parameters) === false) {
            throw new \DomainException(self::text('error_save'), 503);
        }
    }

    /** @param list<mixed> $parameters */
    private function scalar(string $query, array $parameters): int
    {
        return (int)$this->db->fetchScalar($query, $parameters, 0);
    }

    /**
     * @param list<mixed> $parameters
     * @return list<array<string, mixed>>
     */
    private function rows(string $query, array $parameters): array
    {
        $statement = $this->db->run($query, $parameters);

        return $statement === false ? [] : $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
