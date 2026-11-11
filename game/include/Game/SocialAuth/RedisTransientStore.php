<?php

declare(strict_types=1);

namespace Game\SocialAuth;

use Core\Caching\Caching;

final class RedisTransientStore implements TransientStore
{
    private const PREFIX = 'social_auth_';

    public function put(string $key, array $value, int $ttl): void
    {
        Caching::getInstance()->set(self::PREFIX . $key, $value, max(1, $ttl));
    }

    public function peek(string $key): ?array
    {
        $value = Caching::getInstance()->get(self::PREFIX . $key);

        return is_array($value) ? $value : null;
    }

    public function take(string $key): ?array
    {
        $cache = Caching::getInstance();
        // SET NX is the atomic claim: only the first caller proceeds.
        $claim = $cache->lock('social_take_' . $key, 600);
        if ($claim === false) {
            return null;
        }
        $value = $this->peek($key);
        $cache->delete(self::PREFIX . $key);

        return $value;
    }
}
