<?php

declare(strict_types=1);

function failMarketEmptyCountContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$source = file_get_contents(dirname(__DIR__, 2) . '/game/include/Model/MarketModel.php');
if ($source === false) {
    failMarketEmptyCountContract('Market model must be readable.');
}

foreach ([
    'COALESCE(SUM(CEIL((wood+clay+iron+crop) / $merchant_cap)), 0)',
    'COALESCE(SUM(CEIL(giveValue / $merchant_cap)), 0)',
    'return (int)ceil((float)$db->fetchScalar(',
] as $needle) {
    if (!str_contains($source, $needle)) {
        failMarketEmptyCountContract('Empty merchant queries must return a numeric zero before calling ceil().');
    }
}

echo "Market empty-count contract passed.\n";
