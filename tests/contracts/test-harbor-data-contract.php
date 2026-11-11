#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: Harbor (gid 49) building data, ship data and the schema that stores them.
 *
 * Harbor cost/time/population/culture points/ship cap: Knowledge Base building
 * data (the Travian knowledge-base main.js, read 2026-10-31). Ship costs,
 * unit capacities and speeds: Knowledge Base articles 24, 25 and 198. Prerequisites
 * (shore village, Main Building 10, Rally Point 10) are the project owner's
 * decision; the Knowledge Base tool lists only the shore, article 198 adds the
 * two buildings. See docs/research/travian-harbor-data.md.
 */

use Core\Config;
use Game\Buildings\BuildingHelper;
use Game\Formulas;
use Game\Ships;

function assertHarbor(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$config = Config::getInstance();
$originalSpeed = $config->game->speed;
try {
    $config->game->speed = 1;
    $gid = 49;
    assertHarbor((Formulas::$data['buildings'][48]['name'] ?? '') === 'Harbor' && (int)Formulas::$data['buildings'][48]['gid'] === $gid, 'The Harbor is gid 49 at key 48.');
    assertHarbor(array_map('intval', Formulas::buildingUpgradeCosts($gid, 1)) === [1440, 1370, 1290, 495], 'Harbor level 1 cost.');
    assertHarbor(array_map('intval', Formulas::buildingUpgradeCosts($gid, 2)) === [1870, 1780, 1675, 645], 'Harbor level 2 cost.');
    assertHarbor(array_map('intval', Formulas::buildingUpgradeCosts($gid, 20)) === [210515, 200285, 188590, 72365], 'Harbor level 20 cost.');
    assertHarbor((int)Formulas::buildingUpgradeTime($gid, 1, 1) === 4000 && (int)Formulas::buildingUpgradeTime($gid, 2, 1) === 4940, 'Harbor level 1 and 2 time.');
    assertHarbor(abs(Formulas::buildingUpgradeTime($gid, 20, 1) - 96687) <= 10, 'Harbor level 20 time.');
    $population = 0;
    foreach ([1 => 3, 2 => 5, 10 => 21, 20 => 51] as $level => $expected) {
        $population = 0;
        for ($i = 1; $i <= $level; ++$i) {
            $population += (int)Formulas::buildingCropConsumption($gid, $i);
        }
        assertHarbor($population === $expected, "Harbor level $level population.");
    }
    assertHarbor((int)Formulas::buildingCP($gid, 1) === 5 && (int)Formulas::buildingCP($gid, 20) === 153, 'Harbor culture points.');

    // ---- ships ---------------------------------------------------------------------
    $cap = array_map([Ships::class, 'maxShips'], range(0, 21));
    assertHarbor($cap[0] === 0 && $cap[1] === 1 && $cap[3] === 6 && $cap[10] === 55 && $cap[20] === 210 && $cap[21] === 210, 'Harbor ship cap (1 at level 1, 210 at level 20).');
    assertHarbor(Ships::cost(Ships::TRADE) === [2835, 1235, 1985, 750] && Ships::cost(Ships::WAR) === [18500, 8355, 12275, 7500], 'Trade ship and warship cost.');
    assertHarbor(Ships::cost(Ships::DECOY) === [950, 350, 750, 350] && Ships::cost(Ships::RAID) === [750, 450, 450, 150], 'Decoy warship and raid ship cost (raid crop 150, article 198).');
    assertHarbor(Ships::buildTime(Ships::TRADE, 1) === 4500 && Ships::buildTime(Ships::WAR, 1) === 13500 && Ships::buildTime(Ships::DECOY, 1) === 2100 && Ships::buildTime(Ships::RAID, 1) === 1200, 'Ship build times at x1 (1:15:00, 3:45:00, 0:35:00, 0:20:00).');
    assertHarbor(Ships::buildTime(Ships::RAID, 11) < Ships::buildTime(Ships::RAID, 1), 'A higher Harbor level builds ships faster.');
    assertHarbor(Ships::unitCapacity(Ships::DECOY) === 60 && Ships::unitCapacity(Ships::RAID) === 200 && Ships::unitCapacity(Ships::WAR) > 1000000, 'Decoy warships carry 60 units, raid ships 200, warships any army.');
    foreach ([1 => 2500, 2 => 3000, 3 => 2750, 6 => 2500, 7 => 3000, 8 => 2750, 9 => 3000] as $tribe => $capacity) {
        assertHarbor(Ships::tradeCapacity($tribe, 0) === (int)round($capacity * Formulas::TradeOfficeValue($tribe, 0) / 100), "Trade ship capacity of tribe $tribe at x1.");
    }
    $speedFactor = Config::getProperty('game', 'movement_speed_increase');
    assertHarbor(Ships::deepWaterSpeed(Ships::WAR, 2) / $speedFactor === 18.0 && Ships::deepWaterSpeed(Ships::RAID, 3) / $speedFactor === 18.0 && Ships::deepWaterSpeed(Ships::RAID, 9) / $speedFactor === 24.0 && Ships::deepWaterSpeed(Ships::WAR, 9) / $speedFactor === 18.0 && Ships::deepWaterSpeed(Ships::TRADE, 9) / getGameSpeed() === 18.0, 'Deep-water speed is 18 fields/hour (trade ships times the economic speed, the others times the movement multiplier), Viking raid ships 24.');
} finally {
    $config->game->speed = $originalSpeed;
}

// ---- prerequisites ---------------------------------------------------------------
$helper = new BuildingHelper();
$field = static fn (int $gid, int $level): array => ['item_id' => $gid, 'level' => $level];
$ready = [$field(15, 10), $field(16, 10)];
assertHarbor($helper->canCreateNewBuild(false, 9, 49, $ready, false, true) === 1, 'The Harbor can be built on a shore with Main Building 10 and Rally Point 10.');
assertHarbor($helper->canCreateNewBuild(false, 1, 49, $ready, false, true) === 1, 'Every tribe may build the Harbor on a shore.');
assertHarbor($helper->canCreateNewBuild(false, 9, 49, $ready, false, false) === -1, 'An inland village can never build the Harbor.');
assertHarbor($helper->canCreateNewBuild(false, 9, 49, [$field(15, 9), $field(16, 10)], false, true) === 0, 'Main Building 9 is not enough.');
assertHarbor($helper->canCreateNewBuild(false, 9, 49, [$field(15, 10), $field(16, 9)], false, true) === 0, 'Rally Point 9 is not enough.');
assertHarbor($helper->canCreateNewBuild(false, 9, 49, array_merge($ready, [$field(49, 1)]), false, true) === -1, 'Only one Harbor per village.');

// ---- schema -----------------------------------------------------------------------
$world = (string)file_get_contents($root . '/database/world.sql');
foreach ([
    '`deepwater` TINYINT(1) UNSIGNED', '`shore` TINYINT(1) UNSIGNED', '`ship1` BIGINT UNSIGNED', '`ship4` BIGINT UNSIGNED',
    '`ships` SMALLINT(5) UNSIGNED', '`useShip` TINYINT(1) UNSIGNED', '`ship` TINYINT(1) UNSIGNED',
] as $column) {
    assertHarbor(str_contains($world, $column), "world.sql must define $column.");
}
assertHarbor(substr_count($world, '`ship` TINYINT(1) UNSIGNED') === 1, 'Only the movement carries the ship type.');

echo "Harbor data contract passed.\n";
