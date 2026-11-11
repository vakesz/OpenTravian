<?php

declare(strict_types=1);

/**
 * Contract: GoldHelper pros/payment buttons use textButtonV2 class order
 * (buttonFramed before gold/green color tokens).
 */

function failGoldHelperButtonFramedOrderContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$goldHelper = file_get_contents($root . '/game/include/Game/GoldHelper.php');
if ($goldHelper === false) {
    failGoldHelperButtonFramedOrderContract('Unable to read GoldHelper.php');
}

if (str_contains($goldHelper, 'textButtonV2 gold')
    || str_contains($goldHelper, 'textButtonV2 green')) {
    failGoldHelperButtonFramedOrderContract(
        'GoldHelper still puts gold/green before buttonFramed (textButtonV2 gold|green anti-pattern).'
    );
}

echo "GoldHelper buttonFramed order contract passed.\n";
