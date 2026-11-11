<?php

declare(strict_types=1);

function failVillageMapShellContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $contents = file_get_contents($root . '/' . $path);
    if ($contents === false) {
        failVillageMapShellContract("Cannot read {$path}.");
    }
    return $contents;
};
$templates = 'game/include/resources/Templates/';

$dorf1 = $read($templates . 'resource-fields/main.php');
if (!str_contains($dorf1, ' resourceField<?=')
    || !str_contains($dorf1, 'data-aid="<?=$map_index; ?>" data-gid="<?=$map[\'item_id\']; ?>"')
    || preg_match('/class="[^"]*\baid<\?=/', $dorf1) === 1) {
    failVillageMapShellContract('dorf1 resource slots must retain data-aid/data-gid attributes without an aid class.');
}
foreach (['href="/build.php?id=39#td"', 'src="/img/spacer.gif"'] as $fragment) {
    if (!str_contains($dorf1, $fragment)) {
        failVillageMapShellContract("dorf1 troop rows must retain the Rally Point route and root asset path: {$fragment}.");
    }
}

$dorf2 = $read($templates . 'village-center/main.php');
if (str_contains($dorf2, 'levelSwitch') || str_contains($dorf2, '<div class="level ')
    || !str_contains($dorf2, '$buildingHref = str_replace')
    || !str_contains($dorf2, 'href="<?=$buildingHref;?>"')
    || !str_contains($dorf2, 'data-name="<?=$map[\'name\'];?>"')
    || substr_count($dorf2, 'src="/img/spacer.gif"') < 2
    || str_contains($dorf2, '<div class="clear">&nbsp;</div>')
    || str_contains($dorf2, 'initializeWallStates')) {
    failVillageMapShellContract('dorf2 level chips must be build links and the local-only level switch must be gone.');
}

foreach (['map/normal.php'] as $file) {
    $mapTemplate = $read($templates . $file);
    if (str_contains($mapTemplate, 'iconButton')) {
        failVillageMapShellContract("{$file} must use mapToolbarButton/mapIconButton.");
    }
    foreach ([
        'inputmode="numeric"',
        'Travian.Formatter.Filter.aNumber(this)',
        "coordinateXInputId: 'xCoordInputMap', coordinateYInputId: 'yCoordInputMap'",
    ] as $fragment) {
        if (substr_count($mapTemplate, $fragment) < 2) {
            failVillageMapShellContract("{$file} must retain the numeric and paste-safe coordinate inputs.");
        }
    }
}

$client = $read('game/deployment/public/game-client.js');
// The reference keeps each mark list in a `scroll-dataContainer` inside the SimpleBar of the expand container.
if (str_contains($client, '.iconButton.zoomIn') || !str_contains($client, 'scroll-dataContainer')
    || !str_contains($client, 'new window.SimpleBar(') || !str_contains($client, '.mapToolbarButton.filterMy')) {
    failVillageMapShellContract('The map client must target mapToolbarButton/mapIconButton and use SimpleBar for mark lists.');
}

foreach (['ResourceFieldsCtrl', 'VillageCenterCtrl', 'MapCtrl'] as $controller) {
    if (str_contains($read("game/include/Controller/{$controller}.php"), "'headerHelpButtonId']")
        || str_contains($read("game/include/Controller/{$controller}.php"), "'helpTopic']")) {
        failVillageMapShellContract("{$controller} must not render a generic header help control.");
    }
}

$layout = $read($templates . 'layout/Layout.php');
foreach (['class="behindContent"', 'behindContent', 'class="shopNotification"', 'dailyQuestsIndicator'] as $needle) {
    if (!str_contains($layout, $needle)) {
        failVillageMapShellContract("Layout is missing {$needle}.");
    }
}
if (str_contains($layout, 'containerCssClass"] ?? "") === "contentPage"): ?><div class="behindContent"')) {
    failVillageMapShellContract('behindContent must render on village2, not only contentPage.');
}
if (!str_contains($read('game/include/resources/View/GameView.php'), "\$this->vars['headerBar'] = false;")) {
    failVillageMapShellContract('In-game pages must not render #headerBar by default.');
}

foreach (['game-client.js'] as $bundle) {
    $client = $read('game/deployment/public/' . $bundle);
    if (str_contains($client, 'toggleBuildingLevels')) {
        failVillageMapShellContract("{$bundle} must not keep the removed level toggle.");
    }
    if (!str_contains($client, 'jQuery("body.village2").length && Travian.Game.Village.initializeWallStates()')) {
        failVillageMapShellContract("{$bundle} must initialize wall states without emitting a Dorf2-only inline script.");
    }
}
foreach (['Core/Helper/PreferencesHelper.php', 'Controller/ResourceFieldsCtrl.php', 'Controller/VillageCenterCtrl.php'] as $php) {
    if (str_contains($read('game/include/' . $php), 't4level')) {
        failVillageMapShellContract("{$php} must not keep the removed t4level preference.");
    }
}
if (preg_match('/class="village (?:resource|building)View[^"]*active/', $layout) === 1) {
    failVillageMapShellContract('Navigation village links must not carry active/inactive classes (uses the body perspective class).');
}

echo "Village/map shell contract passed.\n";
