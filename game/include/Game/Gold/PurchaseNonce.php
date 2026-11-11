<?php

declare(strict_types=1);

namespace Game\Gold;

use Core\Helper\WebService;

/**
 * One-time confirmation token of a two-step gold purchase. The sign step issues it for
 * a binding (action, player, village, target and price shown); the execute step must
 * present the same token for the same binding. The token lives in the session, is spent by
 * the first execute attempt whatever its outcome, and expires after TTL seconds.
 */
final class PurchaseNonce
{
    private const TTL = 600;

    private function __construct()
    {
    }

    /** @param array<string, mixed> $binding */
    public static function issue(string $action, array $binding): string
    {
        $nonce = bin2hex(random_bytes(16));
        $key = WebService::fixSessionPrefix('purchase_nonce');
        $_SESSION[$key][$action] = [
            'nonce' => $nonce,
            'binding' => self::fingerprint($binding),
            'expires' => time() + self::TTL,
        ];

        return $nonce;
    }

    /** @param array<string, mixed> $binding */
    public static function consume(string $action, array $binding, ?string $submitted): bool
    {
        $key = WebService::fixSessionPrefix('purchase_nonce');
        $stored = $_SESSION[$key][$action] ?? null;
        unset($_SESSION[$key][$action]);

        return is_array($stored)
            && is_string($submitted)
            && $submitted !== ''
            && (int)$stored['expires'] >= time()
            && hash_equals((string)$stored['nonce'], $submitted)
            && hash_equals((string)$stored['binding'], self::fingerprint($binding));
    }

    /** @param array<string, mixed> $binding */
    private static function fingerprint(array $binding): string
    {
        return hash('sha256', json_encode($binding, JSON_THROW_ON_ERROR));
    }
}
