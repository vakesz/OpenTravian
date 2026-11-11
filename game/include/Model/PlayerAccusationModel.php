<?php

declare(strict_types=1);

namespace Model;

use Core\Database\DB;

/** Player reports to the operator: filed from a profile, reviewed in the admin panel. */
final class PlayerAccusationModel
{
    /** Reports one player can file per day (the reference shows "Available reports per day"). */
    public const MAX_PER_DAY = 2;
    public const MAX_MESSAGE_LENGTH = 300;

    /** Reason codes offered by the reference dialog, in its order. */
    public const REASONS = ['multiAccount', 'passwordSharing', 'botScript', 'other', 'inappropriateContent', 'privateFarm'];

    public function remainingToday(int $reporter): int
    {
        $filed = (int) DB::getInstance()->fetchScalar(
            'SELECT COUNT(id) FROM player_accusation WHERE reporter_uid=? AND time > ?',
            [$reporter, time() - 86400]
        );

        return max(0, self::MAX_PER_DAY - $filed);
    }

    /** Files a report; false when the reason is unknown or the reporter has none left today. */
    public function file(int $reporter, int $accused, string $reason, string $message): bool
    {
        if (!in_array($reason, self::REASONS, true) || $this->remainingToday($reporter) === 0) {
            return false;
        }
        $message = mb_substr(trim(strip_tags($message)), 0, self::MAX_MESSAGE_LENGTH);
        DB::getInstance()->run(
            'INSERT INTO player_accusation (reporter_uid, accused_uid, reason, message, time) VALUES (?, ?, ?, ?, ?)',
            [$reporter, $accused, $reason, $message, time()]
        );

        return true;
    }

    /**
     * Newest first, open reports before handled ones.
     *
     * @return list<array{id:int, reporterId:int, reporter:string, accusedId:int, accused:string, reason:string, message:string, time:int, handledTime:int}>
     */
    public function list(int $limit = 100): array
    {
        $result = DB::getInstance()->query(
            'SELECT a.id, a.reporter_uid, a.accused_uid, a.reason, a.message, a.time, a.handled_time,
                    COALESCE(r.name, \'\') AS reporter, COALESCE(c.name, \'\') AS accused
             FROM player_accusation a
             LEFT JOIN users r ON r.id = a.reporter_uid
             LEFT JOIN users c ON c.id = a.accused_uid
             ORDER BY (a.handled_time > 0), a.time DESC LIMIT ' . max(1, $limit)
        );
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                'id' => (int) $row['id'],
                'reporterId' => (int) $row['reporter_uid'],
                'reporter' => (string) $row['reporter'],
                'accusedId' => (int) $row['accused_uid'],
                'accused' => (string) $row['accused'],
                'reason' => (string) $row['reason'],
                'message' => (string) $row['message'],
                'time' => (int) $row['time'],
                'handledTime' => (int) $row['handled_time'],
            ];
        }

        return $rows;
    }

    public function markHandled(int $id): void
    {
        DB::getInstance()->run('UPDATE player_accusation SET handled_time=? WHERE id=? AND handled_time=0', [time(), $id]);
    }
}
