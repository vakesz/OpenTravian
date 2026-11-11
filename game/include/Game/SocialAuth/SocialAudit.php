<?php

declare(strict_types=1);

namespace Game\SocialAuth;

use Game\Admin\AuditLog;

/**
 * Social sign-in events in the administrator audit trail (Game\Admin\AuditLog): the player is the actor, the
 * action is `social.<event>` and the target names the player and the provider. Tokens, subjects and emails never
 * reach the log.
 */
final class SocialAudit
{
    private function __construct()
    {
    }

    public static function record(string $event, int $userId, string $userName, ?Provider $provider, string $details = ''): void
    {
        AuditLog::record('social.' . $event, 'uid=' . $userId . ($provider === null ? '' : ' provider=' . $provider->value), $details, $userId, $userName);
    }
}
