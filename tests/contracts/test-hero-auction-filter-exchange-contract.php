<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

/** @return string */
function auctionFilterExchangeSource(string $relativePath): string
{
    $source = file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
    if ($source === false) {
        fwrite(STDERR, "Unable to read {$relativePath}.\n");
        exit(1);
    }

    return $source;
}

function assertAuctionFilterExchange(bool $condition, string $message): void
{
    if ($condition) {
        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$controller = auctionFilterExchangeSource('game/include/Controller/HeroAuctionCtrl.php');
$filter = auctionFilterExchangeSource('game/include/resources/Templates/hero/AuctionFilter.php');
$buy = auctionFilterExchangeSource('game/include/resources/Templates/hero/AuctionBuy.php');
$detail = auctionFilterExchangeSource('game/include/resources/Templates/hero/AuctionDetail.php')
    . auctionFilterExchangeSource('game/include/resources/Templates/hero/AuctionItemInformation.php');
$sell = auctionFilterExchangeSource('game/include/resources/Templates/hero/AuctionSell.php');
$bids = auctionFilterExchangeSource('game/include/resources/Templates/hero/AuctionBids.php');
$model = auctionFilterExchangeSource('game/include/Model/AuctionModel.php');
$items = auctionFilterExchangeSource('game/include/Game/Hero/HeroItems.php');
$fixes = auctionFilterExchangeSource('assets/gpack/travian/mainPage/lang/overrides.css');
$translations = auctionFilterExchangeSource('game/include/resources/Translation/en-US.php');

assertAuctionFilterExchange(
    str_contains($filter, 'goldenWrapper')
        && str_contains($filter, 'itemTypeSearch')
        && str_contains($filter, 'heroItemSlotFilter filterSlotsRow')
        && str_contains($filter, "'leftHand'")
        && str_contains($filter, "'bag'")
        && str_contains($filter, 'filterSlot')
        && str_contains($filter, 'exchangeWidget')
        && str_contains($filter, 'exchangeGold')
        && str_contains($filter, 'openExchangeOffice')
        && str_contains($filter, 'exchangeSilver')
        && str_contains($filter, "Travian.api('silver/exchange'"),
    'Buy filter chrome must expose goldenWrapper slot filters and the exchange widget API hook.'
);

assertAuctionFilterExchange(
    str_contains($filter, "cssClass: 'basic exchangeOfficeDialog'")
        && str_contains($filter, 'id="exchangeOffice" data-direction="GoldToSilver"')
        && str_contains($filter, 'class="changeDirection" data-direction="GoldToSilver"')
        && str_contains($filter, 'class="exchangeRate"')
        && str_contains($filter, 'class="formV2 valueInput"')
        && str_contains($filter, 'maxlength="6" class="exchangeValue"')
        && str_contains($filter, 'class="preview transparent"')
        && str_contains($filter, "T('Auction', 'silverExchange')")
        && str_contains($filter, "T('Auction', 'Convert')")
        && str_contains($filter, "T('Auction', 'Current')")
        && str_contains($filter, "T('Auction', 'New')")
        && str_contains($filter, "T('inGame', 'goldShort')")
        && str_contains($filter, "T('inGame', 'silverShort')")
        && str_contains($filter, 'setDirection(nextDirection)')
        && str_contains($filter, "amountInput.val(direction === 'GoldToSilver' ? 1 : 200)")
        && str_contains($filter, "root.find('button.exchange')")
        && str_contains($filter, ".prop('disabled', exchanged === 0)")
        && str_contains($filter, 'insertBefore(directionSwitch)')
        && str_contains($filter, 'insertAfter(directionSwitch)')
        && !str_contains($filter, "?: 'Show everything'")
        && !str_contains($filter, "?: 'exchange'")
        && !str_contains($filter, "?: 'Exchange office'")
        && !str_contains($filter, 'Exchange failed')
        && !str_contains($filter, 'Gold to silver')
        && !str_contains($filter, 'Silver to gold')
        && !str_contains($filter, 'Search auction items')
        && !str_contains($filter, 'aria-label="Clear search"')
        && str_contains($translations, "\$Definition['Auction']['Convert'] = 'Convert';")
        && str_contains($translations, "\$Definition['Auction']['Current'] = 'Current';")
        && str_contains($translations, "\$Definition['Auction']['New'] = 'New';"),
    'Exchange office must use the reference-shaped directions, rate, Current/New preview, and canonical Convert copy without local fallbacks.'
);

assertAuctionFilterExchange(
    str_contains($filter, 'autoCompleteResult typeAuctionItem')
        && str_contains($filter, 'cancelSearch')
        && str_contains($filter, "search.on('click', '.autoCompleteResult'")
        && str_contains($controller, 'getAuctionSearchItems')
        && str_contains($model, 'getBuyAuctionSearchCounts')
        && str_contains($items, 'getAvailableItemTypes')
        && !str_contains($filter, "search.on('input', 'input[type=\"text\"]'"),
    'Auction search must expose the complete native item catalogue and live listing counts without hiding it locally.'
);

assertAuctionFilterExchange(
    str_contains($fixes, '#auctions .itemTypeSearch.closed label.input.search')
        && str_contains($fixes, 'display: none;'),
    'Closed auction search must not expose the pre-rendered catalogue.'
);

assertAuctionFilterExchange(
    !str_contains($filter, 'iconFilter')
        && !str_contains($filter, 'repeat(15,32px)')
        && !str_contains($filter, 'itemCategory_bandage25'),
    'The legacy 15-icon auction filter markup must be removed.'
);

assertAuctionFilterExchange(
    str_contains($buy, 'groupAndControls')
        && str_contains($buy, 'filterBar')
        && str_contains($buy, 'itemTypeListWrapper')
        && str_contains($buy, "AuctionSort::header('itemInformation'")
        && str_contains($buy, 'listLayout_detailed')
        && str_contains($buy, 'button type="button"')
        && !str_contains($buy, '<a class="textButtonV2 buttonSecondary listLayout_')
        && str_contains($buy, '<svg viewBox="0 0 14 8"'),
    'Buy view must use native 20px button list controls before the modern filter bar.'
);

assertAuctionFilterExchange(
    str_contains($filter, "'showSlots'")
        && str_contains($filter, "'backPath'")
        && str_contains($filter, 'class="textButtonV2 buttonSecondary backButton')
        && str_contains($detail, 'itemTypeDetailContainer')
        && str_contains($detail, 'itemTypeDetailedInformation')
        && str_contains($detail, 'auctionListWrapper')
        && str_contains($detail, 'listLayout_detailed')
        && str_contains($detail, 'listLayout_compact')
        && str_contains($controller, 'name="bidAmount"')
        && str_contains($controller, 'Average price and sales in the last 24h')
        && str_contains($detail, 'Lowest selling price')
        && str_contains($detail, '<span class="tooltipHelpAnchor">bid</span> on items, with a set minimum price.'),
    'Auction item detail must preserve the native back/exchange bar, item summary, and maximum-bid list chrome.'
);

assertAuctionFilterExchange(
    str_contains($sell, 'heroV2 sell')
        && str_contains($sell, 'sellItems')
        && str_contains($sell, 'filterBar')
        && str_contains($sell, 'inventoryWrapper')
        && str_contains($sell, 'heroItems')
        && str_contains($sell, 'filter_bag'),
    'Sell view must keep the golden filter/exchange shell and client slot filtering.'
);

assertAuctionFilterExchange(
    str_contains($bids, 'filterHtml')
        && str_contains($bids, 'auctionsOverviewContainer'),
    'Overview must render the shared filter/exchange shell above the bid tables.'
);

assertAuctionFilterExchange(
    str_contains($controller, 'auctionsAuctionsOverview')
        && str_contains($controller, 'resolveFilterKey')
        && str_contains($controller, 'renderFilterBar')
        && str_contains($controller, 'getAllBuyAuctionGroups($session->getPlayerId(), $filterKey)')
        && str_contains($controller, 'AuctionSort::order(')
        && str_contains($controller, 'getBuyAuction($session->getPlayerId(), 1, 1000000, $filterKey)')
        && str_contains($controller, "ButtonPagination::renderForScript(\$page, \$pages, 'buy')")
        && str_contains($controller, 'buyDetail(')
        && str_contains($controller, "ButtonPagination::renderForScript(\$page, \$pages, 'detail')")
        && str_contains($controller, "'/auctions/buy/' . (int)\$row['type']")
        && str_contains($controller, 'ButtonPagination::renderForScript(')
        && str_contains(auctionFilterExchangeSource('game/include/Game/ButtonPagination.php'), 'class="pageIndex"')
        && str_contains($controller, 'auctionItemMeta((int)$row[\'btype\'], (int)$row[\'type\'])')
        && str_contains($controller, "return 'Consumable';")
        && str_contains($controller, "2 => 'Armor'")
        && str_contains($controller, "return 'Tier ' . ((\$type - 1) % 3 + 1)")
        && str_contains($controller, "(\$compact ? 'withIcon' : 'withText')")
        && str_contains($controller, "(\$exchange ? ' gold' : ' green')")
        && str_contains($controller, 'class="input amount"')
        && str_contains($controller, 'name="z"')
        && str_contains($detail, 'AuctionSort::bidTableHead($layout')
        && str_contains($fixes, '#auctions .auctionListWrapper table.itemType')
        && str_contains($controller, 'getAvailableGold()'),
    'Controller must wire slot filters, overview content class, exchange balances, and system auction visibility.'
);

assertAuctionFilterExchange(
    str_contains($buy, "<?= \$vars['pagination']; ?>")
        && !str_contains($buy, "<?= \$vars['nav']; ?>"),
    'Buy view must use the Hero V2 paginator instead of legacy PageNavigator markup.'
);

assertAuctionFilterExchange(
    str_contains($controller, 'data-auction-end-time')
        && str_contains($controller, 'formatAuctionTimer')
        && str_contains($controller, "'%02d:%02d:%02d'")
        && !str_contains($controller, '<td class="nextFinishAt"><span class="timerReact">'),
    'Auction list and detail timers must use the reference-shaped, padded HH:MM:SS countdown text.'
);

assertAuctionFilterExchange(
    str_contains($model, 'function auctionFilterClause')
        && str_contains($model, "filter === 'bag'")
        && str_contains($model, "'helmet' => 1"),
    'Auction queries must accept native slot keys including bag consumables.'
);

assertAuctionFilterExchange(
    str_contains($model, 'getBuyAuctionItemRows')
        && str_contains($model, 'getTotalBuyAuctionItemRows')
        && str_contains($model, 'active_bidder_name')
        && str_contains($model, 'getBuyAuctionItemPrices')
        && str_contains($model, 'getBuyAuctionItemHistory')
        && str_contains($controller, 'renderAuctionPriceChart'),
    'Auction item detail must read bounded active rows plus its 24-hour price history from the canonical auction table.'
);

$projectRoot = dirname(__DIR__, 2);
foreach (['accounting.php', 'silverExchange.php'] as $obsolete) {
    assertAuctionFilterExchange(
        !file_exists($projectRoot . '/game/include/resources/Templates/hero/' . $obsolete),
        "{$obsolete} must remain deleted."
    );
}

$slotAssets = [
    'assets/gpack/travian/mainPage/img_ltr/views/hero-v2/inventory/slots/slot-all-medium.png',
    'assets/gpack/travian/mainPage/img_ltr/views/hero-v2/inventory/slots/slot-bag-medium.png',
    'assets/gpack/travian/mainPage/img_ltr/views/hero-v2/inventory/slots/slot-helmet-medium.png',
];
foreach ($slotAssets as $asset) {
    assertAuctionFilterExchange(
        is_file($projectRoot . '/' . $asset),
        "Missing required local Gpack slot asset: {$asset}"
    );
}

echo "Hero auction filter/exchange contract passed.\n";
