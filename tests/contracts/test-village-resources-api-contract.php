<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/game/include/Controller/Ajax/village/resources.php');
$client = file_get_contents($root . '/game/deployment/public/game-client.js');
$routes = file_get_contents($root . '/game/deployment/include/api_legacy_routes.php');

if ($controller === false
    || $client === false
    || $routes === false
    || !str_contains($controller, 'final class resources extends AjaxBase')
    || !str_contains($controller, '$this->session->isValid()')
    || !str_contains($controller, 'StockBar::snapshot(Village::getInstance())')
    || !str_contains($client, 'Travian.api("village/resources"')
    || !str_contains($routes, "'village/resources' => ['village', 'resources']")) {
    fwrite(STDERR, "Village-resource refreshes must answer with the stock bar snapshot.\n");
    exit(1);
}

// The reply has the reference's shape (maxStorage, production, storage and tooltips keyed l1..l5) and
// the client applies all four, not only the stock.
$stockBar = (string)file_get_contents($root . '/game/include/Game/StockBar.php');
$endpoint = (string)file_get_contents($root . '/game/include/Controller/Ajax/village/resources.php');
$client = (string)file_get_contents($root . '/game/deployment/public/game-client.js');
$marketplace = (string)file_get_contents($root . '/game/include/resources/Templates/build/building/MarketplaceSendResources.php');

foreach (["'maxStorage'", "'production'", "'storage'", "'tooltips'"] as $key) {
    if (!str_contains($stockBar, $key)) {
        fwrite(STDERR, "StockBar::snapshot must return $key.\n");
        exit(1);
    }
}
if (!str_contains($endpoint, 'StockBar::snapshot')) {
    fwrite(STDERR, "village/resources must answer with StockBar::snapshot.\n");
    exit(1);
}
foreach (['resources.maxStorage = e.maxStorage', 'resources.production = e.production', 'e.tooltips', '"stockBarFreeCrop"'] as $needle) {
    if (!str_contains(preg_replace('/[()\s,]+/', ' ', $client) ?? '', preg_replace('/[()\s,]+/', ' ', $needle) ?? '')) {
        fwrite(STDERR, "updateResources must apply the reply: $needle\n");
        exit(1);
    }
}
if (str_contains($marketplace, 'updateResources(reload.storage)')) {
    fwrite(STDERR, "updateResources takes no argument, like the reference.\n");
    exit(1);
}

echo "Village resources API contract passed.\n";
