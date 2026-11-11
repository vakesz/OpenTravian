#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: every training building uses the training-time
 * table and renders each percentage reduction with one localized sign.
 */
function failTrainingBuildingValueContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/game/include/Controller/BuildCtrl.php');
$wrapper = file_get_contents($root . '/game/include/resources/Templates/build/buildingReadyWrapper.php');

if ($controller === false || $wrapper === false) {
    failTrainingBuildingValueContract('Unable to read training-building sources.');
}

if (preg_match('/case 19:.*case 20:.*case 21:.*case 29:.*case 30:.*getTrainingTimeValuesTable\(\$contract\)/s', $controller) !== 1) {
    failTrainingBuildingValueContract('All normal and Great training buildings must render the shared training-time value table.');
}

foreach ([
    "T('Buildings', 'trainingTimeCurrent')",
    "T('Buildings', 'trainingTimeNext')",
    "&#8237;+&#8237;",
    "&#8237;−&#8237;",
] as $fragment) {
    if (!str_contains($controller, $fragment)) {
        failTrainingBuildingValueContract("Training-time table is missing reference fragment: $fragment");
    }
}

if (!str_contains($wrapper, '$trainingTimeSign')
    || substr_count($wrapper, '$trainingTimeSign(') < 6) {
    failTrainingBuildingValueContract('Every normal/Great training benefit must share one sign formatter.');
}

foreach ([
    "'infantryBonusTime' => -",
    "'cavalryBonusTime' => -",
    "'siegeTime' => -",
    '= -$contract[\'culturePointsAndPopulation\'][\'infantryBonusTime\']',
    '= -$contract[\'culturePointsAndPopulation\'][\'cavalryBonusTime\']',
    '= -$contract[\'culturePointsAndPopulation\'][\'siegeTime\']',
] as $retiredNegativeValue) {
    if (str_contains($controller, $retiredNegativeValue)) {
        failTrainingBuildingValueContract("Training-time model must keep unsigned reduction magnitudes: $retiredNegativeValue");
    }
}

echo "Training-building value contract passed.\n";
