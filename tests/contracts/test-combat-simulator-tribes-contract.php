<?php

declare(strict_types=1);

/**
 * Contract: the rally point combat simulator (tt=3) calculates every tribe pairing,
 * lets Natars attack and defend, applies each tribe's wall, and reduces loyalty with
 * every tribe's chief. Also pins the compact form and its local
 * submit adapter, which keeps the player-facing DOM separate from the
 * calculation request payload.
 */

use Controller\RallyPoint\Simulator;
use Controller\RallyPoint\Combat;
use Game\Hero\HeroHelper;

function failSimulatorContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

/** Tribe ids: 1 Romans, 2 Teutons, 3 Gauls, 4 Nature, 5 Natars, 6 Egyptians, 7 Huns, 8 Spartans, 9 Vikings. */
$attackers = [1, 2, 3, 5, 6, 7, 8, 9];
$defenders = [1, 2, 3, 4, 5, 6, 7, 8, 9];

$battle = static function (int $attacker, int $defender, array $offUnits, array $defUnits, int $wall, bool $raid = false): array {
    $data = [
        'R' => $raid,
        'trapped2killed' => [],
        'defender' => ['r' => $defender - 1, 't' => 0, 'p' => 100, 'rpLevel' => 0, 'wLevel' => $wall, 'stone' => 0,
            'artifacts' => ['durability' => 0, 'scout' => 0]],
        'waves' => [],
    ];
    $defWave = ['r' => $defender - 1, 'u' => array_fill(0, 10, 0), 'U' => array_fill(0, 10, 0), 'side' => 'def'];
    foreach ($defUnits as $slot => $count) {
        $defWave['u'][$slot] = $count;
    }
    $data['waves'][] = $defWave;
    $offWave = ['r' => $attacker - 1, 'u' => array_fill(0, 10, 0), 'U' => array_fill(0, 10, 0), 'side' => 'off', 'h' => false, 'p' => 100, 'b' => [0, 0],
        'hero' => ['health' => 100, 'total_power' => 0, 'offBonus' => 0, 'str' => 0, 'arm' => 0, 'bandage' => ['num' => 0, 'eff' => 0], 'cages' => 0]];
    foreach ($offUnits as $slot => $count) {
        $offWave['u'][$slot] = $count;
    }
    $data['waves'][] = $offWave;

    return (new Simulator())->init($data);
};

foreach ($attackers as $attacker) {
    foreach ($defenders as $defender) {
        $report = $battle($attacker, $defender, [0 => 500, 4 => 300], [0 => 400, 1 => 300], 0);
        $losses = $report[0]['losses'] ?? null;
        if (!is_array($losses) || !is_numeric($losses[0] ?? null) || !is_numeric($losses[1] ?? null)
            || $losses[0] < 0 || $losses[0] > 1 || $losses[1] < 0 || $losses[1] > 1) {
            failSimulatorContract("Simulator must return loss ratios for tribe $attacker attacking tribe $defender.");
        }
        $raid = $battle($attacker, $defender, [0 => 500, 4 => 300], [0 => 400, 1 => 300], 0, true);
        if (!is_array($raid[0]['losses'] ?? null)) {
            failSimulatorContract("Simulator must calculate a raid for tribe $attacker against tribe $defender.");
        }
    }
}

// A wall helps every tribe that has one: more defender strength means fewer defender losses.
foreach ([1, 2, 3, 5, 6, 7, 8, 9] as $defender) {
    // Size the defence until the open-field fight is close, so the wall bonus shows in the losses.
    $count = 200;
    while ($count < 400000 && $battle(1, $defender, [0 => 800], [1 => $count], 0)[0]['losses'][1] >= 0.9) {
        $count *= 2;
    }
    $open = $battle(1, $defender, [0 => 800], [1 => $count], 0)[0]['losses'][1];
    $walled = $battle(1, $defender, [0 => 800], [1 => $count], 20)[0]['losses'][1];
    if (!($walled < $open)) {
        failSimulatorContract("Wall level 20 must reduce defender losses for tribe $defender.");
    }
}

