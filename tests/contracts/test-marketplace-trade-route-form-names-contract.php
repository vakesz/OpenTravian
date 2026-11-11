<?php

declare(strict_types=1);

/**
 * Contract: trade-route create/edit form uses resource field names (lumber/clay/iron/crop).
 */

function failMarketplaceTradeRouteFormNamesContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$template = file_get_contents($root . '/game/include/resources/Templates/build/building/MarketplaceTradeRouteAdd.php');
$controller = file_get_contents($root . '/game/include/Controller/Build/MarketPlaceCtrl.php');

if ($template === false || $controller === false) {
    failMarketplaceTradeRouteFormNamesContract('Unable to read trade route form sources.');
}

if (!str_contains($template, "\$resourceFieldNames = ['lumber', 'clay', 'iron', 'crop']")
    || !str_contains($template, 'name="<?= $fieldName; ?>"')) {
    failMarketplaceTradeRouteFormNamesContract('TradeRouteAdd must emit lumber/clay/iron/crop resource input names.');
}

foreach (['name="r1"', 'name="r2"', 'name="r3"', 'name="r4"'] as $legacy) {
    if (str_contains($template, $legacy)) {
        failMarketplaceTradeRouteFormNamesContract("TradeRouteAdd still uses legacy field {$legacy}.");
    }
}

if (!str_contains($controller, 'tradeRouteResourcesFromPost')
    || !str_contains($controller, "\$_POST['lumber']")
    || !str_contains($controller, "\$_POST['clay']")
    || !str_contains($controller, "\$_POST['iron']")
    || !str_contains($controller, "\$_POST['crop']")) {
    failMarketplaceTradeRouteFormNamesContract('MarketPlaceCtrl must read lumber/clay/iron/crop for trade routes.');
}

if (preg_match("/\\\$_POST\\['r[1-4]'\\]/", $controller) === 1) {
    failMarketplaceTradeRouteFormNamesContract('MarketPlaceCtrl must not read r1–r4 from POST for trade routes.');
}

echo "Marketplace trade route form names contract passed.\n";
