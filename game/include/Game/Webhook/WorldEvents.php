<?php

declare(strict_types=1);

namespace Game\Webhook;

use Core\Config;
use Core\Database\DB;

/**
 * Records world events for the Discord webhook. record() only writes a row to webhook_queue (the engine's
 * webhookDelivery job posts it later), does nothing while the webhook is off or the event is not ticked, and
 * keeps one row per event subject, so an event is announced at most once even if the code that raises it runs twice.
 */
final class WorldEvents
{
    /** Pending rows beyond this are dropped: a burst can never grow the queue without bound. */
    public const MAX_PENDING = 100;

    public function __construct(private readonly WebhookConfig $config, private readonly DB $db)
    {
    }

    public static function default(): self
    {
        return new self(WebhookConfig::fromEnvironment(), DB::getInstance());
    }

    /** Static shortcut for the game code that raises an event. */
    public static function record(string $event, ?string $subject = null, array $facts = []): void
    {
        try {
            self::default()->enqueue($event, $subject, $facts);
        } catch (\Throwable) {
            // An announcement must never break the game action that raised it.
        }
    }

    /** Static shortcut for the end of the round (see announceFinish()). */
    public static function finished(int $resultCode): void
    {
        try {
            self::default()->announceFinish($resultCode);
        } catch (\Throwable) {
            // An announcement must never break the game action that raised it.
        }
    }

    /** @param array{level?:int,alliance?:array{name:string,tag:string}|null} $facts */
    public function enqueue(string $event, ?string $subject = null, array $facts = [], bool $force = false): bool
    {
        if (!$force && !$this->config->eventEnabled($event)) {
            return false;
        }
        if ($this->pending() >= self::MAX_PENDING) {
            return false;
        }
        $facts['world'] = (string) Config::getProperty('settings', 'serverName');
        $payload = json_encode(WebhookMessage::build($event, $facts), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return false;
        }
        $key = substr($event . ($subject === null ? '' : ':' . $subject), 0, 64);
        $statement = $this->db->run(
            'INSERT IGNORE INTO webhook_queue (dedupe_key, payload, created_at) VALUES (?, ?, ?)',
            [$key, $payload, time()]
        );

        return $statement !== false && $statement->affected_rows === 1;
    }

    /** The admin "send a test message" button: queued like any event, whichever events are ticked. */
    public function enqueueTest(): bool
    {
        return $this->config->enabled() && $this->enqueue('test', (string) time(), [], true);
    }

    /** Announces every configured World Wonder level that some World Wonder has reached. */
    public function pollMilestones(): void
    {
        if (!$this->config->eventEnabled('wwMilestone')) {
            return;
        }
        $top = (int) $this->db->fetchScalar('SELECT MAX(f99) FROM fdata');
        foreach ($this->config->milestones() as $level) {
            if ($top >= $level) {
                $this->enqueue('wwMilestone', (string) $level, ['level' => $level]);
            }
        }
    }

    /** The round winner: the alliance of the World Wonder's owner, by its public name and tag only. */
    public function announceFinish(int $resultCode): void
    {
        $this->enqueue('worldEnd');
        if ($resultCode !== 1) {
            return;
        }
        $alliance = null;
        $kid = (int) $this->db->fetchScalar('SELECT kid FROM fdata WHERE f99=100 LIMIT 1');
        $statement = $kid > 0 ? $this->db->run(
            'SELECT a.name, a.tag FROM vdata v JOIN users u ON u.id=v.owner JOIN alidata a ON a.id=u.aid WHERE v.kid=?',
            [$kid]
        ) : false;
        $row = $statement === false ? null : $statement->get_result()->fetch_assoc();
        if (is_array($row)) {
            $alliance = ['name' => (string) $row['name'], 'tag' => (string) $row['tag']];
        }
        $this->enqueue('roundWinner', null, ['alliance' => $alliance]);
    }

    private function pending(): int
    {
        return (int) $this->db->fetchScalar('SELECT COUNT(*) FROM webhook_queue WHERE sent_at=0 AND dropped=0');
    }
}
