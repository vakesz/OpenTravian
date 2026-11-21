<?php

declare(strict_types=1);

namespace Core\Caching;

use Core\Config;
use Core\Time;
use Redis;

use function getWorldUniqueId;

class Caching
{
    private static ?Redis $instance = null;
    /** Process that opened $instance; a forked engine worker must not reuse its parent's socket. */
    private static ?int $instancePid = null;
    /** @var list<Redis> inherited connections kept alive so their destructor never touches the parent's socket */
    private static array $inherited = [];
    private static ?self $_self = null;

    public static function getInstance(): self
    {
        return self::$_self ??= new self();
    }

    /**
     * The shared Redis connection. The key prefix is fixed by the first caller.
     */
    public static function singleton(?string $key = null): Redis
    {
        $config = Config::getInstance();
        if (self::$instance instanceof Redis && self::$instancePid !== getmypid()) {
            // Inherited across pcntl_fork: parent and child would read each other's replies on the
            // shared socket (a GET could return another worker's 3-byte reply). Set it aside without
            // closing, since close() or the destructor could end the parent's session, and connect afresh.
            self::$inherited[] = self::$instance;
            self::$instance = null;
        }
        if (!(self::$instance instanceof Redis)) {
            if ($key === null) {
                $indexUrlParts = explode('.', (string)$config->settings->indexUrl);
                $key = trim(getWorldUniqueId() . ($indexUrlParts[1] ?? '') . ':');
            }
            try {
                $redis = new Redis();
                $redisHost = getenv('TRAVIAN_REDIS_HOST') ?: '127.0.0.1';
                $redisPort = (int)(getenv('TRAVIAN_REDIS_PORT') ?: 6379);
                // Web workers reuse one connection per FPM process. The engine forks its workers, and a
                // pooled socket would be shared across those processes, so the command line connects afresh.
                $connected = PHP_SAPI === 'cli' ? $redis->connect($redisHost, $redisPort) : $redis->pconnect($redisHost, $redisPort);
                if (!$connected) {
                    throw new \RuntimeException('Redis connection refused.');
                }
                if (($redisPassword = (string)getenv('TRAVIAN_REDIS_PASSWORD')) !== '' && !$redis->auth($redisPassword)) {
                    throw new \RuntimeException('Redis authentication failed.');
                }
                $redis->setOption(Redis::OPT_PREFIX, $key);
                self::$instance = $redis;
                self::$instancePid = getmypid();
            } catch (\Exception $e) {
                throw new CacheUnavailableException('Cache server unavailable: ' . $e->getMessage(), 0, $e);
            }
        }
        return self::$instance;
    }

    public function add(string $key, mixed $value, int|float|null $expiration = null): void
    {
        $this->set($key, $value, $expiration);
    }

    /**
     * Acquires an advisory lock and returns its ownership token. The token is
     * required to renew or release it, so an expired holder cannot delete a
     * newer holder's lease.
     */
    public function lock(string $key, int $ttl = Time::MINUTE): string|false
    {
        $key = 'lock_' . $key;
        $token = bin2hex(random_bytes(16));

        return self::singleton()->set($key, $token, ['nx', 'ex' => max(1, (int)$ttl)]) ? $token : false;
    }

    public function renewLock(string $key, string|false $token, int $ttl = Time::MINUTE): bool
    {
        $key = 'lock_' . $key;
        if ($token === false || $token === '') {
            return false;
        }

        return (bool)self::singleton()->eval(
            'if redis.call("GET", KEYS[1]) == ARGV[1] then return redis.call("EXPIRE", KEYS[1], ARGV[2]) end return 0',
            [$key, $token, max(1, (int)$ttl)],
            1
        );
    }

    public function unlock(string $key, string|false $token): bool
    {
        $key = 'lock_' . $key;
        if ($token === false || $token === '') {
            return false;
        }

        return (bool)self::singleton()->eval(
            'if redis.call("GET", KEYS[1]) == ARGV[1] then return redis.call("DEL", KEYS[1]) end return 0',
            [$key, $token],
            1
        );
    }

    /** Explicit administrative override for an orphaned lock. */
    public function forceUnlock(string $key): bool
    {
        return (int) self::singleton()->del('lock_' . $key) > 0;
    }