// Rams bring a wall down, catapults hit the chosen target.
$rams = $battle(1, 1, [6 => 300, 0 => 200], [0 => 100], 20)[0]['info']['wall'] ?? null;
if (!is_array($rams) || $rams[1] >= $rams[0]) {
    failSimulatorContract('Rams must lower the wall level.');
}

// An attack with no offensive strength (settlers only) loses every unit instead of dividing by zero.
$settlersOnly = $battle(1, 1, [9 => 3], [0 => 10], 0)[0]['losses'] ?? null;
if (!is_array($settlersOnly) || (float)$settlersOnly[0] !== 1.0 || (float)$settlersOnly[1] !== 0.0) {
    failSimulatorContract('A settlers-only attack must lose every unit and kill nothing, got ' . json_encode($settlersOnly) . '.');
}

// A hero with cages against an unoccupied oasis (Nature) cages animals instead of fighting.
$cageData = [
    'R' => false,
    'trapped2killed' => [],
    'defender' => ['r' => 3, 't' => 0, 'p' => 100, 'rpLevel' => 0, 'wLevel' => 0, 'stone' => 0],
    'waves' => [
        ['r' => 3, 'u' => [20, 0, 0, 0, 0, 0, 0, 0, 0, 0], 'U' => array_fill(0, 10, 0), 'side' => 'def'],
        ['r' => 0, 'u' => [10, 0, 0, 0, 0, 0, 0, 0, 0, 0], 'U' => array_fill(0, 10, 0), 'side' => 'off', 'h' => true, 'p' => 100, 'b' => [0, 0],
            'hero' => ['health' => 100, 'total_power' => 100, 'offBonus' => 0, 'arm' => 0, 'bandage' => ['num' => 0, 'eff' => 0], 'cages' => 15]],
    ],
];
$caged = (new Simulator())->init($cageData)[0]['caged'] ?? null;
if (!is_array($caged) || (int)$caged[0] !== 15 || array_sum($caged) != 15) {
    failSimulatorContract('Fifteen cages must catch fifteen of the twenty animals, got ' . json_encode($caged) . '.');
}

// The combat formulas, tribe tables and constants are BattleSetter's and BattleModel's; the simulator keeps no copy.
$simulatorClass = new ReflectionClass(Simulator::class);
foreach (['limit', 'int_check_limit', 'move_to_cages', 'move_to_traps', 'stat_with_upg', 'common_morale', 'calc_diffusion',
    'remorale', 'sigma', 'early_ramming', 'bonusCatsRams', 'demolish', 'hero_str3'] as $copy) {
    if ($simulatorClass->hasMethod($copy)) {
        failSimulatorContract("The simulator must call BattleSetter instead of its own $copy.");
    }
}
foreach (['mode', 'wall_durability', 'wall_extra', 'administrator_effect'] as $copy) {
    if ($simulatorClass->hasProperty($copy)) {
        failSimulatorContract("The simulator must not keep its own $copy.");
    }
}
if ($simulatorClass->getConstants() !== []) {
    failSimulatorContract('The simulator must read the battle constants from BattleModel.');
}

// Every playable tribe's chief lowers loyalty; Natars and Nature have none.
foreach ([1, 2, 3, 6, 7, 8, 9] as $attacker) {
    $loyalty = $battle($attacker, 1, [0 => 2000, 8 => 1], [0 => 1], 0)[0]['info']['loy'] ?? null;
    if (!is_array($loyalty) || $loyalty[1] <= 0) {
        failSimulatorContract("Chief of tribe $attacker must lower loyalty.");
    }
}

if (array_slice(Combat::attackerTribes(), -1) !== [5] || count(Combat::attackerTribes()) !== count(\Game\Tribes::playable()) + 1
    || !in_array(5, Combat::defenderTribes(), true) || !in_array(4, Combat::defenderTribes(), true)
    || in_array(5, Combat::reinforcementTribes(), true)) {
    failSimulatorContract('Attackers: playable + Natars; defenders: playable + Nature + Natars; reinforcements exclude Natars.');
}

