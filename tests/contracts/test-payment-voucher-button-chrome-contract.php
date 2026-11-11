<?php

declare(strict_types=1);

/**
 * Contract: payment voucher / low-gold buttons use textButtonV2 class order.
 */

function failPaymentVoucherButtonChromeContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);

$vouchers = file_get_contents($root . '/game/include/resources/Templates/payment/vouchers.php');
$lowGold = file_get_contents($root . '/game/include/resources/Templates/payment/showLowGold.php');
if ($vouchers === false || $lowGold === false) {
    failPaymentVoucherButtonChromeContract('Unable to read payment templates.');
}

foreach ([
    'textButtonV2 buttonFramed disabled rectangle withText green',
    'textButtonV2 buttonFramed rectangle withText green',
] as $needle) {
    if (!str_contains($vouchers, $needle)) {
        failPaymentVoucherButtonChromeContract("vouchers.php missing: {$needle}");
    }
}

if (str_contains($vouchers, 'textButtonV2 green')
    || str_contains($lowGold, 'textButtonV2 green')) {
    failPaymentVoucherButtonChromeContract('Payment templates still put green before buttonFramed.');
}

if (!str_contains($lowGold, 'textButtonV2 buttonFramed rectangle withText green')) {
    failPaymentVoucherButtonChromeContract('showLowGold.php Buy gold must use class order.');
}

echo "Payment voucher button chrome contract passed.\n";
