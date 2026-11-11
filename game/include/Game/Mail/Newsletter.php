<?php

declare(strict_types=1);

namespace Game\Mail;

use Core\Caching\Caching;
use Core\Database\DBI;

/** The administrator's newsletter: only players who opted in (users.newsletter), one personalised mail each. */
final class Newsletter
{
    /** One campaign per hour, at most three per day. */
    private const CAMPAIGNS_PER_HOUR = 1;
    private const CAMPAIGNS_PER_DAY = 3;

    private function __construct()
    {
    }

    /**
     * Players with the newsletter on, a verified address, not banned and not in deletion, one per address.
     *
     * @return list<array{uid: int, name: string, email: string}>
     */
    public static function recipients(): array
    {
        $rows = DBI::getInstance()->run(
            "SELECT MIN(u.id) AS uid, MIN(u.name) AS name, MIN(u.email) AS email FROM users u"
            . " WHERE u.id > 2 AND u.newsletter = 1 AND u.email_verified = 1 AND u.email <> '' AND u.access > 0"
            . ' AND NOT EXISTS (SELECT 1 FROM deleting d WHERE d.uid = u.id) GROUP BY LOWER(u.email) ORDER BY uid'
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): array => ['uid' => (int) $row['uid'], 'name' => (string) $row['name'], 'email' => (string) $row['email']], $rows);
    }

    /** Takes one campaign from the world's hourly and daily allowance; false when it is used up. */
    public static function campaignAllowed(): bool
    {
        try {
            $cache = Caching::getInstance();

            return $cache->incrementWithinLimit(throttle_key('newsletter-campaign', 'world', 'hour'), self::CAMPAIGNS_PER_HOUR, 3600)
                && $cache->incrementWithinLimit(throttle_key('newsletter-campaign', 'world', 'day'), self::CAMPAIGNS_PER_DAY, 86400);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Queues the mail for each recipient.
     *
     * @param list<array{uid: int, name: string, email: string}> $recipients
     * @return array{queued: int, skipped: int} skipped: no verified address any more, or already mailed within the rate limit
     */
    public static function queueTo(array $recipients, string $subject, string $bodyHtml): array
    {
        $queued = 0;
        foreach ($recipients as $recipient) {
            $queued += MailService::queue($recipient['uid'], EmailKind::Newsletter, $subject, $bodyHtml) ? 1 : 0;
        }

        return ['queued' => $queued, 'skipped' => count($recipients) - $queued];
    }
}
