#!/usr/bin/env php
<?php

declare(strict_types=1);

function failRetiredTooltipAliasesContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$routes = require $root . '/game/deployment/include/api_legacy_routes.php';
$routes = $routes['routes'] ?? null;
if (!is_array($routes)) {
    failRetiredTooltipAliasesContract('The maintained Ajax route table must be readable.');
}

foreach ([
    'tooltip/hero',
    'tooltip/incoming-troops',
    'tooltip/quick-link',
    'tooltip/layout-button',
] as $retired) {
    if (array_key_exists($retired, $routes)) {
        failRetiredTooltipAliasesContract("Retired tooltip alias remains registered: {$retired}");
    }
}

foreach ([
    'tooltip/incomingTroops' => ['tooltip', 'incoming-troops'],
    'tooltip/quickLink' => ['tooltip', 'quick-link'],
    'tooltip/layoutButton' => ['tooltip', 'layout-button'],
] as $route => $target) {
    if (($routes[$route] ?? null) !== $target) {
        failRetiredTooltipAliasesContract("Maintained tooltip route is missing: {$route}");
    }
}

if (is_file($root . '/game/include/Controller/Ajax/tooltip/hero.php')) {
    failRetiredTooltipAliasesContract('The retired tooltip/hero shim must be deleted.');
}

$view = file_get_contents($root . '/game/include/resources/View/GameView.php');
$quickLinks = file_get_contents($root . '/game/include/resources/Templates/layout/sidebarBoxVillageList.php');
$client = file_get_contents($root . '/game/deployment/public/game-client.js');
if ($view === false || $quickLinks === false || $client === false) {
    failRetiredTooltipAliasesContract('Cannot read maintained tooltip callers.');
}
foreach ([
    'data-load-tooltip="incomingTroops"',
    "attributes['data-load-tooltip'] = 'quickLink'",
    'Travian.api("tooltip/" + e._tooltipName',
] as $required) {
    if (!str_contains($view . $quickLinks . $client, $required)) {
        failRetiredTooltipAliasesContract("Maintained caller missing expected tooltip contract: {$required}");
    }
}
foreach (['data-load-tooltip="hero"', 'data-load-tooltip="incoming-troops"', "'quick-link'", "'layout-button'"] as $retiredCaller) {
    if (str_contains($view . $quickLinks, $retiredCaller)) {
        failRetiredTooltipAliasesContract("Retired tooltip name remains in player markup: {$retiredCaller}");
    }
}

echo "Retired tooltip aliases contract passed.\n";