    /**
     * Atomically consume one slot from a fixed-window public-form limit.
     * This is intentionally separate from get()/set(), whose serialized
     * values cannot safely participate in a Redis INCR operation.
     */
    public function incrementWithinLimit(string $key, int $limit, int $expiration): bool
    {
        if ($key === '' || $limit < 1 || $expiration < 1) {
            throw new \InvalidArgumentException('Invalid cache rate-limit parameters.');
        }

        return (bool)self::singleton()->eval(
            'local raw = redis.call("GET", KEYS[1]) ' .
            'local current = tonumber(raw or "0") ' .
            'if current == nil and raw then ' .
            '  current = tonumber(string.match(raw, "^i:([%-]?%d+);$")) ' .
            '  if current then ' .
            '    local ttl = redis.call("TTL", KEYS[1]) ' .
            '    redis.call("SET", KEYS[1], current) ' .
            '    if ttl > 0 then redis.call("EXPIRE", KEYS[1], ttl) end ' .
            '  else current = 0 end ' .
            'end ' .
            'if current >= tonumber(ARGV[1]) then return 0 end ' .
            'local nextValue = redis.call("INCR", KEYS[1]) ' .
            'if nextValue == 1 then redis.call("EXPIRE", KEYS[1], tonumber(ARGV[2])) end ' .
            'return 1',
            [$key, $limit, $expiration],
            1
        );
    }

    /**
     * @param list<mixed> $vars arguments for the callback
     */
    public function returnCacheWithCallBack(string $key, int $expire, array $vars, callable $callBack): mixed
    {
        if ($this->exists($key)) {
            return $this->get($key);
        }
        $value = call_user_func_array($callBack, $vars);
        $this->set($key, $value, $expire);

        return $value;
    }

    public function set(string $key, mixed $value, int|float|null $expiration = null): void
    {
        if ($expiration === null) {
            self::singleton()->set($key, serialize($value));

            return;
        }
        self::singleton()->setex($key, (int) $expiration, serialize($value));
    }

    /**
     * @param string|list<string> $key
     */
    public function delete(string|array $key): void
    {
        if (!is_array($key)) {
            self::singleton()->del([$key]);
        } elseif ($key) {
            self::singleton()->del($key);
        }
    }

    /**
     * Streams keys matching $pattern without the connection prefix. This deliberately returns a Generator rather
     * than an array: materializing a broad pattern defeats SCAN by moving the unbounded memory cost into PHP.
     */
    /**
     * @return \Generator<int, string>
     */
    public function keys(string $pattern): \Generator
    {
        foreach ($this->scanBatches($pattern) as $batch) {
            foreach ($batch as $key) {
                yield $key;
            }
        }
    }

    /**
     * Streams unprefixed keys in bounded batches. This lets invalidation avoid
     * retaining every match in PHP memory on a large world.
     */
    /**
     * @return \Generator<int, list<string>>
     */
    private function scanBatches(string $pattern): \Generator
    {
        $redis = self::singleton();
        $prefix = (string)$redis->getOption(Redis::OPT_PREFIX);
        // phpredis does not prefix the SCAN pattern itself, so do it explicitly.
        $match = $prefix . $pattern;
        $iterator = null;
        $redis->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);
        while (($batch = $redis->scan($iterator, $match, 500)) !== false) {
            $keys = [];
            foreach ($batch as $fullKey) {
                $key = $prefix !== '' && strncmp($fullKey, $prefix, strlen($prefix)) === 0
                    ? substr($fullKey, strlen($prefix))
                    : $fullKey;
                $keys[$key] = $key;
            }
            if ($keys) {
                yield array_values($keys);
            }
            if ($iterator === 0) {
                break;
            }
        }
    }

    public function deleteByPattern(string $pattern): void
    {
        foreach ($this->scanBatches($pattern) as $batch) {
            $this->delete($batch);
        }
    }
    public function get(string $key): mixed
    {
        $value = self::singleton()->get($key);
        if (!is_string($value)) {
            return false;
        }
        // A corrupt entry must read as a miss, not as a warning: the engine turns warnings into
        // exceptions, and one bad cache value would fail every attack or battle that reads it.
        set_error_handler(static fn (): bool => true);
        try {
            $decoded = unserialize($value, ['allowed_classes' => false]);
        } finally {
            restore_error_handler();
        }
        if ($decoded === false && $value !== serialize(false)) {
            self::singleton()->del([$key]);
        }

        return $decoded;
    }

    public function exists(string $key): bool
    {
        return (bool) self::singleton()->exists($key);
    }
}
