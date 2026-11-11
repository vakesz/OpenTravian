<?php

namespace Core\Jobs;

use Core\Caching\Caching;
use Core\Database\DB;

/**
 * Engine liveness, stale-code detection and the maintenance pause flag.
 *
 * The engine parent process writes a heartbeat to Redis; the admin panel reads it. The pause flag lets an operator
 * action (backup restore) stop the workers cooperatively without any process-manager call: the engine acknowledges by
 * publishing state "paused" and, once the flag is cleared, exits so Compose restarts it on fresh code and fresh data.
 */
final class EngineHeartbeat
{
    public const FRESH_SECONDS = 90;
    public const BEAT_INTERVAL = 15;
    public const FINGERPRINT_INTERVAL = 300;

    private const BEAT_KEY = 'engine:heartbeat';
    private const PAUSE_KEY = 'engine:pause';
    private const BEAT_TTL = 3600;
    /**
     * Unprefixed key so the container health check can read liveness without loading
     * the game or knowing the per-world cache prefix. Same JSON and TTL as the beat.
     */
    public const LIVENESS_KEY = 'travian:engine:heartbeat';
    private const ROUND_KEY = 'engine:lastRound';
    private const ROUND_WRITE_INTERVAL = 1.0;

    private static float $roundWrittenAt = 0.0;

    /**
     * Maps a stored beat to the admin status. Pure function so it can be contract-tested.
     *
     * @param array<string, mixed>|null $beat
     * @return array{state:string,text:string}
     */
    public static function describe(?array $beat, int $now): array
    {
        if ($beat === null || !isset($beat['ts'])) {
            return ['state' => 'not_running', 'text' => 'Not running'];
        }
        $age = max(0, $now - (int)$beat['ts']);
        $paused = ($beat['state'] ?? '') === 'paused' ? ', paused for maintenance' : '';
        if (($beat['state'] ?? '') === 'catching-up') {
            $paused = ', Catching up (' . (int)($beat['remaining'] ?? 0) . ')';
        }
        if ($age <= self::FRESH_SECONDS) {
            return ['state' => 'running', 'text' => "Running (last beat {$age}s ago{$paused})"];
        }

        return ['state' => 'stalled', 'text' => 'Stalled (last beat ' . self::formatAge($age) . ' ago)'];
    }

    /**
     * True while an engine is beating: the last beat is no older than FRESH_SECONDS. The Redis key outlives a stopped
     * engine by an hour, so its mere presence says nothing. Pure function so it can be contract-tested.
     *
     * @param array<string, mixed>|null $beat
     */
    public static function isAlive(?array $beat, int $now): bool
    {
        return self::describe($beat, $now)['state'] === 'running';
    }

    private static function formatAge(int $age): string
    {
        if ($age < 120) {
            return $age . 's';
        }
        if ($age < 7200) {
            return intdiv($age, 60) . 'm';
        }

        return intdiv($age, 3600) . 'h';
    }

