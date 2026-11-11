<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$marketplaceTemplate = file_get_contents($root . '/game/include/resources/Templates/build/building/MarketplaceNavigation.php');
$marketplaceController = file_get_contents($root . '/game/include/Controller/Build/MarketPlaceCtrl.php');
$showTemplate = file_get_contents($root . '/game/include/resources/Templates/build/building/MarketplaceTradeRouteShow.php');
$addTemplate = file_get_contents($root . '/game/include/resources/Templates/build/building/MarketplaceTradeRouteAdd.php');
if ($marketplaceTemplate === false || $marketplaceController === false || $showTemplate === false || $addTemplate === false) {
    fwrite(STDERR, "Marketplace trade-route templates are unreadable.\n");
    exit(1);
}

foreach ([
    "3 => 'Trade routes'",
    'case 3:',
    '$this->TradeRoutes();',
    'private function TradeRoutes()',
] as $fragment) {
    if (!str_contains($marketplaceController, $fragment)) {
        fwrite(STDERR, "Trade routes must be a dedicated marketplace controller tab.\n");
        exit(1);
    }
}

foreach ([
    "[3, T('MarketPlace', 'Trade routes'), !(bool)\$vars['hasGoldClub']]",
    "'featureKey' => 'tradeRoute'",
] as $fragment) {
    if (!str_contains($marketplaceTemplate, $fragment)) {
        fwrite(STDERR, "Marketplace navigation must expose the reference Trade routes tab.\n");
        exit(1);
    }
}

foreach ([
    'id="tradeRoutes" class="contentV2"',
    'class="tradeRouteCollection"',
    'class="headline"',
    'class="expandCollapse expanded"',
    'class="resources"',
    'Travian.Game.Marketplace.toggleTradeRoutes',
] as $fragment) {
    if (!str_contains($showTemplate, $fragment)) {
        fwrite(STDERR, "Trade-route listing must use the native V2 collection contract.\n");
        exit(1);
    }
}

foreach ([
    "'&amp;t=3'",
    '<form class="contentV2 tradeRouteDialog formV2" action="<?= $tradeRouteUrl; ?>" method="post">',
    '<input type="hidden" name="a" value="1">',
    '<input type="hidden" name="option"',
    'class="destinationOptions"',
    'class="resourceSelector"',
    'class="actionButtons',
] as $fragment) {
    if (!str_contains($addTemplate, $fragment)) {
        fwrite(STDERR, "Trade-route forms must submit to the selected marketplace field.\n");
        exit(1);
    }
}

if (str_contains($addTemplate . $showTemplate, '&amp;t=0')) {
    fwrite(STDERR, "Trade-route actions must remain on the dedicated marketplace tab.\n");
    exit(1);
}

if (str_contains($addTemplate, 'name="gid"') || str_contains($addTemplate, 'name="t"')) {
    fwrite(STDERR, "Trade-route forms must not override their selected marketplace field with hidden route values.\n");
    exit(1);
}

if (!str_contains($marketplaceController, "contentCssClass'] .= ' buildMarketTradeRoutes'")) {
    fwrite(STDERR, "The trade-route tab must activate the maintained V2 Gpack selector.\n");
    exit(1);
}

if (str_contains($addTemplate . $showTemplate, 'roundedCornersBox')
    || str_contains($addTemplate . $showTemplate, 'window.marketPlace')
    || str_contains($showTemplate, 'id="trading_routes"')) {
    fwrite(STDERR, "Trade routes must not retain retired V1 editor or table markup.\n");
    exit(1);
}

echo "Marketplace trade-route contract passed.\n";
