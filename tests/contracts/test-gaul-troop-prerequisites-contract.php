#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: current the x2 manual preserves these standard Gaul
 * prerequisites. Community Week changes selected numeric stats only; order is
 * player-visible in the manual.
 */

use Game\Formulas;
use Game\Manual\ManualContent;

function failGaulPrerequisites(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function assertGaulPrerequisites(bool $condition, string $message): void
{
    if (!$condition) {
        failGaulPrerequisites($message);
    }
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$academyController = (string) file_get_contents($root . '/game/include/Controller/Build/AcademyCtrl.php');
assertGaulPrerequisites(
    str_contains($academyController, 'Formulas::uResearchPreRequests('),
    'Academy availability must consume the shared troop prerequisite data.'
);

$expected = [
    22 => [[22 => 3, 13 => 1], "Academy</a> Level 3, <a onclick=\"Travian.Game.Manual.open('building', 13)\">Smithy</a> Level 1"],
    23 => [[22 => 5, 20 => 1], "Academy</a> Level 5, <a onclick=\"Travian.Game.Manual.open('building', 20)\">Stable</a> Level 1"],
    24 => [[22 => 5, 20 => 3], "Academy</a> Level 5, <a onclick=\"Travian.Game.Manual.open('building', 20)\">Stable</a> Level 3"],
    25 => [[22 => 5, 20 => 5], "Academy</a> Level 5, <a onclick=\"Travian.Game.Manual.open('building', 20)\">Stable</a> Level 5"],
    26 => [[22 => 15, 20 => 10], "Academy</a> Level 15, <a onclick=\"Travian.Game.Manual.open('building', 20)\">Stable</a> Level 10"],
    27 => [[22 => 10, 21 => 1], "Academy</a> Level 10, <a onclick=\"Travian.Game.Manual.open('building', 21)\">Workshop</a> Level 1"],
];

$manual = new ManualContent();
foreach ($expected as $unitId => [$requirements, $rendered]) {
    assertGaulPrerequisites(
        Formulas::uResearchPreRequests(3, $unitId - 20) === $requirements,
        "Gaul unit {$unitId} must keep the official prerequisite data and display order."
    );
    assertGaulPrerequisites(
        str_contains($manual->renderUnit($unitId), $rendered),
        "Gaul unit {$unitId} manual prerequisites must match."
    );
}

echo "Gaul troop prerequisite contract passed.\n";
