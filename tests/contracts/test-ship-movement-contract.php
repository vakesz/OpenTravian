#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: ship movement.
 *
 * Deep water is crossed at the ship's fixed speed, land at the army's own speed
 * (Knowledge Base article 25), along the flat straight line (no shortcut over the world edge); ships choose decoy warship, warship or raid ship
 * by army size and movement type (article 198); a ship comes home with its army,
 * at once when the army stays, and is lost with a destroyed Harbor (article 24).
 */

use Core\Database\DB;
use Game\Formulas;
use Game\Map\ShipRoute;
use Game\Ships;
use Game\SpeedCalculator;

function assertMovement(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$db = DB::getInstance();

// ---- routes over water --------------------------------------------------------------
$water = $db->query('SELECT x, y FROM wdata WHERE deepwater=1 AND x BETWEEN -80 AND 80 AND y BETWEEN -80 AND 80 ORDER BY id LIMIT 400');
$found = null;
while ($tile = $water->fetch_assoc()) {
    $from = Formulas::xy2kid((int)$tile['x'] - 12, (int)$tile['y']);
    $to = Formulas::xy2kid((int)$tile['x'] + 12, (int)$tile['y']);
    $fraction = ShipRoute::deepFraction($from, $to);
    if ($fraction > 0.2 && $fraction < 0.9) {
        $found = [$from, $to, $fraction];
        break;
    }
}
assertMovement($found !== null, 'The world needs a route that is partly over deep water.');
[$from, $to, $fraction] = $found;
assertMovement(ShipRoute::deepFraction($from, $from) === 0.0 || ShipRoute::deepFraction($from, $from) === 1.0, 'A zero-length route is all land or all water.');
$dry = Formulas::xy2kid(0, 0);
assertMovement(ShipRoute::deepFraction($dry, Formulas::xy2kid(3, 3)) === 0.0, 'Routes near the capital stay on land.');

$landSpeed = 6 * Core\Config::getProperty('game', 'movement_speed_increase');
$distance = Formulas::getDistance($from, $to);
$calculator = new SpeedCalculator();
$calculator->setFrom($from);
$calculator->setTo($to);
$calculator->setMinSpeed($landSpeed);
$byLand = $calculator->calc();
$calculator->setShip(Ships::WAR, 2);
$bySea = $calculator->calc();
$deep = $distance * $fraction;
$expected = round($deep / Ships::deepWaterSpeed(Ships::WAR, 2) * 3600 + ($distance - $deep) / $landSpeed * 3600);
assertMovement($bySea === (float)$expected, "Sailing takes deep/shipSpeed + land/armySpeed (expected $expected, got $bySea).");
assertMovement($bySea < $byLand, 'A slow army is faster by ship over water.');
$calculator->setShip(0, 0);
assertMovement($calculator->calc() === $byLand, 'Without a ship the army walks.');

// ---- the map is flat for ships --------------------------------------------------------
// The world wraps for armies and merchants, but a ship sails the straight line across the map: 95|0 to -95|0 is 190
// fields by ship and 11 on foot, and the line never leaves the map.
$west = Formulas::xy2kid(-95, 0);
$east = Formulas::xy2kid(95, 0);
assertMovement(Formulas::getFlatDistance($west, $east) === 190.0 && Formulas::getDistance($west, $east) < 12.0, 'The flat distance ignores the world wrap while the walking distance uses it.');
$flatFraction = ShipRoute::deepFraction($west, $east);
$calculator = new SpeedCalculator();
$calculator->setFrom($west);
$calculator->setTo($east);
$calculator->setMinSpeed($landSpeed);
$calculator->setShip(Ships::WAR, 2);
$flatDeep = 190.0 * $flatFraction;
assertMovement(
    $calculator->calc() === (float)round($flatDeep / Ships::deepWaterSpeed(Ships::WAR, 2) * 3600 + (190.0 - $flatDeep) / $landSpeed * 3600),
    'An army on a ship sails the flat route, not the short way over the world edge.'
);
$merchantSpeed = Formulas::merchantSpeed(9);
assertMovement(
    Ships::tradeTravelSeconds($west, $east, 9, true) === (int)round($flatDeep / Ships::deepWaterSpeed(Ships::TRADE, 9) * 3600 + (190.0 - $flatDeep) / $merchantSpeed * 3600),
    'Trade ships sail the flat route too, while merchants still use the wrapped distance.'
);
assertMovement(Ships::tradeTravelSeconds($west, $east, 9, false) === (int)round(Formulas::getDistance($west, $east) / $merchantSpeed * 3600), 'Merchants walk the wrapped distance.');
assertMovement(!str_contains((string)file_get_contents($root . '/game/include/Game/Map/ShipRoute.php'), 'Formulas::delta'), 'ShipRoute must not wrap the line over the world edge.');

// ---- choosing a ship ----------------------------------------------------------------
$kid = (int)$db->fetchScalar('SELECT kid FROM units WHERE race=4 ORDER BY kid LIMIT 1');
$row = $db->query("SELECT ship1, ship2, ship3, ship4 FROM units WHERE kid=$kid")->fetch_assoc();
try {
    $db->query("UPDATE units SET ship1=1, ship2=1, ship3=1, ship4=1 WHERE kid=$kid");
    assertMovement(Ships::pickForArmy($kid, 4, 150) === Ships::RAID, 'A raid of up to 200 units takes the raid ship.');
    assertMovement(Ships::pickForArmy($kid, 4, 201) === Ships::WAR, 'A raid of more than 200 units takes the warship.');
    assertMovement(Ships::pickForArmy($kid, 3, 60) === Ships::DECOY, 'An attack of up to 60 units takes the decoy warship first.');
    assertMovement(Ships::pickForArmy($kid, 3, 61) === Ships::WAR && Ships::pickForArmy($kid, 2, 5000) === Ships::WAR, 'Larger armies take the warship; raid ships never carry attacks or reinforcements.');
    $db->query("UPDATE units SET ship1=0, ship2=0, ship3=0, ship4=1 WHERE kid=$kid");
    assertMovement(Ships::pickForArmy($kid, 3, 10) === 0 && Ships::pickForArmy($kid, 4, 10) === Ships::RAID, 'Only a raid ship waits: raids sail, attacks walk.');
    assertMovement(Ships::take($kid, Ships::RAID) && !Ships::take($kid, Ships::RAID), 'Taking the last ship works once.');
    Ships::restore($kid, Ships::RAID);
    assertMovement(Ships::homeShips($kid)[Ships::RAID] === 1, 'A ship taken for a failed send is restored.');
} finally {
    $db->query("UPDATE units SET ship1={$row['ship1']}, ship2={$row['ship2']}, ship3={$row['ship3']}, ship4={$row['ship4']} WHERE kid=$kid");
}

// ---- coming home ----------------------------------------------------------------------
$home = (int)$db->fetchScalar('SELECT kid FROM vdata WHERE owner=2 ORDER BY kid LIMIT 1');
assertMovement($home > 0, 'The world needs a Multihunter village for the docking test.');
$fields = $db->query("SELECT f26, f26t FROM fdata WHERE kid=$home")->fetch_assoc();
$ships = $db->query("SELECT ship1, ship2, ship3, ship4 FROM units WHERE kid=$home")->fetch_assoc();
$count = static fn (): int => (int)$db->fetchScalar("SELECT ship2 FROM units WHERE kid=$home");
try {
    $db->query("UPDATE units SET ship1=0, ship2=0, ship3=0, ship4=0 WHERE kid=$home");
    $db->query("UPDATE fdata SET f26=0, f26t=0 WHERE kid=$home");
    $return = ['ship' => Ships::WAR, 'mode' => 1, 'kid' => 5, 'to_kid' => $home, 'attack_type' => 3];
    Ships::dockOnArrival($return);
    assertMovement($count() === 0 && !Ships::hasHarbor($home), 'A ship returning to a village without a Harbor is lost.');
    $db->query("UPDATE fdata SET f26=3, f26t=" . Ships::HARBOR_GID . " WHERE kid=$home");
    assertMovement(Ships::hasHarbor($home), 'A level 3 Harbor is a standing Harbor.');
    Ships::dockOnArrival($return);
    assertMovement($count() === 1, 'A returning ship docks at its home Harbor.');
    Ships::dockOnArrival(['ship' => Ships::WAR, 'mode' => 0, 'kid' => $home, 'to_kid' => 5, 'attack_type' => 2]);
    assertMovement($count() === 2, 'The ship of a reinforcement comes home at once.');
    Ships::dockOnArrival(['ship' => Ships::WAR, 'mode' => 0, 'kid' => $home, 'to_kid' => 5, 'attack_type' => 5]);
    assertMovement($count() === 3, 'The ship of settlers comes home at once.');
    Ships::dockOnArrival(['ship' => Ships::WAR, 'mode' => 0, 'kid' => $home, 'to_kid' => 5, 'attack_type' => 3]);
    Ships::dockOnArrival(['ship' => Ships::WAR, 'mode' => 0, 'kid' => $home, 'to_kid' => 5, 'attack_type' => 4]);
    Ships::dockOnArrival(['ship' => 0, 'mode' => 1, 'kid' => 5, 'to_kid' => $home, 'attack_type' => 3]);
    assertMovement($count() === 3, 'An outbound attack, a raid and a shipless army dock nothing; the return row brings the ship.');
} finally {
    $db->query("UPDATE fdata SET f26={$fields['f26']}, f26t={$fields['f26t']} WHERE kid=$home");
    $db->query("UPDATE units SET ship1={$ships['ship1']}, ship2={$ships['ship2']}, ship3={$ships['ship3']}, ship4={$ships['ship4']} WHERE kid=$home");
}

// ---- trade ships ----------------------------------------------------------------------
$merchantSeconds = Ships::tradeTravelSeconds($from, $to, 9, false);
$shipSeconds = Ships::tradeTravelSeconds($from, $to, 9, true);
$landSpeed = Formulas::merchantSpeed(9);
$deepDistance = $distance * $fraction;
assertMovement($merchantSeconds === (int)round($distance / $landSpeed * 3600), 'Merchants walk at their tribe speed.');
assertMovement($shipSeconds === (int)round($deepDistance / Ships::deepWaterSpeed(Ships::TRADE, 9) * 3600 + ($distance - $deepDistance) / $landSpeed * 3600), 'Trade ships sail deep water at ship speed and walk the rest at merchant speed.');
$fixture = [];
try {
    $db->query("UPDATE fdata SET f26=3, f26t=" . Ships::HARBOR_GID . " WHERE kid=$home");
    $db->query("UPDATE units SET ship1=0 WHERE kid=$home");
    $db->query("INSERT INTO send (kid, to_kid, wood, clay, iron, crop, x, ships, mode, end_time) VALUES ($home, 5, 10, 0, 0, 0, 1, 2, 0, 4000000000)");
    $fixture[] = (int)$db->lastInsertId();
    $db->query("INSERT INTO send (kid, to_kid, wood, clay, iron, crop, x, ships, mode, end_time) VALUES (5, $home, 10, 0, 0, 0, 0, 1, 1, 4000000000)");
    $fixture[] = (int)$db->lastInsertId();
    assertMovement(Ships::awayShips($home) === 3, 'Trade ships on the way out and on the way back both count as away.');
    $merchantsBefore = (new Model\MarketModel())->getOnTheWayMerchantsCount($home, 500);
    $db->query("INSERT INTO send (kid, to_kid, wood, clay, iron, crop, x, ships, mode, end_time) VALUES ($home, 5, 10, 0, 0, 0, 1, 0, 0, 4000000000)");
    $fixture[] = (int)$db->lastInsertId();
    assertMovement((new Model\MarketModel())->getOnTheWayMerchantsCount($home, 500) === 1 && $merchantsBefore === 0, 'Only merchant deliveries use up merchants; trade-ship deliveries do not.');
    Ships::dockTradeShips($home, 2);
    assertMovement((int)$db->fetchScalar("SELECT ship1 FROM units WHERE kid=$home") === 2, 'Trade ships dock at their home Harbor.');
} finally {
    if ($fixture) {
        $db->query('DELETE FROM send WHERE id IN (' . implode(',', $fixture) . ')');
    }
    $db->query("UPDATE fdata SET f26={$fields['f26']}, f26t={$fields['f26t']} WHERE kid=$home");
    $db->query("UPDATE units SET ship1={$ships['ship1']}, ship2={$ships['ship2']}, ship3={$ships['ship3']}, ship4={$ships['ship4']} WHERE kid=$home");
}

// ---- wiring -----------------------------------------------------------------------------
foreach ([
    'game/include/Core/Automation.php' => ['Ships::dockOnArrival($row)'],
    'game/include/Model/MovementsModel.php' => ['$ship = 0', 'data,ship)'],
    'game/include/Model/BattleModel.php' => ["'redeployHero', 'ship',", "setShip(\$this->row['ship'], (int)\$this->row['race'])", "NULL, \$this->row['ship']);"],
    'game/include/Controller/RallyPoint/SendTroops.php' => ['Ships::pickForArmy', 'Ships::take', 'Ships::restore', 'shipChoice'],
    'game/include/Controller/Ajax/marketplace/prepare.php' => ['Ships::tradeTravelSeconds', 'not enough trade ships'],
    'game/include/Model/MarketModel.php' => ['ships=0 AND ((kid=? AND mode=0)'],
    'game/include/Model/MarketPlaceProcessor.php' => ['dockTradeShips', 'tradeTravelSeconds', "(int) \$row['ships']"],
    'game/include/resources/Templates/build/building/MarketplaceSendResources.php' => ['data-market-use-ship', 'useShip: state.useShip ? 1 : 0'],
    'game/include/resources/Templates/rallypoint/prepareSend.php' => ['name="useShip"', 'class="useShip"', 'class="shipAvailability"'],
    'game/include/Game/SpeedCalculator.php' => ['function setShip', 'ShipRoute::deepFraction'],
] as $file => $needles) {
    $source = (string)file_get_contents($root . '/' . $file);
    foreach ($needles as $needle) {
        assertMovement(str_contains($source, $needle), "$file must contain $needle.");
    }
}

echo "Ship movement contract passed.\n";
