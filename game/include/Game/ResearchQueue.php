<?php

declare(strict_types=1);

namespace Game;

use Core\Database\DB;

/**
 * The Smithy and Academy research queues of one village: one research at a time, or two while Travian Plus is
 * active, the second starting when the one before it finishes. SmithyCtrl and AcademyCtrl call queueFull and
 * queuedEndTime inside the village transaction, so the queue cannot change between the check and the insert.
 */
final class ResearchQueue
{
    public const CAPACITY = 1;
    public const PLUS_CAPACITY = 2;

    private function __construct()
    {
    }

    public static function capacity(bool $plus): int
    {
        return $plus ? self::PLUS_CAPACITY : self::CAPACITY;
    }

    /** Whether the village's queue for that ResearchMode is full. */
    public static function queueFull(int $kid, int $mode, bool $plus): bool
    {
        $count = (int)DB::getInstance()->fetchScalar('SELECT COUNT(*) FROM research WHERE kid=? AND mode=?', [$kid, $mode]);

        return $count >= self::capacity($plus);
    }

    /** When a research queued now finishes: after the last queued one of the same mode, or from now with none. */
    public static function queuedEndTime(int $kid, int $mode, int $duration): int
    {
        $last = (int)DB::getInstance()->fetchScalar('SELECT COALESCE(MAX(end_time), 0) FROM research WHERE kid=? AND mode=?', [$kid, $mode]);

        return max(time(), $last) + $duration;
    }
}
