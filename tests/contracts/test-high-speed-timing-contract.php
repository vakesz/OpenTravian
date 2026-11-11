#!/usr/bin/env php
<?php

declare(strict_types=1);

use Core\Config;
use Game\Formulas;
use Game\SpeedCalculator;
use Game\WorldSpeedProfile;

function assertHighSpeedTiming(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__, 2);
$after = file_get_contents($root . '/game/include/config/config.after.php');
$launcher = file_get_contents($root . '/game/include/Core/Jobs/Launcher.php');
$automation = file_get_contents($root . '/game/include/Core/Automation.php');
$speedCalculator = file_get_contents($root . '/game/include/Game/SpeedCalculator.php');
$market = file_get_contents($root . '/game/include/Model/MarketModel.php');
$marketPlaceController = file_get_contents($root . '/game/include/Controller/Build/MarketPlaceCtrl.php');
$ships = file_get_contents($root . '/game/include/Game/Ships.php');
assertHighSpeedTiming(
    $after !== false
    && str_contains($after, '$config->game->useMilSeconds = $config->game->speed > 20;')
    && str_contains($after, '$config->game->useNanoseconds = $config->game->speed > 20000;'),
    'High-speed worlds must select millisecond and nanosecond training clocks.'
);
assertHighSpeedTiming(
    $launcher !== false && str_contains($launcher, "getGameSpeed() > 20 ? 0.1 : 1"),
    'High-speed troop training must use the worker\'s bounded sub-second cadence.'
);
assertHighSpeedTiming(
    $launcher !== false && str_contains($launcher, "new Job('marketComplete:researchComplete', getGameSpeed() > 20 ? 1 : 3, \$job)"),
    'High-speed research must use the one-second worker cadence required by whole-second research timestamps.'
);
assertHighSpeedTiming(
    $automation !== false
    && !str_contains($automation, 'floor(getGameSpeed() / 1000) * 5')
    && !str_contains($automation, 'nanoseconds() - $delay')
    && !str_contains($automation, 'miliseconds() - $delay')
    && str_contains($automation, 'public function trainingComplete(?int $onlyTrainingId = null)'),
    'Training completion must use the actual queue timestamp without a high-speed hold.'
);
assertHighSpeedTiming(
    $speedCalculator !== false
    && $market !== false
    && $marketPlaceController !== false
    && $ships !== false
    && str_contains($speedCalculator, 'Formulas::travelTimeSeconds($time)')
    && str_contains($market, 'Formulas::travelTimeSeconds(Formulas::getDistance(')
    && substr_count($marketPlaceController, 'Formulas::travelTimeSeconds(Formulas::getDistance(') === 2
    && !str_contains($marketPlaceController, 'round(Formulas::getDistance(')
    && substr_count($ships, 'Formulas::travelTimeSeconds(') === 2,
    'Troop movement and every merchant dispatch or marketplace ETA must share the positive whole-second travel floor.'
);

require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$config = Config::getInstance();
$originalSpeed = $config->game->speed;
$originalMilliseconds = $config->game->useMilSeconds;
$originalNanoseconds = $config->game->useNanoseconds;
$originalMovementSpeed = $config->game->movement_speed_increase;

try {
    /** @var array<int, array<int, array{time: int, cost: array<int, int>}>> $playerUnitTables */
    $playerUnitTables = array_intersect_key(
        Formulas::$data['units'],
        array_flip([0, 1, 2, 5, 6, 7, 8])
    );

    foreach ([101, 1000, 1000000] as $speed) {
        $config->game->speed = $speed;
        $config->game->useMilSeconds = $speed > 20;
        $config->game->useNanoseconds = $speed > 20000;

        assertHighSpeedTiming(
            Formulas::getTrapRepairTime() >= 1,
            sprintf('Trap repair must retain a positive queue duration at %dx.', $speed)
        );
        assertHighSpeedTiming(
            Formulas::uTrainingTime(1, 1) >= 1,
            sprintf('Troop training must retain a positive queue duration at %dx.', $speed)
        );
        foreach ($playerUnitTables as $race => $units) {
            foreach ($units as $unitNumber => $unit) {
                $unitId = $race * 10 + $unitNumber + 1;
                assertHighSpeedTiming(
                    Formulas::uTrainingTime($unitId, 1) >= 1,
                    sprintf('Troop %d must retain a positive training queue duration at %dx.', $unitId, $speed)
                );
                assertHighSpeedTiming(
                    Formulas::uResearchTime($unitId) >= 1 && Formulas::uUpgradeTime($unitId, 1, 1) >= 1,
                    sprintf('Troop %d must retain positive research and smithy queue durations at %dx.', $unitId, $speed)
                );
            }
        }
        assertHighSpeedTiming(
            Formulas::uResearchTime(1) >= 1 && Formulas::uUpgradeTime(1, 1, 1) >= 1,
            sprintf('Research and upgrades must retain positive integer queue durations at %dx.', $speed)
        );
        assertHighSpeedTiming(
            Formulas::buildingUpgradeTime(1, 1, 1) >= 1,
            sprintf('Ordinary construction must retain a positive integer queue duration at %dx.', $speed)
        );

        $config->game->movement_speed_increase = WorldSpeedProfile::movementSpeedMultiplier($speed);
        assertHighSpeedTiming(
            Formulas::travelTimeSeconds(0.05) === 1 && Formulas::travelTimeSeconds(0.0) === 0,
            sprintf('Positive sub-second travel must floor to one second at %dx without changing a zero-length route.', $speed)
        );
        $travel = new SpeedCalculator();
        $travel->setFrom(['x' => 0, 'y' => 0]);
        $travel->setTo(['x' => 1, 'y' => 0]);
        $travel->setMinSpeed(25 * $config->game->movement_speed_increase);
        assertHighSpeedTiming(
            $travel->calc() >= 1,
            sprintf('Troop movement must retain a positive whole-second duration at %dx.', $speed)
        );
    }
} finally {
    $config->game->speed = $originalSpeed;
    $config->game->useMilSeconds = $originalMilliseconds;
    $config->game->useNanoseconds = $originalNanoseconds;
    $config->game->movement_speed_increase = $originalMovementSpeed;
}

echo "High-speed timing contract passed.\n";
