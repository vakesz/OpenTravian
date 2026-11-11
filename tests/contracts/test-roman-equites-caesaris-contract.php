#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: the standard Roman Equites Caesaris prerequisite is Stable 10 and
 * Academy 15. The current Community Week page is event-specific and cannot
 * prove the regular-world values; this local row instead stays locked to the
 * regular x2 manual capture from 2026-11-03.
 */

use Core\Config;
use Game\Formulas;
use Game\Manual\ManualContent;

function failEquitesCaesaris(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function assertEquitesCaesaris(bool $condition, string $message): void
{
    if (!$condition) {
        failEquitesCaesaris($message);
    }
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$config = Config::getInstance();
$originalSpeed = $config->game->speed;
$originalMultiplier = $config->game->extra_training_time_multiplier;
$originalMovement = $config->game->movement_speed_increase;

try {
    $config->game->speed = 2;
    $config->game->extra_training_time_multiplier = 1;
    $config->game->movement_speed_increase = 2;

    $unit = Formulas::$data['units'][0][5] ?? null;
    assertEquitesCaesaris(is_array($unit), 'Roman Equites Caesaris unit data must exist.');
    assertEquitesCaesaris(
        [$unit['off'], $unit['def_i'], $unit['def_c'], $unit['speed'], array_values($unit['cost']), $unit['cu'], $unit['time'], $unit['cap']]
        === [180, 80, 105, 10, [550, 640, 800, 180], 4, 3520, 70],
        'Standard Equites Caesaris data must retain the captured regular-world values; Community Week data is not its source of truth.'
    );
    assertEquitesCaesaris(
        Formulas::uResearchPreRequests(1, 6) === [20 => 10, 22 => 15],
        'Equites Caesaris research must require Stable level 10 and Academy level 15.'
    );

    $manual = new ManualContent();
    $html = $manual->renderUnit(6);
    assertEquitesCaesaris(
        str_contains($html, '<span class="value ">0:29:20</span>')
        && str_contains($html, '<td>20 fields/hour</td>')
        && str_contains($html, "Stable</a> Level 10, <a onclick=\"Travian.Game.Manual.open('building', 22)\">Academy</a> Level 15"),
        'The x2 manual must render the captured Equites Caesaris time, velocity and prerequisites.'
    );
} finally {
    $config->game->speed = $originalSpeed;
    $config->game->extra_training_time_multiplier = $originalMultiplier;
    $config->game->movement_speed_increase = $originalMovement;
}

echo "Roman Equites Caesaris contract passed.\n";
