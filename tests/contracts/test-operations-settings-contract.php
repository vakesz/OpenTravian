<?php

declare(strict_types=1);

use Game\Admin\WorldRulesFile;
use Game\OperationsSettings;
use Game\WorldOverrides;

// Operations settings fall back to the environment, validate input and persist atomically;
// the world rules file keeps the database login and the install-only keys when it is rewritten.

$root = dirname(__DIR__, 2);
require_once $root . '/game/include/Game/OperationsSettings.php';
require_once $root . '/game/include/Game/WorldOverrides.php';
require_once $root . '/game/include/Game/Admin/WorldRulesFile.php';

function expectOperations(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$directory = sys_get_temp_dir() . '/ops-' . bin2hex(random_bytes(4));
mkdir($directory);
$path = $directory . '/operations.local.json';

putenv('TRAVIAN_BACKUP_KEEP_LAST=5');
putenv('TRAVIAN_CATCHUP_RATE_PER_SECOND=0');
putenv('TRAVIAN_BACKUP_ENABLED');
$defaults = OperationsSettings::all($path);
expectOperations($defaults['backupKeepLast'] === 5 && $defaults['backupKeepDaily'] === 7 && $defaults['backupsEnabled'] === 1, 'Unsaved values come from the environment, then the default.');
expectOperations($defaults['catchupRate'] === 50 && $defaults['auditRetentionDays'] === 365, 'An out-of-range environment value and the audit retention fall back to the default.');
putenv('TRAVIAN_BACKUP_ENABLED=0');
expectOperations(OperationsSettings::all($path)['backupsEnabled'] === 0, 'TRAVIAN_BACKUP_ENABLED=0 still switches backups off.');

$parsed = OperationsSettings::fromSubmission(['backupsEnabled' => '1', 'backupKeepLast' => '0', 'backupKeepDaily' => 'x', 'catchupAfter' => '300', 'catchupRate' => '50', 'catchupMax' => '600', 'auditRetentionDays' => '0']);
expectOperations(count($parsed['errors']) === 2, 'Both invalid numbers are reported.');
$parsed = OperationsSettings::fromSubmission(['backupKeepLast' => '4', 'backupKeepDaily' => '0', 'catchupAfter' => '120', 'catchupRate' => '10', 'catchupMax' => '60', 'auditRetentionDays' => '0']);
expectOperations($parsed['errors'] === [] && $parsed['values']['backupsEnabled'] === 0 && $parsed['values']['auditRetentionDays'] === 0, 'An unchecked box is off and 0 days keeps the audit log forever.');
expectOperations(OperationsSettings::save($parsed['values'], $path) && (fileperms($path) & 0777) === 0600, 'The settings file is written with mode 0600.');
$saved = OperationsSettings::all($path);
expectOperations($saved['backupKeepLast'] === 4 && $saved['catchupMax'] === 60 && $saved['backupsEnabled'] === 0, 'Saved values win over the environment.');
file_put_contents($path, '{"backupKeepLast":"9","catchupRate":99999999}');
expectOperations(OperationsSettings::all($path)['backupKeepLast'] === 5, 'A tampered or out-of-range file entry is ignored.');

$connection = $directory . '/connection.local.php';
file_put_contents($connection, "<?php\n\$connection = " . var_export(['database' => ['password' => 'secret'], 'overrides' => ['discordUrl' => 'https://discord.example/x', 'vacationDays' => 3]], true) . ";\n");
chmod($connection, 0600);
expectOperations(WorldRulesFile::read($connection) == ['discordUrl' => 'https://discord.example/x', 'vacationDays' => 3], 'The stored overrides are read back.');
expectOperations(WorldRulesFile::write($connection, ['vacationDays' => 9, 'discordUrl' => 'https://evil.example', 'unknown' => 1, 'starvation' => false]), 'The rules are written.');
$after = WorldRulesFile::read($connection);
expectOperations($after['vacationDays'] === 9 && $after['starvation'] === false && !isset($after['unknown']), 'Editable rules change and unknown keys are dropped.');
expectOperations($after['discordUrl'] === 'https://discord.example/x', 'Install-only keys are kept as they were.');
expectOperations(str_starts_with((string) file_get_contents($connection), "<?php\n\$connection = ") && ($after = WorldRulesFile::read($connection)) !== [], 'The file stays a plain include.');
expectOperations((fileperms($connection) & 0777) === 0600, 'The connection file keeps its permissions.');
expectOperations(!in_array('discordUrl', WorldOverrides::editable(), true) && in_array('vacationDays', WorldOverrides::editable(), true), 'Install-only keys are not editable.');

array_map('unlink', glob($directory . '/*'));
rmdir($directory);
echo "Operations settings contract passed.\n";
