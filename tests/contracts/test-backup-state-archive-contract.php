<?php

declare(strict_types=1);

use Core\Database\StateArchive;

$root = dirname(__DIR__, 2);

function expectBackupStateArchive(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

require_once $root . '/game/include/Core/Database/StateArchive.php';

$directory = sys_get_temp_dir() . '/backup-state-' . getmypid();
mkdir($directory);
file_put_contents($directory . '/smtp.local.php', 'SECRET-SMTP-PASSWORD');
file_put_contents($directory . '/admin-2fa.local.json', '{"secret":"SECRET-TOTP"}');
file_put_contents($directory . '/connection.local.php', 'DATABASE-LOGIN');
file_put_contents($directory . '/setup.lock', 'lock');
file_put_contents($directory . '/error_log.log', 'log');

try {
    $names = array_map('basename', StateArchive::files($directory));
    expectBackupStateArchive($names === ['admin-2fa.local.json', 'smtp.local.php'], 'The archive carries the *.local.* files except the database login: ' . implode(', ', $names));

    expectBackupStateArchive(StateArchive::write($directory . '/out.enc', 'correct horse battery', $directory), 'The archive must be written.');
    expectBackupStateArchive((fileperms($directory . '/out.enc') & 0777) === 0600, 'The archive must be private (0600).');
    expectBackupStateArchive(glob($directory . '/*.partial') === [], 'No temporary file may remain.');
    $blob = (string) file_get_contents($directory . '/out.enc');
    expectBackupStateArchive(!str_contains($blob, 'SECRET-SMTP-PASSWORD') && !str_contains($blob, 'SECRET-TOTP') && !str_contains($blob, 'smtp.local'), 'The archive must not contain plaintext.');
    expectBackupStateArchive(str_starts_with($blob, 'TSA1') && StateArchive::hasHeader($blob), 'The archive must carry its header.');

    $files = StateArchive::open($blob, 'correct horse battery');
    expectBackupStateArchive($files !== null && array_keys($files) === ['admin-2fa.local.json', 'smtp.local.php'] && $files['smtp.local.php']['data'] === 'SECRET-SMTP-PASSWORD', 'The right passphrase must return the files.');
    expectBackupStateArchive(StateArchive::open($blob, 'wrong passphrase') === null, 'A wrong passphrase must be rejected.');
    $tampered = $blob;
    $tampered[strlen($tampered) - 5] = chr(ord($tampered[strlen($tampered) - 5]) ^ 1);
    expectBackupStateArchive(StateArchive::open($tampered, 'correct horse battery') === null, 'A changed archive must be rejected.');
    expectBackupStateArchive(StateArchive::open('TSA1' . str_repeat("\0", 100), 'x') === null && StateArchive::open('short', 'x') === null, 'A file that is not an archive must be rejected.');
    $expensive = substr($blob, 0, 4) . pack('NN', 1, 4000000000) . substr($blob, 12);
    expectBackupStateArchive(StateArchive::open($expensive, 'correct horse battery') === null, 'Cost parameters beyond the bounds must be rejected without running the key derivation.');

    expectBackupStateArchive(StateArchive::problem($directory . '/out.enc', null) === null, 'Without a passphrase only the header is checked.');
    expectBackupStateArchive(StateArchive::problem($directory . '/out.enc', 'wrong') === 'the state archive cannot be opened with the configured passphrase', 'verify must report a passphrase that does not open the archive.');
    expectBackupStateArchive(StateArchive::problem($directory . '/missing.enc', null) === 'the state archive is missing or damaged', 'A missing archive must be reported.');

    // A hostile archive cannot name a path or the database login.
    foreach (['../escape.local.php', 'connection.local.php', 'dir/x.local.php', 'plain.txt'] as $name) {
        $evil = StateArchive::seal([$name => ['mode' => 0600, 'data' => base64_encode('x')]], 'k');
        expectBackupStateArchive(StateArchive::open($evil, 'k') === null, 'The name ' . $name . ' must be rejected.');
    }

    $target = $directory . '/restored';
    mkdir($target);
    $written = StateArchive::restore($files, $target);
    expectBackupStateArchive($written === ['admin-2fa.local.json', 'smtp.local.php'] && file_get_contents($target . '/smtp.local.php') === 'SECRET-SMTP-PASSWORD', 'Restore must write the files back.');
    expectBackupStateArchive((fileperms($target . '/smtp.local.php') & 0777) === 0600 && glob($target . '/*.restore') === [], 'Restored files must be private, with no temporary file left.');
} finally {
    foreach (array_merge(glob($directory . '/*') ?: [], glob($directory . '/restored/*') ?: []) as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
    @rmdir($directory . '/restored');
    @rmdir($directory);
}

putenv('TRAVIAN_BACKUP_STATE_PASSPHRASE');
expectBackupStateArchive(StateArchive::passphrase() === null, 'The archive is off without a passphrase.');
putenv('TRAVIAN_BACKUP_STATE_PASSPHRASE=');
expectBackupStateArchive(StateArchive::passphrase() === null, 'An empty passphrase is off.');
putenv('TRAVIAN_BACKUP_STATE_PASSPHRASE=secret');
expectBackupStateArchive(StateArchive::passphrase() === 'secret', 'The passphrase comes from the environment.');
putenv('TRAVIAN_BACKUP_STATE_PASSPHRASE');

// Wiring: the passphrase reaches the services that make and restore backups, the databases are not
// touched before the archive opens, and retention treats the archive as part of its backup.
$compose = (string) file_get_contents($root . '/compose.yaml');
expectBackupStateArchive(str_contains($compose, 'TRAVIAN_BACKUP_STATE_PASSPHRASE: ${TRAVIAN_BACKUP_STATE_PASSPHRASE:-}'), 'compose.yaml must pass the passphrase through the shared application environment.');
$tool = (string) file_get_contents($root . '/tools/backup.php');
expectBackupStateArchive(strpos($tool, 'StateArchive::open(') < strpos($tool, 'restoreFrom($world)'), 'tools/backup.php must open the state archive before restoring any database.');
$database = (string) file_get_contents($root . '/game/include/Core/Database/DB.php');
expectBackupStateArchive(str_contains($database, '-account|-state') && str_contains($database, 'StateArchive::write('), 'Retention and backup_tables() must handle the state archive.');
expectBackupStateArchive(!preg_match('/logError\([^;]*[Pp]assphrase\)/', (string) file_get_contents($root . '/game/include/Core/Database/StateArchive.php')), 'The passphrase must never reach a log call.');

echo "Backup state archive contract passed.\n";
