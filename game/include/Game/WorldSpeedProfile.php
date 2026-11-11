<?php

namespace Game;

/**
 * The gameplay values that vary with a world's advertised economic speed.
 *
 * A fast world is not a raw multiplication of every number in the game.  In
 * particular, map movement and recovery use deliberately bounded curves.  The
 * installer persists these values for new worlds; the runtime derives them for
 * older connection files that predate the profile.
 */
final class WorldSpeedProfile
{
    private function __construct()
    {
    }

    private static function speed(int $speed): int
    {
        return max(1, $speed);
    }

    /**
     * Official Legends speed tables use these five speeds. Mechanics that have
     * a separate local x20 table must opt in through isPublishedSpeed().
     */
    public static function isOfficialLegendsSpeed(int $speed): bool
    {
        return in_array($speed, [1, 2, 3, 5, 10], true);
    }

    /**
     * The local quest/reward policy also has a published x20 band.
     */
    public static function isPublishedSpeed(int $speed): bool
    {
        return self::isOfficialLegendsSpeed($speed) || $speed === 20;
    }

    /**
     * Starting Culture Points have published values only through x10. Custom
     * worlds retain the established local progression cap; x20 is published
     * for quest/reward tables only and must not change expansion progress.
     */
    public static function startingCulturePointSpeed(int $speed): int
    {
        $speed = self::speed($speed);
        return self::isOfficialLegendsSpeed($speed) ? $speed : min($speed, 8);
    }

    /**
     * Moderated troop/hero map movement vs economic speed.
     * Above 250× the historical high-speed band is floored at the 250× value so
     * raising world speed never reduces movement (product decision 2026-11-04).
     */
    public static function movementSpeedMultiplier(int $speed): int
    {
        $speed = self::speed($speed);
        if ($speed === 1) {
            return 1;
        }
        if ($speed <= 5) {
            return 2;
        }
        if ($speed <= 10) {
            return 4;
        }
        if ($speed <= 20) {
            return 8;
        }
        if ($speed <= 250) {
            return max(8, (int)round($speed / 5));
        }

        $continued = min(max((int)round($speed * 8 / 100 / 5) * 5, 1), 3000);
        $floorAt250 = max((int)round(250 / 5), 1);

        return max($continued, $floorAt250);
    }

    /**
     * Official OpenTravian gameworlds do not share a single linear endgame
     * schedule. These published x1/x2/x3/x5/x10 milestones are kept together
     * so the timer configuration cannot accidentally revert to legacy 100/200
     * day releases. The Natar Wonder starts when plans arrive and finishes at
     * the listed world age.
     *
     * @return array{registrationCloseAfter: int, artifactsReleaseAfter: int, plansReleaseAfter: int, natarWwFinishAfter: int}|null
     */
    public static function officialRoundTimeline(int $speed): ?array
    {
        $day = 24 * 3600;
        if ($speed === 1) {
            return [
                'registrationCloseAfter' => 70 * $day,
                'artifactsReleaseAfter' => 90 * $day,
                'plansReleaseAfter' => 180 * $day,
                'natarWwFinishAfter' => 250 * $day,
            ];
        }
        if ($speed === 2) {
            return [
                'registrationCloseAfter' => 35 * $day,
                'artifactsReleaseAfter' => 45 * $day,
                'plansReleaseAfter' => 90 * $day,
                'natarWwFinishAfter' => 160 * $day,
            ];
        }
        if ($speed === 3) {
            return [
                'registrationCloseAfter' => 28 * $day,
                'artifactsReleaseAfter' => 30 * $day,
                'plansReleaseAfter' => 60 * $day,
                'natarWwFinishAfter' => 95 * $day,
            ];
        }
        if ($speed === 5) {
            return [
                'registrationCloseAfter' => 15 * $day,
                'artifactsReleaseAfter' => 18 * $day,
                'plansReleaseAfter' => 36 * $day,
                'natarWwFinishAfter' => 71 * $day,
            ];
        }
        if ($speed === 10) {
            return [
                'registrationCloseAfter' => 7 * $day,
                'artifactsReleaseAfter' => 9 * $day,
                'plansReleaseAfter' => 18 * $day,
                'natarWwFinishAfter' => 35 * $day + 12 * 3600,
            ];
        }

        return null;
    }

