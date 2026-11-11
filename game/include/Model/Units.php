<?php

declare(strict_types=1);

namespace Model;

use Core\Database\DB;

class Units
{
    /**
     * Subtracts the sent units from the village's stationed troops.
     *
     * @param array<int, int|string> $units unit counts keyed by unit number (1-11)
     */
    public static function modifyUnits(int $kid, array $units): bool
    {
        $modify = [];
        for ($i = 1; $i <= 11; $i++) {
            if (isset($units[$i]) && $units[$i] > 0) {
                $modify[] = "u{$i}=u{$i}-" . (int) $units[$i];
            }
        }
        if ($modify === []) {
            return false;
        }
        $db = DB::getInstance();
        $db->query("SELECT * FROM units WHERE kid=$kid FOR UPDATE");

        return $db->query('UPDATE units SET ' . implode(',', $modify) . " WHERE kid=$kid") && $db->affectedRows() > 0;
    }
}
