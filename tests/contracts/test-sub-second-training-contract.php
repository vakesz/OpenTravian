#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: troop training on speed worlds may take less than a second. The floor is one tick of the queue clock
 * (1 s up to x20, 1 ms above x20, 1 ns above x20000), never zero (a training_time of 0 marks a row complete).
 */

use Core\Config;
use Game\Formulas;

function assertSubSecondTraining(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$config = Config::getInstance();
$original = [$config->game->speed, $config->game->useMilSeconds, $config->game->useNanoseconds, $config->game->extra_training_time_multiplier];
$clock = static function (int $speed) use ($config): void {
    $config->game->speed = $speed;
    $config->game->useMilSeconds = $speed > 20;
    $config->game->useNanoseconds = $speed > 20000;
};
try {
    $config->game->extra_training_time_multiplier = 1;

    // x1: whole seconds, Legionnaire at a level-1 Barracks takes its full 1 600 s.
    $clock(1);
    assertSubSecondTraining((int)Formulas::uTrainingTime(1, 1) === 1600, 'A x1 Legionnaire at Barracks 1 trains in 1600 s.');

    // x10 000 (millisecond clock): a Legionnaire at Barracks 20 trains well under a second.
    $clock(10000);
    $ms = (int)Formulas::uTrainingTime(1, 20);
    assertSubSecondTraining($ms > 0 && $ms < 1000, "A x10000 Legionnaire at Barracks 20 must train in under a second (got {$ms} ms).");
    assertSubSecondTraining(
        $ms === (int)floor(1600 / 10000 * 0.9 ** 19 * 1000),
        "Sub-second training must follow the formula, not a one-second floor (got {$ms} ms)."
    );

    // x1 000 000 000 (nanosecond clock): still at least one tick, never zero.
    $clock(1_000_000_000);
    $ns = (int)Formulas::uTrainingTime(1, 20);
    assertSubSecondTraining($ns >= 1 && $ns < 1_000_000_000, "A nanosecond-clock Legionnaire must train in at least 1 ns and under a second (got {$ns} ns).");
    assertSubSecondTraining((int)Formulas::uHealingTime(1, 20) >= 1, 'Healing is never zero ticks.');
} finally {
    [$config->game->speed, $config->game->useMilSeconds, $config->game->useNanoseconds, $config->game->extra_training_time_multiplier] = $original;
}

echo "Sub-second training contract passed.\n";
