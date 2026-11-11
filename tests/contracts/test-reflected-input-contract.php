<?php

declare(strict_types=1);

function failReflectedInputContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$sources = [
    'messages controller' => $root . '/game/include/Controller/MessagesCtrl.php',
    'messages template' => $root . '/game/include/resources/Templates/messages/write.php',
    'voucher redemption endpoint' => $root . '/game/include/Controller/Ajax/paynet/redeemVoucher.php',
    'banned controller' => $root . '/game/include/Controller/BannedCtrl.php',
];

foreach ($sources as $name => $path) {
    $source = file_get_contents($path);
    if ($source === false) {
        failReflectedInputContract("Could not read the $name source.");
    }
    if (str_contains($source, 'FILTER_SANITIZE_STRING')) {
        failReflectedInputContract("The $name source must not use the deprecated string filter.");
    }
    $sources[$name] = $source;
}

if (!str_contains($sources['messages controller'], "strip_tags(is_string(\$_REQUEST['an'] ?? null) ? \$_REQUEST['an'] : '')")
    || !str_contains($sources['messages controller'], "strip_tags(is_string(\$_REQUEST['message'] ?? null) ? \$_REQUEST['message'] : '')")
    || !str_contains($sources['messages template'], "htmlspecialchars((string)\$vars['receiver'], ENT_QUOTES, 'UTF-8')")
    || !str_contains($sources['messages template'], "htmlspecialchars((string)\$vars['message'], ENT_QUOTES, 'UTF-8')")) {
    failReflectedInputContract('Message composition must accept only scalar text and escape reflected form values.');
}

if (!str_contains($sources['voucher redemption endpoint'], "is_string(\$_POST['code']) ? trim(\$_POST['code']) : ''")) {
    failReflectedInputContract('The modern voucher redemption endpoint must normalize the submitted code.');
}

if (!str_contains($sources['banned controller'], "is_string(\$requestUri) ? \$requestUri : '/dorf1.php?ok=1'")
    || !str_contains($sources['banned controller'], "htmlspecialchars(\$requestUri, ENT_QUOTES, 'UTF-8')")) {
    failReflectedInputContract('Banned-page continuation links must escape a scalar request URI.');
}

echo "Reflected input contract passed.\n";
