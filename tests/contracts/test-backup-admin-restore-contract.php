<?php

declare(strict_types=1);

// The control panel's restore handles a backup as the pair it is: a safety backup first that
// protects the chosen backup from retention, then the account dump as well as the world dump.

$root = dirname(__DIR__, 2);
$source = (string) file_get_contents($root . '/game/include/admin/include/Controllers/BackupsCtrl.php');

function expectBackupAdminRestore(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

expectBackupAdminRestore(str_contains($source, 'backup_tables(null, [$stamp])'), 'The control panel restore must take a safety backup that protects the chosen backup.');
expectBackupAdminRestore(str_contains($source, 'GlobalDB::getInstance()->restoreFrom($accountFile)'), 'The control panel restore must restore the account dump with the world dump.');
expectBackupAdminRestore(strpos($source, 'backup_tables(null, [$stamp])') < strpos($source, 'EngineHeartbeat::requestPause()'), 'The safety backup must be taken before the engine is paused.');
expectBackupAdminRestore(!str_contains($source, 'Only the world database was restored; restore the account database with tools/backup.php'), 'The control panel must no longer tell the operator to restore the account database by hand.');

// One shared follow-up: the control panel and the command line restore apply the same downtime
// compensation through RestoreAftercare, so the two cannot drift apart.
$tool = (string) file_get_contents($root . '/tools/backup.php');
expectBackupAdminRestore(str_contains($source, 'RestoreAftercare::apply($timestamp)') && str_contains($tool, 'RestoreAftercare::apply($set[\'time\'])'), 'Both restores must call RestoreAftercare::apply().');
expectBackupAdminRestore(!str_contains($source, 'SET delayTime') && !str_contains($source, 'deleteInfoByTypeInServer') && !str_contains($tool, 'SET delayTime'), 'The downtime compensation lives only in RestoreAftercare.');

echo "Backup admin restore contract passed.\n";