    /**
     * The round length persisted for server discovery and administration.
     *
     * Official speeds use the published World Wonder finish day. Custom
     * high-speed worlds retain the established proportional fallback.
     */
    public static function roundLengthDays(int $speed): int
    {
        $speed = self::speed($speed);
        $timeline = self::officialRoundTimeline($speed);
        if ($timeline !== null) {
            return max(1, (int)ceil($timeline['natarWwFinishAfter'] / 86400));
        }

        return max(1, (int)ceil(350 / $speed));
    }

    public static function storageMultiplier(int $speed): int
    {
        $speed = self::speed($speed);
        if ($speed <= 20) {
            return 1;
        }
        if ($speed <= 10000) {
            return max((int)ceil($speed / 2), 1);
        }

        // Above the normal high-speed bands, production rises much faster
        // than a /5 storage curve can absorb it. Blend from the established
        // 0.5x capacity ratio at 10,000x to full economic-speed capacity at
        // 1,000,000x, keeping the curve continuous and giving an extreme
        // local world enough time to use its output before it caps.
        $ratio = min(1.0, 0.5 + log10($speed / 10000) / 4);
        return max((int)ceil($speed * $ratio), 1);
    }

    public static function cageMultiplier(int $speed): int
    {
        return self::speed($speed) <= 20 ? 1 : max((int)ceil($speed / 4), 1);
    }

    public static function trapMultiplier(int $speed): int
    {
        return self::cageMultiplier($speed);
    }

    public static function crannyMultiplier(int $speed): int
    {
        return self::speed($speed) <= 20 ? 1 : max((int)ceil($speed / 8), 1);
    }

    public static function dailyQuestInterval(int $speed): int
    {
        // Keep the published daily cycle on official speed bands. Custom
        // high-speed worlds use a shorter cycle so daily tasks remain useful
        // when the rest of the game advances much faster.
        $speed = self::speed($speed);
        if ($speed <= 20) {
            return 24 * 3600;
        }

        return $speed >= 5000 ? 6 * 3600 : 12 * 3600;
    }

    public static function protectionTime(int $speed): int
    {
        $speed = self::speed($speed);
        // Product decision 2026-11-04: never shorter than 3 hours, including
        // extreme configured speeds (1_000_000×).
        $minimum = 3 * 3600;
        if ($speed <= 20) {
            return $minimum;
        }
        if ($speed <= 100) {
            return 72 * 3600;
        }
        if ($speed <= 500) {
            return 24 * 3600;
        }
        if ($speed <= 1000) {
            return 16 * 3600;
        }
        if ($speed <= 2000) {
            return 8 * 3600;
        }

        return $minimum;
    }

    /**
     * Nature troops use the published oasis-protection windows at the
     * official world speeds. Keeping this separate from player protection in
     * the connection file lets an operator tune either policy without making
     * oasis respawns implicit.
     */
    public static function oasisInitialProtectionTime(int $speed): int
    {
        $speed = self::speed($speed);
        if ($speed === 1) {
            return 5 * 24 * 3600;
        }
        if ($speed <= 3) {
            return 3 * 24 * 3600;
        }
        if ($speed <= 5) {
            return 2 * 24 * 3600;
        }
        if ($speed <= 10) {
            return 24 * 3600;
        }

        return self::protectionTime($speed);
    }

    /**
     * Multiplier on a living hero's health regeneration: 1 through x20, then
     * (s/20)^0.25 (two decimals, capped at 100), continuous and monotone so the
     * regeneration rate never jumps at the x20/x21 boundary.
     */
    public static function heroRegenerationMultiplier(int $speed): float
    {
        $speed = self::speed($speed);

        return $speed <= 20 ? 1.0 : round(min(100.0, ($speed / 20) ** 0.25), 2);
    }

    /**
     * Neutral oases remain bounded. The 100x local profile reaches 75 animals,
     * rather than the old fixed cap of 15 or an unbounded speed-derived value.
     */
    public static function oasisAnimalCap(int $speed, float $movementSpeed): int
    {
        if (self::speed($speed) <= 20) {
            return 15;
        }

        return min(1100, max(15, 15 * (int)ceil(sqrt(max(1.0, $movementSpeed)))));
    }

    /**
     * Oasis population is a separate nature cadence, with a 15-minute floor
     * on faster worlds so it does not become a busy-loop source of troops.
     */
    public static function oasisRespawnInterval(int $speed, float $movementSpeed): int
    {
        if (self::speed($speed) <= 20) {
            return 2 * 3600;
        }

        return max(15 * 60, (int)floor((2 * 3600) / max(1.0, $movementSpeed)));
    }

