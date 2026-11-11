<?php

declare(strict_types=1);

/**
 * Contract: the reference hero inventory endpoints exist and are wired.
 * hero/v2/inventory/{move-item,use-item,items,resource-transfer-data}.
 */

function failHeroEndpointContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$routes = file_get_contents($root . '/game/deployment/include/api_legacy_routes.php');
foreach ([
    "'hero/v2/inventory/move-item' => ['hero', 'move-item']" => 'moveItem',
    "'hero/v2/inventory/use-item' => ['hero', 'use-item']" => 'useItem',
    "'hero/v2/inventory/items' => ['hero', 'inventory-items']" => 'inventoryItems',
    "'hero/v2/inventory/resource-transfer-data' => ['hero', 'resource-transfer-data']" => 'resourceTransferData',
] as $route => $controller) {
    if (!str_contains($routes, $route)) {
        failHeroEndpointContract("Missing route: $route");
    }
    if (!is_file($root . "/game/include/Controller/Ajax/hero/$controller.php")) {
        failHeroEndpointContract("Missing controller: $controller");
    }
}

$move = file_get_contents($root . '/game/include/Controller/Ajax/hero/moveItem.php');
foreach (['targetPlaceId', 'targetForPlace', 'itemId'] as $needle) {
    if (!str_contains($move, $needle)) {
        failHeroEndpointContract("move-item must read $needle.");
    }
}
$use = file_get_contents($root . '/game/include/Controller/Ajax/hero/useItem.php');
if (!str_contains($use, "'amount'") || !str_contains($use, 'villageId')) {
    failHeroEndpointContract('use-item must take villageId and answer with the applied amount.');
}

$model = file_get_contents($root . '/game/include/Model/HeroInventoryModel.php');
foreach (['-1 => \'helmet\'', '-6 => \'bag\'', '-7 => \'horse\'', 'maxcrop', 'return $this->applied;'] as $needle) {
    if (!str_contains($model, $needle)) {
        failHeroEndpointContract("HeroInventoryModel is missing: $needle");
    }
}

echo "Hero inventory endpoints contract passed.\n";
