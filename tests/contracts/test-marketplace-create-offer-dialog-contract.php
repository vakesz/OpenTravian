<?php

declare(strict_types=1);

/**
 * Contract: Marketplace Offer uses createOfferDialog (no inline sell_resources table).
 */

function failMarketplaceCreateOfferDialogContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$offer = file_get_contents($root . '/game/include/resources/Templates/build/building/MarketplaceOffer.php');
if ($offer === false) {
    failMarketplaceCreateOfferDialogContract('Unable to read MarketplaceOffer.php');
}

foreach ([
    'id="openCreateOfferDialog"',
    'id="createOfferDialogTemplate"',
    'id="createOfferForm"',
    'class="formV2"',
    'basic createOfferDialog',
    'resourcePicker',
    'selectResource',
    'name="maxTime"',
    'name="ownAlliance"',
    'textButtonV2 buttonFramed create disabled rectangle withText green',
    'textButtonV2 buttonFramed cancel rectangle withText grey',
    ':&nbsp;<span class="value">',
] as $needle) {
    if (!str_contains($offer, $needle)) {
        failMarketplaceCreateOfferDialogContract("MarketplaceOffer missing create-offer chrome: {$needle}");
    }
}

if (str_contains($offer, 'class="sell_resources')
    || str_contains($offer, '<table id="sell"')
    || str_contains($offer, 'name="rid1" tabindex')
    || str_contains($offer, 'toggleClass(\'hide\')')) {
    failMarketplaceCreateOfferDialogContract('MarketplaceOffer still ships the obsolete inline sell_resources create path.');
}

$ctrl = file_get_contents($root . '/game/include/Controller/Build/MarketPlaceCtrl.php');
if ($ctrl === false) {
    failMarketplaceCreateOfferDialogContract('Unable to read MarketPlaceCtrl.php');
}
if (!str_contains($ctrl, "\$view->vars['resources'] = array_map(")) {
    failMarketplaceCreateOfferDialogContract('Offer() must pass current village resources into the dialog template.');
}
if (!str_contains($ctrl, "\$_POST['ownAlliance']") || !str_contains($ctrl, "\$_POST['maxTime']")) {
    failMarketplaceCreateOfferDialogContract('Offer() must accept ownAlliance/maxTime aliases.');
}

$translation = file_get_contents($root . '/game/include/resources/Translation/en-US.php');
if ($translation === false || !str_contains($translation, "['Maximum hours of transport'] = 'Maximum hours of transport'")) {
    failMarketplaceCreateOfferDialogContract('Missing Maximum hours of transport translation.');
}

echo "Marketplace create-offer dialog contract passed.\n";
