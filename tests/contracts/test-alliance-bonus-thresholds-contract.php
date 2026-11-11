#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: alliance bonus levels require the cumulative donations published in
 * Knowledge Base article 88 (2026-11-01) and keep the daily limits and upgrade
 * times from the same table.
 */

use Core\Config;
use Game\Formulas;

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

// The thresholds and limits scale with the world speed, so the test pins its own (a world of any speed passes).
Config::getInstance()->game->speed = 100;

$expected = [1 => 1200000, 2 => 5600000, 3 => 17100000, 4 => 51200000, 5 => 153600000];
foreach ($expected as $level => $total) {
    $actual = Formulas::getAllianceBonusContributesNeededForLevel($level);
    if ($actual !== $total) {
        fwrite(STDERR, "Alliance bonus level $level must require $total cumulative resources, got $actual.\n");
        exit(1);
    }
}

if (Formulas::getAllianceBonusContributesNeededForLevel(0) !== 0) {
    fwrite(STDERR, "An alliance without bonus levels must not require donations.\n");
    exit(1);
}
if (Formulas::getAllianceBonusContributesNeededForLevel(99) !== 0) {
    fwrite(STDERR, "Unknown levels must not invent a donation requirement.\n");
    exit(1);
}

foreach ([1 => 300000, 2 => 400000, 3 => 550000, 4 => 750000, 5 => 1000000] as $level => $limit) {
    $expectedLimit = $limit * getGameSpeed();
    if (Formulas::getAllianceBonusDonationLimit($level - 1) !== $expectedLimit) {
        fwrite(STDERR, "The donation limit while reaching level $level must be $expectedLimit.\n");
        exit(1);
    }
}

foreach ([1 => 24, 2 => 48, 3 => 72, 4 => 96, 5 => 120] as $level => $hours) {
    $expectedDuration = (int)round($hours * 3600 / getGameSpeed());
    if ((int)Formulas::getAllianceBonusUpgradeDuration($level) !== $expectedDuration) {
        fwrite(STDERR, "Alliance bonus level $level must take $hours hours divided by the world speed.\n");
        exit(1);
    }
}

$config = Config::getInstance();
$originalSpeed = $config->game->speed;
try {
    $config->game->speed = 1_000_000;
    foreach ([1, 5] as $level) {
        if (Formulas::getAllianceBonusUpgradeDuration($level) !== 1) {
            fwrite(STDERR, "Alliance bonus level $level must retain a one-second queue at 1,000,000x.\n");
            exit(1);
        }
    }
} finally {
    $config->game->speed = $originalSpeed;
}

echo "Alliance bonus threshold contract passed.\n";
