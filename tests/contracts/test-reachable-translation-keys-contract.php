#!/usr/bin/env php
<?php

declare(strict_types=1);

function failReachableTranslationKeysContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

/** @return mixed */
function reachableTranslationValue(array $catalog, string $section, string $key)
{
    $value = $catalog[$section] ?? null;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$callers = [
    'game/include/Controller/HeroAdventureCtrl.php' => ["T('map', 'fields')"],
    'game/include/resources/Templates/hero/HeroAdventure.php' => ["T('map', 'Distance')"],
    'game/include/resources/Templates/reports/All.php' => [
        "T('Reports', 'bulkActions.dontWarnAgain')",
        "T('Reports', 'bulkActions.markAllIntro')",
        "T('Reports', 'bulkActions.markAllCount')",
        "T('Reports', 'bulkActions.deleteAllIntro')",
        "T('Reports', 'bulkActions.deleteSelectedIntro')",
        "T('Reports', 'bulkActions.deleteCount')",
    ],
    'game/include/resources/Templates/options/Account.php' => ["T('Options', 'confirmation.typeExactText')"],
];

foreach ($callers as $path => $needles) {
    $source = file_get_contents($root . '/' . $path);
    if ($source === false) {
        failReachableTranslationKeysContract("Unable to read {$path}.");
    }
    foreach ($needles as $needle) {
        if (!str_contains($source, $needle)) {
            failReachableTranslationKeysContract("{$path} must use reachable translation key {$needle}.");
        }
    }
}

$expectedEnglish = [
    ['map', 'fields', 'fields'],
    ['map', 'Distance', 'Distance'],
    ['Reports', 'bulkActions.dontWarnAgain', "Don't warn me again."],
    ['Reports', 'bulkActions.markAllIntro', 'This action will mark all reports as read.'],
    ['Reports', 'bulkActions.markAllCount', 'Number of reports marked as read:'],
    ['Reports', 'bulkActions.deleteAllIntro', 'This action will delete all reports.'],
    ['Reports', 'bulkActions.deleteSelectedIntro', 'This action will delete the selected reports.'],
    ['Reports', 'bulkActions.deleteCount', 'Number of reports deleted:'],
    ['Options', 'confirmation.typeExactText', 'Please type the exact text displayed above to confirm.'],
    ['MarketPlace', 'No incoming deliveries', 'No incoming deliveries'],
    ['MarketPlace', 'No outgoing deliveries', 'No outgoing deliveries'],
];

// Publishes these modern controls for en/ar/el. It does not publish an
// fa-IR catalog, so requiring invented Persian fallback copy would violate the
// reference-copy policy.
foreach (['en-US', 'ar-AE', 'el-GR'] as $locale) {
    $GLOBALS['Definition'] = [];
    $catalog = require $root . '/game/include/resources/Translation/' . $locale . '.php';
    if (!is_array($catalog)) {
        failReachableTranslationKeysContract("{$locale} catalog did not return an array.");
    }

    foreach ($expectedEnglish as [$section, $key, $english]) {
        $value = reachableTranslationValue($catalog, $section, $key);
        if (!is_string($value)) {
            failReachableTranslationKeysContract("{$locale} is missing reachable {$section}.{$key}.");
        }
        if ($locale === 'en-US' && $value !== $english) {
            failReachableTranslationKeysContract("en-US {$section}.{$key} must retain exact player copy.");
        }
    }
}

echo "Reachable translation keys contract passed.\n";
