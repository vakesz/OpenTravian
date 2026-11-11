<?php

declare(strict_types=1);

function failAuctionSortContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

use Game\Hero\AuctionSort;

$read = static fn (string $path): string => (string)file_get_contents($root . '/' . $path);
$templates = 'game/include/resources/Templates/hero/';
$bids = $read($templates . 'AuctionBids.php');
$script = $read('game/deployment/public/auction-lists.js');
$controller = $read('game/include/Controller/HeroAuctionCtrl.php');

// Every sortable list declares the reference columns in its template.
foreach ([
    'AuctionBids.php' => ["'BIDS_COUNT', 'DESC'", "'FINISHED_AT', 'ASC'", "'ITEM_NAME', 'ASC'", "'SOLD_FOR', 'DESC'", "'price' => 'CURRENT_PRICE'", "'bidder' => 'HIGHEST_BIDDER'", "'alliance' => 'CURRENT_BIDDER_ALLIANCE_NAME'", "tableAttributes('bids'", "tableAttributes('sold'"],
    'AuctionBuy.php' => ["'NAME', 'ASC'", "'AUCTIONS_COUNT', 'ASC'", "'NEXT_FINISH_AT', 'ASC'", "'NEXT_PRICE', 'ASC'", "'price' => 'CURRENT_BID'", "'finish' => 'REMAINING_TIME'"],
    'AuctionDetail.php' => ["'price' => 'CURRENT_BID'", "'bids' => 'BIDS_COUNT'", "'finish' => 'REMAINING_TIME'", "'bidder' => 'CURRENT_BIDDER_NAME'"],
    'AuctionSell.php' => ["'ITEM_NAME', 'DESC'", "'STARTED_AT', 'DESC'"],
] as $file => $needles) {
    $template = $read($templates . $file);
    foreach (array_merge($needles, ['/auction-lists.js', 'AuctionSort::tableAttributes']) as $needle) {
        if (!str_contains($template, $needle)) {
            failAuctionSortContract("{$file} is missing: {$needle}");
        }
    }
}
// The reference's starting states.
foreach (["'FINISHED_AT', 'DESC'", "'REMAINING_TIME', 'ASC'", "'NAME', 'ASC'", "'STARTED_AT', 'DESC'"] as $needle) {
    if (!str_contains($controller, $needle)) {
        failAuctionSortContract("HeroAuctionCtrl must start its lists with {$needle}.");
    }
}
foreach (['sortingArrow', 'contextMenu from sorting', 'sortingContextWrapper', 'SortBy', 'this.order === "ASC" ? "DESC" : "ASC"'] as $needle) {
    if (!str_contains($script, $needle)) {
        failAuctionSortContract("auction-lists.js is missing: {$needle}");
    }
}

// Arrows follow the reference rule: up for ascending, down for descending, dimmed while not sorted.
if (!str_contains(AuctionSort::arrow('FINISHED_AT', 'ASC', 'FINISHED_AT', 'DESC'), 'class="sortingArrow down"')
    || !str_contains(AuctionSort::arrow('BIDS_COUNT', 'DESC', 'FINISHED_AT', 'DESC'), 'class="sortingArrow inactive down"')
    || !str_contains(AuctionSort::arrow('ITEM_NAME', 'ASC', 'FINISHED_AT', 'DESC'), 'class="sortingArrow inactive up"')) {
    failAuctionSortContract('The sorting arrows must follow the reference direction and inactive rules.');
}

// Only declared columns are accepted from the query string; a bad value falls back to the list default.
$columns = ['NAME' => 'ASC', 'NEXT_PRICE' => 'DESC'];
$_GET = ['sortBy' => 'NEXT_PRICE'];
if (AuctionSort::state('', $columns, 'NAME', 'ASC') !== ['NEXT_PRICE', 'DESC']) {
    failAuctionSortContract('A picked column must use its default order.');
}
$_GET = ['bidsSortBy' => 'NEXT_PRICE', 'bidsSortOrder' => 'ASC'];
if (AuctionSort::state('bids', $columns, 'NAME', 'ASC') !== ['NEXT_PRICE', 'ASC']) {
    failAuctionSortContract('An explicit order must win.');
}
$_GET = ['sortBy' => 'DROP TABLE', 'sortOrder' => 'ASC'];
if (AuctionSort::state('', $columns, 'NAME', 'ASC') !== ['NAME', 'ASC']) {
    failAuctionSortContract('An unknown column must fall back to the default.');
}

// Rows are ordered over the whole list, ties keep their incoming order.
$rows = [['n' => 'b', 'v' => 2], ['n' => 'a', 'v' => 2], ['n' => 'c', 'v' => 9]];
$values = static fn (array $row): array => ['NAME' => $row['n'], 'V' => $row['v']];
if (array_column(AuctionSort::order($rows, $values, 'V', 'DESC'), 'n') !== ['c', 'b', 'a']
    || array_column(AuctionSort::order($rows, $values, 'NAME', 'ASC'), 'n') !== ['a', 'b', 'c']) {
    failAuctionSortContract('Rows must be ordered by the picked column with stable ties.');
}

echo "Auction sort contract passed.\n";
