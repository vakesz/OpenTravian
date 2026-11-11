#!/usr/bin/env php
<?php

declare(strict_types=1);

// Rotates the password of the application database user (TRAVIAN_APP_DB_USER, default "travian").
//
//   docker compose exec -u www-data -e TRAVIAN_DB_ADMIN_PASSWORD="$MARIADB_ROOT_PASSWORD" \
//       php php tools/rotate-db-password.php --yes
//   docker compose restart engine
//
// It needs the database administrator, which the running services do not hold: pass the password
// in TRAVIAN_DB_ADMIN_PASSWORD (and TRAVIAN_DB_ADMIN_USER, default root). A new random password
// goes into a 0600 journal first, the account is altered on every host, both generated config
// files (connection.local.php and globalConfig.php) are rewritten, and the login is checked before
// the journal is removed, so an interrupted run is finished by running the same command again.
// Web workers pick the new password up on their next request; restart the engine, which keeps its
// connections for as long as it runs.

use Game\Installer\DatabasePasswordRotation as Rotation;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/game/deployment/include/env.php';
require dirname(__DIR__) . '/game/include/Game/Installer/DatabasePasswordRotation.php';

$stateDirectory = rtrim((string) STATE_DIR, '/');
$connectionFile = $stateDirectory . '/connection.local.php';
$globalFile = $stateDirectory . '/globalConfig.php';
$journalFile = $stateDirectory . '/db-password-rotation.local.json';
$fail = static function (string $message, int $code = 1): never {
    fwrite(STDERR, $message . "\n");
    exit($code);
};

$connection = (static function (string $file): array {
    if (!is_file($file)) {
        return [];
    }
    include $file;

    return $connection ?? [];
})($connectionFile);
$host = (string) ($connection['database']['hostname'] ?? '');
$user = (string) ($connection['database']['username'] ?? '');
$worldDatabase = (string) ($connection['database']['database'] ?? '');
$global = (static function (string $file): array {
    if (!is_file($file)) {
        return [];
    }
    include $file;

    return $globalConfig ?? [];
})($globalFile);
$accountDatabase = (string) ($global['dataSources']['globalDB']['database'] ?? '');
if ($host === '' || $user === '' || $worldDatabase === '' || $accountDatabase === '') {
    $fail('The state directory has no installed world to rotate the database password for.');
}
$adminPassword = getenv('TRAVIAN_DB_ADMIN_PASSWORD');
if (!in_array('--yes', $argv, true) || $adminPassword === false || $adminPassword === '') {
    $fail("Rotates the password of the database user {$user} on {$host} and rewrites connection.local.php and globalConfig.php.\n"
        . "Pass the database administrator password in TRAVIAN_DB_ADMIN_PASSWORD and confirm with --yes.", 2);
}
$adminUser = getenv('TRAVIAN_DB_ADMIN_USER') ?: 'root';

// The new password is journalled before anything changes, so a repeat run reuses it.
$journal = is_file($journalFile) ? json_decode((string) file_get_contents($journalFile), true) : null;
$password = is_array($journal) && is_string($journal['password'] ?? null) ? $journal['password'] : null;
$resumed = $password !== null;
if ($password === null) {
    $password = Rotation::newPassword();
    $partial = $journalFile . '.partial';
    if (@file_put_contents($partial, json_encode(['password' => $password])) === false || !@chmod($partial, 0600) || !@rename($partial, $journalFile)) {
        @unlink($partial);
        $fail('Cannot write the rotation journal in the state directory.');
    }
}

mysqli_report(MYSQLI_REPORT_OFF);
$server = @new mysqli($host, $adminUser, $adminPassword);
if ($server->connect_error) {
    if (!$resumed) {
        @unlink($journalFile);
    }
    $fail('Could not connect to the database server as ' . $adminUser . '.');
}
try {
    $accounts = Rotation::alterUser($server, $user, $password);
} catch (\Throwable $exception) {
    if (!$resumed) {
        @unlink($journalFile);
    }
    $fail($exception->getMessage());
} finally {
    $server->close();
}

try {
    Rotation::writePassword($connectionFile, 'connection', ['database', 'password'], $password);
    Rotation::writePassword($globalFile, 'globalConfig', ['dataSources', 'globalDB', 'password'], $password);
} catch (\Throwable $exception) {
    $fail($exception->getMessage() . "\nThe database password was already changed; run the same command again to finish.");
}
if (!Rotation::canLogin($host, $user, $password, [$worldDatabase, $accountDatabase])) {
    $fail("The new password was written but {$user} cannot log in with it. Run the same command again.");
}
@unlink($journalFile);
fwrite(STDOUT, "Rotated the password of {$user} on {$accounts} account host(s) and updated both config files. Restart the engine: docker compose restart engine\n");
exit(0);
