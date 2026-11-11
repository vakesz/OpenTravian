<?php

declare(strict_types=1);

/**
 * Contract: Main Building "Rearrange your buildings" (gid15). Covers the slot rules of
 * Game\Buildings\BuildingRearranger (GET /dorf2.php?mode=rearrange), the
 * checker-protected, gold-charged `rearrangeBuildings` premium feature (20 gold, charged
 * in the same transaction as the swap), the client PremiumFeature.RearrangeBuildings
 * entry point in both crypt bundles and the dorf2 rearrange-mode markup.
 */

function failRearrangeContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/include/Game/Buildings/BuildingRearranger.php';

use Game\Buildings\BuildingRearranger;

function rearrangeSource(string $root, string $path): string
{
    $source = file_get_contents($root . '/' . $path);
    if ($source === false) {
        failRearrangeContract("Unable to read $path.");
    }

    return $source;
}

$identity = [];
foreach (BuildingRearranger::movableSlots() as $slot) {
    $identity[$slot] = BuildingRearranger::buildingId($slot);
}
$fixed = [39 => BuildingRearranger::buildingId(39), 40 => BuildingRearranger::buildingId(40)];

if (array_keys($identity) !== range(19, 38)) {
    failRearrangeContract('Movable slots must be 19-38 (Rally Point 39 and Wall 40 stay fixed).');
}
// the Main Building (slot 26 there), Rally Point and walls cannot be moved; empty sites can.
$gids = [26 => 15, 19 => 18, 20 => 10];
if (in_array(26, BuildingRearranger::movableSlots($gids), true) || !in_array(24, BuildingRearranger::movableSlots($gids), true)
    || count(BuildingRearranger::movableSlots($gids)) !== 19) {
    failRearrangeContract('The Main Building slot must be immovable while empty sites stay movable.');
}
foreach ([16, 33, 42] as $fixedGid) {
    if (in_array(30, BuildingRearranger::movableSlots([30 => $fixedGid]), true)) {
        failRearrangeContract("A building of type $fixedGid cannot be moved.");
    }
}

// A swap of two slots moves exactly those two.
$swapped = $identity;
$swapped[22] = BuildingRearranger::buildingId(30);
$swapped[30] = BuildingRearranger::buildingId(22);
$plan = BuildingRearranger::plan($swapped + $fixed, []);
if ($plan['error'] !== null || $plan['target'] !== [22 => 30, 30 => 22]) {
    failRearrangeContract('A two-slot swap must plan exactly the two moves.');
}

// A three-way cycle is accepted.
$cycle = $identity;
$cycle[19] = BuildingRearranger::buildingId(20);
$cycle[20] = BuildingRearranger::buildingId(21);
$cycle[21] = BuildingRearranger::buildingId(19);
$plan = BuildingRearranger::plan($cycle, []);
if ($plan['error'] !== null || count($plan['target']) !== 3) {
    failRearrangeContract('A permutation cycle must be accepted.');
}

$rejects = [
    'unchanged layout' => [$identity + $fixed, [], 'nothingToMove'],
    'duplicate building' => [array_replace($identity, [22 => BuildingRearranger::buildingId(30)]) + $fixed, [], 'invalidLayout'],
    'missing slot' => [array_diff_key($swapped, [25 => 1]), [], 'invalidLayout'],
    'unknown building id' => [array_replace($swapped, [22 => 'abc']), [], 'invalidLayout'],
    'array building id' => [array_replace($swapped, [22 => ['x']]), [], 'invalidLayout'],
    'source outside movable slots' => [array_replace($swapped, [22 => BuildingRearranger::buildingId(39)]), [], 'invalidLayout'],
    'rally point moved' => [$swapped + [39 => BuildingRearranger::buildingId(22), 40 => $fixed[40]], [], 'fixedSlot'],
    'wall moved' => [$swapped + [39 => $fixed[39], 40 => BuildingRearranger::buildingId(30)], [], 'fixedSlot'],
    'busy source slot' => [$swapped + $fixed, [22], 'slotBusy'],
    'busy target slot' => [$swapped + $fixed, [30], 'slotBusy'],
];
foreach ($rejects as $label => [$positions, $busy, $error]) {
    $plan = BuildingRearranger::plan($positions, $busy);
    if ($plan['error'] !== $error || $plan['target'] !== []) {
        failRearrangeContract("Rearrange plan must reject: $label (got " . var_export($plan['error'], true) . ').');
    }
}

// Moving the Main Building (or onto its slot) is rejected.
$mainBuildingMove = $swapped;
$mainBuildingMove[26] = BuildingRearranger::buildingId(30);
$mainBuildingMove[30] = BuildingRearranger::buildingId(26);
$plan = BuildingRearranger::plan($mainBuildingMove + $fixed, [], [26 => 15]);
if ($plan['error'] === null) {
    failRearrangeContract('A layout that moves the Main Building must be rejected.');
}
$withMainBuilding = $identity;
unset($withMainBuilding[26]);
$withMainBuilding[22] = BuildingRearranger::buildingId(30);
$withMainBuilding[30] = BuildingRearranger::buildingId(22);
$withMainBuilding[26] = BuildingRearranger::buildingId(26);
$plan = BuildingRearranger::plan($withMainBuilding + $fixed, [], [26 => 15]);
if ($plan['error'] !== null || $plan['target'] !== [22 => 30, 30 => 22]) {
    failRearrangeContract('A swap beside a fixed Main Building must be accepted without touching it.');
}

// A busy slot that stays where it is does not block other moves.
$plan = BuildingRearranger::plan($swapped + $fixed, [25]);
if ($plan['error'] !== null) {
    failRearrangeContract('A busy slot that is not moved must not block other moves.');
}

