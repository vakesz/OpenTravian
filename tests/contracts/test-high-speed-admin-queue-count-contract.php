#!/usr/bin/env php
<?php

declare(strict_types=1);

use Core\Config;

function failHighSpeedAdminQueueCountContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/game/include/admin/include/Controllers/ConfigurationDetailsCtrl.php';
$source = file_get_contents($controllerPath);
if ($source === false || !str_contains($source, 'trainingQueueThreshold')) {
    failHighSpeedAdminQueueCountContract('Queued Tasks must derive its training cutoff from the active timestamp precision.');
}
if (str_contains($source, 'miliseconds() - 1 * 1000')) {
    failHighSpeedAdminQueueCountContract('Queued Tasks must not use a millisecond cutoff on nanosecond worlds.');
}

require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';
require_once $controllerPath;

$config = Config::getInstance();
$originalMilliseconds = $config->game->useMilSeconds;
$originalNanoseconds = $config->game->useNanoseconds;
$method = new ReflectionMethod(ConfigurationDetailsCtrl::class, 'trainingQueueThreshold');
$controller = (new ReflectionClass(ConfigurationDetailsCtrl::class))->newInstanceWithoutConstructor();

try {
    $config->game->useMilSeconds = false;
    $config->game->useNanoseconds = false;
    $threshold = (int)$method->invoke($controller);
    if (abs($threshold - (time() - 1)) > 2) {
        failHighSpeedAdminQueueCountContract('Second-precision Queued Tasks cutoff must look back one second.');
    }

    $config->game->useMilSeconds = true;
    $config->game->useNanoseconds = false;
    $threshold = (int)$method->invoke($controller);
    if (abs($threshold - (miliseconds() - 1000)) > 3000) {
        failHighSpeedAdminQueueCountContract('Millisecond Queued Tasks cutoff must look back one second.');
    }

    $config->game->useMilSeconds = true;
    $config->game->useNanoseconds = true;
    $threshold = (int)$method->invoke($controller);
    if (abs($threshold - (nanoseconds() - 1000000000)) > 3000000000) {
        failHighSpeedAdminQueueCountContract('Nanosecond Queued Tasks cutoff must look back one second in nanoseconds.');
    }
} finally {
    $config->game->useMilSeconds = $originalMilliseconds;
    $config->game->useNanoseconds = $originalNanoseconds;
}

echo "High-speed admin queue-count contract passed.\n";
