<?php

declare(strict_types=1);

// Every movement path applies the world's movement multiplier (x1, x2/x2/x2/x4 for x1/x2/x3/x5/x10, 20 at x100), merchants the
// economic speed. Computes the travel time of each path at x1, x2, x10 and x100 and compares it with distance / (base speed x
// multiplier). Paths: troop attack, raid, reinforcement, scouting and return (they share the Rally Point speed calculator),
// settlers, conquest (senator speed), the farm-list send, the distanceSpeedAndDuration endpoint and the Rally Point and
// simulator displays (same calculator), hero adventure travel and return, hero redeploy and escape (hero speed), merchants,
// trade ships (economic speed) and raiding ships (movement band). A source scan makes sure no caller computes a duration without those speed functions.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Movement speed paths runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/dorf1.php';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/dorf1.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use Core\Config;
use Game\Formulas;
use Game\Hero\HeroHelper;
use Game\Ships;
use Game\SpeedCalculator;
use Game\WorldSpeedProfile;

$fail = static function (string $message): never {
    fwrite(STDERR, 'Movement speed paths runtime failed: ' . $message . "\n");
    exit(1);
};
$assertSeconds = static function (float|int $expected, float|int $actual, string $what) use ($fail): void {
    // Whole-second travel times, never under one second.
    $expected = max(1, (int)round($expected));
    if ((int)$actual !== $expected) {
        $fail("{$what}: expected {$expected} s, got " . (int)$actual . ' s.');
    }
};

// Source scan: each movement path computes its speed from the shared multiplier-aware functions.
$root = '/travian/game/include/';
foreach ([
    'Controller/RallyPoint/SendTroops.php' => ['Formulas::uSpeed(', 'calcTotalSpeed('],
    'Controller/RallyPoint/RallyPoint.php' => ['Formulas::uSpeed('],
    'Model/BattleModel.php' => ['Formulas::uSpeed('],
    'Model/Movements/ReinforcementProcessor.php' => ['Formulas::uSpeed('],
    'Model/Movements/SettlersProcessor.php' => ['Formulas::uSpeed('],
    'Model/Movements/AdventureProcessor.php' => ['Formulas::uSpeed(', 'calcTotalSpeed('],
    'Game/FarmList/FarmListSender.php' => ['Formulas::uSpeed('],
    'Controller/Ajax/troop/distanceSpeedAndDuration.php' => ['Formulas::uSpeed('],
    'Controller/Ajax/adventure/calculateDuration.php' => ['calcTotalSpeed('],
    'Game/Hero/AdventureTravel.php' => ['calcTotalSpeed('],
    'Model/MarketModel.php' => ['Formulas::merchantSpeed('],
    'Controller/Build/MarketPlaceCtrl.php' => ['Formulas::merchantSpeed('],
] as $file => $needles) {
    $source = (string)file_get_contents($root . $file);
    foreach ($needles as $needle) {
        if (!str_contains($source, $needle)) {
            $fail("{$file} does not take its speed from {$needle}.");
        }
    }
}
// No other file turns a distance into hours of travel by itself.
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . 'Game')) as $path) {
    $path = (string)$path;
    if (!str_ends_with($path, '.php') || str_ends_with($path, 'Formulas.php') || str_ends_with($path, 'SpeedCalculator.php') || str_ends_with($path, 'Ships.php')) {
        continue;
    }
    if (preg_match('/getDistance\([^;]*\)\s*\/\s*[^;]*\*\s*3600/', (string)file_get_contents($path)) === 1) {
        $fail(basename($path) . ' computes a travel time outside the shared speed functions.');
    }
}

