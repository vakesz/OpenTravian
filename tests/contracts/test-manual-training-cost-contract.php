#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: every player-trainable unit's manual cost is the same cost the
 * training controller, exchange-resource flow, and resource deduction use.
 */

use Core\Config;
use Game\Formulas;
use Game\Manual\ManualContent;

function failManualTrainingCost(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function assertManualTrainingCost(bool $condition, string $message): void
{
    if (!$condition) {
        failManualTrainingCost($message);
    }
}

$root = dirname(__DIR__, 2);
$manualSource = file_get_contents($root . '/game/include/Game/Manual/ManualContent.php');
$formulasSource = file_get_contents($root . '/game/include/Game/Formulas.php');
if ($manualSource === false || !str_contains($manualSource, "'cost' => Formulas::uTrainingCost(\$unitId),")) {
    failManualTrainingCost('The troop manual must obtain displayed resource costs from Formulas::uTrainingCost().');
}
if ($formulasSource === false || str_contains($formulasSource, 'newSettlersCost')) {
    failManualTrainingCost('Settler costs must have one canonical unit-data row, without the retired parallel override.');
}

require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$config = Config::getInstance();
$originalSpeed = $config->game->speed;
$originalMultiplier = $config->game->extra_training_time_multiplier;
try {
    $config->game->speed = 2;
    $config->game->useMilSeconds = $config->game->speed > 20;
    $config->game->useNanoseconds = $config->game->speed > 20000;
    $config->game->extra_training_time_multiplier = 1;
    $manual = new ManualContent();

    // Romans, Teutons, Gauls, Egyptians, Huns, Spartans and Vikings. Nature
    // and Natars are non-trainable reference formations, so they have no
    // charge to reconcile with a training queue.
    foreach ([1, 2, 3, 6, 7, 8, 9] as $tribe) {
        for ($number = 1; $number <= 10; $number++) {
            $unitId = nrToUnitId($number, $tribe);
            $cost = Formulas::uTrainingCost($unitId);
            $html = $manual->renderUnit($unitId);

            foreach ($cost as $resource => $amount) {
                $needle = '<i class="r' . ($resource + 1) . '"></i><span class="value ">' . $amount . '</span>';
                assertManualTrainingCost(
                    str_contains($html, $needle),
                    "Unit $unitId manual must display its charged resource $resource cost $amount."
                );
            }
        }
    }

    $egyptianSettlerCost = [5040, 6510, 4830, 4620];
    assertManualTrainingCost(
        Formulas::$data['units'][5][9]['cost'] === $egyptianSettlerCost
        && Formulas::uTrainingCost(60) === $egyptianSettlerCost,
        'Egyptian Settler must have one canonical official cost in its data row and training calculation.'
    );
    assertManualTrainingCost(
        (int) Formulas::uTrainingTime(60, 1) === 12400,
        'Egyptian Settler must retain the official 24,800-second base time, displayed as 3:26:40 at x2.'
    );
    $egyptianSettler = $manual->renderUnit(60);
    assertManualTrainingCost(
        str_contains($egyptianSettler, '<i class="r1"></i><span class="value ">5040</span>')
        && str_contains($egyptianSettler, '<i class="r2"></i><span class="value ">6510</span>')
        && str_contains($egyptianSettler, '<i class="r3"></i><span class="value ">4830</span>')
        && str_contains($egyptianSettler, '<i class="r4"></i><span class="value ">4620</span>'),
        'Egyptian Settler manual must show the official 5,040/6,510/4,830/4,620 resource tuple.'
    );
} finally {
    $config->game->speed = $originalSpeed;
    $config->game->extra_training_time_multiplier = $originalMultiplier;
}

echo "Manual training-cost contract passed.\n";
