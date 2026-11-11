<?php

declare(strict_types=1);

namespace Core;

use Core\Security\LoginAttemptLimiter;
use Redis;
use Throwable;

// The game and API share the same atomic login-attempt state machine.
$canonicalLimiter = dirname(__DIR__, 4) . '/game/include/Core/Security/LoginAttemptLimiter.php';
if (!is_file($canonicalLimiter)) {
    throw new \RuntimeException('Missing ' . $canonicalLimiter . '; services/api must run inside the repository.');
}
require_once $canonicalLimiter;

/**
 * API-specific keying and mail delivery throttling.
 */
class LoginThrottle
{
    public const WINDOW = 900;
    public const PAIR_LIMIT = 5;
    public const IP_LIMIT = 20;
    public const MAIL_WINDOW = 3600;
    public const MAIL_LIMIT = 3;
    public const MAIL_GAP = 60;

    private static ?Redis $redis = null;

    private static function connection(): ?Redis
    {
        if (self::$redis instanceof Redis) {
            return self::$redis;
        }
        try {
            $redis = new Redis();
            if (!$redis->connect(getenv('TRAVIAN_REDIS_HOST') ?: '127.0.0.1', (int)(getenv('TRAVIAN_REDIS_PORT') ?: 6379), 1.0)) {
                throw new \RuntimeException('Redis connection refused.');
            }
            if (($password = (string)getenv('TRAVIAN_REDIS_PASSWORD')) !== '' && !$redis->auth($password)) {
                throw new \RuntimeException('Redis authentication failed.');
            }
            $redis->setOption(Redis::OPT_READ_TIMEOUT, 1.0);
            return self::$redis = $redis;
        } catch (Throwable $e) {
            error_log('LoginThrottle: Redis unavailable: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @return array<int, array{0:string,1:int}>
     */
    private static function loginBuckets(int $worldId, ?int $accountId, string|false $ip, ?string $unknownIdentity): array
    {
        $world = hash('sha256', (string)$worldId);
        $ip = hash('sha256', (string)$ip);
        $buckets = [["lt:api:ip:$ip", self::IP_LIMIT]];
        // Pair keys use the database-resolved immutable ID, not user input.
        if ($accountId !== null) {
            array_unshift($buckets, ["lt:api:pair:$world:" . (int)$accountId . ":$ip", self::PAIR_LIMIT]);
        } elseif ($unknownIdentity !== null) {
            $identity = LoginAttemptLimiter::canonicalIdentity((string)$unknownIdentity);
            array_unshift($buckets, ["lt:api:pair:$world:unknown:" . hash('sha256', $identity) . ":$ip", self::PAIR_LIMIT]);
        }
        return $buckets;
    }

    /**
     * Admit a password verification atomically. A false return is a denial,
     * whether the configured budget is exhausted or Redis is unavailable.
     *
     * @return array{token:string,buckets:array<int, array{0:string,1:int}>}|false
     */
    public static function reserveLogin(int $worldId, ?int $accountId, string|false $ip, ?string $unknownIdentity = null): array|false
    {
        $redis = self::connection();
        if (!$redis) {
            return false;
        }
        try {
            return LoginAttemptLimiter::reserve($redis, self::loginBuckets($worldId, $accountId, $ip, $unknownIdentity));
        } catch (Throwable $e) {
            error_log('LoginThrottle: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Complete an admitted attempt. Successful authentication clears only the
     * resolved account/IP pair; IP failures remain protective for the source.
     *
     * @param array{token:string,buckets:array<int, array{0:string,1:int}>}|false $reservation
     */
    public static function completeLogin(array|false $reservation, bool $failed): bool
    {
        if ($reservation === false) {
            return false;
        }
        $redis = self::connection();
        if (!$redis) {
            return false;
        }
        try {
            $buckets = $reservation['buckets'];
            $clearPairKey = !$failed && count($buckets) > 1 ? $buckets[0][0] : null;
            return LoginAttemptLimiter::complete(
                $redis,
                $reservation,
                (bool)$failed,
                self::WINDOW,
                $clearPairKey
            );
        } catch (Throwable $e) {
            error_log('LoginThrottle: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Whether a mail of this kind may be sent now (3 per hour, 60 s apart).
     * If Redis is unavailable, deny the send rather than silently disabling
     * abuse protection.
     */
    public static function allowMail(string $kind, string $email): bool
    {
        $redis = self::connection();
        if (!$redis) {
            return false;
        }
        try {
            $hash = sha1(strtolower(trim((string)$email)));
            if (!$redis->set("lt:api:mailgap:$kind:$hash", 1, ['nx', 'ex' => self::MAIL_GAP])) {
                return false;
            }
            $key = "lt:api:mail:$kind:$hash";
            $count = (int)$redis->incr($key);
            if ($count === 1) {
                $redis->expire($key, self::MAIL_WINDOW);
            }
            return $count <= self::MAIL_LIMIT;
        } catch (Throwable $e) {
            error_log('LoginThrottle: ' . $e->getMessage());
            return false;
        }
    }
}
