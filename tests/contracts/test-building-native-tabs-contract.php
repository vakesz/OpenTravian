<?php

declare(strict_types=1);

$templates = [
    'MarketplaceNavigation.php',
    'Treasury.php',
    'ResidencePalaceNavigation.php',
];

foreach ($templates as $templateName) {
    $template = file_get_contents(__DIR__ . '/../../game/include/resources/Templates/build/building/' . $templateName);
    if ($template === false) {
        fwrite(STDERR, "Unable to read {$templateName}.\n");
        exit(1);
    }

    foreach ([
        "PHPBatchView::render('layout/tabStrip'",
        "'favor' => true",
    ] as $fragment) {
        if (!str_contains($template, $fragment)) {
            fwrite(STDERR, "{$templateName} is missing the native tab fragment: {$fragment}\n");
            exit(1);
        }
    }

    foreach ([
        'class="container',
        "jQuery(window).trigger('tabClicked'",
    ] as $legacyFragment) {
        if (str_contains($template, $legacyFragment)) {
            fwrite(STDERR, "{$templateName} still contains obsolete tab markup: {$legacyFragment}\n");
            exit(1);
        }
    }
}

echo "Building native tab contract passed.\n";
