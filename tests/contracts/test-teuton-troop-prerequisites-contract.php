#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: current the x2 manual and the official Teuton Community Week
 * table preserve these standard prerequisites. Community Week changes selected
 * numeric stats only; prerequisite order is player-visible in the manual.
 */

use Game\Formulas;
use Game\Manual\ManualContent;

function failTeutonPrerequisites(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function assertTeutonPrerequisites(bool $condition, string $message): void
{
    if (!$condition) {
        failTeutonPrerequisites($message);
    }
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$academyController = (string) file_get_contents($root . '/game/include/Controller/Build/AcademyCtrl.php');
assertTeutonPrerequisites(
    str_contains($academyController, 'Formulas::uResearchPreRequests('),
    'Academy availability must consume the shared troop prerequisite data.'
);

$expected = [
    12 => [[22 => 1, 19 => 3], "Academy</a> Level 1, <a onclick=\"Travian.Game.Manual.open('building', 19)\">Barracks</a> Level 3"],
    13 => [[22 => 3, 13 => 1], "Academy</a> Level 3, <a onclick=\"Travian.Game.Manual.open('building', 13)\">Smithy</a> Level 1"],
    14 => [[22 => 1, 15 => 5], "Academy</a> Level 1, <a onclick=\"Travian.Game.Manual.open('building', 15)\">Main Building</a> Level 5"],
    15 => [[22 => 5, 20 => 3], "Academy</a> Level 5, <a onclick=\"Travian.Game.Manual.open('building', 20)\">Stable</a> Level 3"],
    16 => [[22 => 15, 20 => 10], "Academy</a> Level 15, <a onclick=\"Travian.Game.Manual.open('building', 20)\">Stable</a> Level 10"],
    17 => [[22 => 10, 21 => 1], "Academy</a> Level 10, <a onclick=\"Travian.Game.Manual.open('building', 21)\">Workshop</a> Level 1"],
];

$manual = new ManualContent();
foreach ($expected as $unitId => [$requirements, $rendered]) {
    assertTeutonPrerequisites(
        Formulas::uResearchPreRequests(2, $unitId - 10) === $requirements,
        "Teuton unit {$unitId} must keep the official prerequisite data and display order."
    );
    assertTeutonPrerequisites(
        str_contains($manual->renderUnit($unitId), $rendered),
        "Teuton unit {$unitId} manual prerequisites must match."
    );
}

echo "Teuton troop prerequisite contract passed.\n";
