<?php

declare(strict_types=1);

/**
 * Contract: auction buy detail matches listControl + detailed/compact rows.
 */

function failAuctionDetailLayoutContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$template = file_get_contents($root . '/game/include/resources/Templates/hero/AuctionDetail.php')
    . file_get_contents($root . '/game/include/resources/Templates/hero/AuctionItemInformation.php');
$controller = file_get_contents($root . '/game/include/Controller/HeroAuctionCtrl.php');
$translation = file_get_contents($root . '/game/include/resources/Translation/en-US.php');
$fixes = file_get_contents($root . '/assets/gpack/travian/mainPage/lang/overrides.css');

if ($template === false || $controller === false || $translation === false || $fixes === false) {
    failAuctionDetailLayoutContract('Unable to read auction detail sources.');
}

foreach ([
    'controlSection listLayout',
    'listLayout_detailed',
    'listLayout_compact',
    "title=\"<?= T('Auction', 'detailed list'); ?>\"",
    "title=\"<?= T('Auction', 'compact list'); ?>\"",
    "title=\"<?= T('Auction', 'reload'); ?>\"",
    'itemType <?= $layout; ?>',
    'AuctionSort::bidTableHead($layout',
] as $needle) {
    if (!str_contains($template, $needle)) {
        failAuctionDetailLayoutContract("AuctionDetail missing chrome: {$needle}");
    }
}
// The shared head of the single-auction tables: Price sorts, Highest bidder has its menu, in the compact layout.
$head = (string)file_get_contents($root . '/game/include/Game/Hero/AuctionSort.php');
foreach (["self::header('price'", "self::menuHeader('highestBidder'", "self::header('highestBidder'"] as $needle) {
    if (!str_contains($head, $needle)) {
        failAuctionDetailLayoutContract("AuctionSort::bidTableHead missing: {$needle}");
    }
}

if (!str_contains($controller, "layout === 'compact'")
    || !str_contains($controller, 'renderAuctionHighestBidderBadge')
    || !str_contains($controller, "modifier = ' self'")
    || !str_contains($controller, "modifier = ' none'")
    || !str_contains($controller, "td class=\"price\"")
    || !str_contains($controller, "td class=\"highestBidder\"")
    || !str_contains($controller, "ButtonPagination::renderForScript(\$page, \$pages, 'detail')")
) {
    failAuctionDetailLayoutContract(
        'HeroAuctionCtrl must render compact/detailed detail rows and self/none bidder badges.'
    );
}

foreach ([
    'table class="borderGap"',
    'td class="label"',
    'td class="value"',
    'valueWrapper labelRarity <?= $rarity; ?>',
    "require __DIR__ . '/../layout/currencyCoin.php'",
    "\$currencyCoinKind = 'silver';",
    '<?= $silverCoin; ?>',
    "T('Auction', 'itemPriceTableLabelLowest'",
    "T('Auction', 'itemPriceTableLabelAverage'",
    "T('Auction', 'itemPriceTableLabelHighest'",
] as $needle) {
    if (!str_contains($template, $needle)) {
        failAuctionDetailLayoutContract("AuctionDetail missing native price-table markup: {$needle}");
    }
}

if (str_contains($template, 'silver_medium')) {
    failAuctionDetailLayoutContract('AuctionDetail must not use the zero-height legacy silver_medium icon.');
}

if (!str_contains($controller, 'RechartsMarkup::auctionPriceHistory')
    || str_contains($controller, '<polyline class="sales"')
    || str_contains($controller, '<polyline class="averagePrice"')) {
    failAuctionDetailLayoutContract('Auction detail must use the native Recharts-shaped area graph, not a line-chart approximation.');
}

foreach ([
    "['itemPriceChartTitle'] = 'Average price and sales in the last 24h'",
    "['itemPriceChartEmpty'] = 'Not enough data'",
    "['itemPriceChartLabel_averagePrice'] = 'Average price'",
    "['itemPriceChartLabel_sales'] = 'Sales'",
    "['itemPriceChartXTick_now'] = 'Now'",
    "['itemPriceChartXTick_hours'] = '[hours]h'",
    "['itemPriceTableLabelLowest'] = 'Lowest selling price'",
    "['itemPriceTableLabelAverage'] = 'Average selling price'",
    "['itemPriceTableLabelHighest'] = 'Highest selling price'",
] as $needle) {
    if (!str_contains($translation, $needle)) {
        failAuctionDetailLayoutContract("Auction detail is missing the translation: {$needle}");
    }
}

require_once $root . '/game/include/Core/Helper/RechartsMarkup.php';

$labels = [
    'empty' => 'Not enough data',
    'now' => 'Now',
    'hours' => '[hours]h',
    'averagePrice' => 'Average price',
    'sales' => 'Sales',
];
$chart = \Core\Helper\RechartsMarkup::auctionPriceHistory(
    array_fill(0, 24, 100),
    array_fill(0, 24, 2),
    'common',
    $labels
);

foreach ([
    'style="width:0px;overflow-x:visible"',
    'viewBox="0 0 299 160"',
    'x1="40" y1="121" x2="259" y2="121"',
    'height="121" width="219"',
    'class="recharts-curve recharts-area-area"',
    'fill="#D9A90B" fill-opacity=".3"',
    'fill="#B43B35" fill-opacity=".3"',
    'x="30"',
    'text-anchor="end"',
    'x="269"',
    'text-anchor="start"',
    'class="labelRarity common"',
    '<circle class="common" cx="9" cy="9" r="9"></circle>',
] as $needle) {
    if (!str_contains($chart, $needle)) {
        failAuctionDetailLayoutContract("Auction history graph is missing native Recharts geometry: {$needle}");
    }
}

$averageLegend = strpos($chart, 'legend-item-0');
$salesLegend = strpos($chart, 'legend-item-1');
if ($averageLegend === false
    || $salesLegend === false
    || $averageLegend > $salesLegend
    || !str_contains(substr($chart, $averageLegend, $salesLegend - $averageLegend), 'stroke="#B43B35"')
    || !str_contains(substr($chart, $salesLegend), 'stroke="#D9A90B"')
    || str_contains($chart, 'recharts-area-dots')
    || str_contains($chart, '<polyline')) {
    failAuctionDetailLayoutContract('Auction history graph must retain legend order and filled areas without data-point dots.');
}

$emptyChart = \Core\Helper\RechartsMarkup::auctionPriceHistory([], [], 'common', $labels);
if (!str_contains($emptyChart, 'Not enough data')
    || str_contains($emptyChart, 'recharts-cartesian-grid')
    || str_contains($emptyChart, 'recharts-area-area')) {
    failAuctionDetailLayoutContract('Auction history graph must retain the no-data state.');
}

foreach ([
    '#auctions .itemTypeDetailedInformation .priceTable > div',
    '#auctions .itemTypeDetailedInformation .priceChart .legend',
] as $obsoleteOverride) {
    if (str_contains($fixes, $obsoleteOverride)) {
        failAuctionDetailLayoutContract("Auction detail must use existing Gpack styling, not {$obsoleteOverride}.");
    }
}

echo "Auction detail layout contract passed.\n";
