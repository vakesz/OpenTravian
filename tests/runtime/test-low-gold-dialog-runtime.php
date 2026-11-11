<?php

declare(strict_types=1);

// The "not enough gold" dialog opened by an Activate or Redeem click with too little gold has the reference's
// markup: the smallest package as a bestPackage label with productAmount and productPrice, Buy Gold, and
// "Choose a different package" (0 gold, Plus dialog).

require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use resources\View\PHPBatchView;

$view = new PHPBatchView('payment/showLowGold');
$view->vars = [
    'goldNum' => 36,
    'goldProductId' => 'package-a',
    'goldProductName' => 'Package A',
    'goldProductImageName' => 'package1.png',
    'currency' => 'EUR',
    'priceMinor' => 199,
    'bestSeller' => false,
    'bestValue' => true,
];
$html = (string)preg_replace('/\s+/', ' ', $view->output());

$expected = [
    '<div id="smallestPackageDialog">',
    '<div class="infoText">',
    'You do not have enough gold to use this feature!',
    '<input id="package0" type="radio" value="packageA" name="package">',
    '<label for="package0" class="package bestPackage">',
    '<div class="best bestValue"><div class="text">Best value</div></div>',
    '<div class="inlineIcon productAmount">',
    '<span class="value ">36</span>',
    '<div class="packageImage">',
    '<div class="productPrice">',
    '<span class="asterisk">*</span>',
    '<div class="changePackage">Change</div>',
    'id="buySmallestPackage"',
    'Buy Gold',
    'Choose a different package',
];
foreach ($expected as $needle) {
    if (!str_contains($html, $needle)) {
        fwrite(STDERR, "The low gold dialog lacks: $needle\n");
        exit(1);
    }
}
foreach (['inlineIcon amount', 'class="price"', 'Choose another package', 'Buy gold<'] as $old) {
    if (str_contains($html, $old)) {
        fwrite(STDERR, "The low gold dialog still has the old markup: $old\n");
        exit(1);
    }
}
$premium = (string)file_get_contents('/travian/game/include/Controller/Ajax/premium.php');
if (!str_contains($premium, '"paymentShopV5 smallestPackage"')) {
    fwrite(STDERR, "The low gold dialog uses the V5 shop class.\n");
    exit(1);
}

echo "Low gold dialog runtime test passed.\n";
