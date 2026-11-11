<?php

declare(strict_types=1);

namespace Model;

use Core\Config;
use Core\Database\GlobalDB;

class ClubApi
{
    /**
     * @param array<int, int|string>|string $points
     */
    public static function addMedal(string $nickname, int $tribe, string $email, array|string $points, int $type, bool $hidden): int|false
    {
        if ($email === '') {
            return false;
        }
        if (is_array($points)) {
            $points = implode('|', $points);
        }
        $db = GlobalDB::getInstance();
        $db->run(
            'INSERT INTO clubMedals (worldId, nickname, email, tribe, params, type, time, hidden) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [Config::getInstance()->settings->serverName, $nickname, $email, $tribe, $points, $type, time(), $hidden ? 1 : 0]
        );

        return (int) $db->lastInsertId();
    }

    public static function getPlayerEmailMedalById(string $email, int $id): \mysqli_result|false
    {
        $statement = GlobalDB::getInstance()->run('SELECT * FROM clubMedals WHERE id=? AND hidden=0 AND email=? LIMIT 1', [$id, $email]);

        return $statement === false ? false : $statement->get_result();
    }

    public static function getPlayerEmailMedals(string $email, bool $includeHidden = false): \mysqli_result|false
    {
        $statement = GlobalDB::getInstance()->run(
            'SELECT * FROM clubMedals WHERE ' . ($includeHidden ? '' : 'hidden=0 AND ') . 'email=? ORDER BY time DESC',
            [$email]
        );

        return $statement === false ? false : $statement->get_result();
    }
}
