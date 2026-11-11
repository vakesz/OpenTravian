<?php

declare(strict_types=1);

namespace Game\Admin;

/**
 * RFC 6238 time-based one-time passwords (RFC 4226 HOTP with HMAC-SHA1, 30 s
 * steps, 6 digits) with no external service. Secrets are base32 text so they
 * paste into any authenticator app.
 */
final class Totp
{
    public const PERIOD = 30;
    public const DIGITS = 6;

    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    private function __construct()
    {
    }

    /** A fresh 160-bit secret as 32 base32 characters. */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    public static function timeStep(?int $time = null): int
    {
        return intdiv($time ?? time(), self::PERIOD);
    }

    /** The code for one time step. */
    public static function code(string $base32Secret, int $step): string
    {
        $key = self::base32Decode($base32Secret);
        if ($key === null || $key === '') {
            throw new \InvalidArgumentException('The TOTP secret is not valid base32.');
        }
        $hash = hash_hmac('sha1', pack('J', $step), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($binary % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Checks a submitted code against the steps around now. Returns the matching
     * step, or null. A step at or before $lastUsedStep is refused, so a code
     * (or one captured over a shoulder) works once.
     */
    public static function verify(string $base32Secret, string $submitted, ?int $now = null, int $window = 1, int $lastUsedStep = 0): ?int
    {
        $submitted = preg_replace('/\s+/', '', $submitted) ?? '';
        if (preg_match('/^\d{' . self::DIGITS . '}$/', $submitted) !== 1) {
            return null;
        }
        $current = self::timeStep($now);
        $matched = null;
        // Every candidate is compared so the timing does not reveal which step was close.
        for ($step = $current - $window; $step <= $current + $window; ++$step) {
            if (hash_equals(self::code($base32Secret, $step), $submitted) && $step > $lastUsedStep) {
                $matched = $step;
            }
        }

        return $matched;
    }

    /** The otpauth:// URI authenticator apps read from the QR code. */
    public static function uri(string $base32Secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($account);

        return 'otpauth://totp/' . $label . '?secret=' . $base32Secret
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
    }

    public static function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public static function base32Decode(string $text): ?string
    {
        $text = strtoupper(str_replace([' ', '-', '='], '', $text));
        if ($text === '' || preg_match('/^[A-Z2-7]+$/', $text) !== 1) {
            return null;
        }
        $bits = '';
        foreach (str_split($text) as $character) {
            $bits .= str_pad(decbin((int) strpos(self::ALPHABET, $character)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $out .= chr(bindec($chunk));
            }
        }

        return $out;
    }
}
