<?php

declare(strict_types=1);

/**
 * Contract: Marketplace player query destinations must remain root-absolute.
 *
 * The shared base element requires explicit root paths for player query links.
 */

function failMarketplaceRootAbsoluteUrlsContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/game/include/Controller/Build/MarketPlaceCtrl.php');
$buy = file_get_contents($root . '/game/include/resources/Templates/build/building/MarketplaceBuy.php');

if ($controller === false || $buy === false) {
    failMarketplaceRootAbsoluteUrlsContract('Marketplace Buy sources are unreadable.');
}

foreach ([
    "static fn (int \$target): string => '/build.php?' . http_build_query(",
    '$href = "/build.php?id="',
    'href="/build.php?gid=17',
] as $needle) {
    if (!str_contains($controller, $needle)) {
        failMarketplaceRootAbsoluteUrlsContract("Marketplace controller query destination must be root-absolute: {$needle}");
    }
}

if (!str_contains($buy, '$plainBaseUrl = \'/build.php?id=\'')) {
    failMarketplaceRootAbsoluteUrlsContract('Marketplace Buy filters must use a root-absolute build route.');
}

foreach ([
    "'build.php?' . http_build_query(",
    '$plainBaseUrl = \'build.php?id=\'',
] as $legacy) {
    if (str_contains($controller . $buy, $legacy)) {
        failMarketplaceRootAbsoluteUrlsContract("Marketplace still emits a relative player query destination: {$legacy}");
    }
}

echo "Marketplace root-absolute URL contract passed.\n";
