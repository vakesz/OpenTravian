<?php

declare(strict_types=1);

namespace Game\Installer;

use Core\Database\DB;
use Core\Security\Password;
use Game\FinishConstruction;
use Game\SmtpConfig;
use Game\Tribes;
use Game\WorldOverrides;
use Game\WorldSchemaVersion;
use mysqli;
use mysqli_result;
use Model\InstallerModel;
use Model\NatarsModel;
use Model\RegisterModel;
use RuntimeException;
use Throwable;

/**
 * Installs a world: imports both schemas, creates the dedicated database user,
 * writes the generated state files and seeds the map. The setup wizard and the
 * CLI installer (tools/install.php) both call install(), so this is the only
 * place the install steps exist.
 */
final class WorldInstaller
{
    /**
     * @param mixed $path
     * @param mixed $contents
     */
    private static function writeFile($path, $contents): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new RuntimeException('The server cannot write its configuration directory.');
        }
        $temporaryPath = $path . '.tmp';
        if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
            throw new RuntimeException('The server could not write a configuration file.');
        }
        @chmod($temporaryPath, 0600);
        if (!rename($temporaryPath, $path)) {
            @unlink($temporaryPath);
            throw new RuntimeException('The server could not activate a configuration file.');
        }
        @chmod($path, 0600);
    }

    /**
     * @param \mysqli $connection
     * @param mixed $databaseName
     */
    private static function databaseHasTables($connection, $databaseName): bool
    {
        $statement = $connection->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?'
        );
        $statement->bind_param('s', $databaseName);
        $statement->execute();
        $tableCount = 0;
        $statement->bind_result($tableCount);
        $statement->fetch();
        $statement->close();
        return (int)$tableCount > 0;
    }

    /**
     * Returns only configured databases that currently contain tables. This probe
     * is read-only, so the setup form can ask for an explicit destructive choice.
     *
     * @param mixed $host
     * @param mixed $username
     * @param mixed $password
     * @param array<mixed> $databaseNames
     * @return list<mixed>
     */
    public static function nonEmptyDatabases($host, $username, $password, array $databaseNames)
    {
        $server = new mysqli($host, $username, $password);
        if ($server->connect_error) {
            throw new RuntimeException('Could not connect to the database server.');
        }
        $nonEmpty = [];
        foreach (array_unique($databaseNames) as $databaseName) {
            if (!preg_match('/^[A-Za-z0-9_]+$/', $databaseName)) {
                $server->close();
                throw new RuntimeException('Invalid database name.');
            }
            if (self::databaseHasTables($server, $databaseName)) {
                $nonEmpty[] = $databaseName;
            }
        }
        $server->close();
        return $nonEmpty;
    }

    /**
     * The caller has already shown a confirmation page tied to the setup session.
     * Dropping a schema is more reliable than trying to infer and drop every table
     * type, and self::importDatabase() recreates it immediately afterwards.
     *
     * @param mixed $host
     * @param mixed $username
     * @param mixed $password
     * @param list<mixed> $databaseNames
     */
    private static function clearDatabases($host, $username, $password, array $databaseNames): void
    {
        $server = new mysqli($host, $username, $password);
        if ($server->connect_error) {
            throw new RuntimeException('Could not connect to the database server.');
        }
        foreach (array_unique($databaseNames) as $databaseName) {
            if (!preg_match('/^[A-Za-z0-9_]+$/', $databaseName)) {
                $server->close();
                throw new RuntimeException('Invalid database name.');
            }
            if (!$server->query('DROP DATABASE IF EXISTS `' . $databaseName . '`')) {
                $server->close();
                throw new RuntimeException('Could not clear the configured database.');
            }
        }
        $server->close();
    }

    /**
     * A fresh install needs a generated password for its dedicated application
     * user. Remove only the known account created by this wizard, and only after
     * the administrator explicitly confirms the database reset.
     *
     * @param mixed $host
     * @param mixed $username
     * @param mixed $password
     * @param string $appUser
     */
    private static function clearApplicationUser($host, $username, $password, $appUser): void
    {
        if (!preg_match('/^[A-Za-z0-9_]{1,32}$/', $appUser)) {
            throw new RuntimeException('Invalid application database user name.');
        }
        $server = new mysqli($host, $username, $password);
        if ($server->connect_error) {
            throw new RuntimeException('Could not connect to the database server.');
        }
        if (!$server->query("DROP USER IF EXISTS '" . $server->real_escape_string($appUser) . "'@'%'")) {
            $server->close();
            throw new RuntimeException('Could not reset the application database user.');
        }
        $server->close();
    }

    /**
     * @param mixed $host
     * @param mixed $username
     * @param mixed $password
     * @param mixed $databaseName
     * @param mixed $sqlPath
     */
    private static function importSql($host, $username, $password, $databaseName, $sqlPath): void
    {
        $connection = new mysqli($host, $username, $password, $databaseName);
        if ($connection->connect_error) {
            throw new RuntimeException('Could not connect to a configured database.');
        }
        $connection->set_charset('utf8mb4');
        $sql = file_get_contents($sqlPath);
        if ($sql === false || !$connection->multi_query($sql)) {
            $connection->close();
            throw new RuntimeException('Could not import a database schema.');
        }
        do {
            $result = $connection->store_result();
            if ($result instanceof mysqli_result) {
                $result->free();
            }
            if ($connection->errno) {
                $connection->close();
                throw new RuntimeException('A database schema statement failed.');
            }
            if ($connection->more_results() && !$connection->next_result()) {
                $connection->close();
                throw new RuntimeException('A database schema statement failed.');
            }
        } while ($connection->more_results());
        $connection->close();
    }

    public static function installationComplete(): bool
    {
        if (!is_file(CONNECTION_FILE)) {
            return false;
        }
        $connection = null;
        require CONNECTION_FILE;
        /** @var mixed $connection */
        if (!is_array($connection) || empty($connection['database']['hostname']) ||
            strpos($connection['database']['hostname'], '[') !== false) {
            return false;
        }
        mysqli_report(MYSQLI_REPORT_OFF);
        $db = @new mysqli(
            $connection['database']['hostname'],
            $connection['database']['username'],
            $connection['database']['password'],
            $connection['database']['database']
        );
        if ($db->connect_error) {
            return false;
        }
        $result = $db->query('SELECT installed FROM config ORDER BY id LIMIT 1');
        $complete = $result && ($row = $result->fetch_assoc()) && (int)$row['installed'] === 1;
        $db->close();
        return (bool)$complete;
    }

    /**
     * @param mixed $host
     * @param mixed $username
     * @param mixed $password
     * @param mixed $databaseName
     * @param string $schemaPath
     * @param array<mixed> $touchedDatabases
     */
    private static function importDatabase($host, $username, $password, $databaseName, $schemaPath, array &$touchedDatabases): void
    {
        $server = new mysqli($host, $username, $password);
        if ($server->connect_error) {
            throw new RuntimeException('Could not connect to the database server.');
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $databaseName)) {
            $server->close();
            throw new RuntimeException('Invalid database name.');
        }
        $statement = $server->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = ?');
        $statement->bind_param('s', $databaseName);
        $statement->execute();
        $statement->bind_result($existing);
        $statement->fetch();
        $statement->close();
        $existed = (int)$existing > 0;
        if ($existed && self::databaseHasTables($server, $databaseName)) {
            $server->close();
            throw new RuntimeException('Both database names must point to empty databases. Choose fresh database names to avoid overwriting data.');
        }
        // Recorded before any change so a failure rolls back only what this run touched:
        // databases it created are dropped, pre-existing empty ones are emptied again.
        $touchedDatabases[$databaseName] = $existed ? 'emptied' : 'created';
        if (!$existed && !$server->query('CREATE DATABASE `' . $databaseName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')) {
            unset($touchedDatabases[$databaseName]);
            $server->close();
            throw new RuntimeException('Could not create the configured database.');
        }
        $server->close();
        self::importSql($host, $username, $password, $databaseName, $schemaPath);
    }

    /**
     * Creates the dedicated application user with rights on the two game databases only.
     * Returns the generated password.
     *
     * @param mixed $host
     * @param mixed $adminUser
     * @param mixed $adminPassword
     * @param string $appUser
     * @param list<mixed> $databases
     */
    private static function createAppUser($host, $adminUser, $adminPassword, $appUser, array $databases): string
    {
        if (!preg_match('/^[A-Za-z0-9_]{1,32}$/', $appUser)) {
            throw new RuntimeException('Invalid application database user name.');
        }
        $server = new mysqli($host, $adminUser, $adminPassword);
        if ($server->connect_error) {
            throw new RuntimeException('Could not connect to the database server.');
        }
        $statement = $server->prepare("SELECT COUNT(*) FROM mysql.user WHERE User = ? AND Host = '%'");
        $statement->bind_param('s', $appUser);
        $statement->execute();
        $statement->bind_result($exists);
        $statement->fetch();
        $statement->close();
        if ((int)$exists > 0) {
            $server->close();
            throw new RuntimeException('The database user "' . $appUser . '" already exists. Set TRAVIAN_APP_DB_USER to an unused name.');
        }
        $password = bin2hex(random_bytes(16));
        $quotedUser = "'" . $server->real_escape_string($appUser) . "'@'%'";
        if (!$server->query('CREATE USER ' . $quotedUser . " IDENTIFIED BY '" . $server->real_escape_string($password) . "'")) {
            $server->close();
            throw new RuntimeException('Could not create the application database user.');
        }
        foreach ($databases as $databaseName) {
            if (!$server->query(AppDatabaseGrants::statement($databaseName, $quotedUser))) {
                $server->query('DROP USER ' . $quotedUser);
                $server->close();
                throw new RuntimeException('Could not grant the application database user access.');
            }
        }
        $server->close();
        return $password;
    }

    /**
     * Undoes a failed install: removes written config files, drops databases this
     * run created, empties pre-existing empty ones and drops the app user it created.
     * Never touches anything the run did not create.
     *
     * @param mixed $host
     * @param mixed $adminUser
     * @param mixed $adminPassword
     * @param list<mixed> $files
     * @param array<mixed> $touchedDatabases
     * @param string|null $appUser
     */
    private static function rollback($host, $adminUser, $adminPassword, array $files, array $touchedDatabases, $appUser): void
    {
        foreach ($files as $file) {
            @unlink($file);
            @unlink($file . '.tmp');
        }
        $server = @new mysqli($host, $adminUser, $adminPassword);
        if ($server->connect_error) {
            error_log('OpenTravian setup rollback could not connect to the database server.');
            return;
        }
        foreach ($touchedDatabases as $databaseName => $mode) {
            if (!preg_match('/^[A-Za-z0-9_]+$/', $databaseName)) {
                continue;
            }
            if ($mode === 'created') {
                $server->query('DROP DATABASE IF EXISTS `' . $databaseName . '`');
                continue;
            }
            $result = $server->query("SELECT table_name FROM information_schema.tables WHERE table_schema = '" . $server->real_escape_string($databaseName) . "'");
            $server->query('SET FOREIGN_KEY_CHECKS=0');
            while ($result && ($row = $result->fetch_row())) {
                $server->query('DROP TABLE IF EXISTS `' . $databaseName . '`.`' . str_replace('`', '``', $row[0]) . '`');
            }
            $server->query('SET FOREIGN_KEY_CHECKS=1');
        }
        if ($appUser !== null && preg_match('/^[A-Za-z0-9_]{1,32}$/', $appUser)) {
            $server->query("DROP USER IF EXISTS '" . $appUser . "'@'%'");
        }
        $server->close();
    }

    /**
     * @param array<mixed> $values
     */
    private static function runWorldInstaller($values): void
    {
        $startTime = time();
        $worldDb = new mysqli(
            $values['database_host'],
            $values['database_user'],
            $values['database_password'],
            $values['world_database']
        );
        if ($worldDb->connect_error) {
            throw new RuntimeException('Could not connect to the new world database.');
        }
        $mapSize = (int)$values['map_size'];
        $overrides = WorldOverrides::normalize(
            isset($values['overrides']) && is_array($values['overrides']) ? $values['overrides'] : []
        );
        $finishConstruction = (int)($overrides['finishConstruction'] ?? false);
        if ($finishConstruction && !FinishConstruction::isSpeedWorld((int)$values['speed'])) {
            throw new RuntimeException('Palace and Residence gold completion is available only on speed worlds above 1x.');
        }
        $statement = $worldDb->prepare(
            "INSERT INTO config (id, startTime, map_size, worldUniqueId, patchVersion, installed, automationState, installationTime, playableTribes, discordUrl, supportEmail, finishConstruction, message) VALUES (1, ?, ?, 1, ?, 0, 1, ?, ?, ?, ?, ?, '')"
        );
        $playableTribes = Tribes::serialize($values['playable_tribes']);
        $discordUrl = (string)($overrides['discordUrl'] ?? '');
        $patchVersion = WorldSchemaVersion::CURRENT;
        $statement->bind_param('iiiisssi', $startTime, $mapSize, $patchVersion, $startTime, $playableTribes, $discordUrl, $values['support_email'], $finishConstruction);
        $statement->execute();
        if ($statement->errno) {
            $statement->close();
            $worldDb->close();
            throw new RuntimeException('Could not initialize the world configuration.');
        }
        $statement->close();
        $worldDb->close();

        $repositoryRoot = self::repositoryRoot();
        require $repositoryRoot . '/game/include/bootstrap.php';
        self::seedMap($values['multihunter_password']);
        if (!empty($values['create_starter_player'])) {
            $register = new RegisterModel();
            $playerKid = $register->generateBase('rand', 3);
            if (!$playerKid) {
                throw new RuntimeException('Could not find a starting location for the player account.');
            }
            $race = (int)$values['player_race'];
            $playerId = $register->addUser(
                $values['player_name'],
                Password::hash($values['player_password']),
                '',
                $race,
                $playerKid
            );
            if (!$register->createBaseVillage($playerId, $values['player_name'], $race, $playerKid)) {
                throw new RuntimeException('Could not create the player starting village.');
            }
        }
        (new NatarsModel())->seedFarmVillages((int)($values['farm_villages'] ?? 0));
        DB::getInstance()->query(
            'UPDATE odata SET lasttrain=' . (int)$startTime
        );
    }

    /**
     * Generates the map into the empty world tables and creates the system accounts and their villages
     * (Support, Natars with the World Wonder villages, Multihunter). The installer and the round reset
     * both seed a world this way; the caller has loaded the bootstrap and set config.map_size.
     */
    public static function seedMap(string $multihunterPassword): void
    {
        $installer = new InstallerModel();
        $installer->mapToArray();
        $installer->createRestOfTheMap();
        $installer->pushMapToDB();
        $installer->finalize($multihunterPassword);
    }

    public static function repositoryRoot(): string
    {
        return getenv('TRAVIAN_ROOT') ?: dirname(__DIR__, 4);
    }

    /**
     * @param array<mixed> $values
     */
    public static function buildGlobalConfig($values): string
    {
        $config = [
            'staticParameters' => [
                'default_language' => 'en',
                'default_timezone' => 'UTC',
                'default_direction' => 'LTR',
                'default_dateFormat' => 'Y-m-d',
                'default_timeFormat' => 'H:i',
                'indexUrl' => $values['index_url'],
                'forumUrl' => $values['index_url'],
                'helpUrl' => $values['index_url'],
                'adminEmail' => '',
                'session_timeout' => 21600,
                'global_css_class' => 'local',
                // Keep static UI assets same-origin. This remains self-hosted when
                // the world is reached through localhost, a LAN name, or a proxy.
                'gpackBaseUrl' => '/gpack/',
                'recaptcha_public_key' => '',
                'recaptcha_private_key' => '',
            ],
            'cachingServers' => ['memcached' => []],
            'dataSources' => [
                'globalDB' => [
                    'hostname' => $values['database_host'],
                    'username' => $values['database_user'],
                    'password' => $values['database_password'],
                    'database' => $values['account_database'],
                    'charset' => 'utf8mb4',
                ],
            ],
        ];
        return "<?php\n\$globalConfig = " . var_export($config, true) . ";\n";
    }

    /**
     * @param array<mixed> $values
     */
    public static function buildConnection($values): string
    {
        $profile = InstallInput::effectiveProfile($values);
        $connection = [
            'speed' => $values['speed'],
            'movement_speed_increase' => $profile['movement_speed_increase'],
            'storage_multiplier' => $profile['storage_multiplier'],
            'cranny_multiplier' => $profile['cranny_multiplier'],
            'trap_multiplier' => $profile['trap_multiplier'],
            'cage_multiplier' => $profile['cage_multiplier'],
            'protection_time' => $profile['protection_time'],
            'oasisInitialProtectionTime' => $profile['oasisInitialProtectionTime'],
            'dailyQuestInterval' => $profile['dailyQuestInterval'],
            'heroRegenerationMultiplier' => $profile['heroRegenerationMultiplier'],
            'oasisAnimalCap' => $profile['oasisAnimalCap'],
            'oasisRespawnInterval' => $profile['oasisRespawnInterval'],
            'round_length' => $profile['round_length'],
            'startGold' => $values['starting_gold'],
            'worldId' => $values['world_id'],
            'secure_hash_code' => bin2hex(random_bytes(32)),
            'title' => $values['title'],
            'gameWorldUrl' => rtrim($values['world_url'], '/') . '/',
            'serverName' => $values['title'],
            'overrides' => array_diff_key(
                WorldOverrides::normalize(isset($values['overrides']) && is_array($values['overrides']) ? $values['overrides'] : []),
                array_flip(WorldOverrides::INSTALL_ONLY)
            ),
            'database' => [
                'hostname' => $values['database_host'],
                'username' => $values['database_user'],
                'password' => $values['database_password'],
                'database' => $values['world_database'],
                'charset' => 'utf8mb4',
            ],
        ];
        return "<?php\n\$connection = " . var_export($connection, true) . ";\n";
    }

    /**
     * Runs a full install from values produced by InstallInput::fromSubmission().
     * Any failure removes everything this run created and rethrows; existing
     * tables are never overwritten unless $clearExisting was confirmed.
     *
     * @param array<string,mixed> $values
     */
    public static function install(array $values, string $appUser, bool $clearExisting): void
    {
        // One install at a time, whether it comes from the wizard or the CLI.
        $lock = fopen(rtrim(STATE_DIR, '/') . '/install.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Another installation is already running.');
        }
        try {
            self::runInstall($values, $appUser, $clearExisting);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function runInstall(array $values, string $appUser, bool $clearExisting): void
    {
        $touchedDatabases = [];
        $writtenFiles = [];
        $createdAppUser = null;
        $adminValues = $values;
        $databases = [$values['account_database'], $values['world_database']];
        try {
            mysqli_report(MYSQLI_REPORT_OFF);
            if ($clearExisting) {
                self::clearDatabases($values['database_host'], $values['database_user'], $values['database_password'], $databases);
                self::clearApplicationUser($values['database_host'], $values['database_user'], $values['database_password'], $appUser);
            }
            $root = self::repositoryRoot();
            self::importDatabase($values['database_host'], $values['database_user'], $values['database_password'], $values['account_database'], $root . '/database/account.sql', $touchedDatabases);
            self::importDatabase($values['database_host'], $values['database_user'], $values['database_password'], $values['world_database'], $root . '/database/world.sql', $touchedDatabases);

            // The application runs as its own user, limited to these two databases.
            // The administrative credentials are never stored.
            $appPassword = self::createAppUser($values['database_host'], $values['database_user'], $values['database_password'], $appUser, $databases);
            $createdAppUser = $appUser;
            $values['database_user'] = $appUser;
            $values['database_password'] = $appPassword;

            $writtenFiles = [GLOBAL_CONFIG_FILE, CONNECTION_LOCAL_FILE];
            self::writeFile(GLOBAL_CONFIG_FILE, self::buildGlobalConfig($values));
            if (is_array($values['smtp'] ?? null)) {
                $writtenFiles[] = SMTP_CONFIG_FILE;
                if (!SmtpConfig::save($values['smtp'])) {
                    throw new RuntimeException('The server could not write the protected SMTP configuration.');
                }
            } else {
                // No SMTP: drop any file a replaced install left behind.
                @unlink(SMTP_CONFIG_FILE);
            }
            self::writeFile(CONNECTION_LOCAL_FILE, self::buildConnection($values));

            self::registerWorld($values);
            self::runWorldInstaller($values);
            if (file_put_contents(SETUP_LOCK_FILE, gmdate('c') . PHP_EOL, LOCK_EX) === false) {
                throw new RuntimeException('The world installed, but the setup lock could not be written.');
            }
            @chmod(SETUP_LOCK_FILE, 0600);
        } catch (Throwable $exception) {
            error_log('OpenTravian setup failed: ' . $exception->getMessage() . PHP_EOL . $exception->getTraceAsString());
            self::rollback(
                $adminValues['database_host'],
                $adminValues['database_user'],
                $adminValues['database_password'],
                $writtenFiles,
                $touchedDatabases,
                $createdAppUser
            );
            @unlink(SETUP_LOCK_FILE);
            throw new RuntimeException('Setup did not finish.', 0, $exception);
        }
    }

    /**
     * Records the world in the account database's gameServers table.
     *
     * @param array<mixed> $values
     */
    private static function registerWorld(array $values): void
    {
        $globalDb = new mysqli($values['database_host'], $values['database_user'], $values['database_password'], $values['account_database']);
        if ($globalDb->connect_error) {
            throw new RuntimeException('Could not connect to the account database.');
        }
        if (!$globalDb->query('DELETE FROM gameServers')) {
            $globalDb->close();
            throw new RuntimeException('Could not prepare the account database for the new world.');
        }
        $connectionPath = CONNECTION_LOCAL_FILE;
        $worldUrl = rtrim($values['world_url'], '/') . '/';
        $roundLength = InstallInput::effectiveProfile($values)['round_length'];
        $startTime = time();
        $version = 4;
        $statement = $globalDb->prepare(
            'INSERT INTO gameServers (id, worldId, speed, name, version, gameWorldUrl, startTime, roundLength, finished, registerClosed, activation, preregistration_key_only, hidden, promoted, configFileLocation) VALUES (1, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, 0, 1, ?)'
        );
        $statement->bind_param('sisisiis', $values['world_id'], $values['speed'], $values['title'], $version, $worldUrl, $startTime, $roundLength, $connectionPath);
        $statement->execute();
        if ($statement->errno) {
            $statement->close();
            $globalDb->close();
            throw new RuntimeException('Could not add the local world to the account database.');
        }
        $statement->close();
        $globalDb->close();
    }
}
