<?php

declare(strict_types=1);

use Game\Admin\Preflight;
use Game\Admin\SetupChecklist;

$root = dirname(__DIR__, 2);
require_once $root . '/game/include/Game/Installer/InstallInput.php';
require_once $root . '/game/include/Game/Admin/Preflight.php';
require_once $root . '/game/include/Game/Admin/SetupChecklist.php';

function expectSetupChecklist(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$healthy = [
    'phpVersion' => '8.5.0', 'missingExtensions' => [], 'stateWritable' => true, 'looseSecrets' => [], 'diskFree' => 10 * 1073741824, 'diskTotal' => 20 * 1073741824,
    'twoFactor' => 'off', 'installed' => true, 'dbOk' => true, 'missingTables' => [], 'redisOk' => true, 'heartbeat' => 'running', 'smtpConfigured' => false, 'speed' => 10,
    'backupsEnabled' => true, 'lastBackup' => 1000000 - 3600, 'now' => 1000000, 'exportDir' => '', 'exportProblem' => null, 'slowLog' => false, 'slowLogSeconds' => 1.0,
    'slowQueries' => 0, 'excessPrivileges' => [], 'dbUser' => 'travian@%',
];
$results = Preflight::evaluate(['heartbeat' => 'stalled', 'looseSecrets' => ['x.local.php']] + $healthy);
$items = SetupChecklist::items($results);
expectSetupChecklist(count($items) === count($results), 'Every check appears on the checklist.');
$levels = array_column($items, 'level');
$rank = ['fail' => 0, 'warn' => 1, 'info' => 2, 'ok' => 3];
$sorted = $levels;
usort($sorted, static fn (string $a, string $b): int => $rank[$a] <=> $rank[$b]);
expectSetupChecklist($levels === $sorted, 'Failures come first, then warnings, notes and the checks that are fine: ' . implode(',', $levels));
$byId = array_column($items, null, 'id');
expectSetupChecklist($byId['engine']['href'] === 'admin.php?action=engineHealth' && $byId['smtp']['href'] === 'admin.php?action=configurationDetails' && $byId['twoFactor']['href'] === 'admin.php?action=twoFactor' && $byId['backups']['href'] === 'admin.php?action=backups', 'The checks that matter to an operator link to the page that handles them.');
expectSetupChecklist($byId['php']['href'] === null, 'A check with no page has no link.');
expectSetupChecklist(SetupChecklist::needsAttention('x', 'fail') && SetupChecklist::needsAttention('x', 'warn') && SetupChecklist::needsAttention('smtp', 'info') && SetupChecklist::needsAttention('twoFactor', 'info'), 'Failures, warnings and the email and two-factor notes need attention.');
expectSetupChecklist(!SetupChecklist::needsAttention('slowLog', 'info') && !SetupChecklist::needsAttention('php', 'ok'), 'Other notes and ok lines do not.');

$source = (string) file_get_contents($root . '/game/include/admin/include/Controllers/MainCtrl.php');
expectSetupChecklist(str_contains($source, 'Preflight::evaluate(Preflight::collect())') && str_contains($source, 'AdminUi::advanced('), 'The home page builds the checklist from the shared preflight and shows the fine checks in Advanced only.');

echo "Admin setup checklist contract passed.\n";
