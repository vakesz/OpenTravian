<?php

declare(strict_types=1);

namespace Game\SocialAuth;

final class FlowResult
{
    private function __construct(
        public readonly string $kind,
        public readonly ?int $userId,
        public readonly ?IdentityClaims $claims,
        public readonly ?FlowError $error,
        public readonly ?string $locale,
        public readonly string $referral,
        /** The player who asked to link this identity, on every outcome of a link flow. */
        public readonly ?int $linkUserId = null,
    ) {
    }

    public static function login(int $userId, ?string $locale, string $referral): self
    {
        return new self('login', $userId, null, null, $locale, $referral);
    }

    public static function signup(IdentityClaims $claims, ?string $locale, string $referral): self
    {
        return new self('signup', null, $claims, null, $locale, $referral);
    }

    /** An identity the player asked to link to their own account (never matched by email). */
    public static function link(int $userId, IdentityClaims $claims, ?string $locale, string $referral): self
    {
        return new self('link', $userId, $claims, null, $locale, $referral, $userId);
    }

    public static function failure(FlowError $error, ?string $locale = null, string $referral = '', ?int $linkUserId = null): self
    {
        return new self('error', null, null, $error, $locale, $referral, $linkUserId);
    }
}
