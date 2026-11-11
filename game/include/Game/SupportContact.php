<?php

declare(strict_types=1);

namespace Game;

use Core\Config;

/**
 * The world operator's support recipient. Setup requires it and the
 * Multihunter can change it later; delivery credentials remain deployment
 * secrets rather than a player-facing world setting.
 */
final class SupportContact
{
    public const MAX_LENGTH = 254;

    private function __construct()
    {
    }

    public static function normalize(string $email): string
    {
        return trim($email);
    }

    public static function isValid(string $email): bool
    {
        return $email !== ''
            && strlen($email) <= self::MAX_LENGTH
            && !preg_match('/[\r\n\0]/', $email)
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** Returns an empty string when an existing installation is not configured yet. */
    public static function email(): string
    {
        $email = self::normalize((string) (Config::getInstance()->dynamic->supportEmail ?? ''));

        return self::isValid($email) ? $email : '';
    }
}
