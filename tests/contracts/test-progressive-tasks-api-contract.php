<?php

declare(strict_types=1);

function failProgressiveTasksContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $contents = file_get_contents($root . '/' . $path);
    if ($contents === false) {
        failProgressiveTasksContract("Cannot read {$path}.");
    }
    return $contents;
};

$routes = $read('game/deployment/include/api_legacy_routes.php');
foreach ([
    "'progressive-tasks/collectReward' => ['progressive-tasks', 'collect-reward']",
    "'progressive-tasks/reload' => ['progressive-tasks', 'reload']",
    "'hero/dataForHUD' => ['hero', 'data-for-hud']",
] as $route) {
    if (!str_contains($routes, $route)) {
        failProgressiveTasksContract("Missing API route {$route}.");
    }
}
foreach (['progressiveTasks/collectReward.php', 'progressiveTasks/reload.php', 'hero/dataForHud.php'] as $file) {
    if (!is_file($root . '/game/include/Controller/Ajax/' . $file)) {
        failProgressiveTasksContract("Missing handler {$file}.");
    }
}

$collect = $read('game/include/Controller/Ajax/progressiveTasks/collectReward.php');
foreach (['questType', 'scope', 'targetLevel', 'buildingId', "'spawnVillage'", 'TaskBoard::resolve', '->collect(', "'hero'", "'freeGold'", 'TasksModel::FREE_GOLD_KEY'] as $token) {
    if (!str_contains($collect, $token)) {
        failProgressiveTasksContract("collectReward must handle {$token}.");
    }
}
$tasksModel = $read('game/include/Model/TasksModel.php');
$paymentWizard = $read('game/include/Controller/Ajax/paymentWizard.php');
foreach ([
    "public const FREE_GOLD_KEY = 'free-gold'",
    "'type' => 'freeGold'",
    "'baseResources' => 300",
    "'baseExperience' => 20",
    "return ['resources' => 300, 'experience' => 20];",
    'markFreeGoldShopOpened',
    'FREE_GOLD_REWARD_GOLD = 100',
] as $token) {
    if (!str_contains($tasksModel, $token)) {
        failProgressiveTasksContract("Free Gold task contract is missing {$token}.");
    }
}
if (!str_contains($paymentWizard, 'markFreeGoldShopOpened')) {
    failProgressiveTasksContract('Opening the payment wizard must persist Free Gold task eligibility.');
}
$reload = $read('game/include/Controller/Ajax/progressiveTasks/reload.php');
foreach (['tasksData', 'heroData', 'activeVillageTasks', 'generalTasks', "'rows'", "tasks/row"] as $token) {
    if (!str_contains($reload, $token)) {
        failProgressiveTasksContract("reload must return {$token}.");
    }
}
$hud = $read('game/include/Game/Hero/HeroHud.php');
foreach (['levelUp', 'heroImagePath', 'statusInlineIcon', 'tooltipForExperience', "'bars'", 'startAngle', 'endAngle', 'currentValue', "'paths'"] as $token) {
    if (!str_contains($hud, $token)) {
        failProgressiveTasksContract("dataForHUD payload must include {$token}.");
    }
}

// Client class timeline.
$js = $read('game/deployment/public/tasks-board.js');
foreach ([
    'classList.add("animated")', 'classList.remove("animated")', 'classList.add("disabled")', 'classList.add("collected")',
    'classList.remove("achieved")', 'classList.add("collected")', 'ANIMATION_MS = 2000', 'progressive-tasks/collectReward',
    'hero/dataForHUD', 'progressive-tasks/reload', '"GET"',
    'response.freeGold', 'Travian.Game.Layout.updateGold', 'Object.keys(rows)', 'overview.insertBefore(row, anchor)',
] as $token) {
    if (!str_contains($js, $token)) {
        failProgressiveTasksContract("tasks-board.js is missing {$token}.");
    }
}
if (str_contains($read('game/include/resources/Templates/tasks/row.php'), 'type="submit"')
    || str_contains($read('game/include/resources/Templates/tasks/row.php'), '<form')
    || str_contains($read('game/include/Controller/TasksCtrl.php'), "REQUEST_METHOD")) {
    failProgressiveTasksContract('The form-post collect path must be gone.');
}

// Runtime checks of the pure helpers.
spl_autoload_register(static function (string $class) use ($root): void {
    $file = $root . '/game/include/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
$tribes = [1 => 'roman', 2 => 'teuton', 3 => 'gaul', 6 => 'egyptian', 7 => 'hun', 8 => 'spartan', 9 => 'viking'];
foreach ($tribes as $race => $name) {
    if (\Game\Tasks\TaskBoard::tribeClass($race) !== $name) {
        failProgressiveTasksContract("Tribe {$race} must map to {$name}.");
    }
}
$barracks = ['key' => 'building-19', 'type' => 'building', 'image' => 'g19'];
if (\Game\Tasks\TaskBoard::referenceType($barracks) !== 'buildingProgress'
    || \Game\Tasks\TaskBoard::buildingId($barracks) !== 19
    || \Game\Tasks\TaskBoard::imageClass($barracks, 'gaul') !== ' building g19 gaul') {
    failProgressiveTasksContract('Building task type/id/image mapping is wrong.');
}
$field = ['key' => 'field-4', 'type' => 'resourceBuildingsLevelOfOneOfTheBuildings', 'image' => 'g4'];
$growth = ['key' => 'even-growth', 'type' => 'resourceBuildingsLevelOfOneOfTheBuildings', 'image' => 'oneEachResource'];
if (\Game\Tasks\TaskBoard::referenceType($field) !== 'resourceBuildingsFirstLevel'
    || \Game\Tasks\TaskBoard::imageClass($growth, 'gaul') !== 'resourceBuildingsLevelOfOneOfTheBuildings') {
    failProgressiveTasksContract('Resource task mapping is wrong.');
}
$paths = \Game\Hero\HeroHud::healthPaths(93.0);
if ($paths['mask'] !== 'M55 55L47.35 109.46A 55 55 0 0 0 109.92 58.01' || $paths['title'] !== 'M55 55L47.35 109.46A 55 55 0 0 0 109.87 51.16') {
    failProgressiveTasksContract('Health arc paths differ from the reference: ' . json_encode($paths));
}
$paths = \Game\Hero\HeroHud::experiencePaths(28.4);
if ($paths['mask'] !== 'M55 55L32.63 105.25A 55 55 0 0 1 13.5 91.09'
    || $paths['title'] !== 'M55 55L32.63 105.25A 55 55 0 0 1 4 34.4') {
    failProgressiveTasksContract('Experience arc paths differ from the reference: ' . json_encode($paths));
}

echo "Progressive tasks API contract passed.\n";
