<?php

declare(strict_types=1);

namespace Model;

use Core\Database\DBI;
use PDOStatement;
use function array_filter_units;

class ResourceFieldsModel
{
    private DBI $db;

    public function __construct()
    {
        $this->db = DBI::getInstance();
    }

    public function getOasesAsString(int $kid): string
    {
        return implode(',', $this->db->fetchPluck('SELECT kid FROM odata WHERE did=?', $kid));
    }

    public function getEnforcements(int $kid): PDOStatement
    {
        return $this->db->run('SELECT * FROM enforcement WHERE to_kid=?', [$kid]);
    }

    /**
     * @return array{race: mixed, units: array<int, int>}|false
     */
    public function getUnits(int $kid): array|false
    {
        $units = $this->db->fetchSingleRow('SELECT * FROM units WHERE kid=?', $kid);
        if (!$units) {
            return false;
        }

        return ['race' => $units['race'], 'units' => array_filter_units($units)];
    }

    /**
     * @param string $oasesString comma-separated oasis ids from getOasesAsString()
     * @return array{end_time: int, count: int}
     */
    public function getIncomingAttacksToMyOases(string $oasesString): array
    {
        return $this->oasesMovements($oasesString, 'attack_type IN(3,4)');
    }

    /**
     * @param string $oasesString comma-separated oasis ids from getOasesAsString()
     * @return array{end_time: int, count: int}
     */
    public function getIncomingReinforcementToMyOases(string $oasesString): array
    {
        return $this->oasesMovements($oasesString, 'attack_type=2');
    }

    /** @return array{end_time: int, count: int} */
    public function getIncomingAttacksToMe(int $kid): array
    {
        return $this->movements('to_kid=? AND mode=0 AND attack_type IN(3,4)', $kid);
    }

    /** @return array{end_time: int, count: int} */
    public function getIncomingReinforcementToMe(int $kid): array
    {
        return $this->movements('to_kid=? AND ((mode=0 AND attack_type=2) OR (mode=1))', $kid);
    }

    /** @return array{end_time: int, count: int} */
    public function getOutgoingAttacks(int $kid): array
    {
        return $this->movements('kid=? AND mode=0 AND attack_type IN(3,4,1)', $kid);
    }

    /** @return array{end_time: int, count: int} */
    public function getOutGoingReinforcements(int $kid): array
    {
        return $this->movements('kid=? AND mode=0 AND attack_type=2', $kid);
    }

    /** @return array{end_time: int, count: int} */
    public function getOutgoingAdventure(int $kid): array
    {
        return $this->movements('kid=? AND mode=0 AND attack_type=7', $kid);
    }

    /** @return array{end_time: int, count: int} */
    public function getOutgoingEvasion(int $kid): array
    {
        return $this->movements('kid=? AND mode=0 AND attack_type=6', $kid);
    }

    /** @return array{end_time: int, count: int} */
    public function getOutgoingSettlers(int $kid): array
    {
        return $this->movements('kid=? AND mode=0 AND attack_type=5', $kid);
    }

    /**
     * @return array{end_time: int, count: int}
     */
    private function oasesMovements(string $oasesString, string $condition): array
    {
        if ($oasesString === '') {
            return ['end_time' => 0, 'count' => 0];
        }

        return $this->movements("to_kid IN($oasesString) AND mode=0 AND $condition", null);
    }

    /**
     * Number of matching movements and the arrival time of the earliest one.
     *
     * @param string $where trusted condition, with at most one `?` placeholder
     * @return array{end_time: int, count: int}
     */
    private function movements(string $where, ?int $kid): array
    {
        $count = (int) $this->db->fetchScalar("SELECT COUNT(id) FROM movement WHERE $where", $kid);
        if ($count === 0) {
            return ['end_time' => 0, 'count' => 0];
        }

        return [
            'end_time' => (int) $this->db->fetchScalar("SELECT end_time FROM movement WHERE $where ORDER BY end_time ASC LIMIT 1", $kid),
            'count' => $count,
        ];
    }
}
