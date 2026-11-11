<?php

declare(strict_types=1);

foreach (['menuEnabled.php', 'menuDisabled.php'] as $templateName) {
    $template = file_get_contents(__DIR__ . '/../../game/include/resources/Templates/village-statistics/' . $templateName);
    if ($template === false) {
        fwrite(STDERR, "Unable to read {$templateName}.\n");
        exit(1);
    }

    foreach ([
        'layout/tabStrip',
        'villageStatisticsUrl(',
        "'favor' => true",
    ] as $fragment) {
        if (!str_contains($template, $fragment)) {
            fwrite(STDERR, "{$templateName} is missing the native village-navigation fragment: {$fragment}\n");
            exit(1);
        }
    }

    foreach ([
        'class="container',
        "jQuery(window).trigger('tabClicked'",
    ] as $legacyFragment) {
        if (str_contains($template, $legacyFragment)) {
            fwrite(STDERR, "{$templateName} still contains obsolete village-navigation markup: {$legacyFragment}\n");
            exit(1);
        }
    }
}

$disabledMenu = file_get_contents(__DIR__ . '/../../game/include/resources/Templates/village-statistics/menuDisabled.php');
if ($disabledMenu === false
    || !str_contains($disabledMenu, "'type' => 'plusDialog'")
    || !str_contains($disabledMenu, 'villageStatistics')) {
    fwrite(STDERR, "The disabled village overview must retain its Plus upgrade dialog.\n");
    exit(1);
}


$strip = file_get_contents(__DIR__ . '/../../game/include/resources/Templates/layout/tabStrip.php');
if ($strip === false
    || !str_contains($strip, 'new Travian.Game.PlusDialog')
    || !str_contains($strip, 'Travian.Game.TabScrollNavigation();')) {
    fwrite(STDERR, "The shared tab strip must keep the scroll behaviour and the Plus dialog hook.\n");
    exit(1);
}
echo "Village overview native tab contract passed.\n";
