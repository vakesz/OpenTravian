<?php

declare(strict_types=1);

namespace Core;

use Core\Database\DBI;

/**
 * Persistent "remember me" login (the Travian Legends behaviour: a login
 * survives browser restarts for a long period).
 *
 * Cookie value is "<selector>:<validator>". Only sha256(validator) is stored.
 * Every successful use rotates the validator. The previous validator stays
 * valid for ROTATION_GRACE seconds so parallel tabs that present the old
 * cookie at the same moment are not logged out. The row carries a fingerprint
 * of the account password hash, so any password change (player, admin, reset)
 * invalidates all remembered logins without every writer having to know.
 */
final class RememberMe
{
    public const COOKIE = 'travian_remember';
    public const LIFETIME = 30 * 86400;
    public const ROTATION_GRACE = 60;

    public static function fingerprint(string $passwordHash): string
    {
        return hash('sha256', $passwordHash);
    }

    /** Issue a token for an owner login and set the cookie. */
    public static function issue(int $uid): void
    {
        $db = DBI::getInstance();
        $passwordHash = $db->fetchScalar('SELECT password FROM users WHERE id=?', $uid);
        if (!is_string($passwordHash) || $passwordHash === '') {
            return;
        }
        $db->run('DELETE FROM remember_tokens WHERE expires < ?', [time()]);
        $selector = bin2hex(random_bytes(9));
        $validator = bin2hex(random_bytes(32));
        $now = time();
        $db->run(
            'INSERT INTO remember_tokens (uid, selector, validator_hash, pw_fingerprint, expires, created, last_used)'
            . ' VALUES (?,?,?,?,?,?,?)',
            [$uid, $selector, hash('sha256', $validator), self::fingerprint($passwordHash), $now + self::LIFETIME, $now, $now]
        );
        self::setCookie($selector . ':' . $validator, $now + self::LIFETIME);
    }

    /**
     * Authenticate from the cookie. Returns [uid, passwordHash] or null.
     *
     * @return array{0: int, 1: string}|null
     * Rotates the token on success; drops only the presented selector on failure.
     */
    public static function consume(): ?array
    {
        $cookie = $_COOKIE[self::COOKIE] ?? null;
        if (!is_string($cookie) || preg_match('/^([a-f0-9]{18}):([a-f0-9]{64})$/D', $cookie, $m) !== 1) {
            if ($cookie !== null && !headers_sent()) {
                self::setCookie('', time() - 42000);
            }
            return null;
        }
        [, $selector, $validator] = $m;
        $db = DBI::getInstance();
        $row = $db->fetchSingleRow('SELECT * FROM remember_tokens WHERE selector=? AND expires>=?', [$selector, time()]);
        if (!$row) {
            self::setCookie('', time() - 42000);
            return null;
        }
        $hash = hash('sha256', $validator);
        $current = hash_equals((string)$row['validator_hash'], $hash);
        $previous = !$current
            && !empty($row['prev_validator_hash'])
            && (time() - (int)$row['rotated']) <= self::ROTATION_GRACE
            && hash_equals((string)$row['prev_validator_hash'], $hash);
        $user = ($current || $previous)
            ? $db->fetchSingleRow('SELECT id, password FROM users WHERE id=?', (int)$row['uid'])
            : false;
        if (!$user || !hash_equals((string)$row['pw_fingerprint'], self::fingerprint((string)$user['password']))) {
            $db->run('DELETE FROM remember_tokens WHERE selector=?', [$selector]);
            self::setCookie('', time() - 42000);
            return null;
        }
        if ($current) {
            $next = bin2hex(random_bytes(32));
            $now = time();
            $db->run(
                'UPDATE remember_tokens SET validator_hash=?, prev_validator_hash=?, rotated=?, last_used=?, expires=? WHERE selector=?',
                [hash('sha256', $next), $row['validator_hash'], $now, $now, $now + self::LIFETIME, $selector]
            );
            self::setCookie($selector . ':' . $next, $now + self::LIFETIME);
        }
        return [(int)$user['id'], (string)$user['password']];
    }

    /** Revoke the presented token (logout) and clear the cookie. */
    public static function revoke(): void
    {
        $cookie = $_COOKIE[self::COOKIE] ?? null;
        if (is_string($cookie) && preg_match('/^([a-f0-9]{18}):/', $cookie, $m) === 1) {
            DBI::getInstance()->run('DELETE FROM remember_tokens WHERE selector=?', [$m[1]]);
        }
        if (!headers_sent()) {
            self::setCookie('', time() - 42000);
        }
        unset($_COOKIE[self::COOKIE]);
    }

    public static function revokeAll(int $uid): void
    {
        DBI::getInstance()->run('DELETE FROM remember_tokens WHERE uid=?', [$uid]);
    }

    private static function setCookie(string $value, int $expires): void
    {
        if (headers_sent()) {
            return;
        }
        $params = function_exists('travian_session_cookie_params') ? travian_session_cookie_params() : [];
        setcookie(self::COOKIE, $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => !empty($params['secure']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        if ($value !== '') {
            $_COOKIE[self::COOKIE] = $value;
        }
    }
}
