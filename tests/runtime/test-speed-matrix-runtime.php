<?php

declare(strict_types=1);

// World-speed matrix through the real Formulas: construction, training, research, production, merchants, troop speed,
// capacity and celebration at x1, x2, x3, x4, x5, x10, x20, x100 and x1000. Expected values were computed from the code and
// checked against the vendor's speed table (construction and training /s, production x s, troop speed 1/2/2/2/4, celebration
// time /1 /1 /2 /2 /4 and caps 500/500/250/250/125) and the x2 figures from 2026-11-07 (12 construction
// times at Main Building level 11, Phalanx training 0:08:40 at Barracks level 1, Warehouse 11800, Granary 6300, Cranny 3000).
// The sources, versions and open differences are listed in docs/world-speed.md.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Speed matrix runtime skipped outside the php container.\n";
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
use Game\WorldSpeedProfile;

$fail = static function (string $message): never {
    fwrite(STDERR, 'Speed matrix runtime failed: ' . $message . "\n");
    exit(1);
};

$speeds = [1, 2, 3, 4, 5, 10, 20, 100, 1000];
// metric => [values per speed in the order above, null = not pinned at that speed]
$expected = [
    'Legionary movement speed' => [6, 12, 12, 12, 12, 24, 48, 120, 480],
    'Roman merchant speed' => [16, 32, 48, 64, 80, 160, 320, 1600, 16000],
    'Roman merchant capacity (Trade Office 0)' => [500, 1000, 1500, 2000, 2500, 5000, 10000, 50000, 500000],
    'Resource field level 5, Main Building 1, seconds' => [3560, 1780, 1190, 890, 710, 360, 178, 36, 4],
    'Main Building level 10, Main Building 10, seconds' => [9250, 4620, 3080, 2310, 1850, 920, 462, 92, 9],
    'Warehouse level 1, Main Building 1, seconds' => [2000, 1000, 670, 500, 400, 200, 100, 20, 2],
    'Legionary training, Barracks 1, seconds' => [1600, 800, 533, 400, 320, 160, 80, 16, 1.6],
    'Legionary training, Barracks 10, seconds' => [620, 310, 207, 155, 124, 62, 30, 6.198, 1],
    'Legionary research, seconds' => [7080, 3540, 2360, 1770, 1415, 710, 355, 71, 8],
    'Resource field level 10 production per hour' => [280, 560, 840, 1120, 1400, 2800, 5600, 28000, 280000],
    'Warehouse level 20 capacity' => [80000, 80000, 80000, 80000, 80000, 80000, 80000, 4000000, 40000000],
    'Cranny level 10 capacity (Roman)' => [2000, 2000, 2000, 2000, 2000, 2000, 2000, 26000, 250000],
    'Trapper level 10 traps' => [154, 154, 154, 154, 154, 154, 154, 3850, 38500],
    'Small celebration cap' => [500, 500, 250, 250, 250, 125, 125, 125, 125],
    'Large celebration cap' => [2000, 2000, 1000, 1000, 1000, 500, 500, 500, 500],
    'Small celebration time, Town Hall 10, seconds' => [62117, 62117, 31058, 31058, 31058, 15529, null, null, null],
    'Starting culture points' => [500, 250, 167, 125, 100, 50, 63, 63, 63],
    'Second village culture points' => [2000, 800, 500, 400, 300, 200, 200, null, null],
    'Vacation days' => [15, 8, 5, 4, 3, 2, 3, 3, 3],
    'Hero revival, level 10, seconds' => [39600, 39600, 19800, 19800, 19800, 9900, 5657, 2475, 792],
];

