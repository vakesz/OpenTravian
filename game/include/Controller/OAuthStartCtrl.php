<?php

declare(strict_types=1);

namespace Controller;

use Core\Helper\WebService;
use Core\Session;
use Game\PublicHomepageLocale;
use Game\SocialAuth\FlowError;
use Game\SocialAuth\OAuthFlowFactory;
use Game\SocialAuth\Provider;
use Game\SocialAuth\SocialFlash;

/**
 * POST /oauth/{provider}/start. The router supplies the provider id as
 * $_GET['oauthProvider']. The form carries the guest CSRF token in its body
 * (never in the URL), so another site cannot start a sign-in in a visitor's
 * browser. A GET only returns to the registration overlay. There is
 * deliberately no return-URL parameter: the destinations are fixed server-side.
 */
final class OAuthStartCtrl extends OutOfGameCtrl
{
    public const BINDING_COOKIE = 'travian_oauth_apple';

    public function __construct()
    {
        parent::__construct();
        if ($this->session->isValid()) {
            $this->redirect('/dorf1.php');
        }
        $locale = PublicHomepageLocale::fromRequest();
        $referral = self::referral();
        $provider = Provider::tryFrom(is_string($_GET['oauthProvider'] ?? null) ? $_GET['oauthProvider'] : '');
        $flash = SocialFlash::fromGlobals();

        if (!WebService::isPost()) {
            $this->redirect(self::registrationUrl($locale, $referral));
        }
        if ($provider === null || !Session::matchesChecker($_POST[Session::getCheckerName()] ?? null)) {
            $flash->set(FlowError::InvalidState);
            $this->redirect(self::registrationUrl($locale, $referral));
        }
        if (!throttle_public_form('oauth-start', 20, 600)) {
            $flash->set(FlowError::RateLimited);
            $this->redirect(self::registrationUrl($locale, $referral));
        }
        $started = OAuthFlowFactory::create()->begin($provider, $locale, $referral);
        if ($started === null) {
            $flash->set(FlowError::Unavailable);
            $this->redirect(self::registrationUrl($locale, $referral));
        }
        self::remember($started);
        $this->redirect($started['url']);
    }

    /**
     * Binds the callback to this browser: the state goes into the session and, for Apple's cross-site POST, a
     * separate cookie carries the binding secret.
     *
     * @param array{url:string,state:string,binding:?string} $started
     */
    public static function remember(array $started): void
    {
        $_SESSION[WebService::fixSessionPrefix('oauth_state')] = $started['state'];
        if ($started['binding'] !== null) {
            // Apple answers with a cross-site POST, so the session cookie is not
            // sent. This separate cookie binds the callback to this browser.
            // (The __Host- prefix would force Path=/, so the path scopes it instead.)
            setcookie(self::BINDING_COOKIE, $started['binding'], [
                'expires' => time() + 600,
                'path' => '/oauth/apple/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'None',
            ]);
        }
    }

    /** The refer-a-friend code, only in its canonical shape. */
    public static function referral(): string
    {
        $code = $_GET['uc'] ?? '';

        return is_string($code) && preg_match('/\A[A-Za-z0-9_-]{1,5}_[0-9]{1,9}\z/', $code) === 1 ? $code : '';
    }

    public static function registrationUrl(?string $locale, string $referral): string
    {
        $path = '/homepage' . ($referral !== '' ? '?uc=' . rawurlencode($referral) : '') . '#registration';

        return PublicHomepageLocale::append($path, $locale);
    }
}
