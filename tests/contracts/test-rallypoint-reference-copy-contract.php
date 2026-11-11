#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: active Rally Point copy keeps the English gid16 bundle.
 */

function failRallyPointReferenceCopyContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$translation = file_get_contents($root . '/game/include/resources/Translation/en-US.php');
$rallyPoint = file_get_contents($root . '/game/include/Controller/RallyPoint/RallyPoint.php');
$rallyPointHtml = file_get_contents($root . '/game/include/Controller/RallyPoint/RallyPointHTML.php');

if ($translation === false || $rallyPoint === false || $rallyPointHtml === false) {
    failRallyPointReferenceCopyContract('Unable to read Rally Point copy sources.');
}

foreach ([
    "\$Definition['RallyPoint']['myOasis'] = 'Own oasis';",
    "\$Definition['RallyPoint']['occupiedOasis'] = 'occupied oasis';",
    "\$Definition['RallyPoint']['unoccupiedOasis'] = 'unoccupied oasis';",
    "\$Definition['RallyPoint']['TroopKillDesc'] = 'Do you really want to kill your troops?';",
    "\$Definition['RallyPoint']['outSettlers'] = 'Founding a new village';",
] as $expected) {
    if (!str_contains($translation, $expected)) {
        failRallyPointReferenceCopyContract('Missing Rally Point copy: ' . $expected);
    }
}

foreach ([
    "T('RallyPoint', 'myOasis')",
    '"occupiedOasis"',
    '"unoccupiedOasis"',
    "T(\"RallyPoint\", \$mode)",
] as $expected) {
    if (!str_contains($rallyPoint, $expected)) {
        failRallyPointReferenceCopyContract('Rally Point movements no longer render covered copy: ' . $expected);
    }
}

if (!str_contains($rallyPointHtml, 'T("RallyPoint", "TroopKillDesc")')) {
    failRallyPointReferenceCopyContract('Rally Point troop disband dialog no longer renders its covered copy.');
}

echo "Rally Point reference copy contract passed.\n";