$template = (string)file_get_contents($root . '/game/include/resources/Templates/combat/main.php');
$resultTemplate = (string)file_get_contents($root . '/game/include/resources/Templates/combat/simulatorResult.php');
$translations = (string)file_get_contents($root . '/game/include/resources/Translation/en-US.php');
foreach (['switchRoleWrapper', 'class="switch textButtonV1 <?=$playsDefender ? \'attacker\' : \'defender\'; ?>"', 'villageSelection', 'troopIcons', 'troopUnits', 'troopLevels', "'reinforcement'); ?>", 'smallTribe', 'iconButtonV2 reload', 'iconButtonV2 clear', 'svgAttack roleIcon', 'svgDefend roleIcon', 'id="combatSimulatorForm" autocomplete="off"', 'type="button" id="simulate"', 'name="allyBonus"', 'name="buildingMasterBonus"', 'name="artefact_spy"', 'name="brewery"', 'name="celebration"', "addSimulationInput('a1_celebration', 1)", 'buildSimulationPayload', 'data-simulator-payload', '.role.attacker .troopUnits input[data-t]', 'simulate.disabled = !hasAttackerTroops'] as $needle) {
    if (!str_contains($template, $needle)) {
        failSimulatorContract("Combat simulator template must keep: $needle");
    }
}

foreach (['combatSimulatorTemplates', 'data-simulator-role="attacker"', 'data-simulator-role="defender"', 'villageSnapshots', 'replaceAttackerTable', 'renderDefenderTables', 'applyVillage', 'submitSimulation', "table.closest('[data-simulator-tribe]')"] as $needle) {
    if (!str_contains($template, $needle)) {
        failSimulatorContract("Combat simulator must keep its in-place state transition: $needle");
    }
}
foreach (['heroState', 'heroCheckbox', 'renderHeroEditor', 'updateHeroCalculations', 'mapHero', "addSimulationInput(prefix + '_hero'", "'offPoints' : 'defPoints'"] as $needle) {
    if (!str_contains($template, $needle)) {
        failSimulatorContract("Combat simulator must keep the native hero editor path: $needle");
    }
}
foreach (['renderHeroEquipment', 'heroEquipmentCheckmark', "'dropdown ' + slot + ' closed'", "makeSlot('rightHand', 'hero_handR', false)", "makeSlot('leftHand', 'hero_handL', true)", "makeSlot('body', 'hero_torso', true)", "makeSlot('bag', 'hero_bag', true)", "prefix + '_hero_left_hand'", "prefix + '_hero_body'", "prefix + '_hero_bag_amount'"] as $needle) {
    if (!str_contains($template, $needle)) {
        failSimulatorContract("Combat simulator must keep the classic hero-equipment picker: $needle");
    }
}
if (str_contains($template, 'name="hero"')) {
    failSimulatorContract('Attacker and defender hero checkboxes must not share the retired ambiguous hero field name.');
}
if (str_contains($template, ".concat(activeReinforcements(main)).filter(function (tribe, index, values) { return values.indexOf(tribe) === index; }).sort(")) {
    failSimulatorContract('Defender form tables must preserve the selected main-village-first order, not tribe-ID sort order.');
}
foreach (['submitLegacy', 'reloadVillage'] as $retired) {
    if (str_contains($template, $retired)) {
        failSimulatorContract("Combat simulator selector flow must not retain $retired.");
    }
}
if (str_contains($template, 'mapLegacy') || str_contains($template, 'addLegacyInput') || str_contains($template, 'data-simulator-legacy')
    || substr_count($template, 'form.submit()') !== 1
    || !str_contains($template, 'simulate.addEventListener(\'click\', function () { if (!simulate.disabled) { submitSimulation(); } });')) {
    failSimulatorContract('Only Simulate may submit the current simulation payload adapter.');
}

