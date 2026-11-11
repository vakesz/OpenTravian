#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: the in-game footer keeps the Gpack rule on every village (no
 * shore-specific footer override,); ActiveVillage name colors stay
 * pinned so they cannot inherit light chrome.
 */

function failFooterContrastContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$css = (string) file_get_contents($root . '/assets/gpack/travian/mainPage/lang/overrides.css');
$layout = (string) file_get_contents($root . '/game/include/resources/Templates/layout/Layout.php');

if (!str_contains($layout, 'id="footer"') || !str_contains($layout, 'id="pageLinks"')) {
    failFooterContrastContract('Layout must still render #footer / #pageLinks.');
}
if (str_contains($layout, 'class="copyright"')) {
    failFooterContrastContract('The footer must not restore the removed copyright line.');
}

if (str_contains($css, 'isShore') && preg_match('/isShore[^{]*#footer/', $css) === 1) {
    failFooterContrastContract('The in-game footer must keep the Gpack rule on shore villages (has no shore footer styling).');
}

foreach ([
    '.sidebar .sidebarBox .content',
    'isolation: isolate',
    '#sidebarBoxActiveVillage .playerName',
    'color: #333333',
    '#sidebarBoxActiveVillage .content #villageName .villageInput',
    'color: #5e463a',
    '-webkit-text-fill-color: #5e463a',
] as $needle) {
    if (!str_contains($css, $needle)) {
        failFooterContrastContract("overrides.css must pin ActiveVillage readable text ({$needle}).");
    }
}

echo "Footer contrast contract passed.\n";
