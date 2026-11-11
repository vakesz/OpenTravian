<?php

declare(strict_types=1);

namespace Game\Metrics;

use Core\Caching\Caching;
use Core\Database\DB;
use Core\Jobs\EngineHeartbeat;
use Core\Jobs\QueueDepths;

/**
 * The Prometheus text exposition of the engine and the world: heartbeat, queues, mail, players,
 * backups, slow queries and request counts. Only aggregate numbers and labels from a fixed
 * vocabulary: no player, village or alliance names or ids, no addresses, no query text, no
 * configuration values. render() is pure; collect() reads the sources.
 */
final class Metrics
{
    /** Players count as online when they were active this many seconds ago (the admin panel's 10 minutes). */
    public const ONLINE_SECONDS = 600;
    public const REQUEST_COUNTER_KEY = 'metrics:requests';

    private const REQUEST_CLASSES = ['1xx', '2xx', '3xx', '4xx', '5xx'];

    private function __construct()
    {
    }

    /**
     * @return array<string, mixed>
     */
    public static function collect(int $now): array
    {
        $beat = EngineHeartbeat::read();
        $round = EngineHeartbeat::lastRound();
        $world = DB::getInstance();
        $variables = [];
        $requests = [];
        try {
            $requests = Caching::singleton()->hGetAll(self::REQUEST_COUNTER_KEY) ?: [];
        } catch (\Throwable $exception) {
        }
        $lastBackup = self::scalar($world, 'SELECT lastBackup FROM config');
        $slow = $world->query("SHOW GLOBAL STATUS LIKE 'Slow_queries'");
        $slowRow = $slow ? $slow->fetch_row() : null;
        unset($variables);

        return [
            'engineUp' => EngineHeartbeat::describe($beat, $now)['state'] === 'running',
            'heartbeatAge' => $beat !== null && isset($beat['ts']) ? max(0, $now - (int) $beat['ts']) : null,
            'engineStarted' => $beat !== null && isset($beat['started_at']) ? (int) $beat['started_at'] : null,
            'roundEnd' => $round['ended_at'] ?? null,
            'roundDuration' => isset($round['duration_ms']) ? $round['duration_ms'] / 1000 : null,
            'queues' => QueueDepths::read($now),
            'playersOnline' => self::scalar($world, 'SELECT COUNT(*) FROM users WHERE last_login_time > ' . ($now - self::ONLINE_SECONDS)),
            'playersTotal' => self::scalar($world, 'SELECT COUNT(*) FROM users'),
            'backupAge' => $lastBackup !== null && $lastBackup > 0 ? max(0, $now - $lastBackup) : null,
            'slowQueries' => $slowRow ? (int) $slowRow[1] : null,
            'requests' => array_map('intval', array_intersect_key($requests, array_flip(self::REQUEST_CLASSES))),
        ];
    }

    /**
     * @param array<string, mixed> $facts as returned by collect(); a null value leaves that sample out
     */
    public static function render(array $facts): string
    {
        $families = [];
        $add = static function (string $name, string $type, string $help, array $samples) use (&$families): void {
            $samples = array_filter($samples, static fn (array $sample): bool => $sample[1] !== null);
            if ($samples !== []) {
                $families[] = [$name, $type, $help, $samples];
            }
        };
        $one = static fn (mixed $value): array => [[[], $value]];

        $add('travian_engine_up', 'gauge', '1 when the engine heartbeat is fresh, 0 when it is stalled or missing.', $one(isset($facts['engineUp']) ? (int) $facts['engineUp'] : null));
        $add('travian_engine_heartbeat_age_seconds', 'gauge', 'Seconds since the engine last wrote its heartbeat.', $one($facts['heartbeatAge'] ?? null));
        $add('travian_engine_started_timestamp_seconds', 'gauge', 'When the running engine process started (unix time).', $one($facts['engineStarted'] ?? null));
        $add('travian_engine_last_round_end_timestamp_seconds', 'gauge', 'When an automation worker last finished a job run (unix time).', $one($facts['roundEnd'] ?? null));
        $add('travian_engine_last_round_duration_seconds', 'gauge', 'How long that job run took.', $one($facts['roundDuration'] ?? null));

        $queues = [];
        foreach (['construction', 'research', 'movement', 'trade'] as $queue) {
            $queues[] = [['queue' => $queue], $facts['queues'][$queue] ?? null];
        }
        $add('travian_queue_overdue', 'gauge', 'Queued jobs more than 60 seconds past their due time.', $queues);
        $add('travian_quarantined_tasks', 'gauge', 'Engine tasks that failed repeatedly and are quarantined.', $one($facts['queues']['quarantined'] ?? null));
        $add('travian_mail_queue', 'gauge', 'Mails waiting to be sent.', [[['status' => 'queued'], $facts['queues']['mail'] ?? null]]);
        $add('travian_players_online', 'gauge', 'Accounts active in the last 10 minutes.', $one($facts['playersOnline'] ?? null));
        $add('travian_players_total', 'gauge', 'Accounts in the world.', $one($facts['playersTotal'] ?? null));
        $add('travian_last_backup_age_seconds', 'gauge', 'Seconds since the last completed backup.', $one($facts['backupAge'] ?? null));
        $add('travian_db_slow_queries_total', 'counter', 'Queries the database counted as slow since it started.', $one($facts['slowQueries'] ?? null));

        $requests = [];
        foreach (self::REQUEST_CLASSES as $class) {
            $requests[] = [['class' => $class], isset($facts['requests'][$class]) ? (int) $facts['requests'][$class] : null];
        }
        $add('travian_http_requests_total', 'counter', 'Game requests served by PHP, by status class (resets when Redis restarts).', $requests);

        $text = '';
        foreach ($families as [$name, $type, $help, $samples]) {
            $text .= '# HELP ' . $name . ' ' . str_replace(["\\", "\n"], ['\\\\', '\\n'], $help) . "\n# TYPE " . $name . ' ' . $type . "\n";
            foreach ($samples as [$labels, $value]) {
                $pairs = [];
                foreach ($labels as $label => $labelValue) {
                    $pairs[] = $label . '="' . str_replace(["\\", '"', "\n"], ['\\\\', '\\"', '\\n'], (string) $labelValue) . '"';
                }
                $text .= $name . ($pairs === [] ? '' : '{' . implode(',', $pairs) . '}') . ' ' . (is_float($value) ? rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.') : (string) (int) $value) . "\n";
            }
        }

        return $text;
    }

    private static function scalar(DB $db, string $sql): ?int
    {
        try {
            $value = $db->fetchScalar($sql);

            return $value === false || $value === null ? null : (int) $value;
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
