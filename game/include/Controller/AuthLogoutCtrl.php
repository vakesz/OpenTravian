<?php

declare(strict_types=1);

namespace Controller;

use Core\Session;

/**
 * Reference-shaped logout action (`/auth/logout`). Ends the session only when the
 * per-session token is present, then returns the player to the local world login
 * (`/`). Bare `/logout` stays the guest 404 shell and never ends a session.
 */
class AuthLogoutCtrl extends OutOfGameCtrl
{
    public function __construct()
    {
        $session = Session::getInstance();
        if ($session->isValid() && Session::matchesLogoutToken($_GET['c'] ?? null)) {
            // Admin impersonation returns false when it restores the admin account.
            if (!$session->logout()) {
                $this->redirect('/dorf1.php');
            }
            $this->redirect('/');
        }

        if ($session->isValid()) {
            // Token missing or stale: keep the session (prefetch / stale bookmarks).
            $this->redirect('/dorf1.php');
        }

        $this->redirect('/');
    }
}
