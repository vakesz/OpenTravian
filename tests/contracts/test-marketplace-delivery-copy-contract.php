#!/usr/bin/env php
<?php

declare(strict_types=1);

function failMarketplaceDeliveryCopyContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$template = file_get_contents($root . '/game/include/resources/Templates/build/building/MarketplaceSendResources.php');
$translation = file_get_contents($root . '/game/include/resources/Translation/en-US.php');

if (!is_string($template) || !is_string($translation)) {
    failMarketplaceDeliveryCopyContract('Marketplace delivery copy sources must be readable.');
}

foreach ([
    "'deliveryEmptyLabels' => [",
    "'incoming' => T('MarketPlace', 'No incoming deliveries')",
    "'outgoing' => T('MarketPlace', 'No outgoing deliveries')",
    'state.deliveryEmptyLabels[direction] || \'\'',
    "\$Definition['MarketPlace']['No incoming deliveries'] = 'No incoming deliveries';",
    "\$Definition['MarketPlace']['No outgoing deliveries'] = 'No outgoing deliveries';",
] as $needle) {
    $source = str_starts_with($needle, '$Definition') ? $translation : $template;
    if (!str_contains($source, $needle)) {
        failMarketplaceDeliveryCopyContract('Marketplace delivery refresh must retain canonical copy: ' . $needle);
    }
}

if (str_contains($template, "direction === 'incoming' ? 'No incoming deliveries' : 'No outgoing deliveries'")) {
    failMarketplaceDeliveryCopyContract('Marketplace delivery refresh must not restore an inline English empty-state fallback.');
}

echo "Marketplace delivery copy contract passed.\n";
