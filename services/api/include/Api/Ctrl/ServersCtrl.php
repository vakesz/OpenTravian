<?php

declare(strict_types=1);

namespace Api\Ctrl;

use Api\ApiAbstractCtrl;
use Core\Server;
use Database\DB;
use Database\ServerDB;
use PDO;

class ServersCtrl extends ApiAbstractCtrl
{
    public function usernameById(): void
    {
        $worldId = $this->requireString('worldId');
        $uid = $this->requireInt('uid');
        $this->response = ['gameWorldName' => null, 'uid' => null, 'playerName' => null];
        $server = Server::getServerByWId($worldId);
        if ($server !== false) {
            $serverDB = ServerDB::getInstance((string) $server['configFileLocation']);
            $stmt = $serverDB->prepare('SELECT name FROM users WHERE id=:id');
            $stmt->bindValue('id', $uid, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->rowCount()) {
                $this->response['gameWorldName'] = $server['worldId'];
                $this->response['uid'] = $uid;
                $this->response['playerName'] = $stmt->fetchColumn();
            }
        }
    }

    public function validateActivationCode(): void
    {
        $server = Server::getServerById($this->requireInt('gameWorld'));
        $activationCode = $this->requireString('activationCode');
        $this->response['success'] = false;
        if ($server === false) {
            return;
        }
        if ($this->activationExists((int) $server['id'], $activationCode)) {
            $this->response['success'] = true;
        }
    }

    private function activationExists(int $worldId, string $activationCode): bool
    {
        $db = DB::getInstance();
        $stmt = $db->prepare('SELECT id FROM activation WHERE worldId=:wid AND activationCode=:activationCode AND used=0');
        $stmt->bindValue('wid', $worldId, PDO::PARAM_INT);
        $stmt->bindValue('activationCode', $activationCode, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    public function loadServerByID(): void
    {
        $gameWorld = $this->requireInt('gameWorld');
        $this->response['success'] = false;
        $db = DB::getInstance();
        $stmt = $db->prepare("SELECT * FROM gameServers WHERE id=:id");
        $stmt->bindValue('id', $gameWorld, PDO::PARAM_INT);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->response['success'] = true;
            $this->response['gameWorld'] = $this->serverRowToData($row);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function serverRowToData(array $row): array
    {
        //$info = json_decode(file_get_contents($row['gameWorldUrl'] . 'info'), true);
        $server = [
            "id" => (int)$row['id'],
            "speed" => (int)$row['speed'],
            "title" => $row['name'],
            "name" => $row['worldId'],
            "url" => $row['gameWorldUrl'],
            "registrationKeyRequired" => $row['preregistration_key_only'] == 1,
            "start" => (int)$row['startTime'],
            'secondsPast' => time() - (int) $row['startTime'],
            'fireAndSand' => str_ends_with((string) $row['worldId'], 'fs'),
            'tournament' => str_ends_with((string) $row['worldId'], 'tt'),
            'activationRequired' => $row['activation'] == 1,
            'hidden' => $row['hidden'] == 1,
            'registerClosed' => $row['registerClosed'] == 1,
            'finished' => $row['finished'] == 1,
            'finishTrainingEnabled' => false, //$info['finishTrainingEnabled']
        ];
        return $server;
    }

    public function loadServerByWID(): void
    {
        $worldId = $this->requireString('worldId');
        $db = DB::getInstance();
        $stmt = $db->prepare("SELECT * FROM gameServers WHERE worldId=:worldId");
        $this->response['success'] = false;
        $stmt->bindValue('worldId', $worldId, PDO::PARAM_STR);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->response['success'] = true;
            $this->response['gameWorld'] = $this->serverRowToData($row);
        }
    }

    public function loadServers(): void
    {
        $this->response = ['serverTime' => time(), 'gameWorlds' => []];
        $db = DB::getInstance();
        $stmt = $db->select("SELECT * FROM gameServers");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->response['gameWorlds'][] = $this->serverRowToData($row);
        }
    }
}