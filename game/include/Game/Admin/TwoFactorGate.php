<?php

declare(strict_types=1);

namespace Game\Admin;

use Core\Helper\WebService;

/**
 * The second step of an administrator sign-in.
 *
 * Two session records, neither of which is an authenticated session:
 *  - a pending sign-in: written after the password is right, valid five
 *    minutes, bound to the account, the client IP and a random token that the
 *    code form must echo back. It carries no rights; the player session
 *    (uid/user/pw keys) is only created by Session::login(), which asks this
 *    class for permission;
 *  - a grant: written once a code (or recovery code) is accepted, valid a
 *    minute, bound to account and IP, and spent by the very next Session::login().
 *
 * The gate applies to the administrator accounts (Support, Multihunter and the extra ones, see AdminAccounts) when the mode is
 * "required", or "optional" and that admin has enrolled. A sitter signing in to
 * such an account can never pass it.
 */
final class TwoFactorGate
{
    public const PENDING_TTL = 300;
    public const GRANT_TTL = 60;

    /** @param array<string, mixed> $session */
    public function __construct(
        private readonly TwoFactorStore $store,
        private array &$session,
        private readonly string $prefix,
        private readonly string $ip,
        private readonly int $now
    ) {
    }

    public static function fromRequest(): self
    {
        return new self(
            TwoFactorStore::fromEnvironment(),
            $_SESSION,
            WebService::fixSessionPrefix(''),
            WebService::ipAddress(),
            time()
        );
    }

    public function store(): TwoFactorStore
    {
        return $this->store;
    }

    public static function isAdminAccount(int $uid): bool
    {
        return AdminAccounts::isAdmin($uid);
    }

    /** Whether signing in to this account needs a second factor. */
    public function applies(int $uid): bool
    {
        if (!self::isAdminAccount($uid)) {
            return false;
        }
        $mode = $this->store->mode();

        return $mode === 'required' || ($mode === 'optional' && $this->store->isEnrolled($uid));
    }

    /**
     * The check Session::login() makes. Accounts the gate does not cover pass;
     * covered accounts pass only with a live grant (spent here), never as a sitter.
     */
    public function permitLogin(int $uid, bool $isSitter): bool
    {
        if (!$this->applies($uid)) {
            return true;
        }
        $grant = $this->session[$this->prefix . '2fa_grant'] ?? null;
        unset($this->session[$this->prefix . '2fa_grant']);

        return !$isSitter
            && is_array($grant)
            && ($grant['uid'] ?? null) === $uid
            && is_string($grant['ip'] ?? null) && hash_equals($grant['ip'], $this->ip)
            && is_int($grant['exp'] ?? null) && $grant['exp'] >= $this->now;
    }

    public function grant(int $uid): void
    {
        $this->session[$this->prefix . '2fa_grant'] = ['uid' => $uid, 'ip' => $this->ip, 'exp' => $this->now + self::GRANT_TTL];
    }

    /**
     * Starts the second step after a correct password and returns the form token.
     * An admin with no enrolment (required mode) is taken through enrolment first.
     */
    public function begin(int $uid, string $name, string $passwordHash): string
    {
        $token = bin2hex(random_bytes(16));
        $enrolled = $this->store->isEnrolled($uid);
        $this->session[$this->prefix . '2fa_pending'] = [
            'token' => $token,
            'uid' => $uid,
            'name' => $name,
            'hash' => $passwordHash,
            'ip' => $this->ip,
            'exp' => $this->now + self::PENDING_TTL,
            'state' => $enrolled ? 'verify' : 'enrol',
            'secret' => $enrolled ? '' : Totp::generateSecret(),
            'codes' => [],
        ];

        return $token;
    }

    /**
     * The pending sign-in when the submitted token matches and it is still
     * valid for this client; otherwise null (and an expired record is dropped).
     *
     * @return array{token: string, uid: int, name: string, hash: string, ip: string, exp: int, state: string, secret: string, codes: list<string>}|null
     */
    public function pending(?string $token): ?array
    {
        $pending = $this->session[$this->prefix . '2fa_pending'] ?? null;
        if (!is_array($pending) || !is_string($pending['token'] ?? null) || !is_int($pending['uid'] ?? null)) {
            return null;
        }
        if (($pending['exp'] ?? 0) < $this->now) {
            $this->clear();

            return null;
        }
        if ($token === null || !hash_equals($pending['token'], $token) || !hash_equals((string) $pending['ip'], $this->ip)) {
            return null;
        }

        return $pending;
    }

    /** @param array<string, mixed> $changes */
    public function update(array $changes): void
    {
        $key = $this->prefix . '2fa_pending';
        if (is_array($this->session[$key] ?? null)) {
            $this->session[$key] = array_replace($this->session[$key], $changes);
        }
    }

    public function clear(): void
    {
        unset($this->session[$this->prefix . '2fa_pending']);
    }
}
