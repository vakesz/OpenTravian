<?php

declare(strict_types=1);

/*
 * Selling, bidding and exchanging: the Sell item dialog
 * marks the batch size invalid when nothing is chosen and pins its label once one is; every sale is confirmed in
 * the fee dialog (until "Don't show this message again" is ticked, kept as confirmedAuctionFeeAt); a sale of one's
 * own can be cancelled in its first five minutes without a confirmation; the exchange office answers with toasts
 * and asks before it spends gold; the player's own highest bid is a highlighted row, a "You" badge and a dimmed
 * Bid button.
 */

$root = dirname(__DIR__, 2);
$read = static fn (string $path): string => (string)file_get_contents($root . '/' . $path);

$script = $read('game/deployment/public/auction-sell.js');
$controller = $read('game/include/Controller/HeroAuctionCtrl.php');
$filter = $read('game/include/resources/Templates/hero/AuctionFilter.php');
$routes = $read('game/deployment/include/api_legacy_routes.php');
$feeDialog = $read('game/include/resources/Templates/hero/AuctionFeeDialog.php');
$preferences = $read('game/include/Core/Helper/PreferencesHelper.php');
$world = $read('game/include/config.php');

$checks = [
    'invalid batch size' => str_contains($script, 'field.addClass("invalid")') && str_contains($script, 'addClass("show")') && str_contains($script, 'toggleClass("pinned"'),
    'fee dialog' => str_contains($script, 'confirmAuctionFeeDialog') && str_contains($script, 'confirmedAuctionFeeAt')
        && str_contains($feeDialog, 'id="confirmFeeForm"') && str_contains($feeDialog, 'name="skipHint"')
        && str_contains($preferences, 'AuctionFee::PREFERENCE_CONFIRMED') && str_contains($world, "'feePercent'"),
    'cancel without confirmation' => str_contains($script, 'auction/cancel') && str_contains($routes, "'POST auction/cancel'")
        && str_contains($controller, 'data-auction-cancel') && !str_contains($controller, 'cancelAuctionForm'),
    'exchange toasts and confirmation' => str_contains($filter, "Travian.Game.Toast.show({type: 'success'")
        && str_contains($filter, 'exchangeGoldToSilverConfirmation') && !str_contains($filter, 'exchangeMessage'),
    'own bid' => str_contains($controller, "'highlighted'") && str_contains($controller, "T('Auction', 'You')")
        && str_contains($controller, "(\$isMyBid && !\$exchange)"),
    'started at' => str_contains($controller, "TimezoneHelper::date('j.n.Y, H:i'"),
];
foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "Auction sale states contract: $label.\n");
        exit(1);
    }
}

echo "Auction sale states contract passed.\n";
