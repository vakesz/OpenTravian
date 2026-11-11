<?php

declare(strict_types=1);

namespace Core\Database;

use Core\Config;
use PDO;
use PDOStatement;

/**
 * Thin PDO wrapper (exceptions on error, associative rows, native prepares).
 * Unknown methods are forwarded to the PDO connection, e.g. lastInsertId().
 *
 * @method string|false lastInsertId(?string $name = null)
 */
class DBI
{
    /** @var array<string, self> */
    private static array $instances = [];
    private ?PDO $pdo;

    /**
     * @param string $conn connection name
     */
    private function __construct(private readonly string $conn, string $hostname, string $username, string $password, string $database, string $charset = 'utf8mb4')
    {
        $opt = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $hostname, $database, $charset);
        $this->pdo = new PDO($dsn, $username, $password, $opt);
    }

    /**
     * @param array<string, mixed>|null $dbDetails connection details; the configured `db` section when omitted
     */
    public static function getInstance(string $conn = 'default', ?array $dbDetails = null): self
    {
        $dbDetails ??= (array) Config::getInstance()->db;

        return self::$instances[$conn] ??= new self(
            $conn,
            (string) $dbDetails['hostname'],
            (string) $dbDetails['username'],
            (string) $dbDetails['password'],
            (string) $dbDetails['database']
        );
    }

    /**
     * @param array<array-key, mixed>|string|int|float|bool|null $args positional parameters; a scalar is one parameter
     */
    public function run(string $sql, array|string|int|float|bool|null $args = null): PDOStatement
    {
        $pdo = $this->connection();
        if ($args === null) {
            return $pdo->query($sql) ?: throw new \RuntimeException('The query could not be run.');
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute(is_array($args) ? $args : [$args]);

        return $stmt;
    }

    /**
     * @param array<array-key, mixed>|string|int|float|bool|null $args
     * @return array<string, mixed>|list<mixed>|false|mixed the row, or $default when there is none
     */
    public function fetchSingleRow(string $sql, array|string|int|float|bool|null $args = null, mixed $default = false, int $fetch_style = PDO::FETCH_ASSOC): mixed
    {
        $stmt = $this->run($sql, $args);
        if (!$stmt->rowCount()) {
            return $default;
        }

        return $stmt->fetch($fetch_style);
    }

    /**
     * @param array<array-key, mixed>|string|int|float|bool|null $args
     */
    public function fetchScalar(string $sql, array|string|int|float|bool|null $args = null, mixed $default = false): mixed
    {
        $stmt = $this->run($sql, $args);
        if (!$stmt->rowCount()) {
            return $default;
        }

        return $stmt->fetchColumn();
    }

    /**
     * The values of one column; the first column when none is named.
     *
     * @param array<array-key, mixed>|string|int|float|bool|null $args
     * @return list<mixed>
     */
    public function fetchPluck(string $sql, array|string|int|float|bool|null $args = null, ?string $column = null): array
    {
        $stmt = $this->run($sql, $args);
        if ($column === null) {
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (isset($row[$column])) {
                $result[] = $row[$column];
            }
        }

        return $result;
    }

    /**
     * @param list<mixed> $args
     */
    public function __call(string $method, array $args): mixed
    {
        return $this->connection()->{$method}(...$args);
    }

    public function disconnect(): void
    {
        unset(self::$instances[$this->conn]);
        $this->pdo = null;
    }

    private function connection(): PDO
    {
        return $this->pdo ?? throw new \LogicException('The database connection was closed.');
    }
}
