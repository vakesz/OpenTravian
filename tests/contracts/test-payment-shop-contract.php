<?php

declare(strict_types=1);

// Static guards for the gold shop: no flag-gated purchases, no secrets or external links
// in payment code, the webhook stays outside the session, and the routes are wired.

$root = dirname(__DIR__, 2);
$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};
$read = static fn (string $path): string => (string) file_get_contents($root . '/' . $path);

$config = $read('game/include/config.php');
(!str_contains($config, 'paymentWizardBuyGoldEnabled')) || $fail('Purchases must be enabled by admin configuration alone, not a hard-coded flag.');

$nginx = $read('docker/nginx/default.conf.template');
str_contains($nginx, '^/payments/(stripe|paypal)/webhook$') && str_contains($nginx, 'payment_webhook.php') || $fail('nginx must route the payment webhooks.');

$webhook = $read('game/deployment/public/payment_webhook.php');
str_contains($webhook, "define('TRAVIAN_WITHOUT_SESSION', true);") || $fail('The webhook must boot without a session.');
str_contains($read('game/include/bootstrap.php'), "!defined('TRAVIAN_WITHOUT_SESSION')") || $fail('The bootstrap must honour TRAVIAN_WITHOUT_SESSION.');

$main = $read('game/include/mainInclude.php');
str_contains($main, "'/payment/return'") && str_contains($main, "'payment_return' => 'PaymentReturnCtrl'") || $fail('The payment return route must be wired.');
str_contains($read('game/deployment/include/api_legacy_routes.php'), "'payment-checkout'") || $fail('The checkout ajax route must be wired.');

str_contains($read('game/deployment/include/api_legacy_routes.php'), "'payment-history'") || $fail('The order history ajax route must be wired.');
foreach (['Best seller', 'Best value', 'Choose your payment method', 'Select package', 'Payment method', 'Billing information', 'Confirmed', 'Continue', 'Order history'] as $key) {
    str_contains($read('game/include/resources/Translation/en-US.php'), "['PaymentWizard']['$key'] = '$key';") || $fail("Missing canonical PaymentWizard key: $key");
}
foreach (['package1', 'package2', 'package3', 'package4', 'package5', 'package6'] as $image) {
    foreach (['img_ltr', 'img_rtl'] as $tree) {
        is_file($root . "/assets/gpack/travian/mainPage/$tree/legacy/views/shop/$image.png") || $fail("Missing shop artwork $tree/$image.png");
    }
}
(!str_contains($read('game/include/Model/OptionModel.php'), 'paymentLog')) || $fail('OptionModel must not reference the removed paymentLog table.');

$checkout = $read('game/include/Controller/Ajax/paymentCheckout.php');
foreach (['SITTER_CAN_BUY_OR_SPEND_GOLD', 'incrementWithinLimit', 'PaymentShop::isOpen'] as $needle) {
    str_contains($checkout, $needle) || $fail("The checkout action must use $needle.");
}

foreach (array_merge(glob($root . '/game/include/Game/Payment/*.php') ?: [], [
    $root . '/game/include/Controller/Ajax/paymentCheckout.php',
    $root . '/game/include/Controller/PaymentReturnCtrl.php',
    $root . '/game/include/admin/include/Controllers/PaymentsCtrl.php',
    $root . '/game/include/resources/Templates/payment/buyGold.php',
]) as $file) {
    $source = (string) file_get_contents($file);
    (!preg_match('/\b(INSERT|UPDATE)\b[^;]*\b(secret|webhook_secret|client_secret|secret_key)\b/i', $source)) || $fail(basename($file) . ' must not write secrets to SQL.');
}

$schema = $read('database/account.sql');
(!str_contains($schema, 'paymentProviders') && !str_contains($schema, 'paymentLog')) || $fail('The retired payment tables must stay removed.');

echo "Payment shop contract passed.\n";
