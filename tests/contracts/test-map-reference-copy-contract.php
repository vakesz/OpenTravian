#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: active map copy keeps the English map bundle.
 */

function failMapReferenceCopyContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$translation = file_get_contents($root . '/game/include/resources/Translation/en-US.php');
$normalMap = file_get_contents($root . '/game/include/resources/Templates/map/normal.php');
$tileDetails = file_get_contents($root . '/game/include/Controller/Ajax/map/tileDetails.php');
$oasisFree = file_get_contents($root . '/game/include/resources/Templates/map/viewTileDetails_oasisFree.php');

if ($translation === false || $normalMap === false || $tileDetails === false || $oasisFree === false) {
    failMapReferenceCopyContract('Unable to read the map copy sources.');
}

foreach ([
    '"sendTroops" => "Send troops"',
    '"no free slots" => "Hero\'s Mansion has no free slots"',
    '"distribution" => "Land distribution"',
    "'sendMerchants' => 'Send merchant(s)'",
    '"constructMarketplace" => "Build marketplace"',
    '"readySettlers" => "settlers available"',
    '"foundNewVillage" => "Found new village"',
    '"move up" => "move up"',
    '"move down" => "move down"',
    '"move right" => "move right"',
    '"move left" => "move left"',
    "'noUnits' => 'none'",
    "'units' => 'Troops'",
    "'Simulate raid' => 'simulate raid'",
    "'No information<br>available!' => 'No information<br />available!'",
    "'landscape_desc' => 'Wilderness'",
    "'centerMap' => 'Center map'",
    "'constructRallyPoint' => 'Build a rally point'",
    '"please_resend_all_data" => "Not all of the necessary data has been submitted. Please resend this request with all the necessary data."',
    '"invalid_coordinates" => "The entered coordinates do not exist."',
    '"showAllianceMarks" => "Show alliance´s marks"',
    '"showUserMarks" => "Show my marks"',
    '"landscape" => "Wilderness"',
    '"occupiedOasis" => "Occupied oasis"',
    '"natarsVillage" => "Natarian village"',
    '"arrival" => "arrival at"',
    '"supply" => "reinforcement"',
    '"spy" => "scouting"',
    '"return" => "return"',
    '"raid" => "raid"',
    '"attack" => "attack"',
    '"ownPlayerMarkTitle" => "Own player marks."',
    '"ownAllianceMarks" => "Own alliance marks."',
    '"ownFlags" => "Own field marks."',
    '"allianceMarkTitle" => \'Alliance marks for my alliance.\'',
    '"playerMarksTitle" => "Player marks for my alliance."',
    '"allianceFlags" => \'Field marks for my alliance.\'',
    // The server-rendered /karte.php toolbar of the reference (2026-11-08) supersedes the older bundle strings.
    '"largeMap" => "Larger map"',
    '"cropFinder" => "Crop finder"',
    '"needPlus" => "For this feature you need OpenTravian Plus activated."',
    '"needClub" => "For this feature you need the Gold club activated."',
    '"contextSendTroops" => "send troops"',
    '"contextSendMerchants" => "send merchants"',
] as $expected) {
    if (!str_contains($translation, $expected)) {
        failMapReferenceCopyContract('Missing map copy: ' . $expected);
    }
}

foreach ([
    '"largeMap"',
    '"cropFinder"',
    '"showAllianceMarks"',
    '"showUserMarks"',
    '"occupiedOasis"',
    '"arrival"',
    '"supply"',
    '"spy"',
    '"return"',
    '"raid"',
    '"attack"',
] as $expected) {
    if (!str_contains($normalMap, $expected)) {
        failMapReferenceCopyContract('The desktop map no longer renders the covered copy key: ' . $expected);
    }
}

foreach ([
    '"noUnits"',
    '"centerMap"',
    '"constructRallyPoint"',
    '"constructMarketplace"',
    '"foundNewVillage"',
] as $expected) {
    if (!str_contains($tileDetails, $expected)) {
        failMapReferenceCopyContract('Map tile details no longer render the covered copy key: ' . $expected);
    }
}

if (!str_contains($oasisFree, '"units"')) {
    failMapReferenceCopyContract('Free-oasis tile details no longer render the covered Troops label.');
}

foreach ([
    "1 => 'Won as attacker without losses.',",
    "2 => 'Won as attacker with losses.',",
    "3 => 'Lost as attacker.',",
    "4 => 'Won as defender without losses.',",
    "5 => 'Won as defender with losses.',",
    "6 => 'Lost as defender with losses.',",
    "7 => 'Lost as defender without losses.',",
] as $expected) {
    if (!str_contains($translation, $expected)) {
        failMapReferenceCopyContract('Missing report-icon title: ' . $expected);
    }
}

for ($type = 1; $type <= 7; ++$type) {
    $title = "'b.ri{$type}': '<?=T(\"Reports\", \"reportTypes.{$type}\");?>'";
    if (!str_contains($normalMap, $title) || str_contains($normalMap, $title . '.')) {
        failMapReferenceCopyContract('The map must render each canonical report-icon title exactly once, without an extra period.');
    }
}

// The locked toolbar icons open the Plus dialogs exactly like the reference: the larger map icon names the
// link list feature (as the live page does), both use the V5 dialog class, and the Gold club dialog names the
// farm list image `farmList`.
foreach ([
    '"featureKey":"linkList"',
    '"cssClass":"premiumFeaturePackage premiumFeaturePlus paymentShopV5"',
    '"featureKey":"cropFinder"',
    '"cssClass":"premiumFeaturePackage premiumFeatureGoldclub paymentShopV5"',
] as $needle) {
    if (!str_contains($normalMap, $needle)) {
        failMapReferenceCopyContract('The locked map icons must open the reference Plus dialogs: ' . $needle);
    }
}
$goldclub = (string)file_get_contents($root . '/game/include/Controller/Ajax/goldclub.php');
if (!str_contains($goldclub, "'image' => \$key === 'raidList' ? 'farmList' : \$key,")) {
    failMapReferenceCopyContract('The Gold club dialog must draw the farm list feature with the farmList image.');
}

echo "Map reference copy contract passed.\n";
