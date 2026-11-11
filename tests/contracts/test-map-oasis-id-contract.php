<?php

declare(strict_types=1);

function failMapOasisIdContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$template = file_get_contents($root . '/game/include/resources/Templates/map/viewTileDetails_oasisFree.php');
// imports.css is the verbatim file; local-only rules live in lang/overrides.css.
$stylesheets = [
    $root . '/assets/gpack/travian/mainPage/lang/overrides.css',
];

if ($template === false
    || substr_count($template, 'id="troop_info"') !== 1
    || substr_count($template, "'reportsId' => 'mapReports'") !== 1) {
    failMapOasisIdContract('Free-oasis unit and report tables must have distinct IDs.');
}

foreach ($stylesheets as $stylesheetPath) {
    $stylesheet = file_get_contents($stylesheetPath);
    if ($stylesheet === false
        || !str_contains($stylesheet, '#tileDetails table#mapReports {')
        || !str_contains($stylesheet, '#tileDetails table#mapReports.rep tbody')
        || !str_contains($stylesheet, '#tileDetails table#mapReports.rep img.carry')) {
        failMapOasisIdContract('Free-oasis report table styling must cover the renamed report table.');
    }
}

echo "Map oasis ID contract passed.\n";