$controller = (string)file_get_contents($root . '/game/include/Controller/RallyPoint/Combat.php');
foreach (['ownVillageSnapshots', 'villageSnapshots', 'self::buildingLevel', "'SELECT v.kid, v.name, v.pop, u.race FROM vdata v LEFT JOIN units u ON u.kid=v.kid WHERE v.owner=? ORDER BY v.kid'"] as $needle) {
    if (!str_contains($controller, $needle)) {
        failSimulatorContract("Combat simulator must provide trusted own-village prefill snapshots: $needle");
    }
}
foreach (['combat/simulatorResult', 'renderResult', 'resultCard', "'trapped'", "'caged'", "'revived'", "'dead_heroes'", "'combatStrength'"] as $needle) {
    if (!str_contains($controller, $needle)) {
        failSimulatorContract("Combat simulator result view must retain: $needle");
    }
}
foreach (['simulatorHeroData', "'a1_hero'", "'a2_hero'", "'total_power' => \$totalPower", "'arm' => self::boundedPostInt", "'h'    => (bool)\$this->output['attacker']['hero'] && \$attack_type <> 1"] as $needle) {
    if (!str_contains($controller, $needle)) {
        failSimulatorContract("Combat simulator controller must use side-specific canonical hero data: $needle");
    }
}
if (str_contains($controller, "\$_POST['hero']") || str_contains($controller, 'h_power') || str_contains($controller, 'h_off_bonus')) {
    failSimulatorContract('The retired ambiguous and attacker-only simulator hero payload must be removed.');
}
foreach (['separator long', 'result ', 'troopsInitial', 'troopsLoss', 'troopsWounded', 'troopsResult', 'combatStatistics', 'additionalInfo', 'svgAttack roleIcon', 'svgDefend roleIcon'] as $needle) {
    if (!str_contains($resultTemplate, $needle)) {
        failSimulatorContract("Combat simulator result template must keep the native result surface: $needle");
    }
}
if (str_contains($template, 'fighterType') || str_contains($template, 'attackerTable') || str_contains($template, 'defenderTable')
    || is_file($root . '/game/include/resources/Templates/combat/troopsTable.php')) {
    failSimulatorContract('The retired combat result table path must be removed, not retained alongside the native cards.');
}

