<?php

declare(strict_types=1);

namespace Game\SocialAuth;

use Core\Database\DBI;

/** Backed by the world database table account_oauth_identity (see database/world.sql, phase B). */
final class DbIdentityRepository implements IdentityRepository
{
    public function findUserId(Provider $provider, string $subject): ?int
    {
        $uid = DBI::getInstance()->fetchScalar(
            'SELECT uid FROM account_oauth_identity WHERE provider=? AND subject=? LIMIT 1',
            [$provider->value, $subject]
        );

        return is_numeric($uid) ? (int) $uid : null;
    }

    public function emailInUse(string $email): bool
    {
        $db = DBI::getInstance();
        $inUsers = (int) $db->fetchScalar('SELECT COUNT(id) FROM users WHERE email=?', [$email], 0);
        $pending = (int) $db->fetchScalar('SELECT COUNT(id) FROM activation WHERE email=?', [$email], 0);

        return $inUsers + $pending > 0;
    }

    public function providerLinked(int $userId, Provider $provider): bool
    {
        return (int) DBI::getInstance()->fetchScalar('SELECT COUNT(id) FROM account_oauth_identity WHERE uid=? AND provider=?', [$userId, $provider->value], 0) > 0;
    }

    public function link(int $userId, IdentityClaims $claims): bool
    {
        $now = time();

        DBI::getInstance()->run(
            'INSERT INTO account_oauth_identity (provider, subject, uid, email, email_verified, created, last_login)'
            . ' VALUES (?,?,?,?,?,?,?)',
            [$claims->provider->value, $claims->subject, $userId, $claims->email, $claims->emailVerified ? 1 : 0, $now, $now]
        );

        return true;
    }

    public function userEmail(int $userId): ?string
    {
        $row = DBI::getInstance()->fetchSingleRow('SELECT email FROM users WHERE id=? LIMIT 1', [$userId]);

        return is_array($row) ? strtolower(trim((string) ($row['email'] ?? ''))) : null;
    }

    public function userForLogin(int $userId): ?array
    {
        $db = DBI::getInstance();
        $row = $db->fetchSingleRow('SELECT id, name, password, access FROM users WHERE id=? LIMIT 1', [$userId]);
        // access=0 is a banned account (Session::banned()); a queued deletion must not log in either.
        if (!is_array($row) || (int) $row['access'] === 0
            || (int) $db->fetchScalar('SELECT COUNT(uid) FROM deleting WHERE uid=?', [$userId], 0) > 0) {
            return null;
        }

        return is_string($row['password'] ?? null)
            ? ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'password' => $row['password']]
            : null;
    }
}
