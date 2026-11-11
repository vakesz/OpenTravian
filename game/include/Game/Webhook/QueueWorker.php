<?php

declare(strict_types=1);

namespace Game\Webhook;

use Core\Database\DB;

/**
 * Posts queued events to Discord, at most one per engine tick (the job runs every five seconds, so at most twelve
 * posts a minute), isolated from the other jobs by a three second request limit. A leased row cannot be sent
 * twice by concurrent workers; Discord's 429 answers are waited out without counting as a failure; other failures
 * back off and the row is dropped after eight attempts. Sent rows stay as the record that an event was announced.
 */
final class QueueWorker
{
    private const LEASE_SECONDS = 30;
    private const MAX_ATTEMPTS = 8;
    private const RETRY_BASE_SECONDS = 30;
    private const RETRY_MAX_SECONDS = 3600;

    public function __construct(
        private readonly ?WebhookConfig $config = null,
        private readonly ?WebhookSender $sender = null,
        private readonly ?DB $db = null,
    ) {
    }

    /** @return int how many messages were delivered (0 or 1) */
    public function drain(): int
    {
        $config = $this->config ?? WebhookConfig::fromEnvironment();
        $url = $config->url();
        if (!$config->enabled() || $url === null) {
            return 0;
        }
        $db = $this->db ?? DB::getInstance();
        (new WorldEvents($config, $db))->pollMilestones();
        $now = time();
        $statement = $db->run(
            'SELECT id, payload, attempts FROM webhook_queue WHERE sent_at=0 AND dropped=0 AND next_attempt_at<=? AND lease_until<? ORDER BY id LIMIT 1',
            [$now, $now]
        );
        $row = $statement === false ? null : $statement->get_result()->fetch_assoc();
        if (!is_array($row)) {
            return 0;
        }
        $leased = $db->run('UPDATE webhook_queue SET lease_until=? WHERE id=? AND lease_until<?', [$now + self::LEASE_SECONDS, $row['id'], $now]);
        if ($leased === false || $leased->affected_rows !== 1) {
            return 0;
        }
        $result = ($this->sender ?? new DiscordSender())->send($url, (string) $row['payload']);
        $id = (int) $row['id'];
        if ($result['status'] >= 200 && $result['status'] < 300) {
            $db->run('UPDATE webhook_queue SET sent_at=?, lease_until=0 WHERE id=?', [time(), $id]);

            return 1;
        }
        if ($result['status'] === 429) {
            $wait = min(600, max(1, $result['retryAfter']));
            $db->run('UPDATE webhook_queue SET next_attempt_at=?, lease_until=0 WHERE id=?', [time() + $wait, $id]);

            return 0;
        }
        $attempts = (int) $row['attempts'] + 1;
        if ($attempts >= self::MAX_ATTEMPTS) {
            $db->run('UPDATE webhook_queue SET attempts=?, dropped=1, lease_until=0 WHERE id=?', [$attempts, $id]);
        } else {
            $backoff = min(self::RETRY_MAX_SECONDS, self::RETRY_BASE_SECONDS * (2 ** ($attempts - 1)));
            $db->run('UPDATE webhook_queue SET attempts=?, next_attempt_at=?, lease_until=0 WHERE id=?', [$attempts, time() + $backoff, $id]);
        }

        return 0;
    }
}
