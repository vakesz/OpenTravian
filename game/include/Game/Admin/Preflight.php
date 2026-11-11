<?php

declare(strict_types=1);

namespace Game\Admin;

use Core\Caching\Caching;
use Core\Config;
use Core\Database\BackupSets;
use Core\Database\DB;
use Core\Database\GlobalDB;
use Core\Jobs\EngineHeartbeat;
use Game\Installer\AppDatabaseGrants;
use Game\SmtpConfig;

/**
 * Operator preflight: one list of checks shared by the control panel's
 * "Engine health" page and tools/preflight.php. collect() gathers plain facts
 * (needs the game bootstrap once a world is installed) and evaluate() turns
 * them into results without touching anything, so it can be contract-tested.
 * A result names a setting or a table, never a value, so no secret can leak.
 */
final class Preflight
{
    public const OK = 'ok';
    public const INFO = 'info';
    public const WARN = 'warn';
    public const FAIL = 'fail';

    private const MIN_PHP = '8.2.0';
    private const EXTENSIONS = ['mysqli', 'redis', 'pcntl', 'mbstring', 'json', 'gd', 'openssl'];
    private const CORE_TABLES = ['vdata', 'movement', 'building_upgrade', 'research', 'send', 'config'];
    private const MIN_FREE_BYTES = 536870912;
    private const MIN_FREE_PERCENT = 5;
    /** A scheduled backup older than this means the engine's backups have stopped. */
    private const STALE_BACKUP_SECONDS = 172800;
    /** A new backup may take this long to reach the export directory. */
    private const EXPORT_GRACE_SECONDS = 300;

    private function __construct()
    {
    }

    /**
     * @return array<string, mixed>
     */
    public static function collect(): array
    {
        $state = rtrim((string) STATE_DIR, '/');
        $facts = [
            'phpVersion' => PHP_VERSION,
            'missingExtensions' => array_values(array_filter(self::EXTENSIONS, static fn (string $name): bool => !extension_loaded($name))),
            'stateWritable' => is_dir($state) && is_writable($state),
            'looseSecrets' => self::looseSecrets($state),
            'installed' => is_file(SETUP_LOCK_FILE),
            'diskFree' => @disk_free_space($state) ?: null,
            'diskTotal' => @disk_total_space($state) ?: null,
            'twoFactor' => TwoFactorStore::fromEnvironment()->mode(),
        ];
        if (!$facts['installed']) {
            return $facts;
        }

        $facts['smtpConfigured'] = SmtpConfig::isConfigured();
        $facts['heartbeat'] = EngineHeartbeat::status()['state'];
        $facts['speed'] = (int) Config::getInstance()->game->speed;
        $facts['backupsEnabled'] = DB::scheduledBackupsEnabled();
        $facts['exportDir'] = rtrim((string) getenv('TRAVIAN_BACKUP_EXPORT_DIR'), '/');
        $facts['exportProblem'] = self::exportProblem($facts['exportDir']);
        try {
            $db = DB::getInstance();
            $facts['dbOk'] = $db->ping();
            $facts['lastBackup'] = (int) $db->fetchScalar('SELECT lastBackup FROM config');
            $account = GlobalDB::getInstance();
            $facts['dbUser'] = (string) $db->fetchScalar('SELECT CURRENT_USER()');
            $facts['excessPrivileges'] = AppDatabaseGrants::excess(
                array_merge(self::grants($db), self::grants($account)),
                [(string) $db->fetchScalar('SELECT DATABASE()'), (string) $account->fetchScalar('SELECT DATABASE()')]
            );
            $variables = [];
            $result = $db->query("SHOW GLOBAL VARIABLES WHERE Variable_name IN ('slow_query_log', 'long_query_time')");
            while ($result && ($row = $result->fetch_row())) {
                $variables[$row[0]] = $row[1];
            }
            $facts['slowLog'] = strtoupper((string) ($variables['slow_query_log'] ?? 'OFF')) === 'ON';
            $facts['slowLogSeconds'] = (float) ($variables['long_query_time'] ?? 1);
            $status = $db->query("SHOW GLOBAL STATUS LIKE 'Slow_queries'");
            $facts['slowQueries'] = $status && ($row = $status->fetch_row()) ? (int) $row[1] : 0;
            $present = [];
            $result = $db->query('SHOW TABLES');
            while ($result && ($row = $result->fetch_row())) {
                $present[$row[0]] = true;
            }
            $facts['missingTables'] = array_values(array_filter(self::CORE_TABLES, static fn (string $table): bool => !isset($present[$table])));
        } catch (\Throwable $exception) {
            $facts['dbOk'] = false;
        }
        try {
            $facts['redisOk'] = (bool) Caching::singleton()->ping();
        } catch (\Throwable $exception) {
            $facts['redisOk'] = false;
        }

        return $facts;
    }

