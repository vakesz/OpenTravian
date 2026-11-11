<?php

declare(strict_types=1);

/**
 * Contract: hero auction sale bundles and cancellation follow Knowledge Base
 * article 94. Consumables sell in stacks of 5, 10, 30 or 50; equipment and
 * artwork sell as one item; a sale can only be cancelled in its first five
 * minutes.
 */

use Controller\Ajax\heroAuction;
use Model\AuctionModel;

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$controller = file_get_contents($root . '/game/include/Controller/HeroAuctionCtrl.php');
$template = file_get_contents($root . '/game/include/resources/Templates/hero/AuctionSell.php');

if ($controller === false || $template === false) {
    fwrite(STDERR, "Could not read the hero auction sale sources.\n");
    exit(1);
}

if (heroAuction::ITEM_PACKAGES !== [5, 10, 30, 50]) {
    fwrite(STDERR, "Consumables must be sold in stacks of 5, 10, 30 or 50.\n");
    exit(1);
}

foreach ([7, 8, 9, 10, 11, 12, 13, 14] as $stackableBtype) {
    if (heroAuction::getPackagesForItem($stackableBtype) !== [5, 10, 30, 50]) {
        fwrite(STDERR, "Base type $stackableBtype must offer the official sale stacks.\n");
        exit(1);
    }
}

foreach ([1, 2, 3, 4, 5, 6, 15, 16] as $singleBtype) {
    if (heroAuction::getPackagesForItem($singleBtype) !== [1]) {
        fwrite(STDERR, "Base type $singleBtype is not sold in quantity stacks.\n");
        exit(1);
    }
}

$now = time();
$fresh = ['bids' => 0, 'finish' => 0, 'time' => $now + 3600, 'created' => $now - 60];
$old = ['bids' => 0, 'finish' => 0, 'time' => $now + 3600, 'created' => $now - 301];
$bid = ['bids' => 1, 'finish' => 0, 'time' => $now + 3600, 'created' => $now - 60];
$ended = ['bids' => 0, 'finish' => 0, 'time' => $now - 1, 'created' => $now - 60];
$untracked = ['bids' => 0, 'finish' => 0, 'time' => $now + 3600, 'created' => 0];

if (!AuctionModel::canCancel($fresh)) {
    fwrite(STDERR, "A sale without bids must be cancellable inside its first five minutes.\n");
    exit(1);
}
foreach (['after five minutes' => $old, 'with a bid' => $bid, 'already ended' => $ended, 'without a creation time' => $untracked] as $label => $case) {
    if (AuctionModel::canCancel($case)) {
        fwrite(STDERR, "A sale must not be cancellable $label.\n");
        exit(1);
    }
}

foreach ([
    'heroAuction::getPackagesForItem((int)$row[\'btype\'], (int)$row[\'type\'])',
    'AuctionModel::CANCEL_WINDOW_SECONDS',
    'AuctionModel::canCancel($auction)',
    'data-min-amount=',
    '!in_array($amount, $packages, true) || $amount > (int)$item[\'num\']',
    'You can only have x auctions at a time',
] as $contract) {
    if (!str_contains($controller, $contract)) {
        fwrite(STDERR, "The sell controller must pass server-authorized package amounts and the cancel window to the client.\n");
        exit(1);
    }
}

$sellScript = file_get_contents($root . '/game/deployment/public/auction-sell.js');
$dialog = file_get_contents($root . '/game/include/resources/Templates/hero/AuctionSellDialog.php');
foreach ([
    [$sellScript, 'auction/sell-dialog'],
    [$sellScript, 'auction/sell-item'],
    [$sellScript, 'data-min-amount'],
    [$dialog, 'name="batchSize"'],
    [$dialog, '<option></option>'],
] as [$source, $contract]) {
    if ($source === false || !str_contains($source, $contract)) {
        fwrite(STDERR, "The sale dialog must use a valid server-authorized package amount.\n");
        exit(1);
    }
}
if (str_contains($template, 'Confirm sale') || str_contains($template, 'Travian.Game.HeroAuction')) {
    fwrite(STDERR, "The old yes/no sale dialog must be gone.\n");
    exit(1);
}

echo "Hero auction sell-package contract passed.\n";
