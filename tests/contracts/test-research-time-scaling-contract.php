#!/usr/bin/env php
<?php

declare(strict_types=1);

use Core\Config;
use Game\Formulas;

function failResearchTimeScalingContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

require_once dirname(__DIR__, 2) . '/game/deployment/include/env.php';
require_once dirname(__DIR__, 2) . '/game/include/bootstrap.php';

$config = Config::getInstance();
$originalSpeed = $config->game->speed;
$originalMultiplier = $config->game->extra_training_time_multiplier;
$originalMilliseconds = $config->game->useMilSeconds;
$originalNanoseconds = $config->game->useNanoseconds;

try {
    $config->game->extra_training_time_multiplier = 1;
    $config->game->useMilSeconds = false;
    $config->game->useNanoseconds = false;

    $config->game->speed = 1;
    $normalResearch = Formulas::uResearchTime(2);

    $config->game->speed = 2;
    $doubleSpeedResearch = Formulas::uResearchTime(2);

    $config->game->speed = 100;
    $hundredSpeedResearch = Formulas::uResearchTime(2);

    $config->game->speed = 1000000;
    $millionSpeedResearch = Formulas::uResearchTime(2);
    $millionSpeedUpgradeResearch = Formulas::uUpgradeTime(2, 1, 20);
} finally {
    $config->game->speed = $originalSpeed;
    $config->game->extra_training_time_multiplier = $originalMultiplier;
    $config->game->useMilSeconds = $originalMilliseconds;
    $config->game->useNanoseconds = $originalNanoseconds;
}

if ($normalResearch <= $hundredSpeedResearch) {
    failResearchTimeScalingContract('Academy research must become shorter as the world speed rises.');
}

if ($doubleSpeedResearch >= $normalResearch || $doubleSpeedResearch > ($normalResearch / 2) + 5) {
    failResearchTimeScalingContract('At 2x, Academy research must be approximately half its 1x duration.');
}

if ($hundredSpeedResearch > $normalResearch / 50) {
    failResearchTimeScalingContract('Academy research must scale with the high-speed world profile instead of retaining its base overhead.');
}

if ($millionSpeedResearch !== 1.0 && $millionSpeedResearch !== 1) {
    failResearchTimeScalingContract('Extreme-speed Academy research must retain the one-second minimum used by unit training.');
}

if ($millionSpeedUpgradeResearch !== 1) {
    failResearchTimeScalingContract('Academy or Smithy modifiers must not reduce extreme-speed research below one second.');
}

echo "Research-time scaling contract passed.\n";
