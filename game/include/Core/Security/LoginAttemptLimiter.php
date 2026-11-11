<?php

namespace Core\Security;

use Redis;

/**
 * Atomically admits a password-verification attempt, then records its result.
 *
 * The pending counters close the check-then-increment window without charging
 * successful logins as failures.  A reservation expires conservatively when a
 * request dies before it can be settled.
 */
final class LoginAttemptLimiter
{
    private const PENDING_TTL = 180;

    /**
     * Gives unknown identifiers a stable, Unicode-aware bucket until a database
     * lookup can replace them with an immutable user ID.
     */
    public static function canonicalIdentity(string $identity): string
    {
        $identity = trim($identity);
        if (class_exists('Normalizer')) {
            $normalized = \Normalizer::normalize($identity, \Normalizer::FORM_KC);
            if ($normalized !== false) {
                $identity = $normalized;
            }
        }
        if (function_exists('mb_convert_case') && defined('MB_CASE_FOLD')) {
            return mb_convert_case($identity, MB_CASE_FOLD, 'UTF-8');
        }

        return function_exists('mb_strtolower') ? mb_strtolower($identity, 'UTF-8') : strtolower($identity);
    }

    /**
     * @param array<int, array{0:string,1:int}> $buckets
     * @return array{token:string,buckets:array<int, array{0:string,1:int}>}|false
     */
    public static function reserve(Redis $redis, array $buckets): array|false
    {
        $buckets = self::normalizeBuckets($buckets);
        if (!$buckets) {
            throw new \InvalidArgumentException('At least one login throttle bucket is required.');
        }

        $token = bin2hex(random_bytes(16));
        $keys = array_column($buckets, 0);
        $limits = array_column($buckets, 1);
        $script = <<<'LUA'
local token = ARGV[1]
local pendingTtl = tonumber(ARGV[2])

-- Game throttles historically used Caching::set(), which stores PHP integer
-- values as `i:<number>;`.  Convert that short-lived representation while the
-- reservation script is atomic, so a rolling deployment does not deny every
-- login that happens to reuse an existing counter.
local function counterValue(key)
    local raw = redis.call('GET', key)
    if not raw then
        return 0
    end

    local value = tonumber(raw)
    if value then
        return value
    end

    local legacy = string.match(raw, '^i:([%-]?%d+);$')
    if not legacy then
        return 0
    end

    value = tonumber(legacy)
    local ttl = redis.call('PTTL', key)
    if ttl > 0 then
        redis.call('SET', key, value, 'PX', ttl)
    else
        redis.call('SET', key, value)
    end
    return value
end

for index, key in ipairs(KEYS) do
    local failures = counterValue(key)
    local pending = tonumber(redis.call('GET', key .. ':pending') or '0')
    local limit = tonumber(ARGV[index + 2])
    if failures + pending >= limit then
        return 0
    end
end

for _, key in ipairs(KEYS) do
    local pendingKey = key .. ':pending'
    redis.call('INCR', pendingKey)
    -- A later reservation needs the full lease too. Otherwise the shared
    -- pending key can expire while a recently admitted request still has a
    -- live reservation marker, reopening the admission window.
    redis.call('EXPIRE', pendingKey, pendingTtl)
end

redis.call('SET', KEYS[1] .. ':reservation:' .. token, '1', 'EX', pendingTtl)
return 1
LUA;

        $result = $redis->eval($script, array_merge($keys, [$token, self::PENDING_TTL], $limits), count($keys));

        return (int)$result === 1 ? ['token' => $token, 'buckets' => $buckets] : false;
    }

    /**
     * Settle a reservation exactly once. Failed verifications become failures;
     * successful ones only release their pending slots and clear the pair key.
     *
     * @param array<array-key, mixed> $reservation as returned by reserve(); validated again because it may have been stored
     */
    public static function complete(Redis $redis, array $reservation, bool $failed, int $window, ?string $clearKey = null): bool
    {
        if (!isset($reservation['token'], $reservation['buckets']) || !is_string($reservation['token'])) {
            throw new \InvalidArgumentException('Invalid login throttle reservation.');
        }
        $buckets = self::normalizeBuckets($reservation['buckets']);
        if (!$buckets || $window < 1) {
            throw new \InvalidArgumentException('Invalid login throttle completion.');
        }

        $keys = array_column($buckets, 0);
        $clearIndex = $clearKey === null ? false : array_search($clearKey, $keys, true);
        if ($clearKey !== null && $clearIndex === false) {
            throw new \InvalidArgumentException('The login throttle clear key must belong to the reservation.');
        }

        $script = <<<'LUA'
local token = ARGV[1]
local failed = ARGV[2] == '1'
local window = tonumber(ARGV[3])
local clearIndex = tonumber(ARGV[4])
local reservationKey = KEYS[1] .. ':reservation:' .. token

if redis.call('DEL', reservationKey) == 0 then
    return 0
end

for _, key in ipairs(KEYS) do
    local pendingKey = key .. ':pending'
    local pending = tonumber(redis.call('GET', pendingKey) or '0')
    if pending > 1 then
        redis.call('DECR', pendingKey)
    elseif pending == 1 then
        redis.call('DEL', pendingKey)
    end
end

if failed then
    for _, key in ipairs(KEYS) do
        local failures = redis.call('INCR', key)
        if failures == 1 then
            redis.call('EXPIRE', key, window)
        end
    end
elseif clearIndex > 0 then
    redis.call('DEL', KEYS[clearIndex])
end

return 1
LUA;

        return (int)$redis->eval(
            $script,
            array_merge($keys, [$reservation['token'], $failed ? 1 : 0, $window, $clearIndex === false ? 0 : $clearIndex + 1]),
            count($keys)
        ) === 1;
    }

    /**
     * @param array<array-key, mixed> $buckets untrusted shape: validated bucket by bucket
     * @return array<int, array{0:string,1:int}>
     */
    private static function normalizeBuckets(array $buckets): array
    {
        $normalized = [];
        foreach ($buckets as $bucket) {
            if (!is_array($bucket) || !isset($bucket[0], $bucket[1]) || !is_string($bucket[0])) {
                throw new \InvalidArgumentException('Invalid login throttle bucket.');
            }
            $key = $bucket[0];
            $limit = (int)$bucket[1];
            if ($key === '' || strlen($key) > 512 || $limit < 1) {
                throw new \InvalidArgumentException('Invalid login throttle bucket.');
            }
            if (!isset($normalized[$key]) || $limit < $normalized[$key][1]) {
                $normalized[$key] = [$key, $limit];
            }
        }

        return array_values($normalized);
    }
}
