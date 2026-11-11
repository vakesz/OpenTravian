<?php

declare(strict_types=1);

$view = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/View/GameView.php');
$hud = file_get_contents(dirname(__DIR__, 2) . '/game/include/Game/Hero/HeroHud.php');
$template = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/layout/topBarBoxHero.php');

if ($view === false || $template === false || $hud === false) {
    fwrite(STDERR, "Could not read the hero HUD sources.\n");
    exit(1);
}

foreach ([
    "SessionHero::STATUS_HOME => 'heroHome'",
    "SessionHero::STATUS_DEAD => 'heroDead'",
    "SessionHero::STATUS_REVIVING => 'heroReviving'",
    "SessionHero::STATUS_TRAPPED => 'heroTrapped'",
    "SessionHero::STATUS_ON_REINFORCE, SessionHero::STATUS_IN_ENFORCEMENT => 'heroReinforcing'",
    "default => 'heroRunning'",
] as $mapping) {
    if (!str_contains($hud, $mapping)) {
        fwrite(STDERR, "Hero HUD status mapping is incomplete.\n");
        exit(1);
    }
}

if (!str_contains($view, 'HeroHud::statusClass($heroStatus)')) {
    fwrite(STDERR, "GameView must derive the status icon class from HeroHud::statusClass.\n");
    exit(1);
}

if (!str_contains($template, "<i class=\"<?=\$vars['heroStatusClass']; ?>\" aria-hidden=\"true\"></i>")) {
    fwrite(STDERR, "Hero HUD must render the native status icon element.\n");
    exit(1);
}

foreach ([
    'private const HEALTH_START = 98;',
    'private const HEALTH_SPAN = 102;',
    'private const EXPERIENCE_START = 114;',
    'private const EXPERIENCE_SPAN = 88;',
] as $arcContract) {
    if (!str_contains($hud, $arcContract)) {
        fwrite(STDERR, "Hero HUD arcs must keep the native short side geometry.\n");
        exit(1);
    }
}

foreach ([
    'HeroHud::healthPaths(',
    'HeroHud::experiencePaths(',
    '<clipPath id="healthMask" clipPathUnits="userSpaceOnUse">',
    '<clipPath id="experienceMask" clipPathUnits="userSpaceOnUse">',
    '<div id="topBarHero" class="heroV2">',
] as $nativeProgressContract) {
    if (!str_contains($template, $nativeProgressContract)) {
        fwrite(STDERR, "Hero HUD progress rings must retain the native short side arcs and grow along their respective sides.\n");
        exit(1);
    }
}

foreach ([
    'hud/top-bar/hero/frame/health.png',
    'hud/top-bar/hero/frame/experience.png',
] as $nativeFrameAsset) {
    if (!str_contains($template, $nativeFrameAsset)) {
        fwrite(STDERR, "Hero HUD must use the current Gpack frame artwork.\n");
        exit(1);
    }
}

echo "Hero HUD status contract passed.\n";
