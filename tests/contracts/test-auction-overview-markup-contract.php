<?php

declare(strict_types=1);

/**
 * Contract: /auctions/overview matches Hero V2 overview chrome —
 * Your bids + Sold items containers, listControl, modern itemType tables.
 */

function failAuctionOverviewMarkupContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$template = file_get_contents($root . '/game/include/resources/Templates/hero/AuctionBids.php');
$controller = file_get_contents($root . '/game/include/Controller/HeroAuctionCtrl.php');
$translation = file_get_contents($root . '/game/include/resources/Translation/en-US.php');

if ($template === false || $controller === false || $translation === false) {
    failAuctionOverviewMarkupContract('Unable to read auction overview sources.');
}

if (!str_contains($template, "\$vars['filterHtml']")
    || substr_count($template, 'auctionsOverviewContainer') < 2
    || !str_contains($template, 'class="listHeadline"')
    || !str_contains($template, "T('Auction', 'Your bids')")
    || !str_contains($template, "T('Auction', 'Your bids description')")
    || !str_contains($template, "T('Auction', 'Sold items')")
    || !str_contains($template, 'bidListWrapper')
    || !str_contains($template, 'soldListWrapper')
    || !str_contains($template, 'listLayout_detailed')
    || !str_contains($template, 'listLayout_compact')
    || !str_contains($template, 'controlSection reload')
    || !str_contains($template, 'AuctionSort::bidTableHead($vars[\'bidsLayout\']')
    || !str_contains($template, "T('Auction', 'Payout')")
    || str_contains($template, 'table.currentBid')
    || str_contains($template, 'id="del"')) {
    failAuctionOverviewMarkupContract(
        'AuctionBids template must render dual overview containers without legacy currentBid/#del chrome.'
    );
}

if (!str_contains($controller, 'renderAuctionOverviewBidRow')
    || !str_contains($controller, 'renderAuctionSoldRow')
    || !str_contains($controller, 'renderAuctionEmptyRow')
    || !str_contains($controller, 'renderAuctionHeroItemCell')
    || !str_contains($controller, 'renderAuctionBidForm')
    || !str_contains($controller, 'getMyFinishedAuctions')
    || !str_contains($controller, "\$rowClass = \$finished ? 'finished' : \$this->ownBidClass(\$row, \$session, 'default')")
    || !str_contains($controller, 'class="empty"')
    || !str_contains($controller, 'noItemsCurrently')
    || str_contains($controller, 'private function renderAuction(')
    || !str_contains($controller, "new PHPBatchView('hero/AuctionBids')")) {
    failAuctionOverviewMarkupContract(
        'HeroAuctionCtrl::bids must emit modern overview/sold rows and drop legacy renderAuction.'
    );
}

foreach ([
    "['Your bids'] = 'Your bids'",
    "['Your bids description'] = 'All your bids in active and finished auctions.'",
    "['Sold items'] = 'Sold items'",
    "['Maximum bid'] = 'Maximum bid'",
    "['Payout'] = 'Payout'",
    "['noItemsCurrently'] = 'There are currently no items'",
] as $needle) {
    if (!str_contains($translation, $needle)) {
        failAuctionOverviewMarkupContract("Missing Auction translation: {$needle}");
    }
}

echo "Auction overview markup contract passed.\n";
