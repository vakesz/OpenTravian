#!/usr/bin/env php
<?php

declare(strict_types=1);

use Core\Config;
use Game\Hero\SessionHero;

function assertHeroReviveCountdownTime(bool $condition, string $message): void
{
    if ($condition) {
        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$config = Config::getInstance();
$originalMilliseconds = $config->game->useMilSeconds;
$originalNanoseconds = $config->game->useNanoseconds;
$sessionHero = (new ReflectionClass(SessionHero::class))->newInstanceWithoutConstructor();
$training = new ReflectionProperty(SessionHero::class, 'heroTrain');

try {
    $config->game->useMilSeconds = false;
    $config->game->useNanoseconds = false;
    $secondsEndTime = time() + 7200;
    $training->setValue($sessionHero, ['end_time' => $secondsEndTime]);
    assertHeroReviveCountdownTime(
        $sessionHero->getReviveEndTime() === $secondsEndTime,
        'Second-based hero revivals must pass their end time to the browser unchanged.'
    );

    $config->game->useMilSeconds = true;
    $millisecondsEndTime = ((int) miliseconds()) + 7_200_000;
    $training->setValue($sessionHero, ['end_time' => $millisecondsEndTime]);
    assertHeroReviveCountdownTime(
        $sessionHero->getReviveEndTime() === (int) ceil($millisecondsEndTime / 1000),
        'Millisecond hero revivals must normalize their end time to browser seconds.'
    );

    $config->game->useNanoseconds = true;
    $nanosecondsEndTime = ((int) nanoseconds()) + 7_200_000_000_000;
    $training->setValue($sessionHero, ['end_time' => $nanosecondsEndTime]);
    assertHeroReviveCountdownTime(
        $sessionHero->getReviveEndTime() === (int) ceil($nanosecondsEndTime / 1e9),
        'Nanosecond hero revivals must normalize their end time to browser seconds.'
    );
} finally {
    $config->game->useMilSeconds = $originalMilliseconds;
    $config->game->useNanoseconds = $originalNanoseconds;
}

echo "Hero revive countdown timestamp contract passed.\n";
