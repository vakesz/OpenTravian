<?php

declare(strict_types=1);

/**
 * Contract: Troop training shell matches favouriteTroops /
 * nonFavouriteTroops + favourite button + TrainingTroops JS.
 */

function failTrainingFavouriteTroopsContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$layout = file_get_contents($root . '/game/include/resources/Templates/build/building/TroopBuildingLayout.php');
$wrapper = file_get_contents($root . '/game/include/resources/Templates/build/building/TroopBuildingTrainingWrapper.php');
$ctrl = file_get_contents($root . '/game/include/Controller/Build/TroopBuilding.php');
$crypt = file_get_contents($root . '/game/deployment/public/game-client.js');
$en = file_get_contents($root . '/game/include/resources/Translation/en-US.php');

foreach ([$layout, $wrapper, $ctrl, $crypt, $en] as $source) {
    if ($source === false) {
        failTrainingFavouriteTroopsContract('Unable to read training sources.');
    }
}

foreach ([
    'id="favouriteTroops"',
    'id="nonFavouriteTroops"',
    "name=\"did\"",
    'Travian.Game.TrainingTroops.initialize()',
] as $needle) {
    if (!str_contains($layout, $needle)) {
        failTrainingFavouriteTroopsContract('TroopBuildingLayout missing: ' . $needle);
    }
}

foreach ([
    'innerTroopWrapper',
    'button type="button" class="favourite',
    "data-troopID=",
    "T('inGame', 'present')",
    'span class="level"',
] as $needle) {
    if (!str_contains($wrapper, $needle)) {
        failTrainingFavouriteTroopsContract('TroopBuildingTrainingWrapper missing: ' . $needle);
    }
}

foreach ([
    "'favouriteSlots'",
    'loadSmithyLevels',
    "'troopKey'",
] as $needle) {
    if (!str_contains($ctrl, $needle)) {
        failTrainingFavouriteTroopsContract('TroopBuilding missing: ' . $needle);
    }
}

if (!str_contains($crypt, 'setTroopsToTrain')) {
    failTrainingFavouriteTroopsContract('game-client.js missing setTroopsToTrain.');
}

if (str_contains($crypt, 'parseInt(t.attr("data-troopID"))')) {
    failTrainingFavouriteTroopsContract('game-client.js must keep string troop ids (no parseInt on data-troopID).');
}

if (!str_contains($en, "['inGame']['present'] = 'present'")) {
    failTrainingFavouriteTroopsContract('en-US missing present copy.');
}

fwrite(STDOUT, "Training favourite troops contract passed.\n");
