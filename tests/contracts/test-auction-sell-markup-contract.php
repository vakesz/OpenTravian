#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: /auctions/sell matches Hero V2 sell chrome —
 * description + inventory grid, h3.yourAuctions, listControl, Item/Started at/Status.
 */

function failAuctionSellMarkupContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$template = file_get_contents($root . '/game/include/resources/Templates/hero/AuctionSell.php');
$controller = file_get_contents($root . '/game/include/Controller/HeroAuctionCtrl.php');
$translation = file_get_contents($root . '/game/include/resources/Translation/en-US.php');

if ($template === false || $controller === false || $translation === false) {
    failAuctionSellMarkupContract('Unable to read auction sell sources.');
}

if (!str_contains($template, 'class="heroV2 sell"')
    || !str_contains($template, 'class="description"')
    || !str_contains($template, 'class="tooltipHelpText"')
    || !str_contains($controller, 'tooltipHelpAnchor')
    || !str_contains($template, 'h3 class="yourAuctions"')
    || !str_contains($template, 'class="listControl"')
    || !str_contains($template, 'ongoingSellListWrapper')
    || !str_contains($template, 'itemType')
    || !str_contains($template, "T('Auction', 'Started at')")
    || !str_contains($template, "T('Auction', 'Status')")
    || str_contains($template, 'table class="sellings"')
    || str_contains($template, 'auctionEnded')
    || str_contains($template, 'Finished auctions')) {
    failAuctionSellMarkupContract(
        'AuctionSell template must render sell grid without legacy sellings/finished chrome.'
    );
}

if (!str_contains($controller, 'renderAuctionSellRow')
    || !str_contains($controller, 'renderAuctionSellInventoryTile')
    || !str_contains($controller, 'renderAuctionSellEmptyInventoryTile')
    || !str_contains($controller, 'renderAuctionEmptyRow(4)')
    || !str_contains($controller, 'class="startedAt"')
    || !str_contains($controller, 'class="status"')
    || !str_contains($controller, 'cancelAuction')) {
    failAuctionSellMarkupContract(
        'HeroAuctionCtrl::sell must emit modern sell rows and inventory tiles.'
    );
}

// Finished auctions belong on Overview; sell() must not query them anymore.
if (preg_match('/private function sell\(.+?\n    private function /s', $controller, $match) !== 1) {
    failAuctionSellMarkupContract('Unable to isolate HeroAuctionCtrl::sell for finished-block check.');
}
$sellMethod = $match[0];
if (str_contains($sellMethod, 'getMyFinishedAuctions') || str_contains($sellMethod, 'Finished auctions')) {
    failAuctionSellMarkupContract(
        'HeroAuctionCtrl::sell must not render the finished-auctions block (Overview owns Sold items).'
    );
}

if (!str_contains($sellMethod, "AuctionListState::layout(AuctionListState::LIST_SELL, 'layout', 'compact')")) {
    failAuctionSellMarkupContract(
        'HeroAuctionCtrl::sell must default to compact layout (until the player stores another layout).'
    );
}

foreach ([
    "['Started at'] = 'Started at'",
    "['Status'] = 'Status'",
    "['Your auctions'] = 'Your auctions'",
    "['Sell items'] = 'Sell items'",
    "['noItemsCurrently'] = 'There are currently no items'",
] as $needle) {
    if (!str_contains($translation, $needle)) {
        failAuctionSellMarkupContract("Missing Auction translation: {$needle}");
    }
}

echo "Auction sell markup contract passed.\n";
