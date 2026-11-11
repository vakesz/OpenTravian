<?php

declare(strict_types=1);

if (!defined('MAP_SIZE')) {
    define('MAP_SIZE', 400);
}

function getGame($name)
{
    return $name === 'movement_speed_increase' ? 2 : 1;
}

function assertHunnicHeroSpeed(bool $condition, string $message): void
{
    if ($condition) {
        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/include/Game/Formulas.php';
require_once $root . '/game/include/Game/SpeedCalculator.php';
require_once $root . '/game/include/Game/Hero/HeroItems.php';
require_once $root . '/game/include/Game/Hero/HeroHelper.php';

$helper = new \Game\Hero\HeroHelper();
$mountedHorse = ['btype' => 6, 'type' => 103];

assertHunnicHeroSpeed(
    $helper->getHunnicMountedArmySpeedBonus(7, $mountedHorse, true) === 6.0,
    'The Hun mounted-army bonus must add three fields/hour scaled by movement speed.'
);
assertHunnicHeroSpeed(
    $helper->getHunnicMountedArmySpeedBonus(7, $mountedHorse, false) === 0,
    'The Hun mounted-army bonus must not apply when infantry accompanies the hero.'
);
assertHunnicHeroSpeed(
    $helper->getHunnicMountedArmySpeedBonus(6, $mountedHorse, true) === 0,
    'Egyptian heroes must not receive the Hun mounted-army speed bonus.'
);
assertHunnicHeroSpeed(
    $helper->getHunnicMountedArmySpeedBonus(7, 0, true) === 0,
    'An unmounted Hun hero must not grant the mounted-army speed bonus.'
);

$calculator = new \Game\SpeedCalculator();
$calculator->setFrom(['x' => 0, 'y' => 0]);
$calculator->setTo(['x' => 10, 'y' => 0]);
$calculator->setMinSpeed(16);
$calculator->setHunnicMountedArmySpeedBonus(6);
assertHunnicHeroSpeed(
    $calculator->calc() === 1636.0,
    'The Hun mounted-army bonus must increase the army limiting speed, not only the hero speed.'
);

$requiredCallSites = [
    'game/include/Controller/Ajax/adventure/calculateDuration.php' => 1,
    'game/include/Game/Hero/AdventureTravel.php' => 1,
    'game/include/Controller/Ajax/troop/distanceSpeedAndDuration.php' => 1,
    'game/include/Controller/RallyPoint/RallyPoint.php' => 2,
    'game/include/Controller/RallyPoint/SendTroops.php' => 2,
    'game/include/Model/AccountDeleter.php' => 1,
    'game/include/Model/BattleModel.php' => 2,
    'game/include/Model/Movements/AdventureProcessor.php' => 1,
    'game/include/Model/Movements/ReinforcementProcessor.php' => 1,
];

foreach ($requiredCallSites as $path => $expectedCalls) {
    $source = file_get_contents($root . '/' . $path);
    assertHunnicHeroSpeed(
        $source !== false && substr_count($source, 'setHunnicMountedArmySpeedBonus') === $expectedCalls,
        sprintf('%s must apply the Hun mounted-army bonus for every hero movement path.', $path)
    );
}

echo "Hun hero movement-speed contract passed.\n";
