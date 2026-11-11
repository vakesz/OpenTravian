#!/usr/bin/env php
<?php

declare(strict_types=1);

use Core\Config;
use Game\Formulas;

function failOfficialX10ConstructionRounding(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$config = Config::getInstance();
$originalSpeed = $config->game->speed;

try {
    $config->game->speed = 10;

    if ((int) Formulas::buildingUpgradeTime(1, 1, 1) !== 30) {
        failOfficialX10ConstructionRounding(
            'Official calculator regression: Woodcutter level 1 at Main Building level 1 must display as 30 seconds at x10.'
        );
    }
    if ((int) Formulas::buildingUpgradeTime(1, 6, 20) !== 290) {
        failOfficialX10ConstructionRounding(
            'Official calculator regression: Woodcutter level 6 at Main Building level 20 must display as 4:50 at x10.'
        );
    }
} finally {
    $config->game->speed = $originalSpeed;
}

echo "Official x10 construction-rounding contract passed.\n";
