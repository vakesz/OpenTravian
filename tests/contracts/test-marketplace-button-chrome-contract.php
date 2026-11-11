<?php

declare(strict_types=1);

/**
 * Contract: Marketplace Buy/Offer/accept controls use textButtonV2 class order.
 */

function failMarketplaceButtonChromeContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);

$buy = file_get_contents($root . '/game/include/resources/Templates/build/building/MarketplaceBuy.php');
if ($buy === false) {
    failMarketplaceButtonChromeContract('Unable to read MarketplaceBuy.php');
}
foreach ([
    'textButtonV2 buttonFramed rectangle withIcon toggle grey',
    'class="resource <?=$class;?>_small"',
    'data-option="<?=$id;?>"',
    ':&nbsp;<span class="value">',
] as $needle) {
    if (!str_contains($buy, $needle)) {
        failMarketplaceButtonChromeContract("MarketplaceBuy missing chrome: {$needle}");
    }
}
if (str_contains($buy, 'buttonFramed grey rectangle textButtonV2')
    || str_contains($buy, '<?=$class;?>_small resource')
    || str_contains($buy, 'Merchants");?> : <span')
    || str_contains($buy, "Merchants');?> : <span")) {
    failMarketplaceButtonChromeContract('MarketplaceBuy still uses older button/icon/colon chrome.');
}

$offer = file_get_contents($root . '/game/include/resources/Templates/build/building/MarketplaceOffer.php');
if ($offer === false) {
    failMarketplaceButtonChromeContract('Unable to read MarketplaceOffer.php');
}
if (!str_contains($offer, 'textButtonV2 buttonFramed rectangle withText green')) {
    failMarketplaceButtonChromeContract('MarketplaceOffer Create offer must use textButtonV2 buttonFramed rectangle withText green.');
}
if (str_contains($offer, 'buttonFramed green rectangle textButtonV2')
    || str_contains($offer, 'class="sell_resources')) {
    failMarketplaceButtonChromeContract('MarketplaceOffer still uses older Create offer chrome or inline sell_resources.');
}
if (!str_contains($offer, ':&nbsp;<span class="value">')) {
    failMarketplaceButtonChromeContract('MarketplaceOffer merchants/capacity labels must use colon+nbsp spacing.');
}

$ctrl = file_get_contents($root . '/game/include/Controller/Build/MarketPlaceCtrl.php');
if ($ctrl === false) {
    failMarketplaceButtonChromeContract('Unable to read MarketPlaceCtrl.php');
}
if (!str_contains($ctrl, "textButtonV2 buttonFramed' . (\$disabled ? ' disabled' : '') . ' rectangle withIcon green")) {
    failMarketplaceButtonChromeContract('MarketPlaceCtrl accept buttons must use textButtonV2 class order with disabled class.');
}
if (str_contains($ctrl, 'buttonFramed green rectangle textButtonV2 withIcon')) {
    failMarketplaceButtonChromeContract('MarketPlaceCtrl still uses older accept button class order.');
}

echo "Marketplace button chrome contract passed.\n";
