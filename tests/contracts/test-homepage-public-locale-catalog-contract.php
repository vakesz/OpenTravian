<?php

declare(strict_types=1);

function failHomepagePublicLocaleCatalog(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$path = $root . '/game/include/resources/Translation/HomepagePublicCatalog.php';
$catalogue = require $path;
if (!is_array($catalogue)) {
    failHomepagePublicLocaleCatalog('The public homepage locale snapshot must return an array.');
}

$source = $catalogue['source'] ?? null;
$options = $catalogue['options'] ?? null;
$copies = $catalogue['catalogues'] ?? null;
if (!is_array($source) || !is_array($options) || !is_array($copies)) {
    failHomepagePublicLocaleCatalog('The public homepage locale snapshot has an invalid top-level shape.');
}

if (($source['retrievedAt'] ?? null) !== '2026-11-06'
    || ($source['startpageBundle'] ?? null) !== 'startpage.434.js'
    || ($source['localisationTemplate'] ?? null) !== 'localisation/{language}.json') {
    failHomepagePublicLocaleCatalog('The public homepage locale snapshot is missing its source provenance.');
}

$keys = $source['keys'] ?? null;
if (!is_array($keys) || count($keys) !== 89 || count(array_unique($keys)) !== 89) {
    failHomepagePublicLocaleCatalog('The public homepage locale snapshot must contain its 89 unique source keys.');
}
foreach ([
    'homeTitle', 'homeDescription', 'newsTitle', 'calendarTitle', 'Date', 'Speed', 'Read full story',
    'Create Legends Lobby Account', 'Register with email address', 'or continue with',
    'I want to receive the newsletter', 'Already have an account?', 'Login now', 'Email Address',
    'Please enter a valid email address. E.g. mymail@provider.com', 'Create Account', 'tribe1',
    'Login to Lobby Account', 'Forgot your password?',
] as $required) {
    if (!in_array($required, $keys, true)) {
        failHomepagePublicLocaleCatalog("The public homepage locale snapshot must include {$required}.");
    }
}

if (count($options) !== 48 || count($copies) !== 37) {
    failHomepagePublicLocaleCatalog('The public homepage locale snapshot must contain the 48 source options and 37 source catalogues.');
}

$ids = [];
$routes = [];
foreach ($options as $option) {
    if (!is_array($option)) {
        failHomepagePublicLocaleCatalog('Every public homepage language option must be structured data.');
    }
    foreach (['id', 'region', 'route', 'flag', 'locale', 'language', 'lang', 'langNative', 'langEnglish', 'countryNative', 'countryEnglish', 'direction', 'hrefLang'] as $field) {
        if (!isset($option[$field]) || !is_string($option[$field]) || $option[$field] === '') {
            failHomepagePublicLocaleCatalog("A public homepage language option is missing {$field}.");
        }
    }
    if (!in_array($option['direction'], ['ltr', 'rtl'], true)) {
        failHomepagePublicLocaleCatalog('A public homepage language option has an invalid direction.');
    }
    if (isset($ids[$option['id']]) || isset($routes[$option['route']])) {
        failHomepagePublicLocaleCatalog('Public homepage language option IDs and routes must stay unique.');
    }
    if (!isset($copies[$option['language']])) {
        failHomepagePublicLocaleCatalog('Every public homepage language option must resolve to a self-hosted source catalogue.');
    }
    $ids[$option['id']] = true;
    $routes[$option['route']] = true;
}

$optionsById = [];
foreach ($options as $option) {
    $optionsById[$option['id']] = $option;
}
if (($optionsById['de-DE']['flag'] ?? null) !== 'de'
    || ($optionsById['de-DE']['langEnglish'] ?? null) !== 'German'
    || ($optionsById['de-DE']['countryNative'] ?? null) !== 'Deutschland'
    || ($optionsById['de-DE']['countryEnglish'] ?? null) !== 'Germany') {
    failHomepagePublicLocaleCatalog('The public homepage locale snapshot must preserve source fields used by flag rendering and language search.');
}

foreach ($copies as $language => $copy) {
    if (!is_string($language) || !is_array($copy)) {
        failHomepagePublicLocaleCatalog('Every public homepage source catalogue must be a named map.');
    }
    foreach ($keys as $key) {
        if (!is_string($key) || !isset($copy[$key]) || !is_string($copy[$key])) {
            failHomepagePublicLocaleCatalog("{$language} is missing a visible public homepage source value.");
        }
    }
}

foreach ($copies as $language => $copy) {
    if (array_keys($copy) !== $keys) {
        failHomepagePublicLocaleCatalog("{$language} must hold exactly the source keys, in order.");
    }
    foreach ($copy as $value) {
        if (trim($value) === '') {
            failHomepagePublicLocaleCatalog("{$language} must not hold empty public homepage values.");
        }
    }
}

$english = $copies['en-US'] ?? [];
if (($english['Play Now'] ?? null) !== 'Play now'
    || ($english['Accept'] ?? null) !== 'Accept'
    || ($english['Support'] ?? null) !== 'Support'
    || ($english['Login now'] ?? null) !== 'Log in now'
    || ($english['Create Legends Lobby Account'] ?? null) !== 'Create Legends lobby account'
    || ($english['I want to receive the newsletter'] ?? null) !== 'I want to receive the newsletter.'
    || ($copies['de-DE']['Login now'] ?? null) !== 'Jetzt einloggen'
    || ($english['Fierce raiders, ruthless warriors, and unrelenting aggression define the Teutons. Lead your savage forces to pillage enemies and strike fear into their hearts with lightning-fast attacks.'] ?? null)
        !== 'Fierce raiders, ruthless warriors, and unrelenting aggression characterize the Teutons. Lead your savage forces to pillage enemies and strike fear into their hearts with lightning-fast attacks.') {
    failHomepagePublicLocaleCatalog('The public homepage locale snapshot must retain exact current English source values.');
}

echo "Homepage public locale catalog contract passed.\n";
