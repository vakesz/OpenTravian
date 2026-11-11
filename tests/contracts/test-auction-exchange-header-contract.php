<?php

declare(strict_types=1);

/*
 * A finished exchange in the auction exchange office updates the top bar's gold and silver as well as the widget.
 */

$root = dirname(__DIR__, 2);
$filter = (string)file_get_contents($root . '/game/include/resources/Templates/hero/AuctionFilter.php');

$ok = str_contains($filter, "jQuery('.ajaxReplaceableGoldAmount').html(Travian.i18n.Number(data.newGold")
    && str_contains($filter, "jQuery('.ajaxReplaceableSilverAmount').html(Travian.i18n.Number(data.newSilver");

if (!$ok) {
    fwrite(STDERR, "The exchange header refresh drifted.\n");
    exit(1);
}
echo "Auction exchange header contract passed.\n";