$config = Config::getInstance();
$original = ['speed' => $config->game->speed, 'movement_speed_increase' => $config->game->movement_speed_increase];
// A straight 30-field line: (0|0) to (30|0).
$from = Formulas::xy2kid(0, 0);
$to = Formulas::xy2kid(30, 0);
$distance = 30.0;
// Base speeds before any multiplier (fields per hour): Legionary 6, Phalanx 7, Settler (Roman) 5, Senator (Roman) 4.
try {
    foreach ([1 => 1, 2 => 2, 10 => 4, 100 => 20] as $speed => $multiplier) {
        $config->game->speed = $speed;
        $config->game->movement_speed_increase = $multiplier;
        if (WorldSpeedProfile::movementSpeedMultiplier($speed) !== $multiplier) {
            $fail("the movement multiplier at x{$speed} is not {$multiplier}.");
        }
        $at = "at x{$speed}";

        // Troop movements: a Roman army (attack, raid, reinforcement, scouting and return all use this calculator).
        $legionary = nrToUnitId(1, 1);
        $settler = nrToUnitId(10, 1);
        $senator = nrToUnitId(9, 1);
        $baseSpeeds = [$legionary => 6, $settler => 5, $senator => 4];
        foreach ($baseSpeeds as $unit => $base) {
            if ((float)Formulas::uSpeed($unit) !== (float)($base * $multiplier)) {
                $fail("unit {$unit} moves " . Formulas::uSpeed($unit) . " fields per hour {$at}, expected " . $base * $multiplier . '.');
            }
            foreach (['outbound' => false, 'return' => true] as $label => $isReturn) {
                $calc = new SpeedCalculator();
                $calc->setFrom($from);
                $calc->setTo($to);
                if ($isReturn) {
                    $calc->isReturn();
                }
                $calc->setMinSpeed([Formulas::uSpeed($unit)]);
                $assertSeconds($distance / ($base * $multiplier) * 3600, $calc->calc(), "unit {$unit} {$label} {$at}");
            }
        }
        // The slowest unit sets the pace of a mixed army.
        $calc = new SpeedCalculator();
        $calc->setFrom($from);
        $calc->setTo($to);
        $calc->setMinSpeed([Formulas::uSpeed($legionary), Formulas::uSpeed($senator)]);
        $assertSeconds($distance / (4 * $multiplier) * 3600, $calc->calc(), "mixed army {$at}");

        // Hero: base speed times the multiplier on foot (no horse, no boots); adventure, return, redeploy and escape use it.
        $heroSpeed = (new HeroHelper())->calcTotalSpeed(1, null, null);
        if ((float)$heroSpeed !== (float)(7 * $multiplier)) {
            $fail("the hero walks {$heroSpeed} fields per hour {$at}, expected " . 7 * $multiplier . '.');
        }
        $calc = new SpeedCalculator();
        $calc->setFrom($from);
        $calc->setTo($to);
        $calc->hasHero();
        $calc->setLeftHand(null);
        $calc->setShoes(null);
        $calc->setMinSpeed($heroSpeed);
        $assertSeconds($distance / (7 * $multiplier) * 3600, $calc->calc(), "hero adventure {$at}");
        $calc = new SpeedCalculator();
        $calc->setFrom($to);
        $calc->setTo($from);
        $calc->hasHero();
        $calc->isReturn();
        $calc->setLeftHand(null);
        $calc->setShoes(null);
        $calc->setMinSpeed($heroSpeed);
        $assertSeconds($distance / (7 * $multiplier) * 3600, $calc->calc(), "hero return {$at}");

        // The hero's horse bonus is not scaled with the world speed (+14, +20, +26 fields per hour at every speed).
        foreach ([103 => 14, 104 => 20, 105 => 26] as $horse => $bonus) {
            $item = (new HeroHelper())->getHeroItemProperties(6, $horse);
            if ((float)$item['speed_horse'] !== (float)$bonus) {
                $fail("horse {$horse} adds {$item['speed_horse']} fields per hour {$at}, expected {$bonus}.");
            }
        }

        // Merchants walk at the economic speed, not the movement band.
        foreach ([1 => 16, 2 => 12, 3 => 24] as $tribe => $base) {
            if ((float)Formulas::merchantSpeed($tribe) !== (float)($base * $speed)) {
                $fail("tribe {$tribe} merchants move " . Formulas::merchantSpeed($tribe) . " fields per hour {$at}, expected " . $base * $speed . '.');
            }
            $assertSeconds($distance / ($base * $speed) * 3600, Ships::tradeTravelSeconds($from, $to, $tribe, false), "merchants of tribe {$tribe} {$at}");
        }

        // Ships: trade ships sail deep water at the fixed ship speed times the economic speed (like merchants on land), raiding
        // ships at the fixed ship speed times the movement multiplier; build time and cargo follow the economic speed.
        if ((float)Ships::deepWaterSpeed(Ships::TRADE, 1) !== (float)(18 * $speed)) {
            $fail("a trade ship sails " . Ships::deepWaterSpeed(Ships::TRADE, 1) . " fields per hour {$at}, expected " . 18 * $speed . '.');
        }
        if ((float)Ships::deepWaterSpeed(Ships::RAID, 1) !== (float)(18 * $multiplier)) {
            $fail("a raiding ship sails " . Ships::deepWaterSpeed(Ships::RAID, 1) . " fields per hour {$at}, expected " . 18 * $multiplier . '.');
        }
        if ((float)Ships::deepWaterSpeed(Ships::RAID, 9) !== (float)(24 * $multiplier)) {
            $fail("a Viking raiding ship sails " . Ships::deepWaterSpeed(Ships::RAID, 9) . " fields per hour {$at}, expected " . 24 * $multiplier . '.');
        }
        if (Ships::tradeCapacity(1, 0) !== (int)round(2500 * $speed * (Formulas::TradeOfficeValue(1, 0) / 100))) {
            $fail("ship cargo {$at} does not scale with the economic speed.");
        }
    }
} finally {
    $config->game->speed = $original['speed'];
    $config->game->movement_speed_increase = $original['movement_speed_increase'];
}

echo "Movement speed paths runtime passed (troops, settlers, conquest, hero, merchants and ships at x1, x2, x10 and x100).\n";
