<?php

declare(strict_types=1);

/**
 * Contract: units carry no unused rs_time key; research costs are rounded to steps of
 * 10 and match the Academy page (Senator and Chieftain wood 15880); adventure stage lengths are the wiki's 1x days
 * (4, 17, 63) divided by min(speed, 20); a chief's 1x research time is (training time + 7200) / 4 (Roman Senator
 * 24475 s, 6:47:55).
 */

use Core\Config;
use Game\Formulas;
use Model\AdventureModel;

function failWikiValuesContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

require_once dirname(__DIR__, 2) . '/game/deployment/include/env.php';
require_once dirname(__DIR__, 2) . '/game/include/bootstrap.php';

Formulas::load();
if (str_contains((string) file_get_contents(dirname(__DIR__, 2) . '/game/include/Game/Formulas.php'), 'rs_time')) {
    failWikiValuesContract('The unused rs_time unit key is gone.');
}

foreach ([9 => [15880, 13800, 36400, 22660], 29 => [15880, 22900, 25200, 22660], 12 => [970, 380, 880, 400]] as $id => $cost) {
    if (array_map('intval', Formulas::uResearchCost($id)) !== $cost) {
        failWikiValuesContract("Research cost of unit {$id} is " . implode(',', Formulas::uResearchCost($id)) . ', expected ' . implode(',', $cost) . '.');
    }
}

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
    $senatorResearch = Formulas::uResearchTime(9);
} finally {
    $config->game->speed = $originalSpeed;
    $config->game->extra_training_time_multiplier = $originalMultiplier;
    $config->game->useMilSeconds = $originalMilliseconds;
    $config->game->useNanoseconds = $originalNanoseconds;
}
if ((int) $senatorResearch !== 24475) {
    failWikiValuesContract("Roman Senator research at 1x is {$senatorResearch} s, expected 24475 s (6:47:55).");
}

$entitled = new ReflectionMethod(AdventureModel::class, 'calcAdventuresUntilNow');
$model = (new ReflectionClass(AdventureModel::class))->newInstanceWithoutConstructor();
$start = (int) Config::getProperty('custom', 'startAdventures');
try {
    foreach ([[1, 4, 12], [1, 17, 12 + 26], [1, 63, 38 + 69], [1, 64, 107 + 1], [5, 4 / 5, 12]] as [$speed, $days, $total]) {
        $config->game->speed = $speed;
        $got = $entitled->invoke($model, $days * 86400) - $start;
        if (abs($got - floor($total)) > 0.001) {
            failWikiValuesContract("Speed {$speed}, day {$days}: entitled {$got}, expected " . floor($total) . '.');
        }
    }
} finally {
    $config->game->speed = $originalSpeed;
}

echo "Wiki unit and adventure values contract passed.\n";
