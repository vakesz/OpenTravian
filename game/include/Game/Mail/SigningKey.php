<?php

declare(strict_types=1);

namespace Game\Mail;

/**
 * The secret behind unsubscribe links, kept in the protected state directory (mode 0600, created on first use):
 * never in SQL, never in the repository.
 */
final class SigningKey
{
    private const FILE = 'mail-signing.key';

    private function __construct()
    {
    }

    public static function get(): string
    {
        $path = self::path();
        $key = is_file($path) && !is_link($path) ? trim((string) file_get_contents($path)) : '';
        if (strlen($key) === 64 && ctype_xdigit($key)) {
            return $key;
        }
        $key = bin2hex(random_bytes(32));
        $temporary = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($temporary, $key . "\n", LOCK_EX) === false || !chmod($temporary, 0600) || !rename($temporary, $path)) {
            @unlink($temporary);
            throw new \RuntimeException('The mail signing key could not be stored.');
        }

        return $key;
    }

    private static function path(): string
    {
        return rtrim(defined('STATE_DIR') ? (string) constant('STATE_DIR') : sys_get_temp_dir(), '/') . '/' . self::FILE;
    }
}
