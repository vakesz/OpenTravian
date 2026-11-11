<?php

declare(strict_types=1);

/**
 * Contract: map toolbar zoom level 3 label matches ("10%").
 */

function failMapZoomLabelContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$normal = file_get_contents($root . '/game/include/resources/Templates/map/normal.php');
if ($normal === false) {
    failMapZoomLabelContract('Unable to read map/normal.php.');
}

if (!str_contains($normal, '== 3 ? \'display\' : "hide"; ?>"><?= $zoomLabel(10); ?>')) {
    failMapZoomLabelContract('Zoom level 3 toolbar entry must render as 10%.');
}

if (!str_contains($normal, '$zoomLabel = static fn(int $percent): string => "\u{202D}\u{202D}" . $percent . "\u{202C}%\u{202C}"')) {
    failMapZoomLabelContract('Zoom labels must carry the reference isolates.');
}

if (str_contains($normal, '">8%')) {
    failMapZoomLabelContract('Remove the legacy 8% zoom label.');
}

echo "Map zoom label contract passed.\n";
