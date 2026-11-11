#!/usr/bin/env php
<?php

declare(strict_types=1);

use Core\Config;
use Game\Formulas;

function failTrainingQueueOfficial(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function assertTrainingQueueOfficial(bool $condition, string $message): void
{
    if (!$condition) {
        failTrainingQueueOfficial($message);
    }
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$config = Config::getInstance();
$originalSpeed = $config->game->speed;
$originalMultiplier = $config->game->extra_training_time_multiplier;
$originalMilliseconds = $config->game->useMilSeconds;
$originalNanoseconds = $config->game->useNanoseconds;

try {
    $config->game->speed = 1;
    $config->game->extra_training_time_multiplier = 1;
    $config->game->useMilSeconds = false;
    $config->game->useNanoseconds = false;

    // First-party Legends building statistics, rechecked 2026-11-03:
    // queue duration is 100%, 90%, 81%, ... at levels 1, 2, 3, ... .
    foreach ([1 => 1600, 2 => 1440, 3 => 1296, 20 => 216] as $level => $seconds) {
        assertTrainingQueueOfficial(
            (int) Formulas::uTrainingTime(1, $level) === $seconds,
            "Barracks level $level must follow the official 0.9-per-level queue duration."
        );
    }

    $config->game->speed = 2;
    foreach ([1 => 800, 2 => 720, 3 => 648, 20 => 108] as $level => $seconds) {
        assertTrainingQueueOfficial(
            (int) Formulas::uTrainingTime(1, $level) === $seconds,
            "Barracks level $level must retain the official x2 /2 queue duration."
        );
    }

    foreach ([[19, 29], [20, 30]] as [$normalGid, $greatGid]) {
        $normalCost = Formulas::buildingUpgradeCosts($normalGid, 1);
        $greatCost = Formulas::buildingUpgradeCosts($greatGid, 1);
        assertTrainingQueueOfficial(
            array_map(static fn(int $cost): int => $cost * 3, $normalCost) === array_map('intval', $greatCost),
            "Great building $greatGid level-1 construction cost must be exactly 3x building $normalGid."
        );
        assertTrainingQueueOfficial(
            (Formulas::$data['buildings'][$greatGid - 1]['req']['capital'] ?? null) === '-1',
            "Great building $greatGid must remain forbidden in a capital."
        );
    }

    // queue behaviour: Great queues charge three unit costs.
    // The current first-party guide supports the economic comparison but does
    // not state this individual-unit multiplier directly.
    assertTrainingQueueOfficial(
        Formulas::uTrainingCost(1, true) === [360, 300, 450, 90],
        'Great Barracks troop production must retain the captured threefold Legionnaire cost.'
    );
} finally {
    $config->game->speed = $originalSpeed;
    $config->game->extra_training_time_multiplier = $originalMultiplier;
    $config->game->useMilSeconds = $originalMilliseconds;
    $config->game->useNanoseconds = $originalNanoseconds;
}

echo "Training-queue source contract passed.\n";
