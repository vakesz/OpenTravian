#!/usr/bin/env php
<?php

declare(strict_types=1);

use Game\WorldSpeedProfile;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This tool may only run from the command line.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/game/deployment/include/env.php';
require_once dirname(__DIR__) . '/game/include/Game/WorldSpeedProfile.php';
require_once dirname(__DIR__) . '/game/include/Game/WorldSpeedRound.php';

/** @return array{speed: int, fromSpeed: int|null, apply: bool, resetStart: bool} */
function world_speed_arguments(array $arguments): array
{
    $speed = null;
    $fromSpeed = null;
    $apply = false;
    $resetStart = false;

    foreach (array_slice($arguments, 1) as $argument) {
        if (str_starts_with($argument, '--speed=')) {
            $speed = substr($argument, strlen('--speed='));
        } elseif (str_starts_with($argument, '--from-speed=')) {
            $fromSpeed = substr($argument, strlen('--from-speed='));
        } elseif ($argument === '--dry-run') {
            $apply = false;
        } elseif ($argument === '--apply') {
            $apply = true;
        } elseif ($argument === '--reset-start') {
            $resetStart = true;
        } else {
            fwrite(STDERR, "Usage: php tools/set-world-speed.php --speed=<1..1000000000> [--from-speed=<old speed>] [--reset-start] [--dry-run|--apply]\n");
            exit(2);
        }
    }

    foreach (['speed' => $speed, 'fromSpeed' => $fromSpeed] as $name => $value) {
        if ($value !== null && (!ctype_digit($value) || (int)$value < 1 || (int)$value > 1000000000)) {
            fwrite(STDERR, sprintf("--%s must be an integer from 1 to 1000000000.\n", $name === 'fromSpeed' ? 'from-speed' : 'speed'));
            exit(2);
        }
    }
    if ($speed === null) {
        fwrite(STDERR, "--speed is required.\n");
        exit(2);
    }

    return [
        'speed' => (int)$speed,
        'fromSpeed' => $fromSpeed === null ? null : (int)$fromSpeed,
        'apply' => $apply,
        'resetStart' => $resetStart,
    ];
}

/** @param array<string, mixed> $connection */
function world_speed_connection(array $connection, int $speed): array
{
    foreach (WorldSpeedProfile::profile($speed) as $key => $value) {
        $connection[$key] = $value;
    }
    $connection['speed'] = $speed;

    return $connection;
}

/** @param array<string, mixed> $connection */
function world_speed_connection_contents(array $connection): string
{
    return "<?php\n\$connection = " . var_export($connection, true) . ";\n";
}

$arguments = world_speed_arguments($argv);
$connectionFile = CONNECTION_LOCAL_FILE;
if (!is_file($connectionFile)) {
    fwrite(STDERR, "No installed local-world connection file was found.\n");
    exit(1);
}

$connection = null;
require $connectionFile;
if (!is_array($connection) || !isset($connection['speed'], $connection['worldId'], $connection['database'])) {
    fwrite(STDERR, "The local-world connection file is invalid.\n");
    exit(1);
}

$currentSpeed = (int)$connection['speed'];
$fromSpeed = $arguments['fromSpeed'] ?? $currentSpeed;
if ($fromSpeed !== $currentSpeed) {
    fwrite(
        STDERR,
        sprintf(
            "--from-speed must match the current installed speed (%dx).\n",
            $currentSpeed
        )
    );
    exit(2);
}
$isNoop = $arguments['speed'] === $currentSpeed;
$newConnection = world_speed_connection($connection, $arguments['speed']);
$profile = sprintf(
    '%dx economy, %dx movement, %dx storage, %d-day round',
    $arguments['speed'],
    $newConnection['movement_speed_increase'],
    $newConnection['storage_multiplier'],
    $newConnection['round_length']
);

