<?php

declare(strict_types=1);

namespace Model;

use Core\Database\DB;
use Core\Log;
use Game\Mail\EventMail;

class MovementsModel
{
    public const SORTTYPE_GOING = 0;
    public const SORTTYPE_RETURN = 1;

    public const ATTACKTYPE_SPY = 1;
    public const ATTACKTYPE_REINFORCEMENT = 2;
    public const ATTACKTYPE_NORMAL = 3;
    public const ATTACKTYPE_RAID = 4;
    public const ATTACKTYPE_SETTLERS = 5;
    public const ATTACKTYPE_EVASION = 6;
    public const ATTACKTYPE_ADVENTURE = 7;

    /**
     * Queues a movement and returns its id (0 when the insert failed).
     *
     * @param array<int|string, int|float|string> $units unit counts keyed by unit number; missing entries count as 0
     */
    public function addMovement(
        int $kid,
        int $to_kid,
        int $race,
        array $units,
        int $ctar1,
        int $ctar2,
        int $spyType,
        bool|int $redeployHero,
        int $mode,
        int $attack_type,
        int|float|string $start_time,
        int|float|string $end_time,
        ?string $data = null,
        int $ship = 0
    ): int {
        $db = DB::getInstance();
        $statement = $db->run(
            'INSERT INTO movement (kid,to_kid,race,u1,u2,u3,u4,u5,u6,u7,u8,u9,u10,u11,ctar1,ctar2,spyType,redeployHero,mode,attack_type,start_time,end_time,data,ship) VALUES (?'
            . str_repeat(',?', 23) . ')',
            [
                $kid,
                $to_kid,
                $race,
                ...self::unitCounts($units),
                $ctar1,
                $ctar2,
                $spyType,
                $redeployHero ? 1 : 0,
                $mode,
                $attack_type,
                (int) ceil((float) $start_time),
                (int) ceil((float) $end_time),
                $data ?? '',
                $ship,
            ]
        );
        $result = $statement === false ? 0 : (int) $db->lastInsertId();
        if ($result > 0 && $mode === 0 && in_array($attack_type, [self::ATTACKTYPE_NORMAL, self::ATTACKTYPE_RAID], true)) {
            EventMail::attackIncoming($kid, $to_kid);
        }
        if ($result === 0) {
            $uid = $db->fetchScalar('SELECT owner FROM vdata WHERE kid=?', [$kid]);
            if ($uid !== false) {
                Log::addLog((int) $uid, 'movementFailed' . $attack_type, sprintf('Troops: %s', implode(',', array_values($units))));
            }
        }

        return $result;
    }

    /**
     * @param array<int|string, int|float|string> $units
     */
    public function addTrapped(int $kid, int $to_kid, int $race, array $units): void
    {
        DB::getInstance()->run(
            'INSERT INTO trapped (kid, to_kid, race, u1, u2, u3, u4, u5, u6, u7, u8, u9, u10, u11) VALUES (?' . str_repeat(',?', 13) . ')',
            [$kid, $to_kid, $race, ...self::unitCounts($units)]
        );
    }

    public function isSameVillageReinforcementExists(int $kid, int $to_kid): int|false
    {
        return $this->existingId('enforcement', $kid, $to_kid);
    }

    public function isSameVillageTrappedExists(int $kid, int $to_kid): int|false
    {
        return $this->existingId('trapped', $kid, $to_kid);
    }

    public function setMovementMarkState(int $to_kid, int $moveId, int $state): bool
    {
        $statement = DB::getInstance()->run(
            'UPDATE movement SET markState=? WHERE id=? AND to_kid=? AND mode=0 AND attack_type IN(3,4)',
            [$state, $moveId, $to_kid]
        );

        return $statement !== false && $statement->affected_rows >= 1;
    }

    /**
     * The movement row with its numeric columns as ints (the database hands them over as strings), so the processors
     * that run under strict types can pass them on.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function typedRow(array $row): array
    {
        $columns = ['id', 'kid', 'to_kid', 'race', 'ctar1', 'ctar2', 'spyType', 'redeployHero', 'ship', 'mode', 'attack_type', 'start_time', 'end_time', 'markState'];
        for ($unit = 1; $unit <= 11; ++$unit) {
            $columns[] = 'u' . $unit;
        }
        foreach ($columns as $column) {
            if (isset($row[$column]) && is_numeric($row[$column])) {
                $row[$column] = (int)$row[$column];
            }
        }

        return $row;
    }

    /** Incoming attacks and raids that have not arrived yet. */
    public function countIncomingAttacks(int $toKid): int
    {
        return (int)DB::getInstance()->fetchScalar(
            'SELECT COUNT(*) FROM movement WHERE to_kid=? AND mode=0 AND attack_type IN(3,4)',
            [$toKid],
            0
        );
    }

    /**
     * @param array<int|string, int|float|string> $units
     */
    public function addEnforce(int $uid, int $kid, int $to_kid, int $race, array $units): bool
    {
        return DB::getInstance()->run(
            'INSERT INTO enforcement (uid, kid, to_kid, race, u1, u2, u3, u4, u5, u6, u7, u8, u9, u10, u11) VALUES (?' . str_repeat(',?', 14) . ')',
            [$uid, $kid, $to_kid, $race, ...self::unitCounts($units)]
        ) !== false;
    }

    private function existingId(string $table, int $kid, int $to_kid): int|false
    {
        $id = DB::getInstance()->fetchScalar("SELECT id FROM $table WHERE kid=? AND to_kid=?", [$kid, $to_kid]);

        return $id === false ? false : (int) $id;
    }

    /**
     * @param array<int|string, int|float|string> $units
     * @return list<int> counts of units 1 to 11
     */
    private static function unitCounts(array $units): array
    {
        $counts = [];
        for ($i = 1; $i <= 11; $i++) {
            $counts[] = (int) ($units[$i] ?? 0);
        }

        return $counts;
    }
}
