#!/usr/bin/env php
<?php

declare(strict_types=1);

// The round reset: one list of tables, the schema it reads and writes, the administrator-only confirmed
// panel action, the shared installer seeding, and the removal of the old post-round path.

function expectRoundReset(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__, 2);
$read = static fn (string $path): string => (string) file_get_contents($root . '/' . $path);
$schema = $read('database/world.sql');
$reset = $read('game/include/Game/Round/RoundReset.php');
$controller = $read('game/include/admin/include/Controllers/RoundResetCtrl.php');

// Every world table is classified exactly once: wiped, kept, or an account table.
preg_match_all('/CREATE TABLE (?:IF NOT EXISTS )?`(\w+)`/', $schema, $tables);
$quoted = static function (string $constant) use ($reset): array {
    preg_match('/' . $constant . ' = \[(.*?)\];/s', $reset, $block);
    preg_match_all("/'(\w+)'/", $block[1] ?? '', $names);

    return $names[1];
};
$classified = [...$quoted('WIPED_TABLES'), ...$quoted('KEPT_TABLES')];
preg_match('/ACCOUNT_TABLES = \[(.*?)\];/s', $reset, $accountBlock);
preg_match_all("/'(\w+)' => '\w+'/", $accountBlock[1] ?? '', $accountTables);
$classified = [...$classified, ...$accountTables[1]];
sort($classified);
$all = $tables[1];
sort($all);
expectRoundReset($classified === $all, 'Every world.sql table must be listed once in RoundReset (wiped, kept or account): ' . implode(', ', array_merge(array_diff($all, $classified), array_diff($classified, $all))));
expectRoundReset(!in_array('rounds', $quoted('WIPED_TABLES'), true) && !in_array('round_standings', $quoted('WIPED_TABLES'), true) && !in_array('admin_log', $quoted('WIPED_TABLES'), true), 'The archive and the audit trail must survive a reset.');

// One table list: nothing else truncates the world.
expectRoundReset(!str_contains($read('game/include/Core/Automation.php'), 'TRUNCATE TABLE'), 'Automation must not hold a second list of tables to empty.');

// The archive schema.
expectRoundReset(preg_match('/CREATE TABLE `rounds` \((.*?)\) ENGINE/s', $schema, $rounds) === 1
    && array_filter(['startTime', 'finishTime', 'resultCode', 'winnerUid', 'winnerName', 'winnerAllianceName', 'playerCount', 'summary', 'accountMode', 'archivedAt'], static fn (string $column): bool => !str_contains($rounds[1], '`' . $column . '`')) === [], 'world.sql must define rounds.');
expectRoundReset(preg_match('/CREATE TABLE `round_standings` \((.*?)\) ENGINE/s', $schema, $standings) === 1
    && str_contains($standings[1], "'player', 'alliance', 'medal', 'allimedal'") && str_contains($standings[1], 'KEY `round` (`roundId`, `kind`, `position`)'), 'world.sql must define round_standings by round, kind and position.');

// The reset reuses the installer's map seeding and the archive; it is the only round-end path.
expectRoundReset(str_contains($reset, 'WorldInstaller::seedMap(') && str_contains($reset, 'RoundArchive::write('), 'The reset must call the installer seeding and the archive.');
$installer = $read('game/include/Game/Installer/WorldInstaller.php');
expectRoundReset(substr_count($installer, 'new InstallerModel()') === 1 && str_contains($installer, 'self::seedMap($values[\'multihunter_password\']);'), 'The installer must seed the map through seedMap() only.');
expectRoundReset(str_contains($reset, 'backup_tables(null, [], true)') && str_contains($reset, 'EngineHeartbeat::requestPause()') && str_contains($reset, 'isRestore=1') && str_contains($reset, "lock(self::LOCK"), 'The reset must back up with a protected stamp, pause the engine, hold the world in recovery mode and lock.');
expectRoundReset(str_contains($reset, "if (!self::finished())"), 'Only a finished round may be reset.');
expectRoundReset(str_contains($read('game/include/Core/Database/DB.php'), '$protect[] = $stamp;'), 'A kept backup must exempt itself from retention.');

// The panel action: administrator only, a POST through the CSRF guard, typed world title, audited.
expectRoundReset(!str_contains($controller, 'getAdminUid()') && str_contains($controller, "hash_equals((string) Config::getInstance()->settings->serverName, \$title)") && str_contains($controller, "AuditLog::record('round.archive'") && str_contains($controller, "AuditLog::record('round.reset'") && str_contains($controller, 'WebService::isPost()'), 'The controller must check the typed title and audit the reset; the panel session check is the gate.');
expectRoundReset(!str_contains($read('game/include/admin/include/Core/RequestGuard.php'), "'roundreset'"), 'The reset is a POST, not a state-changing GET.');
expectRoundReset(str_contains($read('game/include/Game/Admin/AdminMenu.php'), "['admin.php?action=roundReset', 'Round reset'"), 'The menu must list the round reset.');

// The old post-round path is gone.
foreach (['postService', 'handleAutoReinstall', 'TaskQueue', 'auto_reinstall', 'postServiceDone'] as $legacy) {
    foreach (['game/include', 'database', 'tools', 'docs'] as $directory) {
        $found = trim((string) shell_exec('grep -rIl ' . escapeshellarg($legacy) . ' ' . escapeshellarg($root . '/' . $directory) . ' 2>/dev/null'));
        expectRoundReset($found === '', "$legacy must be removed, still found in: $found");
    }
}
expectRoundReset(!is_file($root . '/game/include/Model/TaskQueue.php') && !str_contains($read('database/account.sql'), 'taskQueue'), 'The task queue must be removed.');

echo "Round reset contract passed.\n";
