<?php

declare(strict_types=1);

namespace Controller;

use Core\Helper\WebService;
use Core\Session;
use Game\SocialAuth\DbIdentityRepository;
use Game\SocialAuth\FlowError;
use Game\SocialAuth\FlowResult;
use Game\SocialAuth\OAuthFlowFactory;
use Game\SocialAuth\PendingSignup;
use Game\SocialAuth\Provider;
use Game\SocialAuth\SocialAudit;
use Game\SocialAuth\SocialFlash;

/**
 * /oauth/{provider}/callback: GET for Google and Facebook, form_post for Apple.
 * Known identity: log in. New identity: park it in the session and hand the
 * browser to the registration overlay (name selection, then /activate.php).
 */
final class OAuthCallbackCtrl extends OutOfGameCtrl
{
    public function __construct()
    {
        parent::__construct();
        $stateKey = WebService::fixSessionPrefix('oauth_state');
        $sessionState = is_string($_SESSION[$stateKey] ?? null) ? $_SESSION[$stateKey] : null;
        unset($_SESSION[$stateKey]);

        $provider = Provider::tryFrom(is_string($_GET['oauthProvider'] ?? null) ? $_GET['oauthProvider'] : '');
        $flash = SocialFlash::fromGlobals();
        $fallback = OAuthStartCtrl::registrationUrl(null, '');
        if ($provider === null || !($provider->usesFormPost() ? WebService::isPost() : WebService::isGet())) {
            $flash->set(FlowError::InvalidState);
            $this->redirect($fallback);
        }
        if (self::failureLimitReached()) {
            $flash->set(FlowError::RateLimited);
            $this->redirect($fallback);
        }

        $params = $provider->usesFormPost() ? $_POST : $_GET;
        $cookie = $_COOKIE[OAuthStartCtrl::BINDING_COOKIE] ?? null;
        if ($provider->usesFormPost()) {
            // Single use, whatever the outcome.
            setcookie(OAuthStartCtrl::BINDING_COOKIE, '', ['expires' => time() - 3600, 'path' => '/oauth/apple/', 'secure' => true, 'httponly' => true, 'samesite' => 'None']);
        }
        $result = OAuthFlowFactory::create()->complete($provider, $params, $sessionState, is_string($cookie) ? $cookie : null);
        if ($result->kind === 'error') {
            // Only failed callbacks consume the budget: successful sign-ins are free.
            throttle_public_form('oauth-callback', 20, 600);
        }
        $this->finish($result, $flash, $provider);
    }

    /** Read-only check of the failure budget (the counter is a plain Redis integer). */
    private static function failureLimitReached(): bool
    {
        try {
            $count = \Core\Caching\Caching::singleton()->get(throttle_key('oauth-callback', 'ip', WebService::ipAddress()));
        } catch (\Throwable) {
            return true;
        }

        return is_numeric($count) && (int) $count >= 20;
    }

    private function finish(FlowResult $result, SocialFlash $flash, Provider $provider): void
    {
        if ($result->linkUserId !== null) {
            $this->finishLink($result, $flash, $provider);
        }
        if ($this->session->isValid()) {
            $this->redirect('/dorf1.php');
        }
        $registration = OAuthStartCtrl::registrationUrl($result->locale, $result->referral);
        if ($result->kind === 'login' && $result->userId !== null) {
            $user = (new DbIdentityRepository())->userForLogin($result->userId);
            if ($user !== null) {
                SocialAudit::record('login', $user['id'], $user['name'], $provider);
                // Session::login() regenerates the session id (fixation) and issues remember-me.
                Session::getInstance()->login($user['id'], $user['name'], $user['password']);
                $this->redirect('/dorf1.php');
            }
            $flash->set(FlowError::InvalidToken);
            $this->redirect($registration);
        }
        if ($result->kind === 'signup' && $result->claims !== null) {
            PendingSignup::fromGlobals()->store($result->claims);
            $this->redirect($registration);
        }
        $flash->set($result->error ?? FlowError::InvalidToken);
        $this->redirect($registration);
    }

    /**
     * A link flow ends in Options: the identity goes to the player recorded in the flow (never matched by email),
     * every refusal is a notice there, and every outcome is in the audit trail. A race on the unique keys counts
     * as already linked.
     */
    private function finishLink(FlowResult $result, SocialFlash $flash, Provider $provider): void
    {
        $userId = (int) $result->linkUserId;
        $user = (new DbIdentityRepository())->userForLogin($userId);
        $name = $user['name'] ?? '';
        // A browser signed in as somebody else than the player who started the flow links nothing.
        $sameBrowser = !$this->session->isValid() || (int) $this->session->getPlayerId() === $userId;
        if ($result->kind === 'link' && $result->claims !== null && $user !== null && $sameBrowser) {
            try {
                (new DbIdentityRepository())->link($userId, $result->claims);
                SocialAudit::record('link', $userId, $name, $provider);
                $flash->setNotice('linked', $provider);
            } catch (\PDOException) {
                SocialAudit::record('link-refused', $userId, $name, $provider, FlowError::ProviderLinked->value);
                $flash->setNotice('error', $provider, FlowError::ProviderLinked);
            }
        } else {
            $error = $result->error ?? FlowError::InvalidState;
            SocialAudit::record('link-refused', $userId, $name, $provider, $error->value);
            $flash->setNotice('error', $provider, $error);
        }
        $this->redirect(OAuthLinkCtrl::OPTIONS_URL);
    }
}
