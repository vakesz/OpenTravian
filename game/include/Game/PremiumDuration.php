<?php

declare(strict_types=1);

namespace Game;

use InvalidArgumentException;

final class PremiumDuration
{
    /** User rule 2026-11-08: every timed bonus (Plus, +25% production, quest rewards) lasts at least one day. */
    public const int MIN_SECONDS = 86400;
    private const int DAY_SECONDS = self::MIN_SECONDS;
    private const int MAX_HOURS = 8760;

    /**
     * OpenTravian Plus: seven days at x1 (the vendor's table), three days from x2 through x10 (x2
     * shows "Bonus duration in days: 3" and the vendor table lists 3 days at x2, x3, x5 and x10, so the
     * unpublished speeds in between follow it), and above x10 the three days shrink in proportion
     * (3 days x 10 / speed, never under one day: one day from x30 up, also at x100).
     */
    public static function plusDefault(int $speed): int
    {
        if ($speed < 1) {
            throw new InvalidArgumentException('World speed must be at least 1.');
        }
        if ($speed === 1) {
            return 7 * self::DAY_SECONDS;
        }
        if ($speed <= 10) {
            return 3 * self::DAY_SECONDS;
        }

        return max(self::MIN_SECONDS, (int)round(3 * self::DAY_SECONDS * 10 / $speed));
    }

    /** A bonus never lasts less than one day, whatever its source (admin override, local default, reward). */
    public static function atLeastOneDay(int|float $seconds): int
    {
        return max(self::MIN_SECONDS, (int)ceil($seconds));
    }

    public static function configuredPlusDuration(int $speed, int $overrideSeconds): int
    {
        return $overrideSeconds > 0 ? self::atLeastOneDay($overrideSeconds) : self::plusDefault($speed);
    }

    public static function officialDefault(int $speed, int $localDefaultSeconds): int
    {
        if ($speed < 1) {
            throw new InvalidArgumentException('World speed must be at least 1.');
        }

        if ($speed === 1) {
            return 7 * self::DAY_SECONDS;
        }

        return WorldSpeedProfile::isOfficialLegendsSpeed($speed)
            ? 3 * self::DAY_SECONDS
            : self::atLeastOneDay($localDefaultSeconds);
    }

    public static function configuredDuration(int $speed, int $overrideSeconds, int $localDefaultSeconds): int
    {
        return $overrideSeconds > 0
            ? self::atLeastOneDay($overrideSeconds)
            : self::officialDefault($speed, $localDefaultSeconds);
    }

    public static function dailyQuestRewardSeconds(): int
    {
        return self::DAY_SECONDS;
    }

    public static function hoursToSeconds(string $hours): ?int
    {
        $validated = filter_var($hours, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => self::MAX_HOURS],
        ]);

        return $validated === false ? null : $validated * 3600;
    }
}
