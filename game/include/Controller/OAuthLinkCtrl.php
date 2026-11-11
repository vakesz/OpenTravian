<?php

declare(strict_types=1);

namespace Controller;

use Core\Helper\WebService;
use Core\Session;
use Game\SocialAuth\DbIdentityRepository;
use Game\SocialAuth\FlowError;
use Game\SocialAuth\OAuthFlowFactory;
use Game\SocialAuth\Provider;
use Game\SocialAuth\SocialAudit;
use Game\SocialAuth\SocialFlash;

/**
 * POST /oauth/{provider}/link from Options: starts the provider's sign-in to attach that identity to the signed-in
 * player's account. The callback (OAuthCallbackCtrl) completes it for the player recorded in the flow, so it never
 * depends on the session cookie Apple's cross-site POST may not carry. Sitters cannot link.
 */
final class OAuthLinkCtrl extends GameCtrl
{
    public const OPTIONS_URL = '/options?s=2';

    public function __construct()
    {
        parent::__construct();
        $provider = Provider::tryFrom(is_string($_GET['oauthProvider'] ?? null) ? $_GET['oauthProvider'] : '');
        if ($provider === null || !WebService::isPost() || $this->session->isSitter()
            || !Session::matchesChecker($_POST[Session::getCheckerName()] ?? null)) {
            $this->redirect(self::OPTIONS_URL);
        }
        $flash = SocialFlash::fromGlobals();
        $userId = (int) $this->session->getPlayerId();
        if (!throttle_public_form('oauth-link', 20, 600)) {
            $flash->setNotice('error', $provider, FlowError::RateLimited);
            $this->redirect(self::OPTIONS_URL);
        }
        if ((new DbIdentityRepository())->providerLinked($userId, $provider)) {
            $flash->setNotice('error', $provider, FlowError::ProviderLinked);
            $this->redirect(self::OPTIONS_URL);
        }
        $started = OAuthFlowFactory::create()->begin($provider, null, '', $userId);
        if ($started === null) {
            $flash->setNotice('error', $provider, FlowError::Unavailable);
            $this->redirect(self::OPTIONS_URL);
        }
        SocialAudit::record('link-started', $userId, (string) $this->session->getName(), $provider);
        OAuthStartCtrl::remember($started);
        $this->redirect($started['url']);
    }
}
