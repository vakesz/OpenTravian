<?php

declare(strict_types=1);

namespace Game\SocialAuth;

/** Fetches and caches a provider's JWKS document. */
final class JwksCache
{
    private const DEFAULT_TTL = 3600;
    private const MIN_TTL = 300;
    private const MAX_TTL = 86400;
    /** An unknown kid may force a refetch at most this often (stops fetch amplification). */
    private const REFETCH_INTERVAL = 60;

    public function __construct(
        private readonly HttpClient $http,
        private readonly TransientStore $store,
        private readonly \Closure $clock,
    ) {
    }

    /**
     * @return array<string,mixed>|null the JWK with this kid, or null
     * @throws TokenException when the document cannot be loaded
     */
    public function key(string $uri, string $kid): ?array
    {
        $cacheKey = 'jwks_' . hash('sha256', $uri);
        $now = ($this->clock)();
        $entry = $this->store->peek($cacheKey);
        $fresh = $entry !== null && is_int($entry['expires'] ?? null) && $entry['expires'] > $now;
        if ($fresh) {
            $found = $this->find($entry, $kid);
            if ($found !== null || $now - (int) ($entry['fetchedAt'] ?? 0) < self::REFETCH_INTERVAL) {
                return $found;
            }
        }
        $missKey = 'jwks_miss_' . hash('sha256', $uri . "\0" . $kid);
        $miss = $this->store->peek($missKey);
        if ($miss !== null && is_int($miss['until'] ?? null) && $miss['until'] > $now) {
            return null;
        }
        $entry = $this->fetch($uri, $cacheKey, $now);
        $found = $this->find($entry, $kid);
        if ($found === null) {
            // Negative cache: an unknown kid costs at most one fetch a minute.
            $this->store->put($missKey, ['until' => $now + self::REFETCH_INTERVAL], self::REFETCH_INTERVAL);
        }

        return $found;
    }

    /**
     * @param array<string,mixed> $entry
     * @return array<string,mixed>|null
     */
    private function find(array $entry, string $kid): ?array
    {
        $keys = $entry['keys'] ?? null;
        if (!is_array($keys)) {
            return null;
        }
        foreach ($keys as $jwk) {
            if (is_array($jwk) && ($jwk['kid'] ?? null) === $kid) {
                return $jwk;
            }
        }

        return null;
    }

    /** @return array<string,mixed> */
    private function fetch(string $uri, string $cacheKey, int $now): array
    {
        try {
            $response = $this->http->get($uri);
        } catch (\RuntimeException) {
            throw new TokenException('Key set unavailable.');
        }
        $document = $response->status === 200 ? $response->json() : null;
        $keys = is_array($document) ? ($document['keys'] ?? null) : null;
        if (!is_array($keys) || $keys === []) {
            throw new TokenException('Key set invalid.');
        }
        $ttl = min(self::MAX_TTL, max(self::MIN_TTL, $response->maxAge ?? self::DEFAULT_TTL));
        $entry = ['keys' => array_values($keys), 'fetchedAt' => $now, 'expires' => $now + $ttl];
        $this->store->put($cacheKey, $entry, $ttl);

        return $entry;
    }
}
