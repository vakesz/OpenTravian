<?php

declare(strict_types=1);

/**
 * Contract: the map tile details (`map/tile-details`) use the reference markup for every tile state: one
 * `h1.titleInHeader` heading helper, the "Center map" link with `data-centermap`, the tribe `vid` class,
 * two option groups, one table row per land-distribution field with titles and plural names, reference cell
 * classes, tab links without `href`, the `targetMapId`/`eventType` Rally Point links, the report filter
 * link and the simulator link, and two-decimal distances.
 */
function failMapTileDetailsContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2) . '/game/include';
$controller = file_get_contents($root . '/Controller/Ajax/map/tileDetails.php');
$combat = file_get_contents($root . '/Controller/RallyPoint/Combat.php');
$sendTroops = file_get_contents($root . '/Controller/RallyPoint/SendTroops.php');
$templates = [];
foreach (['fieldFree', 'fieldOccupied', 'oasisFree', 'oasisOccupied', 'landscape'] as $name) {
    $templates[$name] = file_get_contents($root . "/resources/Templates/map/viewTileDetails_{$name}.php");
}
$tabs = file_get_contents($root . '/resources/Templates/map/tileTabs.php');
if ($controller === false || $combat === false || $sendTroops === false || $tabs === false || in_array(false, $templates, true)) {
    failMapTileDetailsContract('The tile detail sources must be readable.');
}

foreach ([
    '<h1 class="titleInHeader">',
    'data-centermap="true"',
    "'&targetMapId='",
    "/report/overview?villageId=",
    '&screen=combatSimulator&kid=',
    'round(Formulas::getDistance($tile[\'id\'], Session::getInstance()->getSelectedVillageID()), 2)',
    "T('Tasks', \$names[\$resource])",
    "T(\"map\", \"raidFreeOasis\")",
    "T(\"map\", \"buildHeroMansion\")",
    "\$settlers . '/3 ' . T(\"map\", \"readySettlers\")",
    'sprintf(T("map", "noResourcesForNewVillage"), $this->number_format(Formulas::newVillageCost()))',
    'data-tippy-placement="top-start"',
    'data-tippy-followCursor="false"',
] as $needle) {
    if (!str_contains($controller, $needle)) {
        failMapTileDetailsContract("The tile details controller must contain {$needle}.");
    }
}
foreach (['noCoordinateText', 'ajaxRequest', 'id="simulateRaid"', "'.\">' . T(\"map\", \"centerMap\")", "\u{200C}"] as $legacy) {
    if (str_contains($controller, $legacy)) {
        failMapTileDetailsContract("The tile details controller must not keep the legacy {$legacy}.");
    }
}
foreach ($templates as $name => $template) {
    if (!str_contains($template, "\$vars['heading']") || str_contains($template, '<h1>') || str_contains($template, 'ajaxRequest') || str_contains($template, 'tabSubWrapper')) {
        failMapTileDetailsContract("Tile template {$name} must print the shared heading and no legacy markup.");
    }
}
foreach (['fieldFree', 'fieldOccupied', 'oasisFree', 'oasisOccupied'] as $name) {
    if (!str_contains($templates[$name], "isset(\$vars['farmOptions'])")) {
        failMapTileDetailsContract("Tile template {$name} must print the farm-list option group separately.");
    }
}
if (str_contains($templates['fieldOccupied'], 'T("map", "player")') || !str_contains($templates['fieldOccupied'], 'class="alliance"') || !str_contains($templates['fieldOccupied'], 'class="player"')) {
    failMapTileDetailsContract('The village info table has no "Player" heading and marks the alliance and owner cells.');
}
foreach (['oasisFree', 'oasisOccupied'] as $name) {
    if (!str_contains($templates[$name], 'id="distance"')) {
        failMapTileDetailsContract("Tile template {$name} must carry the separate distance table.");
    }
}
if (str_contains($tabs, 'href="#"') || !str_contains($tabs, "'href' => null") || !str_contains($tabs, 'class="contentNavi tabNavi "')) {
    failMapTileDetailsContract('The tile tabs must render links without href like the reference.');
}
if (!str_contains($sendTroops, "\$_GET['targetMapId']")) {
    failMapTileDetailsContract('The Rally Point send form must accept the map link parameter targetMapId.');
}
if (!str_contains($combat, "isset(\$_GET['kid'])") || !str_contains($combat, 'oasisRaidInput')) {
    failMapTileDetailsContract('The combat simulator must accept the oasis kid from the map "simulate raid" link.');
}

echo "Map tile details contract passed.\n";
