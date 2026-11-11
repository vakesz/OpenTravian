<?php

declare(strict_types=1);

/**
 * Overview chrome no longer exposes delete-all / outbid-delete
 * controls. Bid changes use the maximum-bid form (secure_id + checker) instead.
 */

/** @return string */
function heroAuctionDeletionSource(string $path): string
{
    $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    if ($source === false) {
        fwrite(STDERR, "Unable to read {$path}.\n");
        exit(1);
    }

    return $source;
}

function assertHeroAuctionDeletion(bool $condition, string $message): void
{
    if ($condition) {
        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$controller = heroAuctionDeletionSource('game/include/Controller/HeroAuctionCtrl.php');
$template = heroAuctionDeletionSource('game/include/resources/Templates/hero/AuctionBids.php');

assertHeroAuctionDeletion(
    !str_contains($controller, "\$_GET['deleteRunning']")
        && !str_contains($controller, "\$_REQUEST['delete']")
        && !str_contains($controller, 'deleteRunning=')
        && !str_contains($controller, "\$_POST['auctionBidDelete']"),
    'Auction overview must not retain legacy bid-deletion mutation paths.'
);

assertHeroAuctionDeletion(
    !str_contains($template, 'id="del"')
        && !str_contains($template, 'name="auctionBidDelete"')
        && !str_contains($template, 'name="delete[]"')
        && !str_contains($template, 'table.currentBid'),
    'Auction overview chrome must not expose the legacy delete-all / currentBid tables.'
);

assertHeroAuctionDeletion(
    str_contains($controller, 'renderAuctionBidForm')
        && str_contains($controller, 'getAuctionBySecureId($_REQUEST[\'a\'])')
        && str_contains($controller, 'name="bidAmount"')
        && str_contains($controller, 'class="formV2 bidInput"'),
    'Overview rebids must use the checker-backed bidAmount form with secure_id (field name).'
);

echo "Hero auction deletion contract passed.\n";
