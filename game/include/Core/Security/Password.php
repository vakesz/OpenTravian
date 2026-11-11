<?php

namespace Core\Security;

final class Password
{
    public const MIN_LENGTH = 8;
    // Fixed Argon2id work for unknown-identity login paths. This is not a
    // credential and only exists to avoid returning before adaptive hashing.
    private const DUMMY_HASH = '$argon2id$v=19$m=65536,t=4,p=1$c1dVR2d0RmNNZnhZQ0ZiTA$UhplzDn40hjiHOO+Npfy3mlzrFDuKExNIK0F3gjKpXw';

    public static function hash(string $password): string
    {
        $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        return password_hash($password, $algorithm);
    }

    public static function verify(string $password, string $storedHash): bool
    {
        if (self::isLegacySha1($storedHash)) {
            $verified = hash_equals(strtolower($storedHash), sha1($password));
            // A legacy SHA-1 comparison is effectively instantaneous, whereas
            // unknown identities and modern hashes perform adaptive work. Keep
            // a failed legacy check in that same timing class until the account
            // is upgraded on a successful login.
            if (!$verified) {
                self::verifyDummy($password);
            }

            return $verified;
        }

        return password_verify($password, $storedHash);
    }

    public static function verifyDummy(string $password): void
    {
        password_verify($password, self::DUMMY_HASH);
    }

    public static function needsRehash(string $storedHash): bool
    {
        if (self::isLegacySha1($storedHash)) {
            return true;
        }

        $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;

        return password_needs_rehash($storedHash, $algorithm);
    }

    public static function isLegacySha1(string $storedHash): bool
    {
        return preg_match('/^[a-f0-9]{40}$/i', $storedHash) === 1;
    }

    /**
     * @return string|null null when acceptable, otherwise the error code
     * ('passwordInsecure' too short, 'passwordLikeName' equals the player name)
     */
    public static function validate(string $password, string $name = ''): ?string
    {
        if (strlen($password) < self::MIN_LENGTH) {
            return 'passwordInsecure';
        }
        if ($name !== '' && self::canonicalName($password) === self::canonicalName($name)) {
            return 'passwordLikeName';
        }

        return null;
    }

    /**
     * Mirrors the Unicode-aware identity handling used by login throttling.
     * Player names are stored in a Unicode case-insensitive database, so an
     * ASCII-only comparison would let visually equivalent name/password pairs
     * bypass the password policy.
     */
    private static function canonicalName(string $value): string
    {
        if (class_exists('Normalizer')) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_KC);
            if ($normalized !== false) {
                $value = $normalized;
            }
        }

        if (function_exists('mb_convert_case') && defined('MB_CASE_FOLD')) {
            return mb_convert_case($value, MB_CASE_FOLD, 'UTF-8');
        }

        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }
}
