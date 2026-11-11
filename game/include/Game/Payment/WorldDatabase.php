<?php

declare(strict_types=1);

namespace Game\Payment;

use Core\Database\DB;
use Core\Database\GlobalDB;

use function getWorldUniqueId;

/**
 * Opens the database of the world an order belongs to, so a payment received
 * by any world's web container credits the right world. The world's own
 * connection file is the one `gameServers.configFileLocation` points to.
 */
final class WorldDatabase
{
    public static function forWorld(int $worldUniqueId): ?DB
    {
        if ($worldUniqueId === (int) getWorldUniqueId()) {
            return DB::getInstance();
        }
        $statement = GlobalDB::getInstance()->run('SELECT configFileLocation FROM gameServers WHERE id=?', [$worldUniqueId]);
        $row = $statement ? $statement->get_result()->fetch_assoc() : null;
        $file = is_array($row) ? (string) $row['configFileLocation'] : '';
        if ($file === '' || !is_file($file)) {
            return null;
        }
        $database = (static function (string $file): ?array {
            // The connection file assigns the global $connection; keep this runtime's own.
            global $connection;
            $own = $connection ?? null;
            require $file;
            $loaded = $connection['database'] ?? null;
            $connection = $own;

            return is_array($loaded) ? $loaded : null;
        })($file);
        if ($database === null) {
            return null;
        }
        $world = new DB(false);
        $world->setDatabaseDetails([$database['hostname'], $database['username'], $database['password'], $database['database'], $database['charset'] ?? 'utf8mb4']);
        $world->reconnect();

        return $world;
    }
}