    /**
     * @param array<string, mixed> $facts
     * @return list<array{id: string, level: string, message: string}>
     */
    public static function evaluate(array $facts): array
    {
        $results = [];
        $add = static function (string $id, string $level, string $message) use (&$results): void {
            $results[] = ['id' => $id, 'level' => $level, 'message' => $message];
        };

        $php = (string) ($facts['phpVersion'] ?? '');
        $add('php', version_compare($php, self::MIN_PHP, '>=') ? self::OK : self::FAIL, 'PHP ' . $php . (version_compare($php, self::MIN_PHP, '>=') ? '' : ' is older than ' . self::MIN_PHP));
        $missing = (array) ($facts['missingExtensions'] ?? []);
        $add('extensions', $missing === [] ? self::OK : self::FAIL, $missing === [] ? 'Required PHP extensions are loaded' : 'Missing PHP extensions: ' . implode(', ', $missing));
        $add('state', !empty($facts['stateWritable']) ? self::OK : self::FAIL, !empty($facts['stateWritable']) ? 'The state directory is writable' : 'The state directory is missing or not writable');
        $loose = (array) ($facts['looseSecrets'] ?? []);
        $add('secrets', $loose === [] ? self::OK : self::WARN, $loose === [] ? 'Local state files are private to the service user' : 'State files readable by other users: ' . implode(', ', $loose));

        $free = $facts['diskFree'] ?? null;
        $total = $facts['diskTotal'] ?? null;
        if (is_int($free) || is_float($free)) {
            $percent = $total ? (int) round($free / $total * 100) : 100;
            $low = $free < self::MIN_FREE_BYTES || $percent < self::MIN_FREE_PERCENT;
            $add('disk', $low ? self::WARN : self::OK, ($low ? 'Low free space on the state volume: ' : 'Free space on the state volume: ') . self::megabytes((int) $free) . ' (' . $percent . '%)');
        }

        $add('twoFactor', self::INFO, 'Administrator two-factor sign-in is ' . (string) ($facts['twoFactor'] ?? 'off'));

        if (empty($facts['installed'])) {
            $add('installed', self::INFO, 'No world is installed yet: finish the setup wizard, then run this check again for the database, Redis, engine and mail checks');

            return $results;
        }

        $dbOk = !empty($facts['dbOk']);
        $add('database', $dbOk ? self::OK : self::FAIL, $dbOk ? 'The database answers' : 'The database does not answer');
        $tables = (array) ($facts['missingTables'] ?? []);
        if ($dbOk) {
            $add('schema', $tables === [] ? self::OK : self::FAIL, $tables === [] ? 'The core world tables exist' : 'Missing world tables: ' . implode(', ', $tables));
        }
        if ($dbOk) {
            $excessPrivileges = (array) ($facts['excessPrivileges'] ?? []);
            $root = str_starts_with(strtolower((string) ($facts['dbUser'] ?? '')), 'root@');
            $add('dbPrivileges', $excessPrivileges === [] && !$root ? self::OK : self::WARN, match (true) {
                $root => 'The application connects to the database as root: run the setup again to move it to a dedicated user',
                $excessPrivileges !== [] => 'The application database user has more access than the game needs: ' . implode(', ', array_slice($excessPrivileges, 0, 5)) . (count($excessPrivileges) > 5 ? ', ...' : ''),
                default => 'The application database user is limited to the game databases',
            });
        }
        $add('redis', !empty($facts['redisOk']) ? self::OK : self::FAIL, !empty($facts['redisOk']) ? 'Redis answers' : 'Redis does not answer');

        $beat = (string) ($facts['heartbeat'] ?? 'not_running');
        $add('engine', $beat === 'running' ? self::OK : self::FAIL, match ($beat) {
            'running' => 'The engine heartbeat is fresh',
            'stalled' => 'The engine heartbeat is stale: the engine is stalled',
            default => 'No engine heartbeat: the engine is not running',
        });
        $add('smtp', !empty($facts['smtpConfigured']) ? self::OK : self::INFO, !empty($facts['smtpConfigured']) ? 'SMTP is configured' : 'SMTP is not configured: mail stays queued and the Support form reports delivery is not configured');

        $add('slowLog', self::INFO, !empty($facts['slowLog'])
            ? 'Slow-query log is on: queries over ' . rtrim(rtrim(number_format((float) ($facts['slowLogSeconds'] ?? 1), 3, '.', ''), '0'), '.') . ' s are logged (' . (int) ($facts['slowQueries'] ?? 0) . ' slow queries since the database started)'
            : 'Slow-query log is off (TRAVIAN_MARIADB_SLOW_LOG=1 turns it on)');
        if (empty($facts['backupsEnabled'])) {
            $add('backups', self::INFO, 'Scheduled backups are off (TRAVIAN_BACKUP_ENABLED=0)');
        } elseif ((int) ($facts['lastBackup'] ?? 0) <= 0) {
            $add('backups', self::INFO, 'No backup has run yet');
        } else {
            $age = max(0, (int) ($facts['now'] ?? time()) - (int) $facts['lastBackup']);
            $stale = $age > self::STALE_BACKUP_SECONDS;
            $add('backups', $stale ? self::WARN : self::OK, ($stale ? 'The last backup is old: ' : 'Last backup: ') . gmdate('Y-m-d H:i', (int) $facts['lastBackup']) . ' UTC (' . self::age($age) . ' ago)');
        }
        if (($facts['exportDir'] ?? '') !== '') {
            $problem = $facts['exportProblem'] ?? null;
            $add('export', $problem === null ? self::OK : self::WARN, $problem === null ? 'Backups are copied to the export directory' : 'The backup export directory ' . $problem . ' (TRAVIAN_BACKUP_EXPORT_DIR)');
        }

        $speed = (int) ($facts['speed'] ?? 0);
        $add('speed', $speed >= 1 && $speed <= \Game\Installer\InstallInput::MAX_SPEED ? self::OK : self::FAIL, $speed >= 1 && $speed <= \Game\Installer\InstallInput::MAX_SPEED ? 'World speed ' . $speed . 'x is within the documented range' : 'World speed ' . $speed . 'x is outside 1x to 1,000,000,000x');

        return $results;
    }

