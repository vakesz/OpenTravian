#!/usr/bin/env php
<?php

declare(strict_types=1);

use Core\Locale;

function failLocaleRuntimeCoverage(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';
$heroItemsSource = file_get_contents($root . '/game/include/Game/Hero/HeroItems.php');
if ($heroItemsSource === false) {
    failLocaleRuntimeCoverage('Hero item source must be readable.');
}

// A legacy literal key can itself include dots. It must win before dot-path
// traversal, while normal nested keys remain reachable.
$locale = new Locale();
$locale->Locale = [
    'Legacy' => [
        'A sentence. With a full stop.' => 'exact legacy sentence',
        'nested' => ['key' => 'nested value'],
    ],
];
$instance = new ReflectionProperty(Locale::class, '_self');
$instance->setValue(null, $locale);

if ($locale->Translate('Legacy', 'A sentence. With a full stop.') !== 'exact legacy sentence') {
    failLocaleRuntimeCoverage('Literal catalog keys containing dots must resolve before nested paths.');
}
if ($locale->Translate('Legacy', 'nested.key') !== 'nested value') {
    failLocaleRuntimeCoverage('Nested catalog paths must remain reachable after literal-key lookup.');
}

$loadCatalog = static function (string $locale) use ($root): array {
    $GLOBALS['Definition'] = [];
    $catalog = require $root . '/game/include/resources/Translation/' . $locale . '.php';
    if (!is_array($catalog)) {
        failLocaleRuntimeCoverage("{$locale} catalog did not return an array.");
    }

    return $catalog;
};

$expected = [
    'ar-AE' => [
        'Alliance' => 'لم يتم إرسال أي شعارات حتى الآن. انتقل إلى %s لإرسال شعارك.',
        'quickLinks' => 'المبنى غير متوفر في القرية النشطة',
        'resourceItemInInventory' => 'انقر على هذا العنصر لإضافة الموارد إلى مخزن قريتك النشطة حاليًا.',
    ],
    'el-GR' => [
        'Alliance' => 'Δεν έχουν υποβληθεί ακόμα λάβαρα. Μεταβείτε στην ενότητα %s για να υποβάλετε το δικό σας.',
        'quickLinks' => 'Κτίριο μη διαθέσιμο στο ενεργό χωριό',
        'resourceItemInInventory' => 'Κάντε κλικ σε αυτό το αντικείμενο για να προσθέσετε ύλες στην αποθήκη πρώτων υλών στο τρέχον ενεργό χωριό σας.',
    ],
];
foreach ($expected as $localeName => $values) {
    $catalog = $loadCatalog($localeName);
    $galleryKey = 'No banners have been submitted yet. Go to %s to submit yours.';
    if (($catalog['Alliance'][$galleryKey] ?? null) !== $values['Alliance']) {
        failLocaleRuntimeCoverage("{$localeName} must retain the reference gallery-empty copy.");
    }
    if (($catalog['quickLinks']['notAvailable'] ?? null) !== $values['quickLinks']) {
        failLocaleRuntimeCoverage("{$localeName} must retain the reference quick-link unavailable copy.");
    }
    if (($catalog['HeroInventory']['resourceItemInInventory'] ?? null) !== $values['resourceItemInInventory']) {
        failLocaleRuntimeCoverage("{$localeName} must retain the reference carried-resource item copy.");
    }
}

if (!str_contains($heroItemsSource, "T('HeroInventory', 'resourceItemInInventory')")
    || !str_contains($heroItemsSource, "['en-US', 'ar-AE', 'el-GR']")
    || !str_contains($heroItemsSource, "'attributes'] = \$title === '' ? [] : [\$title]")) {
    failLocaleRuntimeCoverage('Carried-resource tiles must use the source-backed tooltip and suppress unsupported fa copy.');
}

if (!str_contains($heroItemsSource, '$btype === 4 && $type >= 149 && $type <= 163')
    || !str_contains($heroItemsSource, '$result[\'name\'] = in_array(get_locale(), [\'en-US\', \'ar-AE\', \'el-GR\'], true)')
    || !str_contains($heroItemsSource, '$result[\'title\'] = \'\';')
    || !str_contains($heroItemsSource, '$result[\'attributes\'] = [];')) {
    failLocaleRuntimeCoverage('Source-only weapon variants must not synthesize missing attribute copy.');
}

$persian = $loadCatalog('fa-IR');
if (isset($persian['Reports']['bulkActions'])
    || isset($persian['Options']['confirmation']['typeExactText'])
    || isset($persian['MarketPlace']['No incoming deliveries'])
    || isset($persian['MarketPlace']['No outgoing deliveries'])
    || isset($persian['HeroInventory']['resourceItemInInventory'])
    || isset($persian['HeroItems'][4][149]['name'])) {
    failLocaleRuntimeCoverage('fa-IR must not receive unsupported English fallback additions.');
}

echo "Locale runtime coverage contract passed.\n";
