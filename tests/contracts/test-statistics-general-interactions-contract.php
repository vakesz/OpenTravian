#!/usr/bin/env php
<?php

declare(strict_types=1);

function failStatisticsGeneralInteractions(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$template = file_get_contents($root . '/game/include/resources/Templates/statistics/general.php');
$clientPath = $root . '/game/deployment/public/statistics-general.js';
$client = is_file($clientPath) ? file_get_contents($clientPath) : false;

if ($template === false || $client === false) {
    failStatisticsGeneralInteractions('The General statistics interactive client must exist and be loaded by its maintained template.');
}

foreach ([
    'statistics-general.js',
    'data-statistics-resource-period',
    'data-statistics-resource-mode',
    'data-statistics-strength-mode',
    'data-statistics-cp-mode',
    'data-statistics-cp-period',
] as $binding) {
    if (!str_contains($template, $binding)) {
        failStatisticsGeneralInteractions("General statistics must render $binding.");
    }
}

foreach ([
    'resource-period',
    'resource-mode',
    'strength-mode',
    'strength-village',
    'cp-mode',
    'cp-period',
    'preventDefault()',
] as $binding) {
    if (!str_contains($client, $binding)) {
        failStatisticsGeneralInteractions("General statistics client must handle $binding.");
    }
}

if (str_contains($client, 'href = "#"') || str_contains($client, "href = '#")) {
    failStatisticsGeneralInteractions('General statistics controls must switch their local panel instead of navigating to #.');
}

echo "Statistics General interactions contract passed.\n";