/** @return list<string> keys whose stored value differs from the old speed's derived profile (an Advanced override the new profile replaces) */
function world_speed_custom_values(array $connection, int $fromSpeed, int $toSpeed): array
{
    $custom = [];
    $next = WorldSpeedProfile::profile($toSpeed);
    foreach (WorldSpeedProfile::profile($fromSpeed) as $key => $derived) {
        if (isset($connection[$key]) && (float)$connection[$key] !== (float)$derived) {
            $custom[] = sprintf('%s (%s -> %s)', $key, (string)$connection[$key], (string)$next[$key]);
        }
    }

    return $custom;
}

$customValues = world_speed_custom_values($connection, $fromSpeed, $arguments['speed']);

// A faster world has a shorter round: refuse a change that would end the round at once, unless the start moves to now.
$worldSource = $connection['database'];
$world = new mysqli((string)$worldSource['hostname'], (string)$worldSource['username'], (string)$worldSource['password'], (string)$worldSource['database']);
if ($world->connect_error) {
    fwrite(STDERR, "Could not connect to the world database.\n");
    exit(1);
}
$startRow = $world->query('SELECT startTime FROM config LIMIT 1');
$roundStart = $startRow === false ? 0 : (int)($startRow->fetch_assoc()['startTime'] ?? 0);
$roundRefusal = $isNoop ? null : \Game\WorldSpeedRound::refusal($roundStart, time(), $arguments['speed']);
if ($roundRefusal !== null && !$arguments['resetStart']) {
    fwrite(STDERR, $roundRefusal . "\n");
    exit(1);
}

if (!$arguments['apply']) {
    if ($isNoop) {
        printf("Dry run: %s is already using %s.\n", (string)$connection['worldId'], $profile);
        exit(0);
    }
    printf(
        "Dry run: %s would change from %dx to %s. Hero XP would be reconciled from %dx. Re-run with --apply.\n",
        (string)$connection['worldId'],
        $currentSpeed,
        $profile,
        $fromSpeed
    );
    if ($customValues !== []) {
        printf("Advanced profile overrides that would be reset to the new profile: %s\n", implode(', ', $customValues));
    }
    if ($arguments['resetStart']) {
        printf("The round start would move from %s to now.\n", gmdate('Y-m-d H:i:s', $roundStart) . ' UTC');
    }
    passthru(
        escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/reconcile-world-speed-profile.php')
        . ' --from-speed=' . $fromSpeed . ' --to-speed=' . $arguments['speed']
    );
    exit(0);
}

if ($isNoop) {
    printf("%s already uses %s; no state was changed.\n", (string)$connection['worldId'], $profile);
    exit(0);
}

$lockPath = rtrim(STATE_DIR, '/') . '/world-speed.lock';
$lock = fopen($lockPath, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Another world-speed change is already running.\n");
    exit(1);
}

require GLOBAL_CONFIG_FILE;
$source = $globalConfig['dataSources']['globalDB'] ?? null;
if (!is_array($source)) {
    fwrite(STDERR, "The account-database configuration is invalid.\n");
    exit(1);
}

$account = new mysqli(
    (string)$source['hostname'],
    (string)$source['username'],
    (string)$source['password'],
    (string)$source['database']
);
if ($account->connect_error) {
    fwrite(STDERR, "Could not connect to the account database.\n");
    exit(1);
}

$originalContents = file_get_contents($connectionFile);
if ($originalContents === false) {
    fwrite(STDERR, "Could not read the local-world connection file.\n");
    exit(1);
}
$connectionMode = fileperms($connectionFile);
$connectionOwner = fileowner($connectionFile);
$connectionGroup = filegroup($connectionFile);
if ($connectionMode === false || $connectionOwner === false || $connectionGroup === false) {
    fwrite(STDERR, "Could not read the local-world connection-file permissions.\n");
    exit(1);
}
$temporaryFile = tempnam(dirname($connectionFile), '.connection-speed-');
if ($temporaryFile === false || file_put_contents($temporaryFile, world_speed_connection_contents($newConnection), LOCK_EX) === false) {
    fwrite(STDERR, "Could not prepare the updated world connection file.\n");
    exit(1);
}
if (!chmod($temporaryFile, $connectionMode & 0777)
    || ($connectionOwner !== getmyuid() && !chown($temporaryFile, $connectionOwner))
    || ($connectionGroup !== filegroup($temporaryFile) && !chgrp($temporaryFile, $connectionGroup))) {
    unlink($temporaryFile);
    fwrite(STDERR, "Could not preserve the local-world connection-file permissions.\n");
    exit(1);
}

