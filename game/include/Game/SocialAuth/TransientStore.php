<?php

declare(strict_types=1);

namespace Game\SocialAuth;

/** Short-lived server-side records (flow state, JWKS documents). */
interface TransientStore
{
    /** @param array<string,mixed> $value */
    public function put(string $key, array $value, int $ttl): void;

    /** @return array<string,mixed>|null */
    public function peek(string $key): ?array;

    /**
     * Atomically read and remove a record: at most one caller ever receives it.
     *
     * @return array<string,mixed>|null
     */
    public function take(string $key): ?array;
}
