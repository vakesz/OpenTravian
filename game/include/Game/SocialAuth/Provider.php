<?php

declare(strict_types=1);

namespace Game\SocialAuth;

/** The supported social sign-in providers and their fixed protocol endpoints. */
enum Provider: string
{
    case Google = 'google';
    case Facebook = 'facebook';
    case Apple = 'apple';

    /** The provider's brand name, which is not translated. */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function authorizeEndpoint(): string
    {
        return match ($this) {
            self::Google => 'https://accounts.google.com/o/oauth2/v2/auth',
            self::Facebook => 'https://www.facebook.com/v19.0/dialog/oauth',
            self::Apple => 'https://appleid.apple.com/auth/authorize',
        };
    }

    public function tokenEndpoint(): string
    {
        return match ($this) {
            self::Google => 'https://oauth2.googleapis.com/token',
            self::Facebook => 'https://graph.facebook.com/v19.0/oauth/access_token',
            self::Apple => 'https://appleid.apple.com/auth/token',
        };
    }

    /** JWKS document for id_token providers; null for Facebook (Graph API validation). */
    public function jwksUri(): ?string
    {
        return match ($this) {
            self::Google => 'https://www.googleapis.com/oauth2/v3/certs',
            self::Facebook => null,
            self::Apple => 'https://appleid.apple.com/auth/keys',
        };
    }

    /** @return list<string> */
    public function issuers(): array
    {
        return match ($this) {
            self::Google => ['https://accounts.google.com', 'accounts.google.com'],
            self::Facebook => [],
            self::Apple => ['https://appleid.apple.com'],
        };
    }

    public function scope(): string
    {
        return match ($this) {
            self::Google => 'openid email profile',
            self::Facebook => 'email public_profile',
            self::Apple => 'name email',
        };
    }

    public function usesPkce(): bool
    {
        return $this === self::Google;
    }

    /** Apple answers with a cross-site POST, so it cannot rely on the session cookie. */
    public function usesFormPost(): bool
    {
        return $this === self::Apple;
    }

    public function usesIdToken(): bool
    {
        return $this !== self::Facebook;
    }
}
