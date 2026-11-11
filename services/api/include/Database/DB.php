<?php

declare(strict_types=1);

namespace Database;

use PDO;

class DB extends PDO
{
    private static ?self $instance = null;

    public function __construct()
    {
        /** @var array<string, mixed> $globalConfig */
        global $globalConfig;
        $source = $globalConfig['dataSources']['globalDB'];
        $dsn = 'mysql:charset=utf8mb4;host=' . $source['hostname'] . ';dbname=' . $source['database'];
        parent::__construct($dsn, (string) $source['username'], (string) $source['password'], []);
        $this->exec('set names utf8mb4');
    }

    /** Run a trusted, parameter-free SELECT; a failure is an exception, never `false`. */
    public function select(string $sql): \PDOStatement
    {
        $statement = $this->query($sql);
        if ($statement === false) {
            throw new \RuntimeException('Query failed.');
        }

        return $statement;
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }
}
