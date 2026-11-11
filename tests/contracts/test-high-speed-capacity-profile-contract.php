#!/usr/bin/env php
<?php

declare(strict_types=1);

use Core\Config;
use Game\Formulas;
use Game\WorldSpeedProfile;

function assertHighSpeedCapacityProfile(bool $condition, string $message): void
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
$originalSpeed = $config->game->speed;
$originalStorageMultiplier = $config->game->storage_multiplier;
$originalCrannyMultiplier = $config->game->cranny_multiplier;

try {
    foreach ([
        1 => ['storage' => 1, 'cranny' => 1],
        20 => ['storage' => 1, 'cranny' => 1],
        21 => ['storage' => 11, 'cranny' => 3],
        100 => ['storage' => 50, 'cranny' => 13],
        101 => ['storage' => 51, 'cranny' => 13],
        10_000 => ['storage' => 5_000, 'cranny' => 1_250],
        100_000 => ['storage' => 75_000, 'cranny' => 12_500],
        1_000_000 => ['storage' => 1_000_000, 'cranny' => 125_000],
    ] as $speed => $expected) {
        assertHighSpeedCapacityProfile(
            WorldSpeedProfile::storageMultiplier($speed) === $expected['storage'],
            sprintf('Storage multiplier must follow the documented %dx profile.', $speed),
        );
        assertHighSpeedCapacityProfile(
            WorldSpeedProfile::crannyMultiplier($speed) === $expected['cranny'],
            sprintf('Cranny multiplier must follow the documented %dx profile.', $speed),
        );

        $config->game->speed = $speed;
        $config->game->storage_multiplier = $expected['storage'];
        $config->game->cranny_multiplier = $expected['cranny'];

        assertHighSpeedCapacityProfile(
            (int) Formulas::fieldProduction(1) === 7 * $speed,
            sprintf('A level-one field must retain linear production at %dx.', $speed),
        );
        assertHighSpeedCapacityProfile(
            (int) Formulas::storeCAP(0) === 800 * $expected['storage'],
            sprintf('A level-zero warehouse must use the %dx storage multiplier.', $speed),
        );
        assertHighSpeedCapacityProfile(
            (int) Formulas::crannyCAP(10, 1) === 2_000 * $expected['cranny'],
            sprintf('A level-ten Cranny must use the %dx cranny multiplier.', $speed),
        );
    }
} finally {
    $config->game->speed = $originalSpeed;
    $config->game->storage_multiplier = $originalStorageMultiplier;
    $config->game->cranny_multiplier = $originalCrannyMultiplier;
}

echo "High-speed capacity profile contract passed.\n";
