<?php

declare(strict_types=1);

namespace Controller;

use Core\Helper\WebService;
use Core\Session;
use Game\SocialAuth\PendingSignup;

/**
 * POST /oauth/cancel: closing the registration overlay abandons a pending
 * social sign-up, so reopening it shows the email step again. CSRF-protected;
 * answers 204 with no body.
 */
final class OAuthCancelCtrl extends OutOfGameCtrl
{
    public function __construct()
    {
        parent::__construct();
        if (WebService::isPost() && Session::matchesChecker($_POST[Session::getCheckerName()] ?? null)) {
            PendingSignup::fromGlobals()->clear();
            http_response_code(204);

            return;
        }
        http_response_code(WebService::isPost() ? 403 : 405);
    }
}
