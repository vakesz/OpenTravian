#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: culture point production keeps the Legends values through x10 and is multiplied by speed ÷ 10 above
 * (x100: ×10, x10000: ×1000), wherever points accrue (CulturePointsHelper, Session::getCP) or production is shown
 * (Residence/Palace culture tab, statistics). Stored rates (users.cp_prod, vdata.cp) stay unscaled.
 */

$root = dirname(__DIR__, 2);
require_once $root . '/game/include/Core/Time.php';
require_once $root . '/game/include/Game/WorldSpeedProfile.php';

use Game\WorldSpeedProfile;

function assertCulturePointSpeed(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

foreach ([1 => 1.0, 2 => 1.0, 3 => 1.0, 5 => 1.0, 10 => 1.0, 20 => 2.0, 100 => 10.0, 1000 => 100.0, 10000 => 1000.0] as $speed => $expected) {
    $actual = WorldSpeedProfile::culturePointProductionMultiplier($speed);
    assertCulturePointSpeed($actual === $expected, "Culture point production at x{$speed} must be ×{$expected}, got ×{$actual}.");
}

$sources = [
    'game/include/Game/Helpers/CulturePointsHelper.php' => '$cp_prod *= self::productionMultiplier();',
    'game/include/Core/Session.php' => "\$this->data['cp_prod'] * \\Game\\Helpers\\CulturePointsHelper::productionMultiplier()",
    'game/include/Controller/Build/ResidencePalaceCtrl.php' => '$speedMultiplier = \\Game\\Helpers\\CulturePointsHelper::productionMultiplier();',
    'game/include/Model/StatisticsGeneralModel.php' => '$speedMultiplier = \\Game\\Helpers\\CulturePointsHelper::productionMultiplier();',
];
foreach ($sources as $file => $needle) {
    assertCulturePointSpeed(str_contains((string)file_get_contents("$root/$file"), $needle), "$file must apply the culture point production multiplier.");
}

echo "Culture point speed contract passed.\n";
