<?php

declare(strict_types=1);

function failRetiredVoucherRouteContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$routeMap = file_get_contents($root . '/game/include/mainInclude.php');
$gameView = file_get_contents($root . '/game/include/resources/View/GameView.php');
$paymentWizard = file_get_contents($root . '/game/include/Controller/Ajax/paymentWizard.php');
$redeemVoucher = file_get_contents($root . '/game/include/Controller/Ajax/paynet/redeemVoucher.php');

if ($routeMap === false || $gameView === false || $paymentWizard === false || $redeemVoucher === false) {
    failRetiredVoucherRouteContract('The route map and modern voucher implementation must be readable.');
}

foreach ([
    $root . '/game/include/Controller/VoucherCtrl.php',
    $root . '/game/include/resources/Templates/voucher/main.php',
    $root . '/game/include/resources/Templates/voucher/menu.php',
    $root . '/game/include/resources/Templates/voucher/transfer-gold.php',
    $root . '/game/include/resources/Templates/voucher/history.php',
    $root . '/game/include/resources/Templates/voucher/layout.php',
    $root . '/game/include/resources/Templates/voucher/vouchers.php',
] as $retiredPath) {
    if (file_exists($retiredPath)) {
        failRetiredVoucherRouteContract('The retired full-page voucher renderer must not remain in the runtime.');
    }
}

if (str_contains($routeMap, "'voucher.php' =>") || str_contains($gameView, 'voucher.php')) {
    failRetiredVoucherRouteContract('The retired voucher route must not be dispatched or linked from the game shell.');
}

if (!str_contains($paymentWizard, "case 'vouchers':")
    || !str_contains($paymentWizard, 'renderVoucher()')
    || !str_contains($redeemVoucher, 'Voucher::useVoucher')) {
    failRetiredVoucherRouteContract('The native V5 Payment Wizard voucher redemption flow must remain available.');
}

echo "Retired voucher route contract passed.\n";
