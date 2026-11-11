<?php

declare(strict_types=1);

function failCleanRouteAssetsContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$head = file_get_contents($root . '/game/include/resources/Templates/layout/head.php');
$router = file_get_contents($root . '/game/include/mainInclude.php');

if ($head === false || $router === false) {
    failCleanRouteAssetsContract('Unable to read the clean-route sources.');
}

if (!str_contains($head, '<base href="/"/>')) {
    failCleanRouteAssetsContract('Nested clean routes must resolve shared scripts and sprites from the world root.');
}

foreach ([
    "'/hero/inventory' => ['t' => '1']",
    "'/hero/attributes' => ['t' => '1', 'view' => 'attributes']",
    "'/hero/appearance' => ['t' => '2']",
    "'/hero/adventures' => ['t' => '3']",
    "'/auctions' => '/auctions/buy'",
    "WebService::redirect('/tasks?t=village')",
    "'/auctions/buy' => ['t' => '4', 'action' => 'buy']",
    "'/auctions/sell' => ['t' => '4', 'action' => 'sell']",
    "'/auctions/overview' => ['t' => '4', 'action' => 'bids']",
] as $route) {
    if (!str_contains($router, $route)) {
        failCleanRouteAssetsContract("Missing clean Hero V2 route: {$route}");
    }
}

if (!str_contains($router, "\$requestPath = '/hero/inventory';")
    || str_contains($router, '$opensAttributes')
    || str_contains($router, "'/hero' => ['t' => '1']")) {
    failCleanRouteAssetsContract('Clean /hero must redirect to the reference inventory route without a legacy attribute-panel path.');
}

if (!str_contains($router, 'setRouteParam($key, $value);')) {
    failCleanRouteAssetsContract('Clean Hero routes must synchronise their fixed route state for legacy controllers that read request values.');
}

echo "Clean route asset contract passed.\n";
