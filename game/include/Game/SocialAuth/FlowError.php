<?php

declare(strict_types=1);

namespace Game\SocialAuth;

/**
 * Failure categories. Player copy reuses existing canonical translation keys
 * only (see translationKey()); no social-specific wording exists or is added.
 */
enum FlowError: string
{
    case Unavailable = 'unavailable';
    case Denied = 'denied';
    case InvalidState = 'invalid_state';
    case ProviderFailure = 'provider_failure';
    case InvalidToken = 'invalid_token';
    case EmailMissing = 'email_missing';
    case EmailInUse = 'email_in_use';
    case RateLimited = 'rate_limited';
    /** Linking from Options: the identity already belongs to another player. */
    case LinkedElsewhere = 'linked_elsewhere';
    /** Linking from Options: the account already has an identity from this provider. */
    case ProviderLinked = 'provider_linked';

    /** @return array{0:string,1:string} [translation base, translation key] */
    public function translationKey(): array
    {
        return match ($this) {
            self::EmailInUse => ['EVerify', 'errors.emailAlreadyExists'],
            self::RateLimited => ['Login', 'tooManyRequests'],
            self::LinkedElsewhere => ['Login', 'This %s account is already linked to another player.'],
            default => ['Support', 'errors.Invalid request'],
        };
    }
}