$simulator = new Simulator();
$reflection = new ReflectionClass(Simulator::class);
$offense = $reflection->getMethod('calc_offense');
$finalize = $reflection->getMethod('finalize_stats');
$defense = $reflection->getMethod('calc_total_defense');
$artefact = $reflection->getMethod('art_effect');
$itemBonus = $reflection->getMethod('item_unit_bonus');
$controllerReflection = new ReflectionClass(Combat::class);
$heroInputController = $controllerReflection->newInstanceWithoutConstructor();
$heroInput = $controllerReflection->getMethod('simulatorHeroData');
$originalPost = $_POST;
$_POST = ['a1_hero_health' => '100', 'a1_hero_strength' => '1', 'a1_hero_off_points' => '5', 'a1_hero_horse' => '1'];
$attackingHeroInput = $heroInput->invoke($heroInputController, 1, 'off', 'a1');
if ($attackingHeroInput['total_power'] !== 200 || $attackingHeroInput['offBonus'] !== 5
    || $attackingHeroInput['isCavalry'] !== true) {
    failSimulatorContract('Roman simulator hero input must derive base power, raw Off points and horse state exactly once.');
}
$_POST = ['a2_hero_health' => '100', 'a2_hero_strength' => '1', 'a2_hero_def_points' => '500'];
$defendingHeroInput = $heroInput->invoke($heroInputController, 2, 'def', 'a2');
if ($defendingHeroInput['total_power'] !== 180 || $defendingHeroInput['defBonus'] !== 100
    || isset($defendingHeroInput['isCavalry'])) {
    failSimulatorContract('Defender hero input must use the non-Roman strength factor and official 20 percent bonus cap.');
}
$_POST = [
    'a1_hero_strength' => '101',
    'a1_hero_left_hand' => '78',
    'a1_hero_body' => '93',
    'a1_hero_bag' => '113',
    'a1_hero_bag_amount' => '7',
];
$equipmentHeroInput = $heroInput->invoke($heroInputController, 2, 'off', 'a1');
$heroHelper = new HeroHelper();
$expectedEquipmentPower = 100 + 100 * 80 + $heroHelper->calcItemPower(
    0,
    ['btype' => 3, 'type' => 78],
    ['btype' => 2, 'type' => 93]
);
if ($equipmentHeroInput['total_power'] !== $expectedEquipmentPower
    || $equipmentHeroInput['arm'] !== $heroHelper->calcResist(['btype' => 2, 'type' => 93])
    || $equipmentHeroInput['bandage'] !== $heroHelper->getBandages(['btype' => 8, 'type' => 113, 'num' => 7])
    || $equipmentHeroInput['selectedItems']['leftHand'] !== ['itemTypeId' => 78, 'amount' => 1]
    || $equipmentHeroInput['selectedItems']['body'] !== ['itemTypeId' => 93, 'amount' => 1]
    || $equipmentHeroInput['selectedItems']['bag'] !== ['itemTypeId' => 113, 'amount' => 7]) {
    failSimulatorContract('Classic selected left-hand, body and bandage items must use the same HeroHelper mechanics as battles.');
}
$_POST = ['a1_hero_left_hand' => '81'];
$hornHeroInput = $heroInput->invoke($heroInputController, 1, 'off', 'a1');
if ($hornHeroInput['n'] !== (new \Game\Hero\HeroItems())->getHeroItemProperties(3, 81)['powerAgainstNatars']) {
    failSimulatorContract('Natarian Horn selection must flow to the simulator’s canonical Natar bonus.');
}
$_POST = ['a1_hero_right_hand' => '16'];
$rightHandHeroInput = $heroInput->invoke($heroInputController, 1, 'off', 'a1');
$rightHandProperties = (new \Game\Hero\HeroItems())->getHeroItemProperties(4, 16);
if ($rightHandHeroInput['unitBonus'] !== ['unitId' => $rightHandProperties['unitId'], 'power' => $rightHandProperties['unit_power']]) {
    failSimulatorContract('The canonical simulator payload must carry a right-hand weapon’s troop-specific bonus.');
}
$_POST = ['a1_hero_body' => '999', 'a1_hero_bag' => '111'];
$invalidEquipmentInput = $heroInput->invoke($heroInputController, 1, 'off', 'a1');
if ($invalidEquipmentInput['selectedItems'] !== ['leftHand' => null, 'body' => null, 'bag' => null]) {
    failSimulatorContract('The simulator must reject item IDs outside the observed classic picker catalogues.');
}
$_POST = $originalPost;
$mapTileDetails = (string)file_get_contents($root . '/game/include/Controller/RallyPoint/Combat.php');
foreach (['a1_hero_total_power', 'a1_hero_right_hand', 'a1_hero_natar_bonus', 'a1_hero_off_points', 'a1_hero_bandages', 'a1_hero_cages'] as $needle) {
    if (!str_contains($mapTileDetails, $needle)) {
        failSimulatorContract("The oasis raid input must map the canonical simulator hero field: $needle");
    }
}
if (str_contains($mapTileDetails, 'h_power') || str_contains($mapTileDetails, 'h_off_bonus')) {
    failSimulatorContract('The oasis raid shortcut must not retain the retired attacker-only hero fields.');
}
$wave = ['r' => 0, 'u' => array_fill(0, 11, 0), 'U' => array_fill(0, 10, 0), 'side' => 'off', 'hero' => []];
$wave['u'][0] = 100;
$plain = $offense->invokeArgs($simulator, [&$wave]);
$finalize->invokeArgs($simulator, [&$plain]);
$wave['M'] = 1.1;
$metallurgy = $offense->invokeArgs($simulator, [&$wave]);
$finalize->invokeArgs($simulator, [&$metallurgy]);
if (abs($plain['i'] - 4000) > 0.00001 || abs($metallurgy['i'] - 4400) > 0.00001) {
    failSimulatorContract('The simulator must apply the 10% Metallurgy option after troop strength.');
}
$wave = ['r' => 1, 'u' => array_fill(0, 11, 0), 'U' => array_fill(0, 10, 0), 'side' => 'off', 'hero' => [], 'B' => 20, 'M' => 1.1];
$wave['u'][0] = 100;
$inactiveBrewery = $offense->invokeArgs($simulator, [&$wave]);
$finalize->invokeArgs($simulator, [&$inactiveBrewery]);
$wave['C'] = true;
$brewery = $offense->invokeArgs($simulator, [&$wave]);
$finalize->invokeArgs($simulator, [&$brewery]);
if (abs($inactiveBrewery['i'] - 4400) > 0.00001 || abs($brewery['i'] - 5280) > 0.00001) {
    failSimulatorContract('The simulator must apply Brewery only while a celebration is active.');
}
$accountWideBrewery = ['r' => 2, 'u' => array_fill(0, 11, 0), 'U' => array_fill(0, 10, 0), 'side' => 'off', 'hero' => [], 'B' => 20, 'C' => true];
$accountWideBrewery['u'][0] = 100;
$accountWide = $offense->invokeArgs($simulator, [&$accountWideBrewery]);
$finalize->invokeArgs($simulator, [&$accountWide]);
$withoutAccountWideBrewery = $accountWideBrewery;
unset($withoutAccountWideBrewery['C']);
$withoutAccountWide = $offense->invokeArgs($simulator, [&$withoutAccountWideBrewery]);
$finalize->invokeArgs($simulator, [&$withoutAccountWide]);
if (abs(($accountWide['i'] / $withoutAccountWide['i']) - 1.2) > 0.00001) {
    failSimulatorContract('The simulator must apply an active Brewery account-wide, independent of the selected tribe.');
}
$defWave = ['r' => 0, 'u' => array_fill(0, 11, 0), 'U' => array_fill(0, 10, 0), 'side' => 'def', 'hero' => [], 'M' => 1.1];
$defWave['u'][0] = 100;
$defWaves = [0 => $defWave];
$def = $defense->invokeArgs($simulator, [&$defWaves]);
if (abs($def['i'] - 3850) > 0.00001) {
    failSimulatorContract('The simulator must apply Metallurgy to defensive troop strength.');
}

