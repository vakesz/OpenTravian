<?php

declare(strict_types=1);

namespace Game\SocialAuth;

final class Base64Url
{
    private function __construct()
    {
    }

    public static function encode(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    /** Returns null for anything that is not canonical unpadded base64url. */
    public static function decode(string $text): ?string
    {
        if ($text === '' || preg_match('/\A[A-Za-z0-9_-]+\z/', $text) !== 1) {
            return null;
        }
        $decoded = base64_decode(strtr($text, '-_', '+/'), true);

        // Canonical only: re-encoding must reproduce the input, which rejects
        // non-zero trailing bits (malleable encodings of the same bytes).
        return $decoded === false || self::encode($decoded) !== $text ? null : $decoded;
    }
}
