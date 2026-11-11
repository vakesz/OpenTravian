<?php

declare(strict_types=1);

// World speeds from x1000 up to x1,000,000,000: every construction, training, research, healing and troop movement
// takes at least one second; celebrations, World Wonder levels, trade ships, festivals, auctions, revival and the alliance bonus stay at their floors; no
// speed-scaled value leaves PHP's integer range or its database column (BIGINT / DOUBLE); values never step backwards
// along the ladder; large numbers format without exponents. The minimum is documented in docs/world-speed.md.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Extreme speed runtime skipped outside the php container.\n";
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
use Game\SpeedCalculator;
use Game\WorldSpeedProfile;

$fail = static function (string $message): never {
    fwrite(STDERR, 'Extreme speed runtime failed: ' . $message . "\n");
    exit(1);
};
$bigintMax = 9.2e18;   // signed BIGINT, the narrowest of the columns that hold these values
$speeds = [1000, 10000, 100000, 1000000, 10000000, 100000000, 1000000000];

$config = Config::getInstance();
$keys = array_merge(['speed', 'useMilSeconds', 'useNanoseconds', 'vacationDays'], array_keys(WorldSpeedProfile::profile(1)));
$original = [];
foreach ($keys as $key) {
    $original[$key] = $config->game->$key ?? null;
}
$originalHero = $config->heroConfig->resourcesMultiplier;
$from = Formulas::xy2kid(0, 0);
$near = Formulas::xy2kid(1, 0);
$far = Formulas::xy2kid(60, 0);
$series = [];
try {
    foreach ($speeds as $speed) {
        $config->game->speed = $speed;
        foreach (WorldSpeedProfile::profile($speed) as $key => $value) {
            $config->game->$key = $value;
        }
        $config->game->useMilSeconds = $speed > 20;
        $config->game->useNanoseconds = $speed > 20000;
        $config->heroConfig->resourcesMultiplier = $speed;
        $unit = WorldSpeedProfile::trainingTimeUnit($speed);
        $at = "at x{$speed}";
        $value = [];

        // One second at the least, in the unit each queue stores.
        foreach ([[1, 20, 5], [15, 20, 1], [40, 100, 1]] as [$gid, $level, $mb]) {
            $seconds = Formulas::buildingUpgradeTime($gid, $level, $mb);
            if (!is_int($seconds) && !is_float($seconds) || $seconds < 1) {
                $fail("construction (gid {$gid}, level {$level}) takes {$seconds} s {$at}.");
            }
            $value["build{$gid}_{$level}"] = $seconds;
        }
        foreach ([1, 6, 21, 31] as $unitId) {
            foreach ([1, 20] as $level) {
                $training = Formulas::uTrainingTime($unitId, $level);
                if ($training < $unit) {
                    $fail("training unit {$unitId} at building level {$level} takes {$training} (unit {$unit}) {$at}.");
                }
                $healing = Formulas::uHealingTime($unitId, $level);
                if ($healing < $unit) {
                    $fail("healing unit {$unitId} takes {$healing} (unit {$unit}) {$at}.");
                }
            }
            $value["train{$unitId}"] = Formulas::uTrainingTime($unitId, 1) / $unit;
        }
        foreach ([2, 7, 9] as $unitId) {
            $research = Formulas::uResearchTime($unitId);
            if ($research < 1) {
                $fail("research of unit {$unitId} takes {$research} s {$at}.");
            }
            $value["research{$unitId}"] = $research;
        }
        foreach ([$near, $far] as $to) {
            $calc = new SpeedCalculator();
            $calc->setFrom($from);
            $calc->setTo($to);
            $calc->setMinSpeed([Formulas::uSpeed(1)]);
            $travel = $calc->calc();
            if ($travel < 1) {
                $fail("a troop movement takes {$travel} s {$at}.");
            }
        }
        $value['unitSpeed'] = Formulas::uSpeed(1);
        $value['merchantSpeed'] = Formulas::merchantSpeed(1);

        // Other floors.
        $value['celebration'] = Formulas::celebrationTime(false, 20);
        $value['celebrationBig'] = Formulas::celebrationTime(true, 20);
        if ($value['celebration'] < 1 || $value['celebrationBig'] < 1) {
            $fail("a celebration takes under one second {$at}.");
        }
        // World Wonder levels and trade ships share the one second minimum.
        foreach ([1, 50, 90, 100] as $level) {
            $value["wonder{$level}"] = Formulas::buildingUpgradeTime(40, $level, 1);
            if ($value["wonder{$level}"] < 1) {
                $fail("World Wonder level {$level} takes under one second {$at}.");
            }
        }
        $trade = \Game\Ships::tradeTravelSeconds($from, $far, 1, true);
        $land = \Game\Ships::tradeTravelSeconds($from, $far, 1, false);
        if ($trade < 1 || $land < 1) {
            $fail("a merchant trip takes under one second {$at}.");
        }
        $value['tradeSpeed'] = \Game\Ships::deepWaterSpeed(\Game\Ships::TRADE, 1);
        if ($value['tradeSpeed'] !== 18.0 * $speed) {
            $fail("trade ships sail {$value['tradeSpeed']} fields per hour {$at}, expected " . 18 * $speed . '.');
        }
        $value['festival'] = WorldSpeedProfile::festivalDuration($speed);
        if ($value['festival'] < 300) {
            $fail("a festival takes under 5 minutes {$at}.");
        }
        $value['auction'] = WorldSpeedProfile::auctionDuration($speed);
        if ($value['auction'] < 3600) {
            $fail("an auction takes under an hour {$at}.");
        }
        foreach ([1, 50, 100] as $level) {
            if (Formulas::heroRegenerationTime($level) < $unit) {
                $fail("hero revival at level {$level} takes under a second {$at}.");
            }
        }
        $value['revival'] = Formulas::heroRegenerationTime(50) / $unit;
        foreach ([1, 5] as $level) {
            if (Formulas::getAllianceBonusUpgradeDuration($level) < 1) {
                $fail("an alliance bonus upgrade takes {$level} under a second {$at}.");
            }
        }
        if (WorldSpeedProfile::natarNewVillageTravelTime($speed) < 600) {
            $fail("a Natar attack on a new village arrives in under ten minutes {$at}.");
        }

        // Overflow: production, storage, cranny, trap, merchants, hero experience, festival and celebration amounts.
        $fieldProduction = 18 * Formulas::fieldProduction(20) * 2.5;
        $value['production'] = $fieldProduction;
        $value['storage'] = Formulas::storeCAP(20) * 3;
        $value['cranny'] = Formulas::crannyCAP(10, 3);
        $value['trapper'] = Formulas::getTrapperValueByLevel(20);
        $value['merchantCapacity'] = Formulas::merchantCAP(2, 20, 1.5);
        $value['heroExperience'] = 25 * 100 * 101 * Formulas::getHeroExpLevelMultiplier();
        $value['celebrationCost'] = max(Formulas::celebrationCost(true));
        $value['festivalCost'] = max(Formulas::getFestivalResources());
        $value['celebrationPoints'] = Formulas::getCelebrationMaxCP(true);
        $value['oasisProduction'] = max(Formulas::getOasisProduction(1));
        foreach ($value as $name => $number) {
            if (!is_finite((float)$number) || (float)$number < 0 || (float)$number >= $bigintMax) {
                $fail("{$name} is {$number} {$at}, outside the BIGINT range.");
            }
        }
        $series[$speed] = $value;
    }
} finally {
    foreach ($original as $key => $value) {
        $config->game->$key = $value;
    }
    $config->heroConfig->resourcesMultiplier = $originalHero;
}

