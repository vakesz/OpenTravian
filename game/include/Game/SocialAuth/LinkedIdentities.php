<?php

declare(strict_types=1);

namespace Game\SocialAuth;

use Core\Database\DBI;

/** The social identities a player has linked to their account (Options), and what an administrator sees of them. */
final class LinkedIdentities
{
    private function __construct()
    {
    }

    /** @return list<array{provider: Provider, email: string, verified: bool, created: int, lastLogin: int}> */
    public static function forUser(int $userId): array
    {
        $rows = DBI::getInstance()->run(
            'SELECT provider, email, email_verified, created, last_login FROM account_oauth_identity WHERE uid=? ORDER BY provider',
            [$userId]
        )->fetchAll(\PDO::FETCH_ASSOC);
        $identities = [];
        foreach ($rows as $row) {
            $provider = Provider::tryFrom((string) $row['provider']);
            if ($provider !== null) {
                $identities[] = [
                    'provider' => $provider,
                    'email' => (string) $row['email'],
                    'verified' => (int) $row['email_verified'] === 1,
                    'created' => (int) $row['created'],
                    'lastLogin' => (int) $row['last_login'],
                ];
            }
        }

        return $identities;
    }

    /** Removes the identity; false when the player has none from this provider. */
    public static function unlink(int $userId, Provider $provider): bool
    {
        $statement = DBI::getInstance()->run('DELETE FROM account_oauth_identity WHERE uid=? AND provider=?', [$userId, $provider->value]);

        return $statement->rowCount() > 0;
    }
}
