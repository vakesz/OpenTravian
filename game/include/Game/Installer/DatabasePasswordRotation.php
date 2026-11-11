<?php

declare(strict_types=1);

namespace Game\Installer;

/**
 * The pieces of rotating the application database user's password (tools/rotate-db-password.php):
 * a new random password, the ALTER USER for every host the account exists on, the rewrite of the
 * two generated config files that hold it, and a login check. The tool orders them behind a
 * journal so an interrupted run can be repeated. No message or return value here contains a
 * password.
 */
final class DatabasePasswordRotation
{
    private function __construct()
    {
    }

    public static function newPassword(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * The password stored under $path in a generated config file ("<?php $variable = array (...);"),
     * or null when the file or the key is missing.
     *
     * @param list<string> $path keys leading to the password, e.g. ['database', 'password']
     */
    public static function readPassword(string $file, string $variable, array $path): ?string
    {
        $config = self::load($file, $variable);
        foreach ($path as $key) {
            if (!is_array($config) || !array_key_exists($key, $config)) {
                return null;
            }
            $config = $config[$key];
        }

        return is_string($config) ? $config : null;
    }

    /**
     * Rewrites the password in a generated config file, keeping every other value and the file mode,
     * through a temporary file and a rename so a reader never sees half a file.
     *
     * @param list<string> $path
     */
    public static function writePassword(string $file, string $variable, array $path, string $password): void
    {
        $config = self::load($file, $variable);
        if (!is_array($config) || self::readPassword($file, $variable, $path) === null) {
            throw new \RuntimeException('The config file ' . basename($file) . ' has no database password to replace.');
        }
        $node = &$config;
        foreach ($path as $key) {
            $node = &$node[$key];
        }
        $node = $password;
        unset($node);

        $mode = (int) (@fileperms($file) & 0777) ?: 0600;
        $temporary = $file . '.rotate';
        if (@file_put_contents($temporary, "<?php\n\$" . $variable . ' = ' . var_export($config, true) . ";\n") === false
            || !@chmod($temporary, $mode)
            || !@rename($temporary, $file)) {
            @unlink($temporary);
            throw new \RuntimeException('Cannot write the config file ' . basename($file) . '.');
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
    }

    /**
     * Sets the password of the account on every host it exists on.
     *
     * @return int the number of accounts changed
     */
    public static function alterUser(\mysqli $server, string $user, string $password): int
    {
        if (preg_match('/^[A-Za-z0-9_]{1,32}$/', $user) !== 1) {
            throw new \RuntimeException('Invalid application database user name.');
        }
        $statement = $server->prepare('SELECT Host FROM mysql.user WHERE User = ?');
        $statement->bind_param('s', $user);
        $statement->execute();
        $statement->bind_result($host);
        $hosts = [];
        while ($statement->fetch()) {
            $hosts[] = (string) $host;
        }
        $statement->close();
        if ($hosts === []) {
            throw new \RuntimeException('The database user "' . $user . '" does not exist.');
        }
        foreach ($hosts as $host) {
            $account = "'" . $server->real_escape_string($user) . "'@'" . $server->real_escape_string($host) . "'";
            if (!$server->query('ALTER USER ' . $account . " IDENTIFIED BY '" . $server->real_escape_string($password) . "'")) {
                throw new \RuntimeException('Could not change the password of ' . $user . '@' . $host . '.');
            }
        }

        return count($hosts);
    }

    /**
     * Whether the account can log in with the password and use every database.
     *
     * @param list<string> $databases
     */
    public static function canLogin(string $host, string $user, string $password, array $databases): bool
    {
        mysqli_report(MYSQLI_REPORT_OFF);
        foreach ($databases as $database) {
            $connection = @new \mysqli($host, $user, $password);
            $ok = !$connection->connect_error && $connection->select_db($database) && (bool) $connection->query('SELECT 1');
            if (!$connection->connect_error) {
                $connection->close();
            }
            if (!$ok) {
                return false;
            }
        }

        return true;
    }

    private static function load(string $file, string $variable): mixed
    {
        if (!is_file($file)) {
            return null;
        }

        return (static function (string $file, string $variable): mixed {
            include $file;

            return $$variable ?? null;
        })($file, $variable);
    }
}
