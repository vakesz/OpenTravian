<?php

declare(strict_types=1);

namespace Core\Helper;

/**
 * Resolves the night/dark mood theme.
 *
 * Priority:
 * 1. An explicit system color preference (`prefers-color-scheme`) → matching
 *    night or default theme
 * 2. Guest pages use local night hours (22:00–06:00), matching the public
 *    reference shell, because a guest has no display preference.
 * 3. Authenticated pages use local night hours only when the player opted in.
 * 4. Else → default
 *
 * The browser applies (1) immediately via a small layout script so the
 * shell tracks OS theme changes without a reload.
 */
final class NightMood
{
    private const DAY_START_HOUR = 6;
    private const DAY_END_HOUR = 22;

    public static function theme(
        bool $enabled,
        ?bool $prefersDark = null,
        ?int $hour = null,
    ): string
    {
        if ($prefersDark !== null) {
            return $prefersDark ? 'night' : 'default';
        }

        // Server-side render has no OS preference; use the page's time policy.
        return $enabled ? self::timeBasedTheme($hour) : 'default';
    }

    public static function themeForPage(
        bool $hasAuthenticatedSession,
        bool $nightMoodOptIn,
        ?bool $prefersDark = null,
        ?int $hour = null,
    ): string
    {
        return self::theme(
            !$hasAuthenticatedSession || $nightMoodOptIn,
            $prefersDark,
            $hour,
        );
    }

    public static function timeBasedTheme(?int $hour = null): string
    {
        $hour ??= (int) TimezoneHelper::date('G');

        return $hour < self::DAY_START_HOUR || $hour >= self::DAY_END_HOUR
            ? 'night'
            : 'default';
    }
}
