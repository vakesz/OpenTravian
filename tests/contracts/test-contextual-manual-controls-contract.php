#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: player-facing building and troop names open only their matching
 * local manual dialog. Generic Help/Knowledge Base routes and hash-only
 * callbacks must not survive as a logout/navigation fallback.
 */

function failContextualManualControls(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $source = file_get_contents($root . '/' . $path);
    if ($source === false) {
        failContextualManualControls("Unable to read {$path}.");
    }

    return $source;
};

$chooser = $read('game/include/resources/Templates/build/buildingWrapper.php');
$training = $read('game/include/resources/Templates/build/building/TroopBuildingTrainingWrapper.php');
$academy = $read('game/include/resources/Templates/build/building/AcademyResearch.php');
$smithy = $read('game/include/resources/Templates/build/building/SmithyResearch.php');
$townhall = $read('game/include/resources/Templates/build/building/Townhall.php');
$brewery = $read('game/include/resources/Templates/build/building/Brewery.php');
$routes = $read('game/include/mainInclude.php');
$rallyHtml = $read('game/include/Controller/RallyPoint/RallyPointHTML.php');
$sendTroops = $read('game/include/Controller/RallyPoint/SendTroops.php');
$outOfGame = $read('game/include/resources/Templates/layout/outOfGame.php');
$buildingReady = $read('game/include/resources/Templates/build/buildingReadyWrapper.php');
$buildController = $read('game/include/Controller/BuildCtrl.php');
$troopBuilding = $read('game/include/Controller/Build/TroopBuilding.php');
$academyController = $read('game/include/Controller/Build/AcademyCtrl.php');
$smithyController = $read('game/include/Controller/Build/SmithyCtrl.php');
$hospitalController = $read('game/include/Controller/Build/HospitalCtrl.php');
$onLoadBuildings = $read('game/include/Controller/OnLoadBuildingsDorfCtrl.php');
$dorf1 = $read('game/include/resources/Templates/resource-fields/main.php');
$harbor = $read('game/include/resources/Templates/build/building/Harbor.php');
$mainBuilding = $read('game/include/resources/Templates/build/mainBuilding.php');
$prepareSend = $read('game/include/resources/Templates/rallypoint/prepareSend.php');
$fixes = $read('assets/gpack/travian/mainPage/lang/overrides.css');

if (!str_contains($training, "Travian.Game.Manual.open('troop', <?= \$unitId === 99 ? 36 : \$unitId; ?>);")) {
    failContextualManualControls('Training-card troop names must open their matching local manual entry.');
}

foreach ([$academy, $smithy] as $researchTemplate) {
    if (!str_contains($researchTemplate, "Travian.Game.Manual.open('troop', <?= (int) \$vars['unitId']; ?>);")) {
        failContextualManualControls('Academy and smithy troop names must open their matching local manual entry.');
    }
}

if (is_file($root . '/game/include/Game/Manual/ManualLink.php')
    || str_contains($fixes, 'manualEntry')
    || str_contains($chooser . $buildController . $troopBuilding . $academyController . $smithyController . $hospitalController . $onLoadBuildings . $dorf1 . $harbor . $mainBuilding . $prepareSend, 'ManualLink')) {
    failContextualManualControls('Building and unit names stay plain text outside training/rally-point cards; ManualLink and .manualEntry must stay deleted.');
}

if (!str_contains($buildingReady, "<a onclick=\"Travian.Game.Manual.open('building', <?= (int)\$vars['itemId']; ?>);\"><img class=\"buildingIllustration")
    || str_contains($buildingReady, 'data-cmp-info="9"></a>')) {
    failContextualManualControls('The building illustration is the only building-view manual hook and must keep the reference markup.');
}

if (!str_contains($rallyHtml, "<a onclick=\"Travian.Game.Manual.open(\\'troop\\', ' . (int)\$u . ')\">")
    || !str_contains($rallyHtml, '$u !== "hero"')) {
    failContextualManualControls('Rally Point overview unit icons must open the unit manual (hero stays plain).');
}
if (str_contains($sendTroops, "Manual.open('troop', 'hero')") || str_contains($sendTroops, 'title="\' . T(')) {
    failContextualManualControls('Rally Point send-form unit images carry onclick+alt only; hero is plain.');
}

foreach ([
    "\$manualOpen = \$manualBuildingId >= 1",
    "Travian.Game.Manual.open('building', \" . \$manualBuildingId",
    "'Travian.Game.Manual.open(); return false;'",
    'layoutButton buttonFramed withIcon round help grey',
] as $needle) {
    if (!str_contains($outOfGame, $needle)) {
        failContextualManualControls("The top-right ? must open the contextual manual and cancel navigation: {$needle}");
    }
}
if (!str_contains($buildController, "'manualBuildingId'")) {
    failContextualManualControls('BuildCtrl must pass the built gid to the ? button.');
}

foreach ([
    [$townhall, "Travian.Game.Manual.open('building', 24); return false;"],
    [$brewery, "Travian.Game.Manual.open('building', 35); return false;"],
] as [$source, $safeCallback]) {
    if (!str_contains($source, $safeCallback)) {
        failContextualManualControls("Hash callback must explicitly cancel navigation: {$safeCallback}");
    }
}

if (str_contains($townhall . $brewery, 'return Travian.Game.Manual.open(')) {
    failContextualManualControls('Manual callbacks must not fall through to /#.');
}

if (str_contains($routes, "'help.php' => 'HelpCtrl'")
    || is_file($root . '/game/include/Controller/HelpCtrl.php')
    || is_file($root . '/game/include/resources/Templates/help/dialog.php')) {
    failContextualManualControls('Only contextual local manual controls may remain; generic Help is retired.');
}

echo "Contextual manual controls contract passed.\n";
