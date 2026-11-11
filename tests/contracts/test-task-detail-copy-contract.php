#!/usr/bin/env php
<?php

declare(strict_types=1);

function failTaskDetailCopyContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$taskBoard = file_get_contents($root . '/game/include/Game/Tasks/TaskBoard.php');
$detail = file_get_contents($root . '/game/include/resources/Templates/tasks/detail.php');
$model = file_get_contents($root . '/game/include/Model/TasksModel.php');
$english = file_get_contents($root . '/game/include/resources/Translation/en-US.php');

if (!is_string($taskBoard) || !is_string($detail) || !is_string($model) || !is_string($english)) {
    failTaskDetailCopyContract('Task-detail copy sources must be readable.');
}

foreach ([
    'public static function detailTitle(array $group, array $milestone): string',
    'public static function detailDescription(array $group): string',
    "T('Tasks', 'buildLevel')",
    "T('Tasks', 'upgradeToLevel')",
    "T('Tasks', 'reachCpProduction')",
    "T('Tasks', 'evenGrowthTitle')",
    "T('Tasks', 'completeEconomyTitle')",
    "T('Tasks', 'culturePointsProductionInfo')",
    "T('Tasks', 'freeGoldInfo')",
    'TasksModel::FREE_GOLD_REWARD_GOLD',
] as $needle) {
    if (!str_contains($taskBoard, $needle)) {
        failTaskDetailCopyContract('TaskBoard must retain the task-detail copy resolver: ' . $needle);
    }
}

if (!str_contains($model, 'public const FREE_GOLD_REWARD_GOLD = 100;')) {
    failTaskDetailCopyContract('The detail resolver must use the configured Free Gold reward value.');
}

foreach ([
    'TaskBoard::detailDescription($group)',
    'TaskBoard::detailTitle($group, $milestone)',
] as $needle) {
    if (!str_contains($detail, $needle)) {
        failTaskDetailCopyContract('Task detail must render the resolver result instead of a local generic fallback: ' . $needle);
    }
}

foreach ([
    "'culturePointsProductionInfo' => 'More culture points increase the amount of controllable villages. Increase your production with more buildings and celebrations in the town hall.'",
    "'reachCpProduction' => 'Reach %d culture point production per day'",
    "'buildLevel' => 'Build %s level %d'",
    "'upgradeToLevel' => 'Upgrade %s to level %d'",
    "'oneBuildingOfLevel' => 'One %s to level %d'",
    "'allBuildingsOfLevel' => 'All %s to level %d'",
    "'evenGrowthTitle' => 'One of all four resources to level %d'",
    "'completeEconomyTitle' => 'All resource fields to level %d'",
    "'millInfo' => 'A grain mill will increase the production of crop in this village.'",
    "'mainBuildingInfo' => 'The main building allows your village to construct buildings faster.'",
] as $copy) {
    if (!str_contains($english, $copy)) {
        failTaskDetailCopyContract('Missing exact task copy: ' . $copy);
    }
}

foreach ([
    'Complete each milestone to collect its reward.',
    "T('Tasks', 'reachCulture')",
    "T('Tasks', 'reachLevel')",
    "T('Tasks', 'fieldMilestone')",
    "T('Tasks', 'evenGrowthMilestone')",
    "T('Tasks', 'resourceFieldDescription')",
] as $obsolete) {
    if (str_contains($detail . $taskBoard . $english, $obsolete)) {
        failTaskDetailCopyContract('Obsolete local task-detail fallback survived: ' . $obsolete);
    }
}

echo "Task-detail copy contract passed.\n";