// Along the ladder nothing steps backwards: rates and amounts never fall, durations never rise.
$rising = ['unitSpeed', 'merchantSpeed', 'tradeSpeed', 'production', 'storage', 'cranny', 'trapper', 'merchantCapacity', 'heroExperience', 'celebrationCost', 'festivalCost', 'celebrationPoints', 'oasisProduction'];
$falling = ['build1_20', 'build15_20', 'build40_100', 'train1', 'train6', 'train21', 'train31', 'research2', 'research7', 'research9', 'festival', 'auction', 'revival', 'celebration', 'celebrationBig', 'wonder1', 'wonder50', 'wonder90', 'wonder100'];
$previous = null;
foreach ($series as $speed => $value) {
    if ($previous !== null) {
        foreach ($rising as $name) {
            if ($value[$name] < $previous[1][$name]) {
                $fail("{$name} falls from x{$previous[0]} ({$previous[1][$name]}) to x{$speed} ({$value[$name]}).");
            }
        }
        foreach ($falling as $name) {
            if ($value[$name] > $previous[1][$name]) {
                $fail("{$name} rises from x{$previous[0]} ({$previous[1][$name]}) to x{$speed} ({$value[$name]}).");
            }
        }
    }
    $previous = [$speed, $value];
}

// Large numbers read as digits or compact suffixes, never as exponents.
foreach ([8e13, 4e14, 1e15, 2.5e17, 1e18, 5e19, 1e21] as $number) {
    foreach (['number_format_x', 'number_format_compact'] as $formatter) {
        $text = (string)$formatter($number);
        if ($text === '' || stripos($text, 'e+') !== false || str_contains($text, 'INF')) {
            $fail("{$formatter}({$number}) reads '{$text}'.");
        }
    }
}
if (number_to_string(2e18) !== '2Qi' || number_to_string(3e21) !== '3S') {
    $fail('number_to_string() mislabels quintillions or sextillions.');
}

// The database holds these values: wide columns for troop counts, ships and the speed itself.
$schema = (string)file_get_contents('/travian/database/world.sql');
foreach ([
    '/CREATE TABLE `farmlist` \((?:(?!CREATE TABLE).)*`u10` BIGINT UNSIGNED/s' => 'farm-list troop counts',
    '/CREATE TABLE `units` \((?:(?!CREATE TABLE).)*`ship4` BIGINT UNSIGNED/s' => 'ship counts',
    '/CREATE TABLE `rounds` \((?:(?!CREATE TABLE).)*`speed` DECIMAL\(13, 2\) UNSIGNED/s' => 'the archived speed',
] as $pattern => $what) {
    if (preg_match($pattern, $schema) !== 1) {
        $fail("world.sql does not give {$what} a column wide enough for x1,000,000,000.");
    }
}
if (!\Game\Installer\InstallInput::validSpeed('1000000000') || \Game\Installer\InstallInput::validSpeed('1000000001')) {
    $fail('the installer must accept exactly 1 to 1,000,000,000.');
}

echo 'Extreme speed runtime passed (' . count($speeds) . " speeds from x1000 to x1,000,000,000).\n";
