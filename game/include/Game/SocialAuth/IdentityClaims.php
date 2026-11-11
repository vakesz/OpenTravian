<?php

declare(strict_types=1);

namespace Game\SocialAuth;

/** The verified identity a provider vouched for. */
final class IdentityClaims
{
    public function __construct(
        public readonly Provider $provider,
        public readonly string $subject,
        public readonly string $email,
        public readonly bool $emailVerified,
        public readonly string $name,
    ) {
    }
}
