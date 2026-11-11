<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/game/deployment/include/env.php';
require dirname(__DIR__, 2) . '/game/include/bootstrap.php';

use Core\Config;
use Game\Formulas;
use Game\SpeedCalculator;

set_exception_handler(static function (Throwable $exception): never {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
});

$config = Config::getInstance();
$settings = (array)$config->heroConfig->heroItemsSettings;
$settings['heroDistanceSpeedRate'] = 1;
$settings['heroMinDistanceRate'] = 1;
$config->heroConfig->heroItemsSettings = $settings;

foreach ([6, 12, 1200] as $baseSpeed) {
    foreach ([0, 19, 20, 21, 30, 100] as $distance) {
        foreach ([0, 1, 10, 20] as $level) {
            foreach ([0, 97, 98, 99] as $boots) {
                foreach ([1, 2] as $artefact) {
                    $calculator = new SpeedCalculator();
                    $calculator->setFrom(['x' => 0, 'y' => 0]);
                    $calculator->setTo(['x' => $distance, 'y' => 0]);
                    $calculator->setMinSpeed($baseSpeed);
                    $calculator->setTournamentSqLvl($level);
                    $calculator->setArtefactEffect($artefact);
                    $calculator->hasHero();
                    $calculator->troopsWithHero();
                    $calculator->setLeftHand(0);
                    $calculator->setShoes(0);
                    if ($boots) {
                        $calculator->setShoes(['btype' => 5, 'type' => $boots]);
                    }
                    $bootBonus = $boots ? ($boots - 96) * 0.25 : 0;
                    $expected = round(3600 / ($baseSpeed * $artefact) * (
                        min(20, $distance) + max(0, $distance - 20) / (1 + 0.2 * $level + $bootBonus)
                    ));
                    $actual = $calculator->calc();
                    if ($actual !== $expected) {
                        throw new RuntimeException(sprintf(
                            'FAIL: speed=%s distance=%s square=%d boots=%d artefact=%d: expected %s seconds, got %s.',
                            $baseSpeed, $distance, $level, $boots, $artefact, $expected, $actual
                        ));
                    }
                }
            }
        }
    }
}

foreach ([0, 1, 10, 20] as $level) {
    if (Formulas::TournamentSqValue($level) !== 100 + 20 * $level) {
        throw new RuntimeException('Tournament Square display must match its +20% per-level travel bonus.');
    }
}

foreach ([10, 30] as $threshold) {
    $settings['heroMinDistanceRate'] = $threshold / 20;
    $config->heroConfig->heroItemsSettings = $settings;
    $calculator = new SpeedCalculator();
    $calculator->setFrom(['x' => 0, 'y' => 0]);
    $calculator->setTo(['x' => 40, 'y' => 0]);
    $calculator->setMinSpeed(12);
    $calculator->setTournamentSqLvl(10);
    $calculator->setLeftHand(0);
    $calculator->setShoes(['btype' => 5, 'type' => 97]);
    $calculator->hasHero();
    $calculator->troopsWithHero();
    $expected = $threshold === 10
        ? round(300 * (10 + 10 / 1.25 + 20 / 3.25))
        : round(300 * (20 + 10 / 3 + 10 / 3.25));
    if ($calculator->calc() !== $expected) {
        throw new RuntimeException('Custom boot-distance overrides must preserve their own boundary independently of the Tournament Square.');
    }
}

echo "Troop travel boundaries, Tournament Square, boot stacking, and artefact contract passed.\n";
