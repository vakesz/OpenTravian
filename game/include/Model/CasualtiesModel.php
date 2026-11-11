<?php

declare(strict_types=1);

namespace Model;

use Core\Database\DB;

class CasualtiesModel
{
    public function setTodayCasualties(int|float $casualties): void
    {
        $db = DB::getInstance();
        $today = strtotime('today 00:00');
        $update = 'UPDATE casualties SET attacks=attacks+1, casualties=casualties+? WHERE time=?';
        $updated = $db->run($update, [$casualties, $today]);
        if ($updated !== false && $updated->affected_rows < 1) {
            $db->run('INSERT INTO casualties (attacks, casualties, time) VALUES (0, 0, ?)', [$today]);
            $db->run($update, [$casualties, $today]);
        }
    }
}
