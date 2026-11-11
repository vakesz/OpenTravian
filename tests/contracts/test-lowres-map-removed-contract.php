<?php

declare(strict_types=1);

// Contract: the reference has no low-resolution map (the same page is served with the
// lowRes cookie set), so the local low-resolution path stays deleted.

$root = dirname(__DIR__, 2);

foreach ([
    'game/include/Controller/MapLowResCtrl.php',
    'game/include/Game/Map/MapLowRes.php',
    'game/include/Controller/Ajax/map/lowRes.php',
    'game/include/resources/Templates/map/lowRes.php',
    'game/deployment/public/game-client-lowres.js',
    'assets/gpack/travian/mainPage/css_ltr/lowres.css',
    'assets/gpack/travian/mainPage/css_rtl/lowres.css',
] as $relative) {
    if (file_exists($root . '/' . $relative)) {
        fwrite(STDERR, "$relative belongs to the removed low-resolution map.\n");
        exit(1);
    }
}

$needles = ['is_lowres', "'lowRes'", '"lowRes"', 'MapLowRes', 'game-client-lowres', 'map/low-res', 'lowResMode', '.map2.lowRes', "['Login']['lowRes"];
foreach ([
    'game/include/mainInclude.php',
    'game/include/functions.general.php',
    'game/include/Controller/LoginCtrl.php',
    'game/include/Controller/ReportsCtrl.php',
    'game/include/resources/Templates/layout/head.php',
    'game/include/resources/Templates/options/Game.php',
    'game/deployment/include/api_legacy_routes.php',
    'services/api/include/Api/Ctrl/AuthCtrl.php',
    'assets/gpack/travian/mainPage/lang/overrides.css',
    'game/include/resources/Translation/en-US.php',
] as $relative) {
    $source = (string)file_get_contents($root . '/' . $relative);
    foreach ($needles as $needle) {
        if (str_contains($source, $needle)) {
            fwrite(STDERR, "$relative still mentions the low-resolution map ($needle).\n");
            exit(1);
        }
    }
}

echo "Low-resolution map removal contract passed.\n";
