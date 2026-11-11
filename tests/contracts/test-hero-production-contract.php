<?php

declare(strict_types=1);

/**
 * Contract: hero resource production follows Travian Legends (Travian support
 * article 141): 9 of each resource per production point (30 of one selected
 * resource) at x1, Egyptians 12/40, scaled by world speed through
 * heroConfig->resourcesMultiplier. Every producer and every display must go
 * through Formulas::heroProductionPerPoint().
 */

function failHeroProductionContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$files = [
    'game/include/Game/ResourcesHelper.php',
    'game/include/Controller/ProductionCtrl.php',
    'game/include/Controller/HeroInventoryCtrl.php',
    'game/include/Game/Hero/SessionHero.php',
];
foreach ($files as $file) {
    $source = file_get_contents($root . '/' . $file);
    if ($source === false) {
        failHeroProductionContract("Unable to read {$file}.");
    }
    if (!str_contains($source, 'Formulas::heroProductionPerPoint(')) {
        failHeroProductionContract("{$file} must use Formulas::heroProductionPerPoint().");
    }
    if (preg_match('/\b(?:6|20) \* \$[a-zA-Z>\[\]\'-]*production|\(\$img == \'r0\' \? 6 : 20\)|\* (?:6|20) \* \$rate/', $source) === 1) {
        failHeroProductionContract("{$file} still hard-codes the pre-Legends 6/20 hero production constants.");
    }
}

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Hero production contract passed (static).\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['QUERY_STRING'] = '';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use Core\Config;
use Game\Formulas;

$config = Config::getInstance();
$previous = $config->heroConfig->resourcesMultiplier;
foreach ([1, 2, 5] as $speed) {
    $config->heroConfig->resourcesMultiplier = $speed;
    foreach ([[1, false, 9], [1, true, 30], [6, false, 12], [6, true, 40]] as [$tribe, $single, $base]) {
        $actual = Formulas::heroProductionPerPoint($tribe, $single);
        if ($actual != $base * $speed) {
            failHeroProductionContract("Tribe {$tribe} single=" . (int)$single . " at x{$speed}: expected " . $base * $speed . ", got {$actual}.");
        }
    }
}
$config->heroConfig->resourcesMultiplier = $previous;

echo "Hero production contract passed.\n";