$heroWave = ['r' => 0, 'u' => array_fill(0, 11, 0), 'U' => array_fill(0, 10, 0), 'side' => 'off', 'h' => true,
    'hero' => ['health' => 100, 'total_power' => 100, 'offBonus' => 5, 'arm' => 0, 'bandage' => ['num' => 0, 'eff' => 0], 'cages' => 0, 'isCavalry' => false]];
$heroWave['u'][0] = 100;
$heroWave['u'][10] = 1;
$heroOffense = $offense->invokeArgs($simulator, [&$heroWave]);
$finalize->invokeArgs($simulator, [&$heroOffense]);
if (abs($heroOffense['i'] - 4141) > 0.00001) {
    failSimulatorContract('Five raw hero Off-bonus points must add one percent after the hero strength is included.');
}
$heroDefenseWave = ['r' => 0, 'u' => array_fill(0, 11, 0), 'U' => array_fill(0, 10, 0), 'side' => 'def', 'h' => true,
    'hero' => ['health' => 100, 'total_power' => 100, 'defBonus' => 5, 'arm' => 0]];
$heroDefenseWave['u'][10] = 1;
$heroDefenders = [0 => $heroDefenseWave];
$heroDefense = $defense->invokeArgs($simulator, [&$heroDefenders]);
if (abs($heroDefense['i'] - 101) > 0.00001 || abs($heroDefense['c'] - 101) > 0.00001) {
    failSimulatorContract('Five raw hero Defence-bonus points must add one percent on the defending hero.');
}
$weaponBonusWave = ['r' => 0, 'u' => array_fill(0, 11, 0), 'hero' => ['unitBonus' => ['unitId' => 1, 'power' => 3]]];
$weaponBonusWave['u'][10] = 1;
$weaponBonus = $itemBonus->invoke($simulator, $weaponBonusWave);
if (($weaponBonus[0] ?? 0) !== 3) {
    failSimulatorContract('A matching Roman right-hand weapon must add its unit bonus to the canonical simulator wave.');
}
$noHeroWeaponBonus = $itemBonus->invoke($simulator, ['r' => 0, 'u' => array_fill(0, 11, 0), 'hero' => ['unitBonus' => ['unitId' => 1, 'power' => 3]]]);
if (array_sum($noHeroWeaponBonus) !== 0) {
    failSimulatorContract('A right-hand troop bonus must never apply when the wave has no hero.');
}
$egyptianWeaponProperties = (new \Game\Hero\HeroItems())->getHeroItemProperties(4, 115);
$egyptianWeaponWave = ['r' => 5, 'u' => array_fill(0, 11, 0), 'hero' => ['unitBonus' => ['unitId' => $egyptianWeaponProperties['unitId'], 'power' => $egyptianWeaponProperties['unit_power']]]];
$egyptianWeaponWave['u'][10] = 1;
$egyptianWeaponBonus = $itemBonus->invoke($simulator, $egyptianWeaponWave);
if (($egyptianWeaponBonus[0] ?? 0) !== $egyptianWeaponProperties['unit_power']) {
    failSimulatorContract('The current simulator must no longer suppress right-hand bonuses for newer playable tribes.');
}
$heroKill = $reflection->getMethod('hero_check_kill');
$unarmouredHero = ['u' => array_fill(0, 11, 0), 'hero' => ['health' => 10, 'arm' => 0]];
$unarmouredHero['u'][10] = 1;
$armouredHero = ['u' => array_fill(0, 11, 0), 'hero' => ['health' => 10, 'arm' => 10]];
$armouredHero['u'][10] = 1;
if ($heroKill->invokeArgs($simulator, [&$unarmouredHero, .15]) !== 1
    || $heroKill->invokeArgs($simulator, [&$armouredHero, .15]) !== 0) {
    failSimulatorContract('Hero resistance must be supplied as `arm` and reduce simulator death damage.');
}
if (abs($artefact->invoke($simulator, ['scout' => 0.33], 'scout', 10) - 0.33) > 0.00001
    || abs($artefact->invoke($simulator, ['scout' => 5], 'scout', 10) - 5) > 0.00001) {
    failSimulatorContract('The simulator must preserve Better Spies multiplier and inverse Fool values.');
}
if (!str_contains($translations, "['simulatorDescription'] = 'You are unsure about winning a battle? You want to know how many losses or resources you can expect? Just choose your army, the defender and reinforcements (not mandatory), and find out!'")
    || !str_contains($translations, "['needAttackerData'] = 'Please provide more data for the attacker first.'")) {
    failSimulatorContract('The simulator must use the captured empty-state copy and tooltip.');
}
foreach ([
    '"name" => "Large Shield"',
    '"name" => "Small Horn of the Natarian"',
    '"name" => "Huge Horn of the Natarian"',
    '"name" => "Armor of Regeneration"',
    '"name" => "Heavy Scale Armor"',
    '"name" => "Heavy Breastplate"',
    '"name" => "Heavy Segmented Armor"',
    '"name" => "Bandage"',
] as $referenceEquipmentName) {
    if (!str_contains($translations, $referenceEquipmentName)) {
        failSimulatorContract("The classic simulator catalogue must use the current English item name: $referenceEquipmentName");
    }
}

