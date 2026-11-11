<?php

declare(strict_types=1);

namespace Core\Database;

class GlobalDB
{
    private static ?DB $_self = null;

    public static function getInstance(): DB
    {
        return self::$_self ??= self::getAConnection();
    }

    /** A new, independent connection to the global database. */
    public static function getAConnection(): DB
    {
        global $globalConfig;
        $databaseDetails = $globalConfig['dataSources']['globalDB'];
        $connection = new DB(false);
        $connection->setDatabaseDetails([$databaseDetails['hostname'], $databaseDetails['username'], $databaseDetails['password'], $databaseDetails['database'], $databaseDetails['charset']]);
        $connection->reconnect();

        return $connection;
    }
}
