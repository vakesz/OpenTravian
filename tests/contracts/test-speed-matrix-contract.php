<?php

declare(strict_types=1);

/**
 * Speed matrix: every speed-derived profile value at every boundary speed.
 *
 * Fixtures only; no database and no world state. Each metric states its
 * expected direction. Where a metric is knowingly not monotone, the exemption
 * is named below with the reason, so a new accidental dip fails this test.
 */

namespace Tests\Stub {
    final class SpeedMatrixConfigStub
    {
        public static int $speed = 1;

        public static function getProperty(string ...$properties): mixed
        {
            return match ($properties) {
                ['quest', 'multiplyRewards'] => true,
                ['game', 'speed'] => self::$speed,
                ['gold', 'plusAccountDurationSeconds'],
                ['gold', 'productionBoostDurationSeconds'] => 10800,
                default => null,
            };
        }
    }
}

namespace {
    class_alias(\Tests\Stub\SpeedMatrixConfigStub::class, 'Core\\Config');

    function getGameSpeed(): int
    {
        return \Tests\Stub\SpeedMatrixConfigStub::$speed;
    }

    function speedMatrixFail(string $message): never
    {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    function speedMatrixAssertSame(mixed $expected, mixed $actual, string $what): void
    {
        if ($expected !== $actual) {
            speedMatrixFail(sprintf('%s: expected %s, got %s.', $what, var_export($expected, true), var_export($actual, true)));
        }
    }

    $root = dirname(__DIR__, 2) . '/game/include';
    require $root . '/Game/WorldSpeedProfile.php';
    require $root . '/Game/PremiumDuration.php';
    require $root . '/Model/Quest.php';

    use Game\WorldSpeedProfile as Profile;

    $speeds = [1, 2, 3, 4, 5, 6, 10, 11, 20, 21, 50, 100, 101, 250, 251, 1000, 10000, 20000, 20001, 100000, 1000000, 10000000, 100000000, 1000000000];

    // Published values at the official speeds, from the Travian Legends tables.
    $published = [
        1 => ['movement' => 1, 'storage' => 1, 'protection' => 10800],
        2 => ['movement' => 2, 'storage' => 1, 'protection' => 10800],
        3 => ['movement' => 2, 'storage' => 1, 'protection' => 10800],
        4 => ['movement' => 2, 'storage' => 1, 'protection' => 10800],
        5 => ['movement' => 2, 'storage' => 1, 'protection' => 10800],
        10 => ['movement' => 4, 'storage' => 1, 'protection' => 10800],
        20 => ['movement' => 8, 'storage' => 1, 'protection' => 10800],
        100 => ['movement' => 20, 'storage' => 50, 'protection' => 72 * 3600],
    ];
    foreach ($published as $speed => $expected) {
        speedMatrixAssertSame($expected['movement'], Profile::movementSpeedMultiplier($speed), "movement at x{$speed}");
        speedMatrixAssertSame($expected['storage'], Profile::storageMultiplier($speed), "storage at x{$speed}");
        speedMatrixAssertSame($expected['protection'], Profile::protectionTime($speed), "protection at x{$speed}");
    }
    foreach ([1, 2, 3, 5, 10, 20] as $speed) {
        speedMatrixAssertSame(1, Profile::trapMultiplier($speed), "trap capacity at x{$speed} (unscaled through x20)");
        speedMatrixAssertSame(1, Profile::crannyMultiplier($speed), "cranny at x{$speed} (unscaled through x20)");
    }

    // The vendor's "game versions and speed" table (OpenTravian), 2026-11-07: troop speed x1/x2/x2/x2/x4, OpenTravian Plus 7/3/3/3/3 days,
    // Natar attack delay in the grey area 24 h / 12 h / 8 h / 4:48 / 2:24, protection of nature in oases 5 / 3 / 3 / 2 / 1 days.
    // x4 is not published: it follows the neighbouring band. Pinned so any change to a band fails here.
    $vendorRows = [
        // speed => [troop speed, Plus seconds, grey-zone delay seconds, oasis protection days]
        1 => [1, 7 * 86400, 86400, 5],
        2 => [2, 3 * 86400, 43200, 3],
        3 => [2, 3 * 86400, 28800, 3],
        4 => [2, 3 * 86400, 21600, 2],
        5 => [2, 3 * 86400, 17280, 2],
        10 => [4, 3 * 86400, 8640, 1],
    ];
    foreach ($vendorRows as $speed => [$troops, $plus, $natarDelay, $oasisDays]) {
        speedMatrixAssertSame($troops, Profile::movementSpeedMultiplier($speed), "vendor troop speed at x{$speed}");
        speedMatrixAssertSame($natarDelay, (int)Profile::natarNewVillageTravelTime($speed), "vendor Natar grey-zone delay at x{$speed}");
        speedMatrixAssertSame($oasisDays * 86400, Profile::oasisInitialProtectionTime($speed), "oasis protection at x{$speed}");
        if (Profile::isOfficialLegendsSpeed($speed)) {
            speedMatrixAssertSame($plus, \Game\PremiumDuration::officialDefault($speed, 123), "OpenTravian Plus duration at x{$speed}");
        }
    }
    // Auction length: the vendor table says 24/12/8/4/2 hours at x1/x2/x3/x5/x10: floor(24/s) hours, never under one hour.
    foreach ([1 => 24, 2 => 12, 3 => 8, 4 => 6, 5 => 4, 10 => 2, 11 => 2, 12 => 2, 13 => 1, 20 => 1, 100 => 1, 1000 => 1] as $speed => $hours) {
        speedMatrixAssertSame($hours * 3600, Profile::auctionDuration($speed), "auction duration at x{$speed}");
    }
    // Starting culture points follow the vendor row 500/250/167/100/50 (x4 is the same 500/s rule, x20 and above are capped at the x8 value).
    foreach ([1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 10 => 10, 20 => 8, 100 => 8, 1000 => 8] as $speed => $effective) {
        speedMatrixAssertSame($effective, Profile::startingCulturePointSpeed($speed), "starting culture point speed at x{$speed}");
    }

    // Time unit of training queue timestamps: s up to 20x, ms above, ns above 20000x.
    speedMatrixAssertSame(1, Profile::trainingTimeUnit(20), 'time unit at x20');
    speedMatrixAssertSame(1000, Profile::trainingTimeUnit(21), 'time unit at x21');
    speedMatrixAssertSame(1000, Profile::trainingTimeUnit(20000), 'time unit at x20000');
    speedMatrixAssertSame(1_000_000_000, Profile::trainingTimeUnit(20001), 'time unit at x20001');

    // Approved smooth curves: published values at x1/x2/x3/x5/x10 are exact,
    // the others are pinned so any change to a curve fails here.
    $curves = [
        // speed => [hero health %/day (flat 10 through x10, then 10*sqrt(s/10)), revival divisor, spawn x, festival hours, starvation x]
        1 => [10, 1, 1.0, 72, 1.0],
        2 => [10, 1, 1.0, 72, 1.0],
        3 => [10, 2, 1.0, 72, 1.0],
        5 => [10, 2, 1.0, 72, 1.0],
        10 => [10, 4, 1.0, 72, 1.0],
        11 => [10.5, 4, 1.0, 72, 1.19],
        20 => [14.1, 7, 1.0, 72, 2.35],
        21 => [14.5, 8, 1.01, 70.3, 2.45],
        50 => [22.4, 12, 1.26, 45.5, 4.15],
        100 => [31.6, 16, 1.5, 32.2, 5.5],
        101 => [31.8, 16, 1.5, 32.0, 5.52],
        250 => [50.0, 25, 1.88, 20.4, 7.29],
        1000 => [100.0, 50, 2.0, 10.2, 10.0],
        10000 => [316.2, 100, 2.0, 3.2, 10.0],
        100000 => [1000.0, 100, 2.0, 1.0, 10.0],
        1000000 => [3162.3, 100, 2.0, 0.3, 10.0],
        10000000 => [10000.0, 100, 2.0, 0.1, 10.0],
        100000000 => [31622.8, 100, 2.0, 0.0833, 10.0],
        1000000000 => [100000.0, 100, 2.0, 0.0833, 10.0],
    ];
    foreach ($curves as $speed => [$health, $divisor, $spawn, $festivalHours, $starvation]) {
        $near = static fn (float $expected, float $actual, float $tolerance, string $what) => abs($expected - $actual) <= $tolerance
            ?: speedMatrixFail(sprintf('%s at x%d: expected about %s, got %s.', $what, $speed, $expected, $actual));
        $near($health, Profile::heroHealthPerDay($speed), 0.06, 'hero health');
        speedMatrixAssertSame($divisor, Profile::heroRevivalDivisor($speed), "hero revival divisor at x{$speed}");
        $near($spawn, Profile::adventureSpawnMultiplier($speed), 0.006, 'adventure spawn multiplier');
        $near($festivalHours, Profile::festivalDuration($speed) / 3600, 0.06, 'festival hours');
        $near($starvation, Profile::starvationCropMultiplier($speed), 0.006, 'starvation crop multiplier');
    }
    // Living-hero health regeneration multiplier: 1 through x20, then (s/20)^0.25.
    foreach ([1 => 1.0, 2 => 1.0, 3 => 1.0, 5 => 1.0, 10 => 1.0, 11 => 1.0, 20 => 1.0, 21 => 1.01, 50 => 1.26, 100 => 1.5, 101 => 1.5, 250 => 1.88, 1000 => 2.66, 1000000 => 14.95] as $speed => $multiplier) {
        speedMatrixAssertSame($multiplier, Profile::heroRegenerationMultiplier($speed), "hero regeneration multiplier at x{$speed}");
    }
    speedMatrixAssertSame(8, Profile::movementSpeedMultiplier(21), 'movement never dips below the x20 value of 8');
    speedMatrixAssertSame(8, Profile::movementSpeedMultiplier(40), 'movement at x40');

    // Direction of each metric across the speed ladder. 'up' never decreases,
    // 'down' never increases.
    $metrics = [
        'movement_speed_increase' => ['up', static fn (int $s) => Profile::movementSpeedMultiplier($s)],
        'storage_multiplier' => ['up', static fn (int $s) => Profile::storageMultiplier($s)],
        'cranny_multiplier' => ['up', static fn (int $s) => Profile::crannyMultiplier($s)],
        'trap_multiplier' => ['up', static fn (int $s) => Profile::trapMultiplier($s)],
        'cage_multiplier' => ['up', static fn (int $s) => Profile::cageMultiplier($s)],
        'heroRegenerationMultiplier' => ['up', static fn (int $s) => Profile::heroRegenerationMultiplier($s)],
        'oasisAnimalCap' => ['up', static fn (int $s) => Profile::oasisAnimalCap($s, Profile::movementSpeedMultiplier($s))],
        'oasisRespawnInterval' => ['down', static fn (int $s) => Profile::oasisRespawnInterval($s, Profile::movementSpeedMultiplier($s))],
        'dailyQuestInterval' => ['down', static fn (int $s) => Profile::dailyQuestInterval($s)],
        'auctionDuration' => ['down', static fn (int $s) => Profile::auctionDuration($s)],
        'trainingTimeUnit' => ['up', static fn (int $s) => Profile::trainingTimeUnit($s)],
        'hero_health_per_day' => ['up', static fn (int $s) => Profile::heroHealthPerDay($s)],
        'hero_revival_divisor' => ['up', static fn (int $s) => Profile::heroRevivalDivisor($s)],
        'adventure_spawn_multiplier' => ['up', static fn (int $s) => Profile::adventureSpawnMultiplier($s)],
        'festival_duration' => ['down', static fn (int $s) => Profile::festivalDuration($s)],
        'starvation_crop_multiplier' => ['up', static fn (int $s) => Profile::starvationCropMultiplier($s)],
        'protection_time' => ['up', static fn (int $s) => Profile::protectionTime($s)],
    ];
    // Named exemptions. 'above' stops direction checks past a speed; 'steps' lists
    // single transitions ("from>to") that are known and tracked.
    $exemptions = [
        'protection_time' => ['above' => 100, 'reason' => 'documented: protection peaks at 100x and shortens on extreme worlds (never under 3 h)'],
    ];

    foreach ($metrics as $name => [$direction, $compute]) {
        $limit = $exemptions[$name]['above'] ?? PHP_INT_MAX;
        $steps = $exemptions[$name]['steps'] ?? [];
        $previous = null;
        foreach ($speeds as $speed) {
            if ($speed > $limit) {
                break;
            }
            $value = $compute($speed);
            if ($previous !== null && !in_array($previous[0] . '>' . $speed, $steps, true)) {
                $broken = $direction === 'up' ? $value < $previous[1] : $value > $previous[1];
                if ($broken) {
                    speedMatrixFail(sprintf(
                        '%s must not %s from x%d (%s) to x%d (%s).',
                        $name,
                        $direction === 'up' ? 'decrease' : 'increase',
                        $previous[0],
                        (string)$previous[1],
                        $speed,
                        (string)$value
                    ));
                }
            }
            $previous = [$speed, $value];
        }
    }

    // profile() is the single source the installer and set-world-speed share.
    foreach ($speeds as $speed) {
        $profile = Profile::profile($speed);
        speedMatrixAssertSame(Profile::movementSpeedMultiplier($speed), $profile['movement_speed_increase'], "profile movement at x{$speed}");
        speedMatrixAssertSame(Profile::roundLengthDays($speed), $profile['round_length'], "profile round length at x{$speed}");
    }

    // One quest reward rate: the displayed reward and the granted (tutorial) reward share it.
    foreach ($speeds as $speed) {
        \Tests\Stub\SpeedMatrixConfigStub::$speed = $speed;
        speedMatrixAssertSame(
            (float)(100 * \Model\Quest::rewardRate('res', false)),
            (float)\Model\Quest::calcEffect(100, 'res'),
            "displayed resource reward at x{$speed}"
        );
    }
    \Tests\Stub\SpeedMatrixConfigStub::$speed = 1;

    echo "Speed matrix contract passed (" . count($speeds) . " speeds, " . count($metrics) . " metrics).\n";
}
