<?php

declare(strict_types=1);

namespace Game\SocialAuth;

final class HttpResponse
{
    public function __construct(public readonly int $status, public readonly string $body, public readonly ?int $maxAge = null)
    {
    }

    /** @return array<string,mixed>|null */
    public function json(): ?array
    {
        $decoded = json_decode($this->body, true, 16);

        return is_array($decoded) ? $decoded : null;
    }
}
