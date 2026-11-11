<?php

declare(strict_types=1);

$layout = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/layout/Layout.php');
if ($layout === false) {
    fwrite(STDERR, "Could not read the shared layout.\n");
    exit(1);
}

foreach (['$browserClasses', '"v35"', '"webkit"', '"blink"', '"chrome"', 'get_locale()', 'global_css_class', 'season-<?= detect_season() ?> buildingsV3"'] as $forbidden) {
    if (str_contains($layout, $forbidden)) {
        fwrite(STDERR, "The shared layout must not emit extra body class source: {$forbidden}\n");
        exit(1);
    }
}

if (!str_contains($layout, '"mobileOptimized"') || !str_contains($layout, '"ltr"')) {
    fwrite(STDERR, "Body class must be <page> ltr|rtl mobileOptimized.\n");
    exit(1);
}

if (!str_contains($layout, '<div id="background" class="<?= !empty($vars[\'isShore\']) ? \'isShore\' : \'\'; ?>">')) {
    fwrite(STDERR, "The shared shell must retain an empty background class outside shore villages.\n");
    exit(1);
}

if (!str_contains($layout, 'Travian.Game.Layout.MobileOptimizations.init();')
    || !str_contains($layout, 'jQuery(function () {')) {
    fwrite(STDERR, "Mobile layout initialization must run after the shared page shell is rendered.\n");
    exit(1);
}

$portalRoot = strpos($layout, '<div id="reactPortalRoot"></div>');
$dialogWrapper = strpos($layout, '<div id="reactDialogWrapper"></div>');
$background = strpos($layout, '<div id="background"');
if ($portalRoot === false || $dialogWrapper === false || $background === false
    || !($portalRoot < $dialogWrapper && $dialogWrapper < $background)) {
    fwrite(STDERR, "The shared shell must render portal root, dialog wrapper, then background.\n");
    exit(1);
}

echo "Layout browser-class contract passed.\n";
