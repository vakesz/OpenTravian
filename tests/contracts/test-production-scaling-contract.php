<?php

declare(strict_types=1);

/**
 * Contract: resource production scales with world speed and the production
 * overview agrees with (x2).
 *
 * (x2, read-only capture 2026-10-30): field L3 = 42, L4 = 62; hero with
 * 10 production points on "all resources" = 10 x 9 x 2 = 180 per resource,
 * plus a flat 6 crop; crop interim balance = fields + hero - (villagers +
 * constructions); net = interim - own troops.
 */

function failProductionScalingContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$balance = file_get_contents($root . '/game/include/resources/Templates/production/balance.php');
$ctrl = file_get_contents($root . '/game/include/Controller/ProductionCtrl.php');
if ($balance === false || $ctrl === false) {
    failProductionScalingContract('Unable to read production overview sources.');
}
foreach ([
    '$consumption = Village::getInstance()->getPop() + $loadingCrop;',
    '$interimBalance = $totalProd + $totalBonus - $consumption + $heroCrop',
    '$figure($interimBalance - $total_mine)',
] as $needle) {
    if (!str_contains($balance, $needle)) {
        failProductionScalingContract("Crop balance must subtract villagers and construction orders once: {$needle}");
    }
}
if (str_contains($balance, "getPop() + \$vars['heroProd']")) {
    failProductionScalingContract('Crop balance still has an inline interim formula that omits construction orders.');
}
if (!str_contains($ctrl, 'ResourcesHelper::oasisBonus(')) {
    failProductionScalingContract('The overview must take its oasis bonus from ResourcesHelper::oasisBonus.');
}

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Production scaling contract passed (static).\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['QUERY_STRING'] = '';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use Core\Config;
use Game\Formulas;
use Game\ResourcesHelper;

// Field production = x1 table (Legends values, round-half-up of 1.4 x the classic
// 2,5,9,...) x world speed. Exact at every level.
$x1 = [3, 7, 13, 21, 31, 46, 70, 98, 140, 203, 280, 392, 525, 693, 889, 1120, 1400, 1820, 2240, 2800, 3430];
$speed = getGameSpeed();
foreach ($x1 as $level => $value) {
    $actual = Formulas::fieldProduction($level);
    if ($actual != $value * $speed) {
        failProductionScalingContract("Field level {$level} at x{$speed}: expected " . $value * $speed . ", got {$actual}.");
    }
}
if ($speed == 2 && (Formulas::fieldProduction(3) != 42 || Formulas::fieldProduction(4) != 62 || Formulas::fieldProduction(5) != 92)) {
    failProductionScalingContract('x2 shows level 3 = 42, level 4 = 62 and level 5 = 92.');
}

// Hero: 10 points on all resources at x2 = 180 per resource.
$config = Config::getInstance();
$previous = $config->heroConfig->resourcesMultiplier;
$config->heroConfig->resourcesMultiplier = 2;
if (10 * Formulas::heroProductionPerPoint(2, false) != 180 || 10 * Formulas::heroProductionPerPoint(2, true) != 600) {
    failProductionScalingContract('Hero production at x2 must be 180 (all resources) / 600 (single resource) for 10 points.');
}
$config->heroConfig->resourcesMultiplier = $previous;

// Only the three best oases count, independent of row order.
$oases = [
    ['kid' => 1, 'type' => 1], ['kid' => 2, 'type' => 3], ['kid' => 3, 'type' => 12], ['kid' => 4, 'type' => 2], ['kid' => 5, 'type' => 3],
];
$a = ResourcesHelper::topBonusOases($oases);
$b = ResourcesHelper::topBonusOases(array_reverse($oases));
if (count($a) !== 3 || $a !== $b) {
    failProductionScalingContract('topBonusOases must return the same three oases for any row order.');
}

echo "Production scaling contract passed.\n";
