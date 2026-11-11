#!/usr/bin/env php
<?php

declare(strict_types=1);

use Core\Locale;

/**
 * Contract: the page shell loads its client strings from the generated
 * per-locale bundle at /js/<locale>/strings.js, not from inline template
 * blocks, and the bundle carries reference text where the reference has it.
 */
function failClientStringsBundle(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$head = (string) file_get_contents($root . '/game/include/resources/Templates/layout/head.php');
if (!str_contains($head, 'get_client_strings_link()')
    || str_contains($head, "'allgemein.anleitung'")
    || str_contains($head, '$videoFeatureStrings')) {
    failClientStringsBundle('head.php must load /js/<locale>/strings.js instead of inline Travian.Translation.add blocks.');
}

foreach (array_keys(Locale::manifest()) as $locale) {
    $file = $root . "/game/deployment/public/js/{$locale}/strings.js";
    $source = is_file($file) ? (string) file_get_contents($file) : '';
    if (preg_match('/^Travian\.Translation\.add\((\{.*\})\);\n$/s', preg_replace('~^//[^\n]*\n~', '', $source) ?? '', $match) !== 1) {
        failClientStringsBundle("{$locale} strings.js is missing or malformed.");
    }
    $strings = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
    foreach (['allgemein.ok', 'allgemein.close', 'cropfinder.keine_ergebnisse'] as $key) {
        if (!isset($strings[$key]) || $strings[$key] === '') {
            failClientStringsBundle("{$locale} strings.js is missing {$key}.");
        }
    }
    if (preg_match('~https?://|travian\.com~i', $source) === 1) {
        failClientStringsBundle("{$locale} strings.js must not carry external links.");
    }
    $generalFile = $root . "/assets/i18n/reference/{$locale}/general.json";
    if (is_file($generalFile)) {
        $general = json_decode((string) file_get_contents($generalFile), true, 512, JSON_THROW_ON_ERROR);
        foreach (['anleitung', 'cancel', 'ok', 'close', 'close_with_capital_c'] as $key) {
            if (($strings['allgemein.' . $key] ?? null) !== ($general[$key] ?? null)) {
                failClientStringsBundle("{$locale} allgemein.{$key} must equal the reference allgemein bundle text.");
            }
        }
    }
}

echo "Client strings bundle contract passed.\n";
