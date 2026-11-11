<?php

declare(strict_types=1);

namespace Model;

use Core\Database\DB;

class PlayerModel
{
    public function setPlayerPoints(int $uid, int|float $attack, int|float $defense, int|float $rob): void
    {
        //TODO: add to plus statistics.
        DB::getInstance()->run(
            'UPDATE users SET
                total_attack_points=total_attack_points+?,
                week_attack_points=week_attack_points+?,
                total_defense_points=total_defense_points+?,
                week_defense_points=week_defense_points+?,
                week_robber_points=week_robber_points+?
            WHERE id=?',
            [$attack, $attack, $defense, $defense, $rob, $uid]
        );
    }
}
