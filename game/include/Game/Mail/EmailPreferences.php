<?php

declare(strict_types=1);

namespace Game\Mail;

use Core\Database\DBI;

/** Which mail a player has opted into. The newsletter is users.newsletter, the rest are email_preference rows. */
final class EmailPreferences
{
    private function __construct()
    {
    }

    public static function enabled(int $userId, EmailKind $kind): bool
    {
        $db = DBI::getInstance();
        if ($kind === EmailKind::Newsletter) {
            return (int) $db->fetchScalar('SELECT newsletter FROM users WHERE id=?', [$userId], 0) === 1;
        }

        return (int) $db->fetchScalar('SELECT enabled FROM email_preference WHERE uid=? AND kind=?', [$userId, $kind->value], 0) === 1;
    }

    public static function set(int $userId, EmailKind $kind, bool $enabled): void
    {
        $db = DBI::getInstance();
        if ($kind === EmailKind::Newsletter) {
            $db->run('UPDATE users SET newsletter=? WHERE id=?', [$enabled ? 1 : 0, $userId]);

            return;
        }
        $db->run('INSERT INTO email_preference (uid, kind, enabled) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled)', [$userId, $kind->value, $enabled ? 1 : 0]);
    }

    /** Switches off one kind, or (scope "all") every kind including the newsletter. */
    public static function unsubscribe(int $userId, string $scope): void
    {
        if ($scope === UnsubscribeToken::ALL) {
            DBI::getInstance()->run('UPDATE users SET newsletter=0 WHERE id=?', [$userId]);
            DBI::getInstance()->run('DELETE FROM email_preference WHERE uid=?', [$userId]);

            return;
        }
        $kind = EmailKind::tryFrom($scope);
        if ($kind !== null) {
            self::set($userId, $kind, false);
        }
    }
}
