<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/game/include/Controller/HeroAuctionCtrl.php');
$templates = [
    'AuctionBuy.php',
    'AuctionSell.php',
    'AuctionBids.php',
    'AuctionFilter.php',
    'AuctionLock.php',
];

if ($controller === false) {
    fwrite(STDERR, "Could not read the native auction controller.\n");
    exit(1);
}

if (!str_contains($controller, "contentCssClass'] = 'auctions'")
    || !str_contains($controller, '<div id="auctions" class="contentV2">')
    || str_contains($controller, '<div id="heroAuction"><div id="auction" class="heroV2">')) {
    fwrite(STDERR, "Auction markup must use the native #content.auctions #auctions.contentV2 Gpack scope.\n");
    exit(1);
}

if (str_contains($controller, "\$this->view->vars['content'] .= '<div class=\"bar\"")) {
    fwrite(STDERR, "The sell view must not close the native auction scope with a legacy progress bar.\n");
    exit(1);
}

if (!str_contains($controller, 'private function renderAuctionListRow($row, $grouped)')
    || !str_contains($controller, "row['auction_count']")) {
    fwrite(STDERR, "Auction listings must render as reference-style native item rows.\n");
    exit(1);
}

foreach ($templates as $template) {
    $source = file_get_contents($root . '/game/include/resources/Templates/hero/' . $template);
    if ($source === false) {
        fwrite(STDERR, "Could not read {$template}.\n");
        exit(1);
    }
}

foreach ([
    'AuctionBuy.php' => '<div class="groupAndControls">',
    'AuctionSell.php' => '<div class="heroV2 sell">',
    'AuctionBids.php' => '<?php',
    'AuctionFilter.php' => '<?php',
    'AuctionLock.php' => '<div class="auctionsLocked">',
] as $template => $openingElement) {
    $source = file_get_contents($root . '/game/include/resources/Templates/hero/' . $template);
    if ($source === false
        || !str_starts_with(ltrim($source), $openingElement)
        || str_contains($source, 'Spear of the Phalanx')) {
        fwrite(STDERR, "{$template} must contain only its live native auction markup.\n");
        exit(1);
    }
}

$buyTemplate = file_get_contents($root . '/game/include/resources/Templates/hero/AuctionBuy.php');
if ($buyTemplate === false
    || !str_contains($buyTemplate, 'Current auctions')
    || !str_contains($buyTemplate, 'Next finish')
    || !str_contains($buyTemplate, 'Next price')
    || (!str_contains($buyTemplate, 'filterBar') && !str_contains($buyTemplate, 'filterHtml'))
    || !str_contains($buyTemplate, 'listLayout_detailed')) {
    fwrite(STDERR, "Auction buy view must retain the reference item, count, finish, price columns, and filter bar.\n");
    exit(1);
}

$filterTemplate = file_get_contents($root . '/game/include/resources/Templates/hero/AuctionFilter.php');
if ($filterTemplate === false
    || !str_contains($filterTemplate, 'goldenWrapper')
    || !str_contains($filterTemplate, 'heroItemSlotFilter filterSlotsRow')
    || !str_contains($filterTemplate, 'exchangeWidget')
    || !str_contains($filterTemplate, 'openExchangeOffice')
    || !str_contains($filterTemplate, 'silver/exchange')) {
    fwrite(STDERR, "Auction filter chrome must use the native goldenWrapper slot filters and exchange widget.\n");
    exit(1);
}

if (!str_contains($controller, "new PHPBatchView('hero/AuctionLock')")
    || !str_contains($controller, "getSuccessAdventuresCount()")
    || !str_contains($controller, "min(100, \$completedAdventures * 10)")
    || !str_contains($controller, 'resolveFilterKey')
    || !str_contains($controller, 'renderFilterBar')) {
    fwrite(STDERR, "Auction lock state and native slot filters must be wired in the controller.\n");
    exit(1);
}

if (str_contains($controller, "'accounting'")
    || str_contains($controller, 'hero/accounting')
    || str_contains($controller, 'hero/silverExchange')) {
    fwrite(STDERR, "Obsolete accounting/silverExchange dual paths must stay removed.\n");
    exit(1);
}

foreach (['accounting.php', 'silverExchange.php'] as $obsoleteTemplate) {
    if (file_exists($root . '/game/include/resources/Templates/hero/' . $obsoleteTemplate)) {
        fwrite(STDERR, "{$obsoleteTemplate} must be removed with the obsolete auction navigation.\n");
        exit(1);
    }
}

$model = file_get_contents($root . '/game/include/Model/AuctionModel.php');
if ($model === false || !str_contains($model, 'function auctionFilterClause')) {
    fwrite(STDERR, "Auction listings must filter by native slot keys including bag consumables.\n");
    exit(1);
}

echo "Hero auction native scope contract passed.\n";
