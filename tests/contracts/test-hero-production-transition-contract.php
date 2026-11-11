<?php

declare(strict_types=1);

function failHeroProductionTransitionContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$adventure = file_get_contents(dirname(__DIR__, 2) . '/game/include/Model/Movements/AdventureProcessor.php');
$battle = file_get_contents(dirname(__DIR__, 2) . '/game/include/Model/BattleModel.php');
$training = file_get_contents(dirname(__DIR__, 2) . '/game/include/Model/TrainingModel.php');
$inventory = file_get_contents(dirname(__DIR__, 2) . '/game/include/Model/HeroInventoryModel.php');
if ($adventure === false || $battle === false || $training === false || $inventory === false) {
    failHeroProductionTransitionContract('Hero transition sources must be readable.');
}

if (!str_contains($adventure, 'ResourcesHelper::updateVillageResources($heroKid, true);')
    || !str_contains($adventure, 'ResourcesHelper::updateVillageResources($heroKid, false, false);')
    || str_contains($adventure, "ResourcesHelper::updateVillageResources(\$row['to_kid'], false);")) {
    failHeroProductionTransitionContract('Adventure death must settle the hero home village while alive, then refresh only the future dead-hero rate.');
}

if (!str_contains($battle, 'ResourcesHelper::updateVillageResources($this->row[\'kid\'], true);')
    || !str_contains($battle, 'ResourcesHelper::updateVillageResources($value[\'kid\'], true);')
    || substr_count($battle, 'false, false)') < 2) {
    failHeroProductionTransitionContract('Battle deaths must settle the prior living-hero rate before changing health.');
}

if (!str_contains($training, 'ResourcesHelper::updateVillageResources($kid, true);')
    || !str_contains($training, 'ResourcesHelper::updateVillageUpkeep($uid, $kid);')
    || !str_contains($training, 'ResourcesHelper::updateVillageResources($kid, false, false);')) {
    failHeroProductionTransitionContract('Normal revival must start production and upkeep together without retroactive production.');
}

foreach ([
    'ResourcesHelper::updateVillageResources($oldKid, true);',
    'UPDATE units SET u11=1 WHERE kid=?',
    'ResourcesHelper::updateVillageUpkeep($uid, $villageId);',
    'ResourcesHelper::updateVillageResources($villageId, false, false);',
] as $required) {
    if (!str_contains($inventory, $required)) {
        failHeroProductionTransitionContract("Bucket revival must restore the hero and its economy atomically: missing $required");
    }
}

echo "Hero production transition contract passed.\n";
