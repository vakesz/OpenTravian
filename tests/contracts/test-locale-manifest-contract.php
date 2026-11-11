#!/usr/bin/env php
<?php

declare(strict_types=1);

use Core\Locale;

/**
 * Contract: installed locales come from the generated manifest, every locale
 * resolves through en-US, and generated overlays only carry reference-sourced
 * values for keys that exist in en-US.
 */
function failLocaleManifest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$manifest = Locale::manifest();
if (!isset($manifest['en-US'], $manifest['de-DE'], $manifest['ar-AE'], $manifest['he-IL'], $manifest['fa-IR'])) {
    failLocaleManifest('The manifest must list en-US, de-DE, ar-AE, he-IL and fa-IR.');
}
foreach (['ar-AE', 'he-IL', 'fa-IR'] as $rtl) {
    if (($manifest[$rtl]['direction'] ?? '') !== 'RTL') {
        failLocaleManifest("{$rtl} must be RTL so it uses the img_rtl/css_rtl Gpack path.");
    }
}
if (($manifest['de-DE']['direction'] ?? '') !== 'LTR') {
    failLocaleManifest('de-DE must be LTR.');
}
$names = [];
foreach ($manifest as $locale => $entry) {
    if (preg_match('/^[a-z]{2}-[A-Z]{2}$/', $locale) !== 1 || ($entry['name'] ?? '') === '' || ($entry['country'] ?? '') === '') {
        failLocaleManifest("Manifest entry {$locale} is incomplete.");
    }
    if (isset($names[$entry['name']])) {
        failLocaleManifest("Language label {$entry['name']} is shared by {$names[$entry['name']]} and {$locale}.");
    }
    $names[$entry['name']] = $locale;
    $hasCatalog = $locale === 'en-US'
        || is_file($root . "/game/include/resources/Translation/{$locale}.php")
        || is_file($root . "/game/include/resources/Translation/generated/{$locale}.php");
    if (!$hasCatalog) {
        failLocaleManifest("{$locale} has neither a hand catalogue nor a generated overlay.");
    }
    if ($locale !== 'fa-IR' && !is_file($root . "/assets/i18n/reference/{$locale}/strings.js")) {
        failLocaleManifest("{$locale} has no reference bundle under assets/i18n/reference.");
    }
}
$config = file_get_contents($root . '/game/include/config.php');
if ($config === false || !str_contains($config, "resources/Translation/locales.php")
   ) {
    failLocaleManifest('config.php must build availableLanguages from Translation/locales.php.');
}

// Fallback chain: every locale exposes every en-US section and key.
$english = Locale::catalog('en-US');
$flatten = static function (array $tree, string $prefix = '') use (&$flatten): array {
    $leaves = [];
    foreach ($tree as $key => $value) {
        if (is_array($value) && !array_is_list($value)) {
            $leaves += $flatten($value, $prefix . $key . "\x1f");
        } else {
            $leaves[$prefix . $key] = $value;
        }
    }

    return $leaves;
};
$englishLeaves = $flatten($english);
foreach (['de-DE', 'ar-AE', 'he-IL', 'zh-TW'] as $locale) {
    $leaves = $flatten(Locale::catalog($locale));
    $missing = array_diff_key($englishLeaves, $leaves);
    if ($missing !== []) {
        failLocaleManifest($locale . ' loses en-US keys, e.g. ' . str_replace("\x1f", '.', (string) array_key_first($missing)));
    }
}
$german = Locale::catalog('de-DE');
$overlay = require $root . '/game/include/resources/Translation/generated/de-DE.php';
foreach ($flatten($overlay) as $path => $value) {
    if (!array_key_exists($path, $englishLeaves)) {
        failLocaleManifest('Generated overlay carries a key that is not in en-US: ' . str_replace("\x1f", '.', $path));
    }
}
$sampleSection = array_key_first($overlay);
$sampleKey = array_key_first($overlay[$sampleSection]);
if (is_array($overlay[$sampleSection][$sampleKey])) {
    $sampleLeaf = $flatten([$sampleSection => $overlay[$sampleSection]]);
    $german = $flatten($german);
    $path = array_key_first($sampleLeaf);
    if (($german[$path] ?? null) !== $sampleLeaf[$path]) {
        failLocaleManifest('The generated overlay must win over en-US.');
    }
} elseif (($german[$sampleSection][$sampleKey] ?? null) !== $overlay[$sampleSection][$sampleKey]) {
    failLocaleManifest('The generated overlay must win over en-US.');
}

// Every generated line is traceable to a reference source and no outbound links.
foreach (glob($root . '/game/include/resources/Translation/generated/*.php') ?: [] as $file) {
    $source = (string) file_get_contents($file);
    foreach (explode("\n", $source) as $number => $line) {
        if (str_contains($line, '=>') && !str_ends_with(rtrim($line), '[') && !str_ends_with(rtrim($line), '],')
            && preg_match('~// (bundle|page):\S~', $line) !== 1) {
            failLocaleManifest(basename($file) . ':' . ($number + 1) . ' has no reference provenance comment.');
        }
    }
    if (preg_match('~https?://|travian\.com~i', preg_replace('~// (bundle|page):.*$~m', '', $source) ?? '') === 1) {
        failLocaleManifest(basename($file) . ' must not carry external links.');
    }
}

echo "Locale manifest contract passed.\n";
