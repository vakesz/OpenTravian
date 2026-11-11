<?php

declare(strict_types=1);

namespace Game\Mail;

use Core\Config;

/**
 * Signed unsubscribe links: `<uid>.<scope>.<mac>` where scope is a mail kind or `all`. The mac is an HMAC under the
 * mail signing key, so a link cannot be forged or turned into another player's or another kind. Links never expire:
 * unsubscribing twice is harmless and an old mail must keep working.
 */
final class UnsubscribeToken
{
    public const ALL = 'all';

    private function __construct()
    {
    }

    public static function issue(int $userId, string $scope): string
    {
        return $userId . '.' . $scope . '.' . self::mac($userId, $scope);
    }

    public static function url(int $userId, EmailKind $kind): string
    {
        return rtrim((string) Config::getProperty('settings', 'gameWorldUrl'), '/') . '/unsubscribe/' . self::issue($userId, $kind->value);
    }

    /** @return array{uid: int, scope: string}|null null for anything that is not an authentic token */
    public static function verify(string $token): ?array
    {
        if (preg_match('/\A([1-9][0-9]{0,9})\.([A-Za-z]{1,24})\.([a-f0-9]{32})\z/', $token, $m) !== 1) {
            return null;
        }
        $uid = (int) $m[1];
        $valid = $m[2] === self::ALL || EmailKind::tryFrom($m[2]) !== null;

        return $valid && hash_equals(self::mac($uid, $m[2]), $m[3]) ? ['uid' => $uid, 'scope' => $m[2]] : null;
    }

    private static function mac(int $userId, string $scope): string
    {
        return substr(hash_hmac('sha256', 'unsubscribe|' . $userId . '|' . $scope, SigningKey::get()), 0, 32);
    }
}
