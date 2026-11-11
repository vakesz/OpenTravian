<?php

declare(strict_types=1);

function failReportActionIcons(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$reportLayout = file_get_contents($root . '/game/include/resources/Templates/reports/report_layout.php');
$profile = file_get_contents($root . '/game/include/resources/Templates/profile/Profile.php');
$farm = file_get_contents($root . '/game/include/resources/Templates/statistics/farm.php');
$reportController = file_get_contents($root . '/game/include/Controller/ReportsCtrl.php');
$layoutFixes = file_get_contents($root . '/assets/gpack/travian/mainPage/lang/overrides.css');

if ($reportLayout === false || $profile === false || $farm === false || $reportController === false || $layoutFixes === false) {
    failReportActionIcons('Could not read the report action templates.');
}

foreach (['delete-tiny.png', 'mark-read-small.png', 'archive-tiny.png', 'raid-list-small.png'] as $icon) {
    foreach (['img_ltr', 'img_rtl'] as $direction) {
        if (!is_file($root . '/assets/gpack/travian/mainPage/' . $direction . '/report/' . $icon)) {
            failReportActionIcons(sprintf('Missing %s report action icon for %s.', $icon, $direction));
        }
    }
}

// The report layout leaves the icons to the Gpack, like the reference; each one must be styled there.
$gpackStyles = file_get_contents($root . '/assets/gpack/travian/mainPage/css_ltr/imports.css');
if ($gpackStyles === false) {
    failReportActionIcons('Could not read the Gpack stylesheet.');
}
foreach (['delete-tiny.png', 'mark-read-small.png', 'archive-tiny.png', 'raid-list-small.png'] as $icon) {
    if (!str_contains($gpackStyles, 'report/' . $icon)) {
        failReportActionIcons(sprintf('The Gpack stylesheet must style the %s report action icon.', $icon));
    }
}

foreach ([$profile, $farm] as $template) {
    if (str_contains($template, 'reportAction.png')) {
        failReportActionIcons('Profile and farm report actions must not request the removed reportAction sprite.');
    }
}

$nativeWindowLookup = 'var tWindow = tWindows[tWindows.length - 1]; if (tWindow) { tWindow.close(); }';
if (str_contains($reportController, 'tWindows.getLast()')
    || substr_count($reportController, $nativeWindowLookup) !== 2) {
    failReportActionIcons('Report farm-list actions must close their dialog through native array indexing.');
}

foreach ([
    '#reportWrapper .body .role .header .outcome > img.losses',
    '#reportWrapper .body .role .header .outcome > img.loot',
    'inset-inline-end: 15px',
    'width: 48px',
    'height: 60px',
    'transform: scaleX(-1)',
] as $rule) {
    if (!str_contains($layoutFixes, $rule)) {
        failReportActionIcons(sprintf('Report outcome artwork must be constrained by the native %s rule.', $rule));
    }
}

echo "Report action icon contract passed.\n";
