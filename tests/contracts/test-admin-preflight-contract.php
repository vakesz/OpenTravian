<?php

declare(strict_types=1);

use Game\Admin\Preflight;

$root = dirname(__DIR__, 2);

function failAdminPreflightContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function expectAdminPreflight(bool $condition, string $message): void
{
    if (!$condition) {
        failAdminPreflightContract($message);
    }
}

require_once $root . '/game/include/Game/Installer/InstallInput.php';
require_once $root . '/game/include/Game/Admin/Preflight.php';

/** @param list<array{id: string, level: string, message: string}> $results */
function preflightLevels(array $results): array
{
    return array_column($results, 'level', 'id');
}

$healthy = [
    'phpVersion' => '8.5.0', 'missingExtensions' => [], 'stateWritable' => true, 'looseSecrets' => [],
    'diskFree' => 10 * 1073741824, 'diskTotal' => 20 * 1073741824, 'twoFactor' => 'off', 'installed' => true,
    'dbOk' => true, 'missingTables' => [], 'redisOk' => true, 'heartbeat' => 'running', 'smtpConfigured' => true, 'speed' => 10,
    'backupsEnabled' => true, 'lastBackup' => 1000000 - 3600, 'now' => 1000000, 'exportDir' => '', 'exportProblem' => null,
];
$results = Preflight::evaluate($healthy);
expectAdminPreflight(!Preflight::failed($results), 'A healthy world must not fail.');
$levels = preflightLevels($results);
expectAdminPreflight($levels['twoFactor'] === Preflight::INFO, 'Two-factor "off" is the documented default and must be an info line, never a warning.');
expectAdminPreflight(!in_array(Preflight::WARN, $levels, true), 'A healthy world must not warn.');

$broken = Preflight::evaluate([
    'missingExtensions' => ['redis'], 'stateWritable' => false, 'looseSecrets' => ['payments.local.php'], 'diskFree' => 100, 'diskTotal' => 1000,
    'installed' => true, 'dbOk' => true, 'missingTables' => ['vdata'], 'redisOk' => false, 'heartbeat' => 'stalled', 'speed' => 0,
] + $healthy);
$levels = preflightLevels($broken);
foreach (['extensions', 'state', 'schema', 'redis', 'engine', 'speed'] as $id) {
    expectAdminPreflight($levels[$id] === Preflight::FAIL, 'Check ' . $id . ' must fail.');
}
expectAdminPreflight($levels['secrets'] === Preflight::WARN && $levels['disk'] === Preflight::WARN, 'Loose secrets and low disk space warn.');
expectAdminPreflight(Preflight::failed($broken), 'Any failed check fails the preflight.');

$privileges = static fn (array $facts): array => array_column(Preflight::evaluate($facts + $healthy), null, 'id')['dbPrivileges'];
expectAdminPreflight($privileges(['excessPrivileges' => [], 'dbUser' => 'travian@%'])['level'] === Preflight::OK, 'A limited application user is fine.');
$excessUser = $privileges(['excessPrivileges' => ['FILE on *.*'], 'dbUser' => 'travian@%']);
expectAdminPreflight($excessUser['level'] === Preflight::WARN && str_contains($excessUser['message'], 'FILE on *.*'), 'Extra privileges warn and are named.');
$rootUser = $privileges(['excessPrivileges' => ['ALL PRIVILEGES on *.*'], 'dbUser' => 'root@%']);
expectAdminPreflight($rootUser['level'] === Preflight::WARN && str_contains($rootUser['message'], 'as root'), 'Connecting as root warns.');
expectAdminPreflight(!isset(array_column(Preflight::evaluate(['dbOk' => false] + $healthy), null, 'id')['dbPrivileges']), 'No privilege check without a database.');
$slow = static fn (array $facts): array => Preflight::evaluate($facts + $healthy);
$slowOff = array_values(array_filter($slow(['slowLog' => false]), static fn (array $r): bool => $r['id'] === 'slowLog'))[0];
$slowOn = array_values(array_filter($slow(['slowLog' => true, 'slowLogSeconds' => 0.5, 'slowQueries' => 7]), static fn (array $r): bool => $r['id'] === 'slowLog'))[0];
expectAdminPreflight($slowOff['level'] === Preflight::INFO && str_contains($slowOff['message'], 'off'), 'A switched-off slow-query log is an info line.');
expectAdminPreflight($slowOn['level'] === Preflight::INFO && str_contains($slowOn['message'], 'over 0.5 s') && str_contains($slowOn['message'], '7 slow queries'), 'An active slow-query log reports its threshold and count: ' . $slowOn['message']);
$backups = static fn (array $facts): array => preflightLevels(Preflight::evaluate($facts + $healthy));
expectAdminPreflight($backups([])['backups'] === Preflight::OK && !isset($backups([])['export']), 'A recent backup is fine and an unset export directory is not checked.');
expectAdminPreflight($backups(['lastBackup' => 1000000 - 3 * 86400])['backups'] === Preflight::WARN, 'A backup older than two days warns.');
expectAdminPreflight($backups(['lastBackup' => 0])['backups'] === Preflight::INFO, 'No backup yet is an info line.');
expectAdminPreflight($backups(['backupsEnabled' => false, 'lastBackup' => 1])['backups'] === Preflight::INFO, 'Switched-off backups are an info line, never stale.');
expectAdminPreflight($backups(['exportDir' => '/exports'])['export'] === Preflight::OK, 'A working export directory is ok.');
expectAdminPreflight($backups(['exportDir' => '/exports', 'exportProblem' => 'is missing'])['export'] === Preflight::WARN, 'A missing or behind export directory warns.');
$noSmtp = preflightLevels(Preflight::evaluate(['smtpConfigured' => false] + $healthy));
expectAdminPreflight($noSmtp['smtp'] === Preflight::INFO, 'Omitting SMTP is a supported install and must be an info line.');

