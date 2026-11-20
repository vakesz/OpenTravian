<?php

declare(strict_types=1);

namespace Core;

/**
 * Time units in seconds, plus the millisecond factors that movement end_time, miliseconds()
 * and the resource accrual (production per hour over elapsed milliseconds) use.
 * Composite durations (7200, 43200, ...) stay literal on purpose.
 */
final class Time
{
    /** Milliseconds per second: `$seconds * Time::SECOND_MS`, `$ms / Time::SECOND_MS`. */
    public const SECOND_MS = 1000;
    public const MINUTE = 60;
    public const HOUR = 3600;
    public const DAY = 86400;
    public const WEEK = 604800;
    /** Milliseconds per hour: `$elapsedMs * $perHour / Time::HOUR_MS`. */
    public const HOUR_MS = 3600000;
}
