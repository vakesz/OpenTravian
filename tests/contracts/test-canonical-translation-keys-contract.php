#!/usr/bin/env php
<?php

declare(strict_types=1);

function failCanonicalTranslationKeys(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);

$calls = [
    'game/include/resources/Templates/combat/simulatorResult.php' => [
        "T('Statistics', 'titleInHeader')",
    ],
    'game/include/resources/Templates/build/buildingWrapper.php' => [
        "T('Manual', 'duration')",
    ],
    'game/include/resources/Templates/build/buildingReadyWrapper.php' => [
        "T('Manual', 'duration')",
    ],
    'game/include/resources/Templates/build/building/EmbassyVillagesNearby.php' => [
        "T('Treasury', 'Village')",
        "T('Treasury', 'Owner')",
        "T('Treasury', 'Distance')",
    ],
    'game/include/resources/Templates/hero/heroFace.php' => [
        "T('HeroInventory', 'save')",
    ],
    'game/include/resources/Templates/options/Game.php' => [
        '"language settings"',
        '"No reports about transfers between your own villages"',
        '"No reports about transfers to foreign villages"',
        '"No reports about transfers from foreign villages"',
        '"Don\'t display images in reports"',
    ],
];

foreach ($calls as $path => $needles) {
    $source = file_get_contents($root . '/' . $path);
    if ($source === false) {
        failCanonicalTranslationKeys("Unable to read {$path}.");
    }

    foreach ($needles as $needle) {
        if (!str_contains($source, $needle)) {
            failCanonicalTranslationKeys("{$path} must use canonical translation key {$needle}.");
        }
    }
}

foreach ([
    "T('combat', 'statistics', 'Statistics')",
    "T('Buildings', 'duration', 'Duration')",
    "T('Embassy', 'Village', 'Village')",
    "T('Embassy', 'Owner', 'Owner')",
    "T('Embassy', 'Distance', 'Distance')",
    "T('HeroFace', 'saveChanges', 'Save changes')",
    '"Language settings",',
    '"No reports about transfers between your own villages.",',
    '"No reports about transfers to foreign villages.",',
    '"No reports about transfers from foreign villages.",',
    '"Don\'t display images in reports.",',
] as $fallback) {
    foreach (array_keys($calls) as $path) {
        $source = file_get_contents($root . '/' . $path);
        if ($source !== false && str_contains($source, $fallback)) {
            failCanonicalTranslationKeys("Fallback translation survived in {$path}: {$fallback}");
        }
    }
}

require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$keys = [
    ['Statistics', 'titleInHeader'],
    ['Manual', 'duration'],
    ['Treasury', 'Village'],
    ['Treasury', 'Owner'],
    ['Treasury', 'Distance'],
    ['HeroInventory', 'save'],
    ['Options', 'language settings'],
    ['Options', 'No reports about transfers between your own villages'],
    ['Options', 'No reports about transfers to foreign villages'],
    ['Options', 'No reports about transfers from foreign villages'],
    ['Options', "Don't display images in reports"],
];

// Every installed locale resolves through en-US, so each must expose the keys.
foreach (array_keys(\Core\Locale::manifest()) as $locale) {
    $catalog = \Core\Locale::catalog($locale);
    foreach ($keys as [$section, $key]) {
        if (!isset($catalog[$section]) || !array_key_exists($key, $catalog[$section])) {
            failCanonicalTranslationKeys("{$locale} is missing {$section}.{$key}.");
        }
    }
}

$english = \Core\Locale::catalog('en-US');
foreach ([
    ['Statistics', 'titleInHeader', 'Statistics'],
    ['Manual', 'duration', 'Duration'],
    ['Treasury', 'Village', 'Village'],
    ['Treasury', 'Owner', 'Owner'],
    ['Treasury', 'Distance', 'Distance'],
    ['HeroInventory', 'save', 'Save changes'],
    ['Options', 'language settings', 'Language settings'],
    ['Options', 'No reports about transfers between your own villages', 'No reports about transfers between your own villages.'],
    ['Options', 'No reports about transfers to foreign villages', 'No reports about transfers to foreign villages.'],
    ['Options', 'No reports about transfers from foreign villages', 'No reports about transfers from foreign villages.'],
    ['Options', "Don't display images in reports", "Don't display images in reports."],
] as [$section, $key, $expected]) {
    if (($english[$section][$key] ?? null) !== $expected) {
        failCanonicalTranslationKeys("en-US {$section}.{$key} must retain {$expected}.");
    }
}

echo "Canonical translation keys contract passed.\n";
