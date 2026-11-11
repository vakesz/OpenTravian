<?php

declare(strict_types=1);

namespace Controller;

use Core\Database\DBI;
use Core\Helper\WebService;
use Core\Security\Password;
use Core\Session;
use Game\SocialAuth\LinkedIdentities;
use Game\SocialAuth\Provider;
use Game\SocialAuth\SocialAudit;
use Game\SocialAuth\SocialFlash;

/**
 * POST /oauth/{provider}/unlink from Options: removes the identity after the player confirms with their current
 * password. Sitters cannot unlink, and a wrong password is throttled.
 */
final class OAuthUnlinkCtrl extends GameCtrl
{
    public function __construct()
    {
        parent::__construct();
        $provider = Provider::tryFrom(is_string($_GET['oauthProvider'] ?? null) ? $_GET['oauthProvider'] : '');
        if ($provider === null || !WebService::isPost() || $this->session->isSitter()
            || !Session::matchesChecker($_POST[Session::getCheckerName()] ?? null)) {
            $this->redirect(OAuthLinkCtrl::OPTIONS_URL);
        }
        $flash = SocialFlash::fromGlobals();
        $userId = (int) $this->session->getPlayerId();
        $name = (string) $this->session->getName();
        $stored = DBI::getInstance()->fetchScalar('SELECT password FROM users WHERE id=?', [$userId], '');
        $given = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if (!throttle_public_form('oauth-unlink', 10, 600) || !is_string($stored) || $given === '' || !Password::verify($given, $stored)) {
            SocialAudit::record('unlink-refused', $userId, $name, $provider, 'password');
            $flash->setNotice('passwordWrong', $provider);
            $this->redirect(OAuthLinkCtrl::OPTIONS_URL);
        }
        if (LinkedIdentities::unlink($userId, $provider)) {
            SocialAudit::record('unlink', $userId, $name, $provider);
            $flash->setNotice('unlinked', $provider);
        }
        $this->redirect(OAuthLinkCtrl::OPTIONS_URL);
    }
}
