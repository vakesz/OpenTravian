#!/usr/bin/env php
<?php

declare(strict_types=1);

use Core\Config;

function failProductionBoostPrice(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$configSource = file_get_contents($root . '/game/include/config.php');
$goldHelper = file_get_contents($root . '/game/include/Game/GoldHelper.php');
$autoExtend = file_get_contents($root . '/game/include/Model/AutoExtendModel.php');
$premium = file_get_contents($root . '/game/include/Controller/Ajax/premium.php');

if ($configSource === false || $goldHelper === false || $autoExtend === false || $premium === false) {
    failProductionBoostPrice('Production-bonus price sources must be readable.');
}

if (!str_contains($configSource, "'productionBoostGold'            => 5,")) {
    failProductionBoostPrice('Production bonuses must use the official five-Gold price.');
}

foreach ([$goldHelper, $autoExtend, $premium] as $source) {
    if (!str_contains($source, 'productionBoostGold')) {
        failProductionBoostPrice('Purchase, renewal, and premium-price paths must share the production-bonus price setting.');
    }
}

require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

if ((int) Config::getInstance()->gold->productionBoostGold !== 5) {
    failProductionBoostPrice('The active local world must expose the official five-Gold production-bonus price.');
}

echo "Production boost price contract passed.\n";
