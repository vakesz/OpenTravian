<?php

declare(strict_types=1);

namespace Core;

use Database\DB;
use PDO;

class Server
{
    /**
     * @return array<string, mixed>|false
     */
    public static function getServerById(int $id): array|false
    {
        $stmt = DB::getInstance()->prepare('SELECT * FROM gameServers WHERE id=:id');
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|false
     */
    public static function getServerByWId(string $wid): array|false
    {
        $stmt = DB::getInstance()->prepare('SELECT * FROM gameServers WHERE worldId=:wid');
        $stmt->bindValue('wid', $wid, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getGameWorldsList(bool $includeDev = false): array
    {
        $result = [];
        $stmt = DB::getInstance()->select('SELECT * FROM gameServers');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!$includeDev && $row['hidden'] == 1) {
                continue;
            }
            $closed = $row['registerClosed'] == 1 || $row['finished'] == 1;
            $result[] = self::listEntry($row, $closed ? 0 : 1);
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getGameWorldsListForRegistration(bool $includeDev = false): array
    {
        $result = [];
        $stmt = DB::getInstance()->select('SELECT * FROM gameServers WHERE finished=0 AND registerClosed=0');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!$includeDev && $row['hidden'] == 1) {
                continue;
            }
            $result[] = self::listEntry($row, 1);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function listEntry(array $row, int $status): array
    {
        $server = [
            'id' => $row['id'],
            'uuid' => sha1((string) $row['id']),
            'title' => $row['name'],
            'name' => $row['worldId'],
            'url' => $row['gameWorldUrl'],
            'status' => $status,
            'registrationKeyRequired' => $row['preregistration_key_only'] == 1,
            'start' => $row['startTime'],
        ];
        if (str_ends_with((string) $row['worldId'], 'tt')) {
            $server['fireAndSand'] = 'yes';
        }

        return $server;
    }
}
