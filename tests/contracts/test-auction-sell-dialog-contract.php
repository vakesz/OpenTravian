<?php

declare(strict_types=1);

/*
 * Selling opens the reference "Sell item" dialog (item tile, effects, selling prices, 24 h chart, Cancel and Sell
 * item, a batch size for stackable items) and posts through the API; the old yes/no confirmation is gone.
 */

$root = dirname(__DIR__, 2);
$read = static fn (string $path): string => (string)file_get_contents($root . '/' . $path);

$routes = $read('game/deployment/include/api_legacy_routes.php');
$dialog = $read('game/include/resources/Templates/hero/AuctionSellDialog.php');
$information = $read('game/include/resources/Templates/hero/AuctionItemInformation.php');
$sell = $read('game/include/resources/Templates/hero/AuctionSell.php');
$script = $read('game/deployment/public/auction-sell.js');
$controller = $read('game/include/Controller/HeroAuctionCtrl.php');

$checks = [
    'both API routes' => str_contains($routes, "'GET auction/sell-dialog'") && str_contains($routes, "'POST auction/sell-item'"),
    'controllers exist' => is_file($root . '/game/include/Controller/Ajax/auction/sellDialog.php')
        && is_file($root . '/game/include/Controller/Ajax/auction/sellItem.php'),
    'dialog markup' => str_contains($dialog, 'id="sellItemForm"') && str_contains($dialog, 'class="actionButtons"')
        && str_contains($dialog, 'name="batchSize"') && str_contains($dialog, "T('Auction', 'Batch size')"),
    'item information' => str_contains($information, 'class="stat"') && str_contains($information, 'class="priceTable"'),
    'sell page' => str_contains($sell, 'auction-sell.js') && !str_contains($sell, 'Confirm sale') && !str_contains($sell, 'sellForm'),
    'script' => str_contains($script, 'sellItemDialog') && str_contains($script, 'toast("warning"') && str_contains($script, 'toast("error"'),
    'in place refresh' => str_contains($script, 'refreshAfterSale') && !str_contains($script, 'location.reload'),
    'old keys gone' => !str_contains($read('game/include/resources/Translation/en-US.php'), "['Auction']['Confirm sale:']")
        && !str_contains($read('game/include/resources/Translation/en-US.php'), "['Auction']['Really sell this item?']"),
    'limit message' => str_contains($controller, "You can only have x auctions at a time"),
    'no form post' => !str_contains($controller, "isset(\$_POST['id'], \$_POST['a'], \$_POST['amount'])"),
];
foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "Sell dialog contract: $label.\n");
        exit(1);
    }
}

echo "Auction sell dialog contract passed.\n";