    /**
     * New-village Natar raids are local high-speed-world policy. The waves
     * grow with economic speed; instant-finish worlds use the established
     * stronger curve.
     */
    public static function natarNewVillageStrengthMultiplier(int $speed, bool $instantFinish): int
    {
        $speed = self::speed($speed);
        if ($speed <= 10) {
            return 1;
        }

        return (int)ceil($speed / ($instantFinish ? 30 : 50));
    }

    /**
     * New-village Natar attacks must never arrive sooner than ten minutes.
     */
    public static function natarNewVillageTravelTime(int $speed): float
    {
        return max(24 * 3600 / self::speed($speed), 10 * 60);
    }

    /**
     * Every speed-derived value persisted in the connection file, as the
     * installer and tools/set-world-speed.php write it.
     *
     * @return array<string,int|float>
     */
    public static function profile(int $speed): array
    {
        $movement = self::movementSpeedMultiplier($speed);

        return [
            'movement_speed_increase' => $movement,
            'storage_multiplier' => self::storageMultiplier($speed),
            'cranny_multiplier' => self::crannyMultiplier($speed),
            'trap_multiplier' => self::trapMultiplier($speed),
            'cage_multiplier' => self::cageMultiplier($speed),
            'protection_time' => self::protectionTime($speed),
            'oasisInitialProtectionTime' => self::oasisInitialProtectionTime($speed),
            'dailyQuestInterval' => self::dailyQuestInterval($speed),
            'heroRegenerationMultiplier' => self::heroRegenerationMultiplier($speed),
            'oasisAnimalCap' => self::oasisAnimalCap($speed, $movement),
            'oasisRespawnInterval' => self::oasisRespawnInterval($speed, $movement),
            'round_length' => self::roundLengthDays($speed),
        ];
    }

    /** Ticks per second of the training-queue timestamps persisted at this speed. */
    public static function trainingTimeUnit(int $speed): int
    {
        $speed = self::speed($speed);
        if ($speed > 20000) {
            return 1_000_000_000;
        }

        return $speed > 20 ? 1_000 : 1;
    }

    /** Multiplier on the x20 adventure spawn rate: 1 up to x20, then (s/20)^0.25 capped at 2. */
    public static function adventureSpawnMultiplier(int $speed): float
    {
        $speed = self::speed($speed);

        return $speed <= 20 ? 1.0 : min(2.0, ($speed / 20) ** 0.25);
    }

    /** Basic hero health regeneration in percent per day: a flat 10 through x10 (vendor article, reference at x2), then 10*sqrt(s/10), continuous at x10. */
    public static function heroHealthPerDay(int $speed): float
    {
        $speed = self::speed($speed);

        return $speed <= 10 ? 10.0 : 10 * sqrt($speed / 10);
    }

    /** Divisor of the hero revival time: floor(s/3)+1 through x20, then ceil(7*sqrt(s/20)) capped at 100. */
    public static function heroRevivalDivisor(int $speed): int
    {
        $speed = self::speed($speed);

        return $speed <= 20 ? intdiv($speed, 3) + 1 : min(100, (int)ceil(7 * sqrt($speed / 20)));
    }

    /** Festival duration in seconds: 72 h through x20, then 72 h*sqrt(20/s) with a 5 minute floor. */
    public static function festivalDuration(int $speed): int
    {
        $speed = self::speed($speed);
        if ($speed <= 20) {
            return 72 * 3600;
        }

        return (int)max(ceil(72 * 3600 * sqrt(20 / $speed)), 300);
    }

    /** Starvation crop multiplier: 1 through x10, then 1+4.5*log10(s/10) capped at 10. */
    public static function starvationCropMultiplier(int $speed): float
    {
        $speed = self::speed($speed);

        return $speed <= 10 ? 1.0 : min(10.0, 1 + 4.5 * log10($speed / 10));
    }

    /** Duration in seconds of a new auction: the vendor's 24 h / 12 h / 8 h / 4 h / 2 h at x1/x2/x3/x5/x10, i.e. floor(24/s) hours, never under one hour. */
    public static function auctionDuration(int $speed): int
    {
        return max(1, intdiv(24, self::speed($speed))) * 3600;
    }
}