$config = Config::getInstance();
$originalSpeed = $config->game->speed;
$originalProfile = [];
foreach (array_keys(WorldSpeedProfile::profile(1)) as $key) {
    $originalProfile[$key] = $config->game->$key ?? null;
}
$compute = [
    'Legionary movement speed' => static fn () => Formulas::uSpeed(1),
    'Roman merchant speed' => static fn () => Formulas::merchantSpeed(1),
    'Roman merchant capacity (Trade Office 0)' => static fn () => Formulas::merchantCAP(1, 0),
    'Resource field level 5, Main Building 1, seconds' => static fn () => Formulas::buildingUpgradeTime(1, 5, 1),
    'Main Building level 10, Main Building 10, seconds' => static fn () => Formulas::buildingUpgradeTime(15, 10, 10),
    'Warehouse level 1, Main Building 1, seconds' => static fn () => Formulas::buildingUpgradeTime(10, 1, 1),
    'Legionary training, Barracks 1, seconds' => static fn () => Formulas::uTrainingTime(1, 1) / WorldSpeedProfile::trainingTimeUnit((int)getGameSpeed()),
    'Legionary training, Barracks 10, seconds' => static fn () => Formulas::uTrainingTime(1, 10) / WorldSpeedProfile::trainingTimeUnit((int)getGameSpeed()),
    'Legionary research, seconds' => static fn () => Formulas::uResearchTime(2),
    'Resource field level 10 production per hour' => static fn () => Formulas::fieldProduction(10),
    'Warehouse level 20 capacity' => static fn () => Formulas::storeCAP(20),
    'Cranny level 10 capacity (Roman)' => static fn () => Formulas::crannyCAP(10, 1),
    'Trapper level 10 traps' => static fn () => Formulas::getTrapperValueByLevel(10),
    'Small celebration cap' => static fn () => Formulas::celebrationCpAtSpeed(false, (int)getGameSpeed()),
    'Large celebration cap' => static fn () => Formulas::celebrationCpAtSpeed(true, (int)getGameSpeed()),
    'Small celebration time, Town Hall 10, seconds' => static fn () => Formulas::celebrationTime(false, 10),
    'Starting culture points' => static fn () => Formulas::startingCulturePoints(),
    'Second village culture points' => static fn () => Formulas::newVillageCP(2),
    'Vacation days' => static fn () => Formulas::maxVacationDays(),
    'Hero revival, level 10, seconds' => static fn () => Formulas::heroRegenerationTime(10) / WorldSpeedProfile::trainingTimeUnit((int)getGameSpeed()),
];

try {
    foreach ($speeds as $index => $speed) {
        $config->game->speed = $speed;
        $config->game->useMilSeconds = $config->game->speed > 20;
        $config->game->useNanoseconds = $config->game->speed > 20000;
        foreach (WorldSpeedProfile::profile($speed) as $key => $value) {
            $config->game->$key = $value;
        }
        $config->game->vacationDays = 15;
        foreach ($expected as $metric => $values) {
            if ($values[$index] === null) {
                continue;
            }
            $actual = $compute[$metric]();
            if ((float)$actual !== (float)$values[$index]) {
                $fail(sprintf('%s at x%d: expected %s, got %s.', $metric, $speed, $values[$index], $actual));
            }
        }
    }

    // At x2 with Main Building level 11: construction times to the second.
    $config->game->speed = 2;
    $config->game->useMilSeconds = $config->game->speed > 20;
    $config->game->useNanoseconds = $config->game->speed > 20000;
    foreach (WorldSpeedProfile::profile(2) as $key => $value) {
        $config->game->$key = $value;
    }
    foreach ([
        [1, 8, '1:30:00'], [4, 6, '0:27:20'], [3, 8, '1:59:30'], [2, 9, '2:15:20'], [18, 4, '0:24:10'], [10, 11, '1:27:50'],
        [11, 8, '0:45:50'], [17, 2, '0:13:50'], [19, 2, '0:15:10'], [15, 12, '1:43:40'], [16, 2, '0:15:10'], [33, 2, '0:15:10'],
    ] as [$gid, $level, $time]) {
        $actual = gmdate('G:i:s', (int)Formulas::buildingUpgradeTime($gid, $level, 11));
        if ($actual !== $time) {
            $fail("x2 reference: building {$gid} to level {$level} takes {$actual}, the reference shows {$time}.");
        }
    }
    if (gmdate('G:i:s', (int)Formulas::uTrainingTime(21, 1)) !== '0:08:40') {
        $fail('x2 reference: Phalanx training at Barracks level 1 must take 0:08:40.');
    }
    if ((int)Formulas::storeCAP(10) !== 11800 || (int)Formulas::crannyCAP(10, 3) !== 3000) {
        $fail('x2 reference: Warehouse level 10 holds 11800 and a Gaul Cranny level 10 hides 3000.');
    }

    // The vendor's construction and training rows: the time divides by the speed (round-off aside), production multiplies.
    foreach ([2, 3, 5, 10] as $speed) {
        $ratio = $expected['Warehouse level 1, Main Building 1, seconds'][0] / $expected['Warehouse level 1, Main Building 1, seconds'][array_search($speed, $speeds, true)];
        if (abs($ratio - $speed) > 0.05 * $speed) {
            $fail("construction time must divide by {$speed}, got a factor of {$ratio}.");
        }
    }
} finally {
    $config->game->speed = $originalSpeed;
    foreach ($originalProfile as $key => $value) {
        $config->game->$key = $value;
    }
}

echo 'Speed matrix runtime passed (' . count($expected) . ' metrics at ' . count($speeds) . " speeds, plus the x2 reference captures).\n";
