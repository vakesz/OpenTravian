<?php

declare(strict_types=1);

namespace Model;

use Core\Database\DB;

class HeroModel
{
    /**
     * @return array<string, mixed>|null
     */
    public function getHero(int $heroId): ?array
    {
        $statement = DB::getInstance()->run(
            'SELECT uid, kid, exp, health, power, offBonus, itemHealth, lastupdate, defBonus, production, productionType, hide FROM hero WHERE uid=?',
            [$heroId]
        );

        return $statement === false ? null : $statement->get_result()->fetch_assoc();
    }
}
