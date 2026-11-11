#!/usr/bin/env php
<?php

declare(strict_types=1);

use Core\Config;
use Game\Formulas;

function failCrannyCapacity(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function assertCrannyCapacity(bool $condition, string $message): void
{
    if (!$condition) {
        failCrannyCapacity($message);
    }
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$config = Config::getInstance();
$originalMultiplier = $config->game->cranny_multiplier;

try {
    // Current official Legends Cranny calculator and Support article 35:
    // normal L1/L2/L3/L10 = 200/260/340/2000 per resource;
    // Gauls protect 1.5 times those values.
    $config->game->cranny_multiplier = 1;
    foreach ([1 => 200, 2 => 260, 3 => 340, 10 => 2000] as $level => $capacity) {
        assertCrannyCapacity(
            (int) Formulas::crannyCAP($level, 1) === $capacity,
            "Normal Cranny level $level must retain the official capacity."
        );
        assertCrannyCapacity(
            (int) Formulas::crannyCAP($level, 3) === (int) ($capacity * 1.5),
            "Gaul Cranny level $level must retain the official 1.5x capacity."
        );
    }
    assertCrannyCapacity(Formulas::crannyCAP(0, 1) === 0, 'A level-zero Cranny must protect no resources.');

    // The documented custom-speed multiplier applies after the official base.
    $config->game->cranny_multiplier = 13;
    assertCrannyCapacity((int) Formulas::crannyCAP(10, 1) === 26000, 'The high-speed Cranny multiplier must scale the official normal base.');
    assertCrannyCapacity((int) Formulas::crannyCAP(10, 3) === 39000, 'The high-speed Cranny multiplier must retain the Gaul bonus.');

    $config->game->cranny_multiplier = 125000;
    assertCrannyCapacity((int) Formulas::crannyCAP(10, 1) === 250000000, 'Extreme supported speed must retain the official Cranny base before scaling.');
} finally {
    $config->game->cranny_multiplier = $originalMultiplier;
}

echo "Cranny capacity contract passed.\n";
