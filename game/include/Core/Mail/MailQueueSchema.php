<?php

declare(strict_types=1);

namespace Core\Mail;

use Core\Database\DB;

/** Account-database schema guard for the mail queue. */
final class MailQueueSchema
{
    /** @var list<string> */
    private const REQUIRED_COLUMNS = [
        'replyTo',
        'unsubscribeUrl',
        'status',
        'attempts',
        'availableAt',
        'lockedAt',
        'leaseToken',
        'lastError',
    ];

    private function __construct()
    {
    }

    public static function isReady(DB $db): bool
    {
        if (!self::tableExists($db)) {
            return false;
        }
        $placeholders = implode(',', array_fill(0, count(self::REQUIRED_COLUMNS), '?'));
        $count = $db->fetchScalar(
            "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='mailServer' AND column_name IN ({$placeholders})",
            self::REQUIRED_COLUMNS,
            0
        );

        return (int) $count === count(self::REQUIRED_COLUMNS) && self::indexExists($db, 'mailQueueClaim');
    }

    private static function tableExists(DB $db): bool
    {
        return (int) $db->fetchScalar(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='mailServer'",
            [],
            0
        ) === 1;
    }

    private static function indexExists(DB $db, string $index): bool
    {
        return (int) $db->fetchScalar(
            "SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='mailServer' AND index_name=?",
            [$index],
            0
        ) > 0;
    }
}
