<?php
declare(strict_types=1);

/**
 * Mobile shell contract: the responsive chrome is the Gpack's
 * .mobileOptimized / .mobileForced rules plus the markup and JS below.
 */
function failMobileShellContract(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

$root = dirname(__DIR__, 2);
$read = static function (string $relative) use ($root): string {
    $contents = @file_get_contents($root . '/' . $relative);
    if ($contents === false) {
        failMobileShellContract("Cannot read {$relative}.");
    }
    return $contents;
};

$layout = $read('game/include/resources/Templates/layout/Layout.php');
foreach ([
    '<input type="checkbox" id="mobileMenuState"/>' => 'mobile menu state checkbox',
    'class="mobileMenuButton" for="mobileMenuState"' => 'mobile menu button label',
    'class="mobileShopButton"' => 'mobile shop button',
    '<nav id="mobileMenu">' => 'mobile menu',
    '<div id="topBarHeroWrapper">' => 'hero wrapper',
    'id="sidebarBeforeContent"' => 'left sidebar',
    'id="sidebarAfterContent"' => 'right sidebar',
    'Travian.Game.Layout.MobileOptimizations.init();' => 'mobile initializer call',
    '"mobileOptimized"' => 'mobileOptimized body class',
] as $needle => $label) {
    if (!str_contains($layout, $needle)) {
        failMobileShellContract("Layout.php must render the {$label}.");
    }
}
if (strpos($layout, 'MobileOptimizations.init();') < strpos($layout, 'id="sidebarAfterContent"')) {
    failMobileShellContract('The mobile initializer must run after the sidebars are rendered.');
}
if (!preg_match(
    '/\\$disableGuestSwipeNavigation\\s*=\\s*!empty\\(\\$vars\\["showGuestNavBar"\\]\\)\\s*&&\\s*!in_array\\("login", \\$bodyClassTokens, true\\);/',
    $layout,
)) {
    failMobileShellContract('A non-login guest drawer must opt out of native sidebar swipes.');
}
if (!str_contains($layout, '$disableGuestSwipeNavigation ? "preventMobileSwipeNavigation" : ""')) {
    failMobileShellContract('The shared body class must carry the native guest-swipe guard.');
}

$guestMobileNav = $read('game/include/resources/Templates/layout/guestMobileNav.php');
foreach ([
    '<div id="header" class="referAFriend">' => 'guest header shell',
    '<input type="checkbox" id="mobileMenuState"/>' => 'guest menu state checkbox',
    'class="mobileMenuButton" for="mobileMenuState"' => 'guest menu button label',
    'class="mainpageItem"' => 'guest Homepage entry',
    'class="loginItem"' => 'guest Login entry',
    'class="registerItem"' => 'guest Register entry',
    "getDiscordUrl() !== ''" => 'conditional guest Discord entry',
    'class="support"' => 'guest Support entry',
] as $needle => $label) {
    if (!str_contains($guestMobileNav, $needle)) {
        failMobileShellContract("Guest mobile navigation must render the {$label}.");
    }
}
if (substr_count($guestMobileNav, '<nav id="mobileMenu"') !== 2
    || strpos($guestMobileNav, '<nav id="mobileMenu"') > strpos($guestMobileNav, '<div id="navigation">')) {
    failMobileShellContract('Guest mobile navigation must keep the footer menu before its navigation trigger and drawer.');
}
foreach (['goldCount', 'silverCount', 'stockBar', 'newReportsCount', 'newMessagesCount'] as $authenticatedOnlyValue) {
    if (str_contains($guestMobileNav, $authenticatedOnlyValue)) {
        failMobileShellContract("Guest mobile navigation must not require authenticated {$authenticatedOnlyValue} state.");
    }
}

$head = $read('game/include/resources/Templates/layout/head.php');
if (!str_contains($head, "\$viewportContent = (string) (\$vars['viewportContent'] ?? 'width=device-width')")
    || !str_contains($head, '<meta name="viewport" content="<?= htmlspecialchars($viewportContent, ENT_QUOTES, \'UTF-8\') ?>"/>')) {
    failMobileShellContract('The shared head must carry the game viewport default and allow the public route override.');
}

foreach (['game-client.js'] as $file) {
    $js = $read('game/deployment/public/' . $file);
    foreach ([
        'MobileOptimizations: {', 'breakPoint: 620', '"mobileForced"',
        'withSidebarBeforeContent', 'withSidebarAfterContent',
        'allowMobileSidebars', '"width=" + mo.breakPoint', 'fixNumericKeyboardOnIOS',
        'target.hasClass("preventMobileSwipeNavigation")',
        'target.parents(".preventMobileSwipeNavigation")',
    ] as $needle) {
        if (!str_contains($js, $needle)) {
            failMobileShellContract("{$file} must contain {$needle} (MobileOptimizations).");
        }
    }
    foreach (['initMobileOptimizations', 'fixViewportWidth', 'width=1280'] as $retired) {
        if (str_contains($js, $retired)) {
            failMobileShellContract("{$file} must not keep the retired mobile path {$retired}.");
        }
    }
}

$dorf1 = $read('game/include/resources/Templates/resource-fields/main.php');
$input = strpos($dorf1, 'id="showTroopsToggleMobile"');
$label = strpos($dorf1, 'class="showTroopsToggleMobile');
$units = strpos($dorf1, 'class="villageInfobox units"');
if ($input === false || $label === false || $units === false || !($input < $label && $label < $units)) {
    failMobileShellContract('dorf1 must render input#showTroopsToggleMobile then its label before .units.');
}
if (str_contains($dorf1, 'id="showTroopsToggleMobile" checked')) {
    failMobileShellContract('The troop toggle must be unchecked by default.');
}
if (!str_contains($dorf1, "Travian.Game.Preferences.get('mobileUnitDisplay') === 'expanded'")) {
    failMobileShellContract('The troop toggle must restore its saved expanded preference.');
}
if (substr_count($dorf1, '<td class="noTroops">') !== 1) {
    failMobileShellContract('Only an actually empty troop panel may render the no-troops message.');
}
if (str_contains($dorf1, 'heroOnly')) {
    failMobileShellContract('A Hero-only troop panel must remain populated rather than rendering an empty-state message.');
}
$preferences = $read('game/include/Core/Helper/PreferencesHelper.php');
if (!str_contains($preferences, "case 'mobileUnitDisplay':")) {
    failMobileShellContract('The preferences endpoint must persist mobile troop visibility.');
}

foreach (['css_ltr', 'css_rtl'] as $dir) {
    $css = $read("assets/gpack/travian/mainPage/{$dir}/imports.css");
    foreach ([
        '.mobileForced #header #navigation',
        '.mobileForced #topBarHeroWrapper',
        '.mobileForced #reactDialogWrapper.withSidebarBeforeContent',
        '.mobileForced div.village1 .villageInfoWrapper .showTroopsToggleMobile',
    ] as $selector) {
        if (!str_contains($css, $selector)) {
            failMobileShellContract("{$dir} imports must define {$selector}.");
        }
    }
}

$fixes = $read('assets/gpack/travian/mainPage/lang/overrides.css');
foreach (['.mobileOptimized', '.mobileForced'] as $mode) {
    $selector = preg_quote($mode . ' div.village1 .villageInfoWrapper .villageInfobox.', '/');
    if (!preg_match('/' . $selector . 'production\s*\{\s*display:\s*block;/', $fixes)) {
        failMobileShellContract("Shared fixes must expose production values in {$mode}.");
    }
    if (!preg_match('/' . $selector . 'units\s*\{[^}]*max-height:\s*max\(120px,\s*calc\(100% - 650px\)\);/', $fixes)) {
        failMobileShellContract("Shared fixes must not clip the {$mode} troop panel to zero height.");
    }
}

echo "Mobile shell contract passed.\n";
