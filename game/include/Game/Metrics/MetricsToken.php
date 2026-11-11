<?php

declare(strict_types=1);

namespace Game\Metrics;

/**
 * The bearer token that guards /metrics, kept in protected state (metrics-token.local in the
 * state directory, mode 0600) and created on request with tools/metrics-token.php. Without a
 * token file the endpoint does not exist. Nothing here returns or logs the token except read(),
 * which only the verification and the operator's own `cat` ever reach.
 */
final class MetricsToken
{
    private function __construct()
    {
    }

    public static function file(): string
    {
        return rtrim((string) STATE_DIR, '/') . '/metrics-token.local';
    }

    public static function read(): ?string
    {
        $file = self::file();
        $token = is_file($file) && is_readable($file) ? trim((string) file_get_contents($file)) : '';

        return preg_match('/\A[0-9a-f]{32,}\z/', $token) === 1 ? $token : null;
    }

    /**
     * Creates the token (or replaces it with $rotate) through a temporary file and a rename.
     *
     * @return bool whether a token was written
     */
    public static function create(bool $rotate): bool
    {
        if (!$rotate && self::read() !== null) {
            return false;
        }
        $file = self::file();
        $temporary = $file . '.partial';
        if (@file_put_contents($temporary, bin2hex(random_bytes(24)) . "\n") === false || !@chmod($temporary, 0600) || !@rename($temporary, $file)) {
            @unlink($temporary);
            throw new \RuntimeException('Cannot write the metrics token in the state directory.');
        }

        return true;
    }

    /** Whether an "Authorization" header value carries the token; false when no token exists. */
    public static function accepts(?string $authorization, ?string $token): bool
    {
        if ($token === null || $authorization === null || preg_match('/\ABearer\s+(\S+)\z/i', trim($authorization), $match) !== 1) {
            return false;
        }

        return hash_equals($token, $match[1]);
    }
}