    /**
     * @param list<array{id: string, level: string, message: string}> $results
     */
    public static function failed(array $results): bool
    {
        foreach ($results as $result) {
            if ($result['level'] === self::FAIL) {
                return true;
            }
        }

        return false;
    }

    /**
     * Why the export directory is not receiving backups, or null when it is. The newest backup in the
     * state volume must also be in the export once it is a few minutes old.
     */
    private static function exportProblem(string $export): ?string
    {
        if ($export === '' || !is_dir($export)) {
            return 'is missing';
        }
        if (!is_writable($export)) {
            return 'is not writable';
        }
        $newest = BackupSets::find(BACKUP_PATH)[0] ?? null;
        if ($newest === null || time() - $newest['time'] < self::EXPORT_GRACE_SECONDS) {
            return null;
        }

        return BackupSets::resolve($newest['name'], $export) === null ? 'is behind: the newest backup was not copied' : null;
    }

    /**
     * @return list<string> the lines of SHOW GRANTS for the connected user
     */
    private static function grants(DB $db): array
    {
        $lines = [];
        $result = $db->query('SHOW GRANTS');
        while ($result && ($row = $result->fetch_row())) {
            $lines[] = (string) $row[0];
        }

        return $lines;
    }

    private static function age(int $seconds): string
    {
        return match (true) {
            $seconds < 3600 => intdiv($seconds, 60) . ' min',
            $seconds < 172800 => intdiv($seconds, 3600) . ' h',
            default => intdiv($seconds, 86400) . ' days',
        };
    }

    /**
     * Generated state files (anything named *.local.*) that group or others can read.
     *
     * @return list<string>
     */
    private static function looseSecrets(string $state): array
    {
        $loose = [];
        foreach (glob($state . '/*.local.*') ?: [] as $file) {
            $mode = @fileperms($file);
            if (is_file($file) && $mode !== false && ($mode & 0077) !== 0) {
                $loose[] = basename($file);
            }
        }

        return $loose;
    }

    private static function megabytes(int $bytes): string
    {
        return number_format(intdiv($bytes, 1048576)) . ' MB';
    }
}
