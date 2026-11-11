#!/usr/bin/env php
<?php

declare(strict_types=1);

// Backup and restore of the world and account databases (see "Backups" in the README).
//
//   php tools/backup.php backup
//   php tools/backup.php list [--dir=PATH]
//   php tools/backup.php verify [<backup-<ms>-<random>>] [--dir=PATH]
//   php tools/backup.php restore <backup-<ms>-<random>> --yes [--world-only] [--no-state] [--dir=PATH]
//
// A backup made with TRAVIAN_BACKUP_STATE_PASSPHRASE set also carries an encrypted archive of the
// operator's generated state (SMTP, payment, social and CAPTCHA credentials, two-factor enrolment).
// Restore opens it first, with the same variable, and writes those files back after the databases;
// --no-state restores the databases only.
//
// --dir reads backups from another directory, for example the host export directory
// (TRAVIAN_BACKUP_EXPORT_DIR) when the state volume is gone. Restore replaces the databases
// with the chosen backup, so it refuses to run without --yes or while the engine is alive
// (docker compose stop engine). It takes a safety backup first.

use Core\Database\BackupSets;
use Core\Database\DB;
use Core\Database\GlobalDB;
use Core\Database\RestoreAftercare;
use Core\Database\StateArchive;
use Core\Jobs\EngineHeartbeat;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This tool may only run from the command line.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/game/deployment/include/env.php';
require_once dirname(__DIR__) . '/game/include/bootstrap.php';

$command = $argv[1] ?? '';
$directory = rtrim(BACKUP_PATH, '/');
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--dir=')) {
        $directory = rtrim(substr($argument, strlen('--dir=')), '/');
    }
}

if ($command === 'backup') {
    exit(DB::getInstance()->backup_tables() ? 0 : 1);
}

if ($command === 'list') {
    foreach (BackupSets::find($directory) as $set) {
        printf("%s  %s  %s  %s\n", gmdate('Y-m-d H:i:s', $set['time']), $set['name'], ($set['account'] !== null ? 'world+account' : 'world only') . ($set['state'] !== null ? ' +state' : ''), number_format(intdiv($set['size'], 1024)) . ' KB');
    }
    exit(0);
}

// verify [<name>]: checks that the dump files are intact and complete (one backup, or every backup).
if ($command === 'verify') {
    $name = isset($argv[2]) && !str_starts_with($argv[2], '--') ? $argv[2] : null;
    $candidates = $name === null ? BackupSets::find($directory) : array_filter([BackupSets::resolve($name, $directory)]);
    if ($candidates === []) {
        fwrite(STDERR, $name === null ? "No backups in {$directory}.\n" : "No such backup in {$directory}: {$name}\n");
        exit(1);
    }
    $damaged = 0;
    foreach ($candidates as $set) {
        $problems = BackupSets::problems($set);
        $damaged += $problems === [] ? 0 : 1;
        printf("%s  %s\n", $set['name'], $problems === [] ? 'ok' : 'DAMAGED: ' . implode('; ', $problems));
    }
    exit($damaged === 0 ? 0 : 1);
}

if ($command !== 'restore' || !isset($argv[2])) {
    fwrite(STDERR, "Usage: backup.php backup | list [--dir=PATH] | verify [<backup-name>] [--dir=PATH] | restore <backup-name> --yes [--world-only] [--no-state] [--dir=PATH]\n");
    exit(2);
}
if (!BackupSets::isName($argv[2])) {
    fwrite(STDERR, "Give the backup name from `list`, for example backup-1793987194489-75bfb76d2f68fde5.\n");
    exit(2);
}
if (!in_array('--yes', $argv, true)) {
    fwrite(STDERR, "Restore replaces the current databases. Re-run with --yes to confirm.\n");
    exit(2);
}
// A stopped engine leaves its beat in Redis for an hour: only a fresh beat means it is running.
if (EngineHeartbeat::isAlive(EngineHeartbeat::read(), time())) {
    fwrite(STDERR, "The engine is running. Stop it first: docker compose stop engine\n");
    exit(1);
}
$set = BackupSets::resolve($argv[2], $directory);
if ($set === null) {
    fwrite(STDERR, "No such backup in {$directory}: {$argv[2]}\n");
    exit(1);
}
$world = $set['world'];
$account = $set['account'];
$withAccount = !in_array('--world-only', $argv, true) && $account !== null;
// Open the state archive before anything is replaced, so a wrong passphrase stops the restore early.
$stateFiles = null;
if ($set['state'] !== null && !in_array('--no-state', $argv, true)) {
    $passphrase = StateArchive::passphrase();
    $blob = $passphrase === null ? false : file_get_contents($set['state']);
    $stateFiles = is_string($blob) && $passphrase !== null ? StateArchive::open($blob, $passphrase) : null;
    if ($stateFiles === null) {
        fwrite(STDERR, 'This backup carries an encrypted state archive that cannot be opened. Set ' . StateArchive::ENVIRONMENT_VARIABLE . " to the passphrase it was made with, or pass --no-state to restore the databases only.\n");
        exit(1);
    }
}

fwrite(STDOUT, "Taking a safety backup first...\n");
// The chosen backup is protected from the retention pass the safety backup runs.
if (!DB::getInstance()->backup_tables(null, [substr($argv[2], strlen('backup-'))])) {
    fwrite(STDERR, "The safety backup failed; nothing was restored.\n");
    exit(1);
}
$ok = DB::getInstance()->restoreFrom($world) && (!$withAccount || GlobalDB::getInstance()->restoreFrom($account));
if ($ok) {
    // The same follow-up as the control panel restore: the downtime since the backup, the timed
    // announcements, the cached infoboxes and the schema patches.
    try {
        RestoreAftercare::apply($set['time']);
    } catch (\Throwable $exception) {
        fwrite(STDERR, 'The databases were restored but the follow-up failed: ' . $exception->getMessage() . "\n");
        $ok = false;
    }
}
if ($ok && $stateFiles !== null) {
    try {
        fwrite(STDOUT, 'Restored state files: ' . implode(', ', StateArchive::restore($stateFiles, rtrim(STATE_DIR, '/'))) . "\n");
    } catch (\Throwable $exception) {
        fwrite(STDERR, $exception->getMessage() . "\n");
        $ok = false;
    }
}
fwrite($ok ? STDOUT : STDERR, $ok ? "Restored {$argv[2]}" . ($withAccount ? " (world and account)" : " (world only)") . ". Start the engine again: docker compose start engine\n" : "Restore failed; see the error log. The safety backup is in {$directory}.\n");
exit($ok ? 0 : 1);
