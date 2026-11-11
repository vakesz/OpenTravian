<?php

declare(strict_types=1);

/**
 * Contract: the local siege rule of docs/world-speed.md. Above 50x, rams and catapults do nothing when the attack
 * kills under 33% of the defenders, and a lost attack keeps 0.3-1.5% of its damage; at 50x and below damage is
 * unchanged. Battles and the Rally Point simulator both apply it through BattleSetter.
 */

use Controller\RallyPoint\Simulator;
use Core\Config;
use Model\BattleSetter;

function failSpeedSiegeContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

require_once dirname(__DIR__, 2) . '/game/deployment/include/env.php';
require_once dirname(__DIR__, 2) . '/game/include/bootstrap.php';

$battle = new BattleSetter();
$scale = new ReflectionMethod(BattleSetter::class, 'fixSpeedCataRamEffect');
// A lost attack: 300 Legionnaires and 50 catapults on a level 10 target against 400 Praetorians (47% losses).
$catapultTargetLevel = static function (): int|float {
    $report = (new Simulator())->init([
        'R' => false,
        'trapped2killed' => [],
        'defender' => ['r' => 0, 't' => 0, 'p' => 100, 'rpLevel' => 0, 'wLevel' => 0, 'stone' => 0],
        'waves' => [
            ['r' => 0, 'u' => [0, 400, 0, 0, 0, 0, 0, 0, 0, 0], 'U' => array_fill(0, 10, 0), 'side' => 'def'],
            ['r' => 0, 'u' => [300, 0, 0, 0, 0, 0, 0, 50, 0, 0], 'U' => array_fill(0, 10, 0), 'side' => 'off', 'h' => false, 'p' => 100, 'b' => [10, 0], 'hero' => []],
        ],
    ]);

    return $report[0]['info']['bl'][1];
};
$config = Config::getInstance();
$originalSpeed = $config->game->speed;
try {
    $config->game->speed = 50;
    $atFifty = $scale->invoke($battle, 100, 1, 0.1);
    $simulatedAtFifty = $catapultTargetLevel();
    $config->game->speed = 100;
    $weakAttack = $scale->invoke($battle, 100, 0.5, 0.30);
    $lostAttack = $scale->invoke($battle, 100, 1, 0.45);
    $lostCloseAttack = $scale->invoke($battle, 100, 1, 0.85);
    $wipedDefence = $scale->invoke($battle, 100, 1, 0.95);
    $wonAttack = $scale->invoke($battle, 100, 0.2, 0.6);
    $simulatedAtHundred = $catapultTargetLevel();
} finally {
    $config->game->speed = $originalSpeed;
}

foreach ([
    'At 50x siege damage is unchanged' => [100, $atFifty],
    'Above 50x an attack killing under 33% of the defenders does no siege damage' => [0, $weakAttack],
    'Above 50x a lost attack with 45% defender losses keeps 0.3% of its damage' => [0.3, $lostAttack],
    'Above 50x a lost attack with 85% defender losses keeps 1.5% of its damage' => [1.5, $lostCloseAttack],
    'Above 50x a lost attack with 90% or more defender losses keeps its damage' => [100, $wipedDefence],
    'Above 50x a won attack with 33% or more defender losses keeps its damage' => [100, $wonAttack],
    'At 50x the simulated lost attack takes the target from level 10 to 4' => [4, $simulatedAtFifty],
    'At 100x the simulator applies the rule: the lost attack keeps the target at level 10' => [10, $simulatedAtHundred],
] as $rule => [$expected, $actual]) {
    if (abs($expected - $actual) > 0.000001) {
        failSpeedSiegeContract("$rule: expected $expected, got $actual.");
    }
}

echo "Speed siege scaling contract passed.\n";