if (is_file($root . '/game/include/Controller/Ajax/village/rearrangeBuildings.php')) {
    failRearrangeContract('The rearrange endpoint is the premium feature; the standalone village endpoint must stay removed.');
}
$routes = require $root . '/game/deployment/include/api_legacy_routes.php';
if (isset($routes['routes']['village/rearrange-buildings'])) {
    failRearrangeContract('village/rearrange-buildings must not be routed.');
}

$premium = rearrangeSource($root, 'game/include/Controller/Ajax/premium.php');
foreach ([
    "'rearrangeBuildings'      => \$config->gold->rearrangeBuildingsGold,",
    'private function rearrangeBuildings()',
    'Session::claimAction($checker)',
    'BuildingRearranger::busySlots($did)',
    'BuildingRearranger::slotTypes($did)',
    'isInVacationMode()',
    '$village->isWW()',
    '$db->begin_transaction();',
    'GoldHelper::decreaseGold($session->getPlayerId(), Config::getInstance()->gold->rearrangeBuildingsGold, true)',
    '$db->rollback();',
    '$db->commit();',
] as $needle) {
    if (!str_contains($premium, $needle)) {
        failRearrangeContract("Rearrange premium feature must keep: $needle");
    }
}
if (strpos($premium, 'decreaseGold($session->getPlayerId(), Config::getInstance()->gold->rearrangeBuildingsGold, true)') > strpos($premium, 'BuildingRearranger::apply($did')) {
    failRearrangeContract('Gold must be taken inside the transaction before the swap so a failed swap rolls it back.');
}
if (!str_contains(rearrangeSource($root, 'game/include/config.php'), "'rearrangeBuildingsGold'         => 20")) {
    failRearrangeContract('The rearrange price defaults to 20 gold.');
}
foreach (['game-client.js'] as $bundle) {
    $js = rearrangeSource($root, 'game/deployment/public/' . $bundle);
    foreach (['Travian.Game.PremiumFeature = {', 'RearrangeBuildings: function ()', '"rearrangeBuildings"', 't.c = e.checker'] as $needle) {
        if (!str_contains($js, $needle)) {
            failRearrangeContract("$bundle must define PremiumFeature.RearrangeBuildings: $needle");
        }
    }
}

$rearranger = rearrangeSource($root, 'game/include/Game/Buildings/BuildingRearranger.php');
if (!str_contains($rearranger, 'UPDATE fdata SET') || !str_contains($rearranger, 'begin_transaction')) {
    failRearrangeContract('Rearranging must persist the slots to fdata in one transaction.');
}

$mainBuilding = rearrangeSource($root, 'game/include/resources/Templates/build/mainBuilding.php');
foreach (['mainBuilding.rearrange_title', "mode=rearrange", 'canRearrange', 'canDemolish'] as $needle) {
    if (!str_contains($mainBuilding, $needle)) {
        failRearrangeContract("Main Building template must keep: $needle");
    }
}

$dorf2 = rearrangeSource($root, 'game/include/resources/Templates/village-center/main.php');
foreach (['movable', 'data-name', 'data-level', 'rearrangeMode', 'dragContainer buildingSlot', 'animationContainer buildingFrom buildingSlot', 'animationContainer buildingTo buildingSlot', 'rearrange.php', 'rearrangeScript.php'] as $needle) {
    if (!str_contains($dorf2, $needle)) {
        failRearrangeContract("dorf2 rearrange mode must render: $needle");
    }
}

$overlay = rearrangeSource($root, 'game/include/resources/Templates/village-center/rearrange.php');
foreach ([
    'rearrangeBuildings hide', 'villageMask', 'class="instructions"', 'step step1 active', 'step step2', 'step dragAndDrop',
    '<a class="close"', 'textButtonV1 gold confirm disabled', 'Travian.Game.Village.RearrangeBuildings.proceed();',
    'proceedDialog', 'rearrangeBuildingsButtons', "'textButtonV1 gold '", 'Travian.Game.PremiumFeature.RearrangeBuildings(); return false;',
'\'coins\' => $cost', '<i class="goldIcon"></i><span class="goldValue">', "closeByContext('rearrangeBuildingsConfirm')",
    'abortDialog', 'decisionButtons', "closeByContext('rearrangeBuildingsAbort')", "window.location.href = '/dorf2.php';",
] as $needle) {
    if (!str_contains($overlay, $needle)) {
        failRearrangeContract("Rearrange overlay must contain: $needle");
    }
}
$script = rearrangeSource($root, 'game/include/resources/Templates/village-center/rearrangeScript.php');
foreach (['gid15.rearrangeBuildings_step2', 'gid15.rearrangeBuildings_dragAndDrop', 'gid15.rearrangeToContinue', 'gid15.rearrangeBuildings', 'allgemein.onUnloadMessage', 'RearrangeBuildings.initialize('] as $needle) {
    if (!str_contains($script, $needle)) {
        failRearrangeContract("Rearrange mode script must contain: $needle");
    }
}
$translation = rearrangeSource($root, 'game/include/resources/Translation/en-US.php');
foreach ([
    "Select or drag a building to change its position.",
    "Now select the new position of the building: [NAME] (Level [LEVEL])",
    "Now drag and drop the building [NAME] (level [LEVEL]) to its new position.",
    "Rearrange a building to continue.",
    "Confirm that you have finished rearranging the buildings of the village %s.",
    "Cancel rearrangement?",
    "Cancel rearrangement",
] as $text) {
    if (!str_contains($translation, $text)) {
        failRearrangeContract("Reference copy missing: $text");
    }
}

echo "Main Building rearrange contract passed.\n";
