<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/game/include/Controller/Ajax/marketplace/exchangeResources.php');
$routes = file_get_contents($root . '/game/deployment/include/api_legacy_routes.php');
$client = file_get_contents($root . '/game/deployment/public/game-client.js');

if ($controller === false
    || $routes === false
    || $client === false
    || !str_contains($controller, 'final class exchangeResources extends AjaxBase')
    || !str_contains($controller, "['resources']")
    || !str_contains($controller, "['distributed']")
    || !str_contains($controller, 'desiredDistribution')
    || !str_contains($controller, 'NpcExchange::scale')
    || !str_contains($routes, "'marketplace/exchange-resources' => ['marketplace', 'exchange-resources']")
    || !str_contains($client, 'Travian.api("marketplace/exchange-resources"')) {
    fwrite(STDERR, "Marketplace exchange distribution must return normalized resource totals without spending stock.\n");
    exit(1);
}

echo "Marketplace exchange-resources API contract passed.\n";
