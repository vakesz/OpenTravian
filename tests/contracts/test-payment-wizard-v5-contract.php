<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

foreach ([
    'game/include/resources/Templates/payment/earnGold.php',
    'game/include/resources/Templates/payment/plusSupport.php',
    'game/include/resources/Templates/payment/paymentFeatures.php',
] as $retiredTemplate) {
    assertPaymentWizardV5(
        !is_file($root . '/' . $retiredTemplate),
        'The maintained V5 shop must not retain the retired V4 template ' . $retiredTemplate . '.'
    );
}

function assertPaymentWizardV5(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function readPaymentWizardV5(string $relativePath): string
{
    global $root;

    $source = file_get_contents($root . '/' . $relativePath);
    assertPaymentWizardV5(is_string($source), 'Missing payment-wizard source: ' . $relativePath);

    return $source;
}

$head = readPaymentWizardV5('game/include/resources/Templates/layout/head.php');
assertPaymentWizardV5(
    str_contains($head, '"shopUIVersion":5,"version":2,"cssClass":"paymentShopV5"'),
    'The maintained payment wizard must open in the V5 dialog shell.'
);

$layout = readPaymentWizardV5('game/include/resources/Templates/payment/paymentWizardLayout.php');
assertPaymentWizardV5(
    str_contains($layout, "'buyGold' => 'Buy Gold'")
        && str_contains($layout, "'specialOffers' => 'Specials'")
        && str_contains($layout, 'tabItem <?=$activeTab === $tab ? \'active\' : \'\';?>'),
    'The V5 payment tabs must retain the reference labels and selected-tab state.'
);
assertPaymentWizardV5(
    !str_contains($layout, 'newRibbon tab'),
    'The V5 payment tabs must not add a Buy Gold ribbon that is absent from the reference.'
);
assertPaymentWizardV5(
    str_contains($layout, "require __DIR__ . '/../layout/currencyCoin.php'")
        && str_contains($layout, '$currencyCoinInteractive = false;'),
    'The V5 payment balance must reuse the maintained reference gold-coin SVG without a nested shop action.'
);
assertPaymentWizardV5(
    str_contains($layout, "T('PaymentWizard', 'paymentUnAvailable')")
        && !str_contains($layout, 'configured for this local world'),
    'The unavailable buy-gold state must use the canonical localized payment message, not local-only copy.'
);
$english = readPaymentWizardV5('game/include/resources/Translation/en-US.php');
assertPaymentWizardV5(
    str_contains($english, "['paymentUnAvailable'] = 'Payment is currently not available. Please try again later.'"),
    'The unavailable buy-gold state must retain the current reference wording.'
);

$controller = readPaymentWizardV5('game/include/Controller/Ajax/paymentWizard.php');
$ajaxController = readPaymentWizardV5('game/include/Controller/AjaxCtrl.php');
assertPaymentWizardV5(
    str_contains($controller, '$view->vars[\'purchaseAvailable\'] = PaymentShop::isOpen(')
        && str_contains($ajaxController, '$rawHtml = !empty($response[\'_rawHtml\']);'),
    'The V5 buy-gold endpoint must expose payment availability and use the default JSON dialog transport.'
);


$layout = readPaymentWizardV5('game/include/resources/Templates/payment/paymentWizardLayout.php');
foreach (['buyGold', 'specialOffers', 'vouchers', 'advantages', 'paymentWizardContent'] as $requiredMarkup) {
    assertPaymentWizardV5(
        str_contains($layout, $requiredMarkup),
        'The V5 payment layout must expose ' . $requiredMarkup . '.'
    );
}
assertPaymentWizardV5(
    !str_contains($layout, 'paymentShopV4'),
    'The V5 payment layout must not retain the retired V4 shell.'
);

$advantages = readPaymentWizardV5('game/include/resources/Templates/payment/pros.php');
foreach (['advantagesBonusBox goldClub', 'advantagesBonusBox travianPlus', "'lumber'", "'crop'", 'ProductionBonus'] as $requiredMarkup) {
    assertPaymentWizardV5(
        str_contains($advantages, $requiredMarkup),
        'The V5 advantages surface must render ' . $requiredMarkup . '.'
    );
}
assertPaymentWizardV5(
    !str_contains($advantages, 'prosLegacyFeatureInfo') && !str_contains($advantages, 'prosAdvantagesGrid'),
    'The V5 advantages surface must not render the retired two-column V4 content.'
);

$controller = readPaymentWizardV5('game/include/Controller/Ajax/paymentWizard.php');
foreach (["'buyGold'", "'specialOffers'", "'vouchers'", "'advantages'"] as $requiredTab) {
    assertPaymentWizardV5(
        str_contains($controller, $requiredTab),
        'The payment-wizard controller must accept the V5 ' . $requiredTab . ' tab.'
    );
}
assertPaymentWizardV5(
    !str_contains($controller, "'earnGold'") && !str_contains($controller, "'paymentFeatures'"),
    'The payment-wizard controller must not expose retired V4 tabs.'
);
assertPaymentWizardV5(
    !str_contains($controller, '$this->view')
        && !str_contains($controller, '$this->callInit')
        && str_contains($controller, 'private function renderPros(): string')
        && str_contains($controller, 'private function renderVoucher(): string'),
    'The V5 payment controller must not create legacy dynamic properties while rendering its tabs.'
);
assertPaymentWizardV5(
    str_contains($controller, 'private function renderSpecialOffers(): string')
        && str_contains($controller, '$this->renderSpecialOffers()'),
    'The V5 Specials tab must render its maintained native empty-offer surface.'
);

$specialOffers = readPaymentWizardV5('game/include/resources/Templates/payment/specialOffers.php');
foreach ([
    'contentV2 shopWrapper selectPackage',
    'packages specialOffersPackages',
    'package specialOffer typeOneTimeOffer noOffer emptyOffer',
    'There are no specials on offer currently.',
    'paymentMethods',
    'choosePayment',
    'transactionHistory',
    'id="continueBuy"',
    'id="confirmBuy"',
    'id="backToGame"',
] as $requiredSpecialOfferMarkup) {
    assertPaymentWizardV5(
        str_contains($specialOffers, $requiredSpecialOfferMarkup),
        'The V5 empty-Specials surface must retain ' . $requiredSpecialOfferMarkup . '.'
    );
}

foreach (['game/deployment/public/game-client.js'] as $bundlePath) {
    $bundle = readPaymentWizardV5($bundlePath);
    assertPaymentWizardV5(
        !str_contains($bundle, 'paymentWizardAnswersLink')
            && !str_contains($bundle, 'paymentWizard.infoButtonLabel'),
        'The payment wizard must not retain the retired generic Knowledge Base/Answers control: ' . $bundlePath
    );
    assertPaymentWizardV5(
        str_contains($bundle, '"advantages" === e.options.data.activeTab')
            && str_contains($bundle, 'input.prolongPlus, input[class*=prolongProductionboost]'),
        'The V5 advantages client bindings are incomplete: ' . $bundlePath
    );
    assertPaymentWizardV5(
        !str_contains($bundle, 'data: { activeTab: "pros" }'),
        'The V5 payment client must not open the retired pros tab: ' . $bundlePath
    );
    assertPaymentWizardV5(
        str_contains($bundle, 'jQuery(t.currentTarget).data("tabname")')
            && !str_contains($bundle, 'jQuery(t.target).data("tabname")'),
        'The V5 tab handler must read data-tabname from its bound tab item, including clicks on the nested label: ' . $bundlePath
    );
    assertPaymentWizardV5(
        str_contains($bundle, 'e.reload &&')
            && str_contains($bundle, 'Travian.Game.Layout.updateGold()')
            && str_contains($bundle, 'i.reloadDialog({}, "paymentWizard")'),
        'Successful premium purchases must refresh the V5 advantages dialog and the header gold balance: ' . $bundlePath
    );
    assertPaymentWizardV5(
        str_contains($bundle, 'Travian.api("paynet/redeem-voucher"')
            && str_contains($bundle, 'initializeVouchersTab = function ()')
            && !str_contains($bundle, 'this.initializeVouchersTab = function () {\n        return !1;'),
        'The V5 voucher tab must bind the supported voucher endpoint instead of remaining inert: ' . $bundlePath
    );
    assertPaymentWizardV5(
        !str_contains($bundle, 'ShopUIV4')
            && !str_contains($bundle, 'payment-wizard/open-offers')
            && !str_contains($bundle, 'payment-wizard/advertised-persons')
            && !str_contains($bundle, 'paynet/init')
            && !str_contains($bundle, 'paynet/cart')
            && !str_contains($bundle, 'paynet/create-payment'),
        'The maintained bundle must not retain the retired V4 payment client or its endpoints: ' . $bundlePath
    );
    assertPaymentWizardV5(
        !str_contains($bundle, 'Travian.Game.ReferAFriend =')
            && !str_contains($bundle, 'referAFriendDialog/sendInvites'),
        'The maintained bundle must not retain the unreachable V4 payment referral bridge: ' . $bundlePath
    );
    assertPaymentWizardV5(
        !str_contains($bundle, 'tgpay.php')
            && !str_contains($bundle, 'openProvider = function')
            && !str_contains($bundle, 'buyNowAction = function'),
        'The maintained V5 payment client must not retain an external-provider hand-off: ' . $bundlePath
    );
}

$vouchers = readPaymentWizardV5('game/include/resources/Templates/payment/vouchers.php');
assertPaymentWizardV5(
    !str_contains($vouchers, 'shopUIV4')
        && !str_contains($vouchers, 'redeemVoucher_script')
        && !str_contains($vouchers, 'formatInfo')
        && !str_contains($vouchers, "T(\"PaymentWizard\", \"Redeem voucher\")"),
    'The V5 voucher template must not retain V4 glue or the local-only Redeem voucher label.'
);

$routes = readPaymentWizardV5('game/deployment/include/api_legacy_routes.php');
foreach ([
    "'payment-wizard/open-offers'",
    "'paynet/cart'",
    "'paynet/create-payment'",
    "'paynet/init'",
    "'paynet/transaction-history-dialog'",
] as $retiredRoute) {
    assertPaymentWizardV5(
        !str_contains($routes, $retiredRoute),
        'The maintained V5 shop must not expose the retired V4 route ' . $retiredRoute . '.'
    );
}

echo "Payment wizard V5 contract passed.\n";
