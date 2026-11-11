<?php

declare(strict_types=1);

/*
 * Content of the confirmation that explains the auction house's fee before a sale (reference class
 * confirmAuctionFeeDialog): the percentage, an example sale of 100 silver, and a tick box that keeps the
 * dialog from showing again.
 */

use Game\Hero\AuctionFee;

$e = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
$currencyCoinKind = 'silver';
$currencyCoinInteractive = false;
ob_start();
require __DIR__ . '/../layout/currencyCoin.php';
$coin = (string)preg_replace('/\sclass="silverCoin"/', '', (string)ob_get_clean(), 1);
$price = 100;
$fee = AuctionFee::of($price);
?>
<div class="contentV2">
    <div id="confirmFeeForm" class="formV2">
        <p><?= $e(str_replace('[NUMBER]', (string)AuctionFee::percent(), (string)T('Auction', 'auctionFeeHint'))); ?></p>
        <div class="feePriceList">
            <em class="head"><?= $e((string)T('Auction', 'auctionFeeExampleHeadline')); ?></em>
            <span><?= $e((string)T('Auction', 'auctionFeeExampleLabelPrice')); ?></span> <span></span> <?= $coin; ?> <span><?= $price; ?></span>
            <span><?= $e((string)T('Auction', 'auctionFeeExampleLabelFee')); ?></span> <span>-</span> <?= $coin; ?> <span><?= $fee; ?></span>
            <span><?= $e((string)T('Auction', 'auctionFeeExampleLabelPayout')); ?></span> <span>=</span> <?= $coin; ?> <span><?= $price - $fee; ?></span>
        </div>
        <label class="checkbox">
            <input type="checkbox" value="1" name="skipHint">
            <svg viewBox="0 0 18 18" class="checkbox"><path class="box" d="M16 0H2a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V2a2 2 0 00-2-2z"></path><rect class="indeterminate" width="10" height="2" x="4" y="8"></rect><path class="checkmark" d="M15.28 4.28l-8.27 8.27-4.3-4.26h0"></path></svg>
            <div class="label"><?= $e((string)T('Auction', 'auctionFeeSkipHint')); ?></div>
        </label>
        <div class="actionButtons">
            <button class="textButtonV2 buttonFramed cancel rectangle withText grey" type="button"><div><?= $e((string)T('HeroInventory', 'buttonCancel')); ?></div></button>
            <button class="textButtonV2 buttonFramed sell rectangle withText green" type="submit"><div><?= $e((string)T('Auction', 'auctionFeeConfirm')); ?></div></button>
        </div>
    </div>
</div>