    /**
     * Fingerprint of the deployed PHP code: path and content hash of every .php file under the include tree
     * (templates and vendored plugins excluded). Changes when a deploy changes a runtime file, not when a file is
     * only touched: a checkout, a patch that reapplies identical text or a copy changes mtimes without changing
     * code, and keyed on mtime they restarted the engine every few minutes.
     */
    public static function fingerprint(string $root): string
    {
        $entries = [];
        $skip = ['resources' => true, 'Plugins' => true, 'stubs' => true];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            static function (\SplFileInfo $file) use ($skip): bool {
                return $file->isDir() ? !isset($skip[$file->getFilename()]) : $file->getExtension() === 'php';
            }
        ));
        foreach ($iterator as $file) {
            $entries[] = $file->getPathname() . ':' . md5_file($file->getPathname());
        }
        sort($entries);

        return sprintf('%08x-%d', crc32(implode("\n", $entries)), count($entries));
    }

    /**
     * @param array<string, mixed> $beat
     */
    public static function beat(array $beat): void
    {
        try {
            $beat['ts'] = time();
            $json = json_encode($beat, JSON_THROW_ON_ERROR);
            $redis = Caching::singleton();
            $redis->setex(self::BEAT_KEY, self::BEAT_TTL, $json);
            // rawCommand is not prefixed, so the health check can GET a fixed key.
            $redis->rawCommand('SETEX', self::LIVENESS_KEY, (string)self::BEAT_TTL, $json);
        } catch (\Throwable $exception) {
            // Liveness reporting must never take the engine down.
        }
        try {
            // Kept in the world database too: the Redis beat expires after an hour and is lost with the cache, and
            // the next start needs to know how long the engine was away (Core\Jobs\CatchUp).
            DB::getInstance()->run('UPDATE config SET engineBeat=?', [$beat['ts']]);
        } catch (\Throwable $exception) {
            // Likewise.
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function read(): ?array
    {
        try {
            $raw = Caching::singleton()->get(self::BEAT_KEY);
            $beat = is_string($raw) ? json_decode($raw, true) : null;

            return is_array($beat) ? $beat : null;
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /**
     * Records that an automation worker just finished a job run: its name, end time and duration. Workers are
     * separate processes from the engine parent that writes the beat, so the last round has its own key and the beat
     * format is unchanged. Written at most once a second per process so busy workers do not flood Redis.
     */
    public static function recordRound(string $job, float $startedAt): void
    {
        $ended = microtime(true);
        if ($ended - self::$roundWrittenAt < self::ROUND_WRITE_INTERVAL) {
            return;
        }
        self::$roundWrittenAt = $ended;
        try {
            Caching::singleton()->setex(self::ROUND_KEY, self::BEAT_TTL, json_encode([
                'job' => $job,
                'ended_at' => (int)$ended,
                'duration_ms' => (int)round(($ended - $startedAt) * 1000),
            ], JSON_THROW_ON_ERROR));
        } catch (\Throwable $exception) {
            // Reporting must never take a worker down.
        }
    }

    /**
     * @return array{job:string,ended_at:int,duration_ms:int}|null
     */
    public static function lastRound(): ?array
    {
        try {
            $raw = Caching::singleton()->get(self::ROUND_KEY);
            $round = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($round) || !isset($round['job'], $round['ended_at'], $round['duration_ms'])) {
                return null;
            }

            return ['job' => (string)$round['job'], 'ended_at' => (int)$round['ended_at'], 'duration_ms' => (int)$round['duration_ms']];
        } catch (\Throwable $exception) {
            return null;
        }
    }

    public static function clear(): void
    {
        try {
            $redis = Caching::singleton();
            $redis->del(self::BEAT_KEY);
            $redis->del(self::ROUND_KEY);
            $redis->rawCommand('DEL', self::LIVENESS_KEY);
        } catch (\Throwable $exception) {
        }
    }

    /**
     * @return array{state:string,text:string}
     */
    public static function status(): array
    {
        return self::describe(self::read(), time());
    }

    public static function requestPause(): void
    {
        Caching::singleton()->set(self::PAUSE_KEY, (string)time());
    }

    public static function clearPause(): void
    {
        Caching::singleton()->del(self::PAUSE_KEY);
    }

    public static function pauseRequested(): bool
    {
        try {
            return (bool)Caching::singleton()->exists(self::PAUSE_KEY);
        } catch (\Throwable $exception) {
            return false;
        }
    }

    /**
     * Waits for the engine to acknowledge a pause. An engine with no fresh beat is treated as already stopped.
     */
    public static function awaitPaused(int $timeoutSeconds): bool
    {
        $deadline = time() + $timeoutSeconds;
        do {
            $status = self::status();
            if ($status['state'] !== 'running') {
                return true;
            }
            $beat = self::read();
            if (($beat['state'] ?? '') === 'paused' && (int)($beat['ts'] ?? 0) >= time() - self::FRESH_SECONDS) {
                return true;
            }
            sleep(1);
        } while (time() < $deadline);

        return false;
    }

    /**
     * Releases quarantined tasks that failed on a missing method/class/function. Only a code change can cure those,
     * and a code change restarts the engine, so only rows quarantined before this engine started are released; a task
     * that fails again under the current code stays quarantined (no retry loop).
     *
     * @return int number of tasks requeued
     */
    public static function requeueStaleCodeQuarantines(int $engineStartedAt): int
    {
        $db = \Core\Database\DB::getInstance();
        $result = $db->query(
            "SELECT task_table, task_id FROM scheduled_task_failures WHERE quarantined_at IS NOT NULL
             AND quarantined_at < " . $engineStartedAt . "
             AND last_error REGEXP 'Call to undefined (method|function)|Class .* not found|Interface .* not found|Trait .* not found'"
        );
        $released = 0;
        while ($result && ($row = $result->fetch_assoc())) {
            try {
                if (TransactionalTask::requeue($row['task_table'], (int)$row['task_id'])) {
                    ++$released;
                    \logError("Requeued {$row['task_table']} #{$row['task_id']} quarantined by a stale-code error.");
                }
            } catch (\Throwable $exception) {
                \logError('Stale-code requeue failed: ' . $exception->getMessage());
            }
        }

        return $released;
    }
}