$resultReport = $battle(1, 1, [0 => 1], [], 0);
if (!isset($resultReport[0]['combatStrength']['attacker'], $resultReport[0]['combatStrength']['defender'])
    || (int)$resultReport[0]['combatStrength']['attacker'] !== 40
    || (int)$resultReport[0]['combatStrength']['defender'] !== 10) {
    failSimulatorContract('A standard simulator result must expose the finalized strength values rendered by the native statistics card.');
}

$rendererReflection = new ReflectionClass(Combat::class);
$renderer = $rendererReflection->newInstanceWithoutConstructor();
$rendererState = $rendererReflection->getProperty('output');
$rendererState->setValue($renderer, [
    'role' => 'attacker',
    'attacker' => [
        'race' => 1,
        'hero' => true,
        'units' => array_merge([['number' => 8, 'level' => 0]], array_fill(0, 9, ['number' => 0, 'level' => 0])),
    ],
    'defender' => [
        'main' => 1,
        'races' => [
            1 => ['units' => array_merge([['number' => 6, 'level' => 0]], array_fill(0, 9, ['number' => 0, 'level' => 0]))],
        ],
    ],
]);
$renderResult = $rendererReflection->getMethod('renderResult');
$renderedSpecialResult = $renderResult->invoke($renderer, [[
    'losses' => [.5, .5],
    'trapped' => [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
    'caged' => [2, 0, 0, 0, 0, 0, 0, 0, 0, 0],
    'revived' => ['off' => [1, 0, 0, 0, 0, 0, 0, 0, 0, 0]],
    'dead_heroes' => ['off' => 1, 'def' => [0 => 0]],
    'combatStrength' => ['attacker' => 280, 'defender' => 250],
    'info' => [
        'isSpy' => true,
        'none_return' => [''],
        'free' => [['sum' => 2]],
        'loy' => [20, 30],
        'wall' => [20, 18],
        'bl' => [10, 8],
    ],
]]);
foreach (['troopsInitial', 'troopsLoss', 'troopsWounded', 'troopsResult', 'combatStatistics', 'Scouting report', 'Troops trapped: 1', 'Troops captured: 2', 'Troops freed: 2', 'Loyalty reduced by 20-30%', 'Ram damage', 'Catapult damage'] as $needle) {
    if (!str_contains((string)$renderedSpecialResult, $needle)) {
        failSimulatorContract("The native result renderer must preserve special simulator state: $needle");
    }
}

$rendererState->setValue($renderer, [
    'role' => 'attacker',
    'attacker' => [
        'race' => 1,
        'hero' => false,
        'units' => array_fill(0, 10, ['number' => 0, 'level' => 0]),
    ],
    'defender' => [
        'main' => 7,
        // This is the native wave order: do not turn it into numeric tribe order.
        'races' => [
            7 => ['units' => array_fill(0, 10, ['number' => 0, 'level' => 0])],
            1 => ['units' => array_fill(0, 10, ['number' => 0, 'level' => 0])],
        ],
    ],
]);
$orderedResult = $renderResult->invoke($renderer, [[
    'losses' => [0, 0],
    'dead_heroes' => ['off' => 0, 'def' => [0 => 0, 1 => 0]],
]]);
$mainCard = strpos((string)$orderedResult, 'result defence tribe7');
$reinforcementCard = strpos((string)$orderedResult, 'result defence reinforcement tribe1');
if ($mainCard === false || $reinforcementCard === false || $mainCard > $reinforcementCard
    || !str_contains((string)$orderedResult, '>Defender<')
    || !str_contains((string)$orderedResult, '>Reinforcement<')) {
    failSimulatorContract('Main defender and reinforcement cards must preserve the simulator wave order and reference labels.');
}

if (str_contains($controller, 'ksort($this->output[\'defender\'][\'races\']')) {
    failSimulatorContract('Defender waves must never be sorted after the main village is selected.');
}
if (!str_contains($controller, "'arm' => self::boundedPostInt") || str_contains($controller, '"armor"       => 0')) {
    failSimulatorContract('The Rally Point controller must use Simulator\'s canonical hero `arm` field.');
}

echo "Combat simulator tribe contract passed.\n";
