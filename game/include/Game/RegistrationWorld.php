<?php

declare(strict_types=1);

namespace Game;

use Core\Config;
use Core\Database\DB;
use Core\Database\GlobalDB;

/**
 * Registration facts required by the local activation flow: availability,
 * world name and the tribes a new account may choose.
 */
final class RegistrationWorld
{
    private function __construct()
    {
    }

    /**
     * @return array{title:string,speed:int,mapSide:int,startTime:int,roundDays:int,players:int,status:string,registrationOpen:bool,keyOnly:bool,tribes:list<string>}
     */
    public static function summary(): array
    {
        $config = Config::getInstance();
        $server = GlobalDB::getInstance()->query(
            'SELECT registerClosed, preregistration_key_only FROM gameServers WHERE id=' . (int)getWorldUniqueId()
        );
        $row = $server && $server->num_rows ? $server->fetch_assoc() : ['registerClosed' => 0, 'preregistration_key_only' => 0];
        $startTime = (int)$config->game->start_time;
        if (!empty($config->dynamic->maintenance)) {
            $status = 'maintenance';
        } elseif (!empty($config->dynamic->serverFinished)) {
            $status = 'finished';
        } elseif ($startTime > time()) {
            $status = 'upcoming';
        } elseif ((int)$row['registerClosed'] === 1) {
            $status = 'closed';
        } else {
            $status = 'open';
        }
        $tribes = [];
        foreach (Tribes::playable() as $tribe) {
            if (isset(Tribes::NAMES[$tribe])) {
                $tribes[] = Tribes::NAMES[$tribe];
            }
        }
        return [
            'title' => (string)$config->settings->serverName,
            'speed' => (int)getGameSpeed(),
            'mapSide' => 2 * (int)MAP_SIZE + 1,
            'startTime' => $startTime,
            'roundDays' => (int)round((float)getGame('round_length')),
            'players' => (int)DB::getInstance()->fetchScalar('SELECT COUNT(id) FROM users WHERE id > 2 AND access <> 3'),
            'status' => $status,
            // Registration also works before the start: players pick a tribe and wait for the opening.
            'registrationOpen' => !in_array($status, ['closed', 'finished', 'maintenance'], true),
            'keyOnly' => (int)$row['preregistration_key_only'] === 1,
            'tribes' => $tribes,
        ];
    }
}
