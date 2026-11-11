<?php

declare(strict_types=1);

function failStockBarCompactValuesContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$helpers = file_get_contents($root . '/game/include/functions.general.php');
$view = file_get_contents($root . '/game/include/resources/View/GameView.php');
$layout = file_get_contents($root . '/game/include/resources/Templates/layout/Layout.php');
$client = file_get_contents($root . '/game/deployment/public/game-client.js');

if (in_array(false, [$helpers, $view, $layout, $client], true)) {
    failStockBarCompactValuesContract('Could not read the stock-bar compact-value sources.');
}

foreach ([
    'function number_format_compact($number, $threshold = 1e6)',
    'return number_to_string($number, $threshold);',
] as $helperContract) {
    if (!str_contains($helpers, $helperContract)) {
        failStockBarCompactValuesContract('Large fixed-width HUD values must use the shared compact formatter.');
    }
}

foreach ([
    "'maxstoreString' => number_format_compact",
    "'maxcropString' => number_format_compact",
    "'freeCropString' => number_format_compact",
    "['storageString'][\$k] = number_format_compact(\$v)",
] as $viewContract) {
    if (!str_contains($view, $viewContract)) {
        failStockBarCompactValuesContract('The stock bar must format stored resources, capacity, and free crop consistently.');
    }
}

if (!str_contains($layout, 'Travian.formatStockBarValue = function (value)')
    || !str_contains($client, 'Travian.formatStockBarValue(i)')) {
    failStockBarCompactValuesContract('Live stock-bar counters must retain compact values after their first client-side update.');
}

echo "Stock-bar compact-value contract passed.\n";