$movedStart = null;
try {
    if (!$account->begin_transaction()) {
        throw new RuntimeException('Could not start the account-database transaction.');
    }
    $statement = $account->prepare(
        'UPDATE gameServers SET speed=?, roundLength=? WHERE worldId=? AND configFileLocation=?'
    );
    if ($statement === false) {
        throw new RuntimeException('Could not prepare the account-server update.');
    }
    $statement->bind_param(
        'iiss',
        $arguments['speed'],
        $newConnection['round_length'],
        $newConnection['worldId'],
        $connectionFile
    );
    if (!$statement->execute() || $statement->affected_rows !== 1) {
        throw new RuntimeException('Could not update exactly one account-server record.');
    }
    $statement->close();
    if ($arguments['resetStart']) {
        $movedStart = \Game\WorldSpeedRound::resetStart($world, $account, (string)$newConnection['worldId'], time());
    }
    if (!rename($temporaryFile, $connectionFile)) {
        throw new RuntimeException('Could not publish the updated world connection file.');
    }
    if (!$account->commit()) {
        throw new RuntimeException('Could not commit the account-server update.');
    }
} catch (Throwable $exception) {
    $account->rollback();
    if ($movedStart !== null) {
        \Game\WorldSpeedRound::restoreStart($world, $movedStart);
    }
    if (!is_file($temporaryFile) && file_put_contents($connectionFile, $originalContents, LOCK_EX) !== false) {
        chmod($connectionFile, $connectionMode & 0777);
        if ($connectionOwner !== getmyuid()) {
            chown($connectionFile, $connectionOwner);
        }
        if ($connectionGroup !== filegroup($connectionFile)) {
            chgrp($connectionFile, $connectionGroup);
        }
    }
    if (is_file($temporaryFile)) {
        unlink($temporaryFile);
    }
    fwrite(STDERR, 'World-speed change failed: ' . $exception->getMessage() . "\n");
    exit(1);
} finally {
    $account->close();
    flock($lock, LOCK_UN);
    fclose($lock);
}

if ($customValues !== []) {
    printf("Reset Advanced profile overrides to the new profile: %s\n", implode(', ', $customValues));
}
if ($movedStart !== null) {
    // The web side keeps the world config for five minutes; the engine reads it when it starts.
    passthru(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('require ' . var_export(dirname(__DIR__) . '/game/deployment/include/env.php', true) . '; require ' . var_export(dirname(__DIR__) . '/game/include/bootstrap.php', true) . '; \\Core\\Caching\\Caching::getInstance()->delete("WorldConfig");'));
    printf("The round start moved from %s to now and the artefact, Wonder plan and Wonder alert flags were cleared; restart the engine so it reads the new round timers.\n", gmdate('Y-m-d H:i:s', $movedStart['startTime']) . ' UTC');
}

$reconcile = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/reconcile-world-speed-profile.php')
    . ' --from-speed=' . $fromSpeed . ' --apply';
passthru($reconcile, $reconcileStatus);
if ($reconcileStatus !== 0) {
    fwrite(STDERR, "The speed was updated, but reconciliation failed. Re-run tools/reconcile-world-speed-profile.php with --from-speed={$fromSpeed} --apply.\n");
    exit($reconcileStatus);
}

printf("World speed changed from %dx to %s.\n", $currentSpeed, $profile);
