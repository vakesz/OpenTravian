<?php

declare(strict_types=1);

/**
 * Contract: product decisions 2026-11-04 for WorldSpeedProfile / merchants /
 * published speeds — movement never drops when speed rises above 250×,
 * protection floor 3h at extreme speeds, isPublishedSpeed includes 20×,
 * merchants use economic speed.
 */

use Game\Formulas;
use Game\WorldSpeedProfile;

function failWorldSpeedProfilePolicy(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

if (!WorldSpeedProfile::isPublishedSpeed(20)
    || WorldSpeedProfile::isPublishedSpeed(100)) {
    failWorldSpeedProfilePolicy('isPublishedSpeed must include 20× and exclude custom high speeds such as 100×.');
}

$at250 = WorldSpeedProfile::movementSpeedMultiplier(250);
$at251 = WorldSpeedProfile::movementSpeedMultiplier(251);
$at1000 = WorldSpeedProfile::movementSpeedMultiplier(1000);
$at100000 = WorldSpeedProfile::movementSpeedMultiplier(100000);
if ($at250 !== 50 || $at251 < $at250 || $at1000 < $at251 || $at100000 < $at1000) {
    failWorldSpeedProfilePolicy(sprintf(
        'Troop movement must be non-decreasing above 250× (got 250=%d 251=%d 1000=%d 100000=%d).',
        $at250,
        $at251,
        $at1000,
        $at100000
    ));
}
if ($at100000 !== 3000) {
    failWorldSpeedProfilePolicy('Movement must remain capped at 3000 at extreme speeds.');
}

foreach ([1, 20, 100, 1000, 100000, 1000000] as $speed) {
    if (WorldSpeedProfile::protectionTime($speed) < 3 * 3600) {
        failWorldSpeedProfilePolicy("Beginner protection must stay ≥ 3 hours at {$speed}×.");
    }
}

$config = \Core\Config::getInstance();
$previousSpeed = $config->game->speed;
$previousMovement = $config->game->movement_speed_increase;
try {
    $config->game->speed = 10;
    $config->game->movement_speed_increase = 4;
    if (Formulas::merchantSpeed(1) !== 16 * 10) {
        failWorldSpeedProfilePolicy('Roman merchants at x10 must use full economic speed (16×10), not the troop movement band.');
    }
    $config->game->speed = 100;
    $config->game->movement_speed_increase = 20;
    if (Formulas::merchantSpeed(1) !== 16 * 100) {
        failWorldSpeedProfilePolicy('Roman merchants at x100 must use full economic speed (16×100).');
    }
} finally {
    $config->game->speed = $previousSpeed;
    $config->game->movement_speed_increase = $previousMovement;
}

$attributes = file_get_contents($root . '/game/include/Controller/Ajax/hero/attributes.php');
if ($attributes === false
    || !str_contains($attributes, 'ResourcesHelper::updateVillageResources($homeKid, false)')
    || !str_contains($attributes, 'SELECT exp, power, offBonus, defBonus, production, kid FROM hero')) {
    failWorldSpeedProfilePolicy('Hero attribute saves must rebuild home-village production rates for dorf1.');
}

echo "World-speed profile policy contract passed.\n";
