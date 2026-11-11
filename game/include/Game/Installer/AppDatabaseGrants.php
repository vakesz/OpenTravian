<?php

declare(strict_types=1);

namespace Game\Installer;

/**
 * What the dedicated application database user may do, in one place: the installer
 * creates it with statement(), and the preflight reads the live grants back
 * with excess() to confirm nothing more was given.
 *
 * The user is one account for the whole runtime, so besides the data privileges it holds the DDL
 * ones the game itself needs: ALTER, CREATE, DROP and INDEX for the world schema patches, restores
 * (DROP and CREATE TABLE) and the cleanups that recreate tables, TRIGGER, SHOW VIEW and LOCK TABLES
 * because the dumps contain them. Everything is limited to the two game databases: no global
 * privilege, no GRANT OPTION, no FILE, PROCESS, RELOAD or SUPER. The account host is "%" on purpose:
 * the database port is not published and the account is only reachable on the private Compose
 * network, where the container addresses change from start to start, so a narrower host would
 * only break restarts.
 */
final class AppDatabaseGrants
{
    /** @var list<string> */
    public const PRIVILEGES = ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'ALTER', 'DROP', 'INDEX', 'LOCK TABLES', 'SHOW VIEW', 'TRIGGER'];

    private function __construct()
    {
    }

    /**
     * The GRANT statement for one game database ("_" is a wildcard in GRANT database names unless escaped).
     *
     * @param string $quotedUser the account as 'name'@'host', already quoted
     */
    public static function statement(string $database, string $quotedUser): string
    {
        return 'GRANT ' . implode(', ', self::PRIVILEGES) . ' ON `' . str_replace('_', '\\_', $database) . '`.* TO ' . $quotedUser;
    }

    /**
     * What the SHOW GRANTS lines give beyond the application's privileges on its own databases.
     * Only privilege names and scopes come back, never anything from the account line itself
     * (which carries the password hash).
     *
     * @param list<string> $grants lines of SHOW GRANTS
     * @param list<string> $databases the game databases
     * @return list<string> entries such as "FILE on *.*" or "DELETE on other"
     */
    public static function excess(array $grants, array $databases): array
    {
        $excess = [];
        foreach ($grants as $line) {
            if (preg_match('/^GRANT\s+(.+?)\s+ON\s+(.+?)\s+TO\s/is', $line, $match) !== 1) {
                continue;
            }
            $scope = str_replace(['`', '\\_', '\\%'], ['', '_', '%'], trim($match[2]));
            $global = $scope === '*.*';
            $database = str_ends_with($scope, '.*') ? substr($scope, 0, -2) : null;
            foreach (array_map(static fn (string $name): string => strtoupper(trim($name)), explode(',', $match[1])) as $privilege) {
                if ($global && $privilege === 'USAGE') {
                    continue;
                }
                $known = in_array($privilege, self::PRIVILEGES, true);
                if (!$global && $database !== null && in_array($database, $databases, true) && $known) {
                    continue;
                }
                $excess[] = $privilege . ' on ' . $scope;
            }
            if (preg_match('/\sWITH\s+GRANT\s+OPTION\s*$/i', $line) === 1) {
                $excess[] = 'GRANT OPTION on ' . $scope;
            }
        }

        return array_values(array_unique($excess));
    }
}
