<?php

declare(strict_types=1);

$template = file_get_contents(__DIR__ . '/../../game/include/resources/Templates/options/menu.php');
$game = file_get_contents(__DIR__ . '/../../game/include/resources/Templates/options/Game.php');
$enUs = file_get_contents(__DIR__ . '/../../game/include/resources/Translation/en-US.php');
$layout = file_get_contents(__DIR__ . '/../../game/include/resources/Templates/layout/Layout.php');
$router = file_get_contents(__DIR__ . '/../../game/include/mainInclude.php');
$readme = file_get_contents(__DIR__ . '/../../README.md');

if ($template === false || $game === false || $enUs === false || $layout === false || $router === false || $readme === false) {
    fwrite(STDERR, "Unable to read the options navigation sources.\n");
    exit(1);
}

foreach ([
    'layout/tabStrip',
    "'href' => '/options/game'",
    "'href' => '/options/account'",
    "'href' => '/options/sitter'",
    "'href' => '/options/vacation'",
] as $fragment) {
    if (!str_contains($template, $fragment)) {
        fwrite(STDERR, "Options navigation is missing native-tab fragment: {$fragment}\n");
        exit(1);
    }
}

if (str_contains($template, "trigger('tabClicked'")) {
    fwrite(STDERR, "Options navigation must not keep legacy tab click proxies.\n");
    exit(1);
}

if (!str_contains($template, "T('Options', 'Avatar')")
    || str_contains($template, "T('Options', 'Account')")) {
    fwrite(STDERR, "Options secondary tab must use the Avatar player-facing label.\n");
    exit(1);
}

if (str_contains($template, '/options?s=') || str_contains($template, '<nav')) {
    fwrite(STDERR, "Options tabs must use /options/game|account|sitter|vacation.\n");
    exit(1);
}

if (str_contains($template, 'options.php')) {
    fwrite(STDERR, "Options tabs must use the clean /options alias, not options.php.\n");
    exit(1);
}

if (!str_contains($router, "'options' => 'OptionCtrl'")) {
    fwrite(STDERR, "The /options clean alias must dispatch to OptionCtrl.\n");
    exit(1);
}

if (!str_contains($enUs, "\$Definition['Global']['General']['save'] = 'Save';")) {
    fwrite(STDERR, "en-US Global.General.save must use reference casing Save.\n");
    exit(1);
}

foreach ([
    'id="v1"',
    'for="v1"',
    'id="v2"',
    'for="v2"',
    'id="v3"',
    'for="v3"',
    'name="c1"',
    'name="c2"',
    'name="c3"',
    'name="hideContextualHelp"',
    'name="option_night_mode"',
    'name="marketplaceOffersPerPage"',
    'name="language"',
    '"value": "<?= T("Global", "General.save") ?>"',
    'id="hideContextualHelp"',
    'id="night_mode_settings"',
    'class="tr_seasonal_mode"',
    'id="option_night_mode"',
    'class="textButtonV1 green "',
] as $fragment) {
    if (!str_contains($game, $fragment)) {
        fwrite(STDERR, "Options Game tab is missing fragment: {$fragment}\n");
        exit(1);
    }
}

if (str_contains($game, 'fast_upgrade') || str_contains($game, 'fastBuilder')) {
    fwrite(STDERR, "Options Game tab must not retain the non-reference fast-upgrade preference.\n");
    exit(1);
}
if (str_contains($readme, 'Fast upgrade is an intentional, admin-gated local deviation')) {
    fwrite(STDERR, "README must not document the retired fast-upgrade feature as an intentional local deviation.\n");
    exit(1);
}
if (substr_count($game, 'inputmode="numeric" maxlength="2"') < 3) {
    fwrite(STDERR, "Options page-count fields must match the numeric, two-digit inputs.\n");
    exit(1);
}

if (preg_match('/value="save"/', $game) === 1) {
    fwrite(STDERR, "Options Game Save button must not hardcode lowercase save.\n");
    exit(1);
}

if (str_contains($game, 'UnloadHelper.watchHtmlForm')) {
    fwrite(STDERR, "Options Game Save must stay enabled before any change,.\n");
    exit(1);
}

if (!str_contains($game, "onchange=\"jQuery('body').find('span.pleaseSaveYourChanges').removeClass('hidden')\"")) {
    fwrite(STDERR, "Seasonal mood must expose the inline please-save notice on change.\n");
    exit(1);
}

if (!str_contains($layout, 'href="/options"')
    || preg_match('/href="options\.php"/', $layout) === 1) {
    fwrite(STDERR, "Shell options links must use /options, not options.php.\n");
    exit(1);
}

echo "Options native tab contract passed.\n";