$database = preflightLevels(Preflight::evaluate(['dbOk' => false] + $healthy));
expectAdminPreflight($database['database'] === Preflight::FAIL && !isset($database['schema']), 'A dead database fails and skips the table check.');

$fresh = Preflight::evaluate(['installed' => false] + $healthy);
expectAdminPreflight(!Preflight::failed($fresh) && !isset(preflightLevels($fresh)['database']) && preflightLevels($fresh)['installed'] === Preflight::INFO, 'Before installation only the environment is checked, with an info line, and nothing fails.');

// Wiring: one read-only page in the System menu, and the tool shares the checker.
$menu = (string) file_get_contents($root . '/game/include/Game/Admin/AdminMenu.php');
expectAdminPreflight(str_contains($menu, "['admin.php?action=engineHealth', 'Engine health'"), 'The Operations menu must link the Engine health page.');
expectAdminPreflight(!str_contains((string) file_get_contents($root . '/game/include/admin/include/Core/RequestGuard.php'), 'engineHealth'), 'The Engine health page is read-only and must not be a mutating GET.');
$page = (string) file_get_contents($root . '/game/include/admin/include/Controllers/EngineHealthCtrl.php');
expectAdminPreflight(str_contains($page, 'Preflight::evaluate(Preflight::collect())') && !preg_match('/\b(INSERT|UPDATE|DELETE|TRUNCATE)\b/', $page), 'The page runs the shared preflight and issues no write.');
expectAdminPreflight(str_contains($page, 'TransactionalTask::requeue(') && !str_contains($page, 'INSERT INTO') && str_contains($page, "REQUEST_METHOD") && str_contains($page, 'Session::getCheckerInput()'), 'Requeue must reuse TransactionalTask::requeue, run on POST only and carry the CSRF token.');
$heartbeat = (string) file_get_contents($root . '/game/include/Core/Jobs/EngineHeartbeat.php');
expectAdminPreflight(str_contains($heartbeat, 'function recordRound(') && str_contains($heartbeat, 'function lastRound('), 'The last automation round must be recorded and readable.');
expectAdminPreflight(str_contains((string) file_get_contents($root . '/game/include/Core/Jobs/Job.php'), 'EngineHeartbeat::recordRound($name, $startedAt)'), 'Workers must record each finished job run.');
$tool = (string) file_get_contents($root . '/tools/preflight.php');
expectAdminPreflight(str_contains($tool, 'Preflight::evaluate(Game\\Admin\\Preflight::collect())') && str_contains($tool, 'Preflight::failed'), 'The CLI tool must use the shared checker and exit 1 on a failure.');
expectAdminPreflight(str_contains((string) file_get_contents($root . '/docs/install.md'), 'tools/preflight.php'), 'docs/install.md must list the preflight after docker compose up.');

echo "Admin preflight contract passed.\n";
