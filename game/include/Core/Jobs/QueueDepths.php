<?php

declare(strict_types=1);

namespace Core\Jobs;

use Core\Database\DB;
use Core\Database\GlobalDB;

/**
 * How much work is waiting in the engine's queues, for the control panel's Engine health page and
 * the metrics endpoint. Counts only: no row content leaves this class.
 */
final class QueueDepths
{
    /** A queued job counts as overdue once it is this many seconds past its due time. */
    public const OVERDUE_GRACE = 60;

    private function __construct()
    {
    }

    /**
     * @return array{construction: int|null, research: int|null, movement: int|null, trade: int|null, quarantined: int|null, mail: int|null} null when a count could not be read
     */
    public static function read(int $now): array
    {
        $due = $now - self::OVERDUE_GRACE;
        $world = DB::getInstance();

        return [
            'construction' => self::count($world, "SELECT COUNT(*) FROM building_upgrade WHERE commence < {$due}"),
            'research' => self::count($world, "SELECT COUNT(*) FROM research WHERE end_time < {$due}"),
            'movement' => self::count($world, "SELECT COUNT(*) FROM movement WHERE end_time < {$due}"),
            'trade' => self::count($world, "SELECT COUNT(*) FROM send WHERE end_time < {$due}"),
            'quarantined' => self::count($world, 'SELECT COUNT(*) FROM scheduled_task_failures WHERE quarantined_at IS NOT NULL'),
            'mail' => self::count(GlobalDB::getInstance(), "SELECT COUNT(*) FROM mailServer WHERE status = 'queued'"),
        ];
    }

    private static function count(DB $db, string $sql): ?int
    {
        try {
            $result = $db->query($sql);
            $row = $result ? $result->fetch_row() : null;

            return $row ? (int) $row[0] : null;
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
