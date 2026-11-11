#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: every tribe is implemented as data, and which tribes are playable
 * is a world setting.
 *
 * Spartan values: manual capture 2026-10-30 (x2 world: stored time is the
 * displayed time * 2, stored speed the displayed speed / 2). Viking combat,
 * cost, upkeep, speed, and training values: Knowledge Base article 139 (x1).
 * That official table does not publish carrying capacity; the separately
 * documented carry values remain an unverified compatibility dataset. Building
 * 46-48 and 50 cost/time/pop/CP: the Knowledge Base building data
 * (the Travian knowledge base), see docs/research/travian-tribe-data.md.
 */

use Core\Config;
use Game\Formulas;
use Game\Tribes;

function failTribesContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function assertTribes(bool $condition, string $message): void
{
    if (!$condition) {
        failTribesContract($message);
    }
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

// ---- the playable-tribe setting ------------------------------------------------
assertTribes(Tribes::parse('1, 2,3,3,4,5,8,9,x,0,10') === [1, 2, 3, 8, 9], 'Only selectable tribe ids survive parsing (Nature and Natars never do).');
assertTribes(Tribes::serialize([9, 1, 8]) === '1,8,9', 'The stored form is the sorted comma list.');
assertTribes(Tribes::DEFAULT_PLAYABLE === [1, 2, 3, 6, 7, 8], 'The default matches playableTribeIds (Vikings off).');
$config = Config::getInstance();
$original = $config->dynamic->playableTribes ?? null;
try {
    $config->dynamic->playableTribes = '1,9,8';
    assertTribes(Tribes::playable() === [1, 8, 9], 'Playable tribes follow the world setting in picker order.');
    assertTribes(Tribes::isPlayable(9) && Tribes::vikingsEnabled() && !Tribes::isPlayable(3), 'Vikings are enabled only when the setting lists tribe 9.');
    $config->dynamic->playableTribes = '';
    assertTribes(Tribes::playable() === [3, 1, 2, 6, 7, 8], 'An empty setting falls back to the default.');
} finally {
    $config->dynamic->playableTribes = $original;
}

$read = static fn (string $path): string => (string)file_get_contents($root . '/' . $path);
foreach ([
    'game/include/config.php', 'game/include/Game/Hero/HeroItems.php',
    'game/include/Controller/ActivateCtrl.php', 'game/include/Model/RegisterModel.php',
    'game/include/resources/Templates/statistics/general.php', 'game/include/admin/include/Controllers/ConfigurationDetailsCtrl.php',
] as $file) {
    assertTribes(!str_contains($read($file), 'allowNewTribes'), "$file still reads the retired allowNewTribes flag.");
}
$world = $read('database/world.sql');
foreach (['`playableTribes` VARCHAR(20)', '`spartans_players_count`', '`vikings_players_count`', 'CREATE TABLE `wounded`'] as $column) {
    assertTribes(str_contains($world, $column), "world.sql must define $column.");
}
$setup = $read('game/deployment/public/setup.php') . $read('game/deployment/include/setup-view.php') . $read('game/include/Game/Installer/InstallInput.php') . $read('game/include/Game/Installer/WorldInstaller.php');
foreach (['name="playable_tribes[]"', 'playableTribes', 'Select at least one playable tribe.'] as $needle) {
    assertTribes(str_contains($setup, $needle), "The installer must handle the playable tribes ($needle).");
}
assertTribes(str_contains($read('game/include/Controller/ActivateCtrl.php'), '\\Game\\Tribes::playable()'), 'Activation must offer the playable tribes.');
assertTribes(str_contains($read('game/include/Model/RegisterModel.php'), '\\Game\\Tribes::playable()'), 'Bot registration must draw from the playable tribes.');
assertTribes(str_contains($read('game/include/admin/include/Controllers/ConfigurationDetailsCtrl.php'), 'savePlayableTribes'), 'The admin panel must edit the playable tribes.');
$activationWizard = $read('game/include/resources/Templates/activation/wizard.php');
assertTribes(str_contains($activationWizard, 'foreach ($tribes as $index => $id)'), 'The activation wizard must render one card per playable tribe.');

// ---- unit ids and training buildings -------------------------------------------
foreach ([71 => 8, 80 => 8, 81 => 9, 90 => 9, 70 => 7, 91 => 0] as $unitId => $tribe) {
    assertTribes(unitIdToTribe($unitId) === $tribe, "Unit $unitId belongs to tribe $tribe.");
}
foreach ([
    [1, 19, [1, 2, 3]], [1, 20, [4, 5, 6]], [2, 19, [11, 12, 13, 14]], [2, 20, [15, 16]], [3, 19, [21, 22]], [3, 20, [23, 24, 25, 26]],
    [5, 19, [41, 42, 43, 44]], [5, 20, [45, 46]], [6, 19, [51, 52, 53]], [7, 19, [61, 62]], [7, 20, [63, 64, 65, 66]],
    [8, 19, [71, 72, 73, 74]], [8, 20, [75, 76]], [8, 21, [77, 78]], [8, 26, [79, 80]],
    [9, 19, [81, 82, 83, 84]], [9, 20, [85, 86]], [9, 21, [87, 88]], [9, 44, [89, 90]],
] as [$tribe, $building, $expected]) {
    assertTribes(Tribes::trainingUnitIds($tribe, $building) === $expected, "Tribe $tribe trains " . implode(',', $expected) . " in building $building.");
}
assertTribes(Tribes::isCavalry(8, 5) && Tribes::isCavalry(8, 6) && !Tribes::isCavalry(8, 4) && !Tribes::isCavalry(9, 4) && Tribes::isCavalry(7, 3), 'Cavalry slots per tribe.');

// ---- unit data -------------------------------------------------------------------
// [id => [off, def_i, def_c, base speed, cost, upkeep, base training seconds, carry]]
$expectedUnits = [
    24 => [100, 25, 40, 19, [350, 450, 230, 60], 2, 2480, 75],
    26 => [140, 60, 165, 13, [500, 620, 675, 170], 3, 3120, 65],
    52 => [30, 55, 40, 6, [115, 100, 145, 60], 1, 1380, 50],
    56 => [110, 120, 150, 10, [450, 560, 610, 180], 3, 3240, 70],
    57 => [55, 30, 95, 4, [995, 575, 340, 80], 3, 4800, 0],
    64 => [120, 30, 15, 16, [290, 370, 190, 45], 2, 2400, 75],
    65 => [110, 80, 70, 15, [320, 350, 330, 50], 2, 2480, 105],
    69 => [50, 40, 30, 5, [37200, 27600, 25200, 27600], 4, 90700, 0],
    70 => [10, 80, 80, 5, [6100, 4600, 4800, 5400], 1, 28950, 3000],
    71 => [50, 35, 30, 6, [110, 185, 110, 35], 1, 1700, 60],
    72 => [0, 40, 22, 9, [185, 150, 35, 75], 1, 1232, 0],
    73 => [40, 85, 45, 8, [145, 95, 245, 45], 1, 1936, 40],
    74 => [90, 55, 40, 6, [130, 200, 400, 65], 1, 2112, 50],
    75 => [55, 120, 90, 16, [555, 445, 330, 110], 2, 2816, 110],
    76 => [195, 80, 75, 9, [660, 495, 995, 165], 3, 3432, 80],
    77 => [65, 30, 80, 4, [525, 260, 790, 130], 3, 4620, 0],
    78 => [50, 60, 10, 3, [550, 1240, 825, 135], 6, 9000, 0],
    79 => [40, 60, 40, 4, [33450, 30665, 36240, 13935], 4, 77550, 0],
    80 => [10, 80, 80, 5, [5115, 5580, 6045, 3255], 1, 34100, 3000],
    81 => [45, 22, 5, 7, [95, 80, 50, 40], 1, 800, 55],
    82 => [20, 50, 30, 7, [125, 70, 85, 40], 1, 1080, 40],
    83 => [70, 30, 25, 5, [235, 220, 200, 70], 2, 1550, 75],
    84 => [0, 10, 5, 9, [155, 95, 50, 50], 1, 1120, 0],
    85 => [45, 95, 100, 12, [385, 295, 290, 85], 2, 2650, 110],
    86 => [160, 50, 75, 9, [475, 535, 515, 100], 2, 3060, 80],
    87 => [65, 30, 80, 4, [950, 325, 375, 70], 3, 4200, 0],
    88 => [50, 60, 10, 3, [850, 1225, 625, 60], 6, 9000, 0],
    89 => [40, 40, 60, 5, [35500, 26600, 25000, 27200], 4, 70500, 0],
    90 => [10, 80, 80, 5, [5800, 4600, 4800, 4800], 1, 31000, 3000],
];
foreach ($expectedUnits as $unitId => [$off, $defI, $defC, $speed, $cost, $upkeep, $time, $carry]) {
    $unit = Formulas::$data['units'][unitIdToTribe($unitId) - 1][unitIdToNr($unitId) - 1] ?? null;
    assertTribes(is_array($unit), "Unit $unitId must have data.");
    assertTribes(
        [$unit['off'], $unit['def_i'], $unit['def_c'], $unit['speed'], array_values($unit['cost']), $unit['cu'], $unit['time'], $unit['cap']] === [$off, $defI, $defC, $speed, $cost, $upkeep, $time, $carry],
        "Unit $unitId must match the captured stats."
    );
}
assertTribes(Formulas::$data['units'][7][8]['loyality'] === [20, 25] && Formulas::$data['units'][8][8]['loyality'] === [15, 30], 'Ephor lowers loyalty by 20-25%, Jarl by 15-30%.');
assertTribes(Formulas::getSpyId(8) === 2 && Formulas::getSpyId(9) === 4 && Formulas::getSpyId(3) === 3 && Formulas::getSpyId(7) === 3 && Formulas::getSpyId(1) === 4, 'Scout slot per tribe.');
assertTribes(Formulas::getWallID(8) === 47 && Formulas::getWallID(9) === 50, 'Spartans wall with gid 47, Vikings with gid 50.');

// ---- combat consumers must read the canonical unit data -------------------------
assertTribes(!is_file($root . '/game/include/Game/BattleCalculator.php'), 'The unreferenced BattleCalculator legacy duplicate must stay removed.');
foreach ([
    'game/include/Model/BattleModel.php',
    'game/include/Controller/RallyPoint/Simulator.php',
] as $file) {
    $source = $read($file);
    assertTribes(
        !preg_match('/(?:protected|private|public)\\s+\\$(?:off|def_i|def_c|upkeep)\\s*=\\s*\\[/', $source)
        && str_contains($source, "Formulas::\$data['units']"),
        "$file must not retain duplicate unit-stat tables."
    );
}

$battle = new ReflectionClass(\Model\BattleModel::class);
$battleInstance = $battle->newInstanceWithoutConstructor();
$battleUnitStat = $battle->getMethod('unitStat');
foreach (range(1, 9) as $tribe) {
    foreach (range(1, 10) as $number) {
        foreach (['off', 'def_i', 'def_c', 'cu'] as $stat) {
            assertTribes(
                $battleUnitStat->invoke($battleInstance, $tribe, $number, $stat) === Formulas::$data['units'][$tribe - 1][$number - 1][$stat],
                "BattleModel must read canonical $stat for tribe $tribe unit $number."
            );
        }
    }
}

$simulator = new ReflectionClass(\Controller\RallyPoint\Simulator::class);
$simulatorInstance = $simulator->newInstanceWithoutConstructor();
$simulatorUnitStat = $simulator->getMethod('unitStat');
foreach (range(0, 8) as $race) {
    foreach (range(0, 9) as $number) {
        foreach (['off', 'def_i', 'def_c', 'cu'] as $stat) {
            assertTribes(
                $simulatorUnitStat->invoke($simulatorInstance, $race, $number, $stat) === Formulas::$data['units'][$race][$number][$stat],
                "Simulator must read canonical $stat for race $race unit $number."
            );
        }
    }
}

// ---- buildings (Knowledge Base data, x1) --------------------------------------------
assertTribes(Formulas::$data['buildings'][36]['breq'] == [15 => 3, 16 => 1], "Hero's Mansion needs Main Building 3 and Rally Point 1 (kirilloid).");
foreach ([1, 2, 3, 4] as $field) {
    assertTribes(!array_key_exists('maxLvl', Formulas::$data['buildings'][$field - 1]), "Resource field $field keeps its maximum in buildingMaxLvl only.");
    assertTribes(Formulas::buildingMaxLvl($field, true, false) === 20 && Formulas::buildingMaxLvl($field, false) === 10, "Resource field $field maximum: 20 in the capital (planning), 10 elsewhere.");
}
$originalSpeed = $config->game->speed;
try {
    $config->game->speed = 1;
    // [gid => [level 1 cost, level 20 cost, level 1 seconds, level 20 seconds, level 1 pop, level 20 pop, level 1 CP, level 20 CP]]
    foreach ([
        46 => [[320, 280, 420, 360], [34845, 30490, 45735, 39200], 3000, 79910, 4, 62, 5, 153],
        47 => [[160, 100, 80, 60], [17420, 10890, 8710, 6535], 2000, 63130, 0, 20, 1, 38],
        48 => [[320, 280, 420, 360], [34845, 30490, 45735, 39200], 3000, 79910, 4, 62, 6, 192],
        50 => [[110, 170, 70, 50], [11980, 18510, 7620, 5445], 2000, 63130, 0, 20, 1, 38],
    ] as $gid => [$cost1, $cost20, $time1, $time20, $pop1, $pop20, $cp1, $cp20]) {
        assertTribes(array_map('intval', Formulas::buildingUpgradeCosts($gid, 1)) === $cost1, "Building $gid level 1 cost.");
        assertTribes(array_map('intval', Formulas::buildingUpgradeCosts($gid, 20)) === $cost20, "Building $gid level 20 cost.");
        assertTribes((int)Formulas::buildingUpgradeTime($gid, 1, 1) === $time1, "Building $gid level 1 time.");
        assertTribes(abs(Formulas::buildingUpgradeTime($gid, 20, 1) - $time20) <= 10, "Building $gid level 20 time.");
        $population20 = 0;
        for ($level = 1; $level <= 20; ++$level) {
            $population20 += (int)Formulas::buildingCropConsumption($gid, $level);
        }
        assertTribes((int)Formulas::buildingCropConsumption($gid, 1) === $pop1 && $population20 === $pop20, "Building $gid population.");
        assertTribes((int)Formulas::buildingCP($gid, 1) === $cp1 && (int)Formulas::buildingCP($gid, 20) === $cp20, "Building $gid culture points.");
    }
    assertTribes(
        array_map('intval', Formulas::buildingUpgradeCosts(49, 1)) === [1440, 1370, 1290, 495]
        && (int)Formulas::buildingUpgradeTime(49, 1, 1) === 4000
        && (int)Formulas::buildingCropConsumption(49, 1) === 3
        && (int)Formulas::buildingCP(49, 1) === 5
        && (Formulas::$data['buildings'][48]['req']['shore'] ?? false) === true,
        'Harbor level 1 cost, time, population, culture points and shore prerequisite must match the official calculator.'
    );
} finally {
    $config->game->speed = $originalSpeed;
}
$hospital = Formulas::$data['buildings'][45]['req']['races'];
assertTribes($hospital === [1, 2, 3, 6, 7, 9] && Formulas::$data['buildings'][47]['req']['race'] === 8 && Formulas::$data['buildings'][46]['req']['race'] === 8 && Formulas::$data['buildings'][49]['req']['race'] === 9 && (int)Formulas::$data['buildings'][49]['gid'] === 50, 'Hospital is for every tribe but Spartans; the Asclepeion and defensive wall are Spartan; the barricade (gid 50, key 49) is Viking.');
assertTribes((int)Formulas::wallPower(8, 20) === 49 && (int)Formulas::wallPower(9, 20) === 35, 'Wall bonus at level 20 (Spartan 48.6%, Viking 34.7%).');
assertTribes((int)(Formulas::merchantSpeed(8) / getGameSpeed()) === 14 && (int)(Formulas::merchantSpeed(9) / getGameSpeed()) === 18, 'Spartan merchants travel 14 fields/hour, Viking merchants 18 (× economic speed).');

echo "Tribes contract passed.\n";
