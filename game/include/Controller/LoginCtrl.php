<?php

namespace Controller;

use Core\Config;
use Core\Database\DB;
use Core\Database\GlobalDB;
use Core\Helper\TimezoneHelper;
use Core\Helper\WebService;
use Core\Session;
use Game\Admin\AuditLog;
use Game\Admin\QrCode;
use Game\Admin\Totp;
use Game\Admin\TwoFactorGate;
use Model\LoginModel;
use resources\View\OutOfGameView;
use resources\View\PHPBatchView;

use function appendTimer;
use function getDisplay;

class LoginCtrl extends OutOfGameCtrl
{
    /** @var \resources\View\PHPBatchView|null */
    private $LoginView;
    /** @var bool */
    private $isAdmin          = false;

    public function __construct()
    {
        parent::__construct();
        if (isset($_GET['handshake']) && is_string($_GET['handshake'])) {
            $db = DB::getInstance();
            $token = trim($_GET['handshake']);
            if (preg_match('/^[a-f0-9]{64}$/D', $token) === 1) {
                $findStatement = $db->run(
                    "SELECT * FROM login_handshake WHERE token=? LIMIT 1",
                    [hash('sha256', $token)]
                );
                $find = $findStatement ? $findStatement->get_result() : false;
            } else {
                $find = false;
            }
            if ($find && $find->num_rows) {
                $find = $find->fetch_assoc();
                $db->run("DELETE FROM login_handshake WHERE id=? AND token=?", [
                    (int)$find['id'],
                    hash('sha256', $token),
                ]);
                if ((time() - $find['time']) <= 60) {
                    $user = $db->run("SELECT name, password FROM users WHERE id=? LIMIT 1", [
                        (int)$find['uid'],
                    ])->get_result();
                    if ($user->num_rows) {
                        $user = $user->fetch_assoc();
                        Session::getInstance()->login($find['uid'], $user['name'], $user['password'], $find['isSitter'] == 1);
                        WebService::redirect("/dorf1.php");
                    }
                }
            }
        }
        // Keeps login.php only as the tokenized SSO hand-off. Its bare
        // legacy endpoint resolves to the modern public lobby at the root.
        $requestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        if ($requestPath === '/login.php' && $_GET === [] && !WebService::isPost()) {
            WebService::redirect('/');
        }
        $this->isAdmin = defined("IS_ADMIN") && (bool)IS_ADMIN == true;
        $this->view = new OutOfGameView();
        $this->configurePublicHomepageLocale();
        $this->view->vars['bodyCssClass'] = 'perspectiveBuildings login';
        // The public reference keeps a separate mobile drawer. It must not
        // reuse the authenticated navigation because that expects player data.
        $this->view->vars['showGuestNavBar'] = true;
        // The public login is a self-contained V2 dialog. Keeping it outside
        // of the generic content-page wrapper prevents the obsolete title bar
        // and contextual-help button from being rendered behind the dialog.
        $this->view->vars['containerCssClass'] = 'loginSceneContainer';
        $this->view->vars['contentCssClass'] = 'loginSceneContainer';
        $config = Config::getInstance();
        if ($this->isAdmin && time() < $config->game->start_time) {
            $this->beforeGame();
        }
        $this->loginAction();
    }

    /**
     * @param int $length
     */
    public function generate_password($length = 20): string
    {
        $arr = range(chr(33), chr(126));
        unset($arr[1], $arr[6], $arr[11], $arr[63]);
        $pwd = implode('', array_rand(array_flip($arr), $length));
        return $pwd;
    }

    private function loginAction(): void
    {
        global $globalConfig;
        $title = Config::getProperty("settings", "availableLanguages")->{Config::getProperty(
            "settings",
            "selectedLang"
        )}->title;
        $this->LoginView = new PHPBatchView("system/login");
        $this->LoginView->vars['WelcomeText'] = sprintf(T("Login", "Welcome"), $title);
        $this->LoginView->vars['worldTitle'] = $title;
        $credentials = WebService::isPost() ? $_POST : $_GET;
        $name = $credentials['name'] ?? $_SESSION[WebService::fixSessionPrefix('user')] ?? '';
        $this->LoginView->vars['name'] = is_string($name) ? $name : '';
        $this->LoginView->vars['password'] = isset($credentials['password']) && is_string($credentials['password'])
            ? $credentials['password']
            : '';
        $this->LoginView->vars['userError'] = $this->LoginView->vars['pwError'] = '';
        $this->LoginView->vars['success'] = false;
        $this->LoginView->vars['newPassErr'] = '';
        $this->LoginView->vars['newPassErrorRule'] = '';
        $this->LoginView->vars['isAdmin'] = $this->isAdmin;
        $this->LoginView->vars['publicLoginUrl'] = $this->publicHomepageUrl('/');
        $this->LoginView->vars['publicRecoveryUrl'] = $this->publicHomepageUrl('/?forgotPassword=true');
        $this->LoginView->vars['emailAvailable'] = \Game\SmtpConfig::isConfigured();
        $this->LoginView->vars['publicRegisterUrl'] = \Game\PublicHomepageLocale::registrationUrl();
        $pwEmail = $_POST['pw_email'] ?? '';
        $this->LoginView->vars['pw_email'] = is_string($pwEmail)
            ? filter_var($pwEmail, FILTER_SANITIZE_EMAIL)
            : '';
        $forgotPassword = $_REQUEST['forgotPassword'] ?? '';
        $this->LoginView->vars['forgotPassword'] = is_string($forgotPassword)
            && in_array($forgotPassword, ['true', '1'], true);
        $isLoginGet = WebService::isGet()
            && (array_key_exists('name', $_GET) || array_key_exists('password', $_GET));
        if (WebService::isPost() && is_string($_POST['twoFactorToken'] ?? null)) {
            $this->twoFactorStep((string)$_POST['twoFactorToken']);
        } elseif (WebService::isPost() || $isLoginGet) {
            if ($this->LoginView->vars['forgotPassword']) {
                $this->passwordForgotten();
            } else {
                $this->post();
            }
        }
        $this->view->vars['content'] .= $this->LoginView->output();
    }

    /**
     * @return bool|null
     */
    private function passwordForgotten()
    {
        if (!\Game\SmtpConfig::isConfigured()) {
            // Nothing would be sent, so do not pretend a reset mail is on its way.
            $this->LoginView->vars['newPassErr'] = T('Login', 'pwResetUnavailable');
            return false;
        }
        if (!throttle_public_form('pwforgot', 5, 3600)) {
            $this->LoginView->vars['newPassErr'] = T('Login', 'Password recovery cooldown');
            return false;
        }
        $pwEmail = $this->LoginView->vars['pw_email'];
        $error = &$this->LoginView->vars['newPassErr'];
        if (empty($pwEmail)) {
            $error = T('Login', 'Please enter your email address');
            $this->LoginView->vars['newPassErrorRule'] = 'notEmpty';
        } elseif (filter_var($pwEmail, FILTER_VALIDATE_EMAIL) === false) {
            $error = T('Login', 'Please enter a valid email address. E.g. mymail@provider.com');
            $this->LoginView->vars['newPassErrorRule'] = 'isEmail';
        } else {
            $model = new LoginModel();
            $find = $model->findLogin($pwEmail);
            if ($find['type'] == 1 && isset($find['row']['id'])) {
                $model->addNewPassword($find['row']);
            }
            // The response remains identical whether or not the account exists.
            $this->LoginView->vars['success'] = true;
        }
        return null;
    }

    private function redirectAfterLogin(): void
    {
        $target = '/dorf1.php';
        $referer = isset($_SERVER['HTTP_REFERER']) ? (string)$_SERVER['HTTP_REFERER'] : '';
        if ($referer !== '' && !preg_match('/[\x00-\x1f\\\\]/', $referer)) {
            $parts = parse_url($referer);
            $host = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
            if ($parts && isset($parts['host']) && isset($parts['scheme']) && in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
                $refHost = strtolower($parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');
                $path = isset($parts['path']) ? $parts['path'] : '';
                if ($refHost === $host && $path !== '' && $path[0] === '/'
                    && !in_array($path, ['/', '/index.php', '/login.php', '/login', '/logout'], true) && strpos($path, '//') === false) {
                    $target = $path . (isset($parts['query']) ? '?' . $parts['query'] : '');
                }
            }
        }
        $this->redirect($target);
    }

    /**
     * @return bool|null
     */
    private function post()
    {
        if (empty($this->LoginView->vars['name'])) {
            $this->LoginView->vars['userError'] = T("Login", "userErrors.empty");
        }
        if (empty($this->LoginView->vars['password'])) {
            $this->LoginView->vars['pwError'] = T("Login", "pwErrors.empty");
        }
        if (empty($this->LoginView->vars['name']) || empty($this->LoginView->vars['password'])) {
            return false;
        }

        $scope = $this->isAdmin ? 'admin' : 'game';
        $m = new LoginModel();
        $find = $m->findLogin($this->LoginView->vars['name']);
        $accountId = isset($find['row']['id']) ? (int)$find['row']['id'] : null;
        $buckets = login_throttle_buckets($scope, $accountId, $this->LoginView->vars['name']);
        $reservation = login_throttle_reserve($buckets);
        if ($reservation === false) {
            $this->LoginView->vars['pwError'] = T("Login", "tooManyAttempts");
            return false;
        }
        $pairKey = count($buckets) > 1 ? $buckets[0][0] : null;

        if (!$find['type'] || !isset($find['row']['id'])) {
            if (!login_throttle_complete($reservation, true)) {
                $this->LoginView->vars['pwError'] = T("Login", "tooManyAttempts");
                return false;
            }
            $this->LoginView->vars['userError'] = T("Login", "userErrors.notFound");
            return false;
        }
        if ($find['row']['id'] > 2 && $this->isAdmin) {
            if (!login_throttle_complete($reservation, true)) {
                $this->LoginView->vars['pwError'] = T("Login", "tooManyAttempts");
                return false;
            }
            $this->LoginView->vars['userError'] = T("Login", "userErrors.notFound");
            return false;
        }
        $result = $m->checkLogin($this->LoginView->vars['password'], $find);
        if (!login_throttle_complete($reservation, $result === 3, $result === 3 ? null : $pairKey)) {
            $this->LoginView->vars['pwError'] = T("Login", "tooManyAttempts");
            return false;
        }
        $success = true;


        if ($result <> 3) {
            switch ($find['type']) {
                case 1:
                    if ($result <> 0 && $find['row']['last_owner_login_time'] <= time() - 21 * 86400) {
                        $this->LoginView->vars['pwError'] = T("Login", "pwErrors.error21Days");
                        $success = false;
                        break;
                    }
                    $passwordHash = $m->getAuthenticatedPasswordHash() ?: $find['row']['password'];
                    $twoFactor = TwoFactorGate::fromRequest();
                    if ($twoFactor->applies((int)$find['row']['id'])) {
                        if ($result <> 0) {
                            // A sitter has no second factor, so a sitter can never enter an admin account that needs one.
                            AuditLog::record('sign-in-refused', 'sitter', 'A sitter tried an administrator account that needs two-factor sign-in.', (int)$find['row']['id'], (string)$this->LoginView->vars['name']);
                            $this->LoginView->vars['pwError'] = T("Login", "pwErrors.wrong");
                            $success = false;
                            break;
                        }
                        $token = $twoFactor->begin((int)$find['row']['id'], $this->LoginView->vars['name'], (string)$passwordHash);
                        $this->showTwoFactor($twoFactor, $token, '');
                        break;
                    }
                    Session::getInstance()->login(
                        $find['row']['id'],
                        $this->LoginView->vars['name'],
                        $passwordHash,
                        $result <> 0
                    );
                    $this->redirectAfterLogin();
                    break;
                case 2:
                    $_SESSION[WebService::fixSessionPrefix('token')] = $find['row']['token'];
                    $this->redirect("/activate.php");
                    break;
            }
            return (bool)$success;
        }
        if (TwoFactorGate::isAdminAccount((int)$find['row']['id'])) {
            AuditLog::record('sign-in-failed', 'password', '', (int)$find['row']['id'], (string)$this->LoginView->vars['name']);
        }
        $this->LoginView->vars['pwError'] = T("Login", "pwErrors.wrong");
        return null;
    }

    /**
     * Second step of an administrator sign-in: renders the code, enrolment or
     * recovery-code form for a pending sign-in.
     */
    private function showTwoFactor(TwoFactorGate $gate, string $token, string $error): void
    {
        $pending = $gate->pending($token);
        $view = new PHPBatchView('system/loginTwoFactor');
        $view->vars['action'] = $this->isAdmin ? '/admin.php' : $this->publicHomepageUrl('/');
        $view->vars['token'] = $token;
        $view->vars['state'] = $pending['state'] ?? 'verify';
        $view->vars['error'] = $error;
        $view->vars['secret'] = $pending['secret'] ?? '';
        $view->vars['codes'] = $pending['codes'] ?? [];
        $view->vars['qr'] = '';
        if (($pending['state'] ?? '') === 'enrol') {
            $issuer = (string)(Config::getProperty('settings', 'availableLanguages')->{Config::getProperty('settings', 'selectedLang')}->title ?? 'OpenTravian');
            $view->vars['qr'] = QrCode::svg(Totp::uri($pending['secret'], (string)$pending['name'], $issuer), 5);
        }
        $this->LoginView = $view;
    }

    private function twoFactorStep(string $token): void
    {
        $gate = TwoFactorGate::fromRequest();
        $pending = $gate->pending($token);
        if ($pending === null) {
            $this->LoginView->vars['pwError'] = T("Login", "pwErrors.wrong");
            return;
        }
        $uid = $pending['uid'];
        $store = $gate->store();
        if ($pending['state'] === 'recovery') {
            if (isset($_POST['acknowledged'])) {
                $this->completeTwoFactor($gate, $pending);
            }
            $this->showTwoFactor($gate, $token, '');
            return;
        }

        $buckets = login_throttle_buckets('admin2fa', $uid);
        $reservation = login_throttle_reserve($buckets);
        if ($reservation === false) {
            AuditLog::record('two-factor-throttled', $pending['state'], '', $uid, (string)$pending['name']);
            $this->showTwoFactor($gate, $token, (string)T("Login", "tooManyAttempts"));
            return;
        }
        $method = 'authenticator code';
        $code = is_string($_POST['code'] ?? null) ? trim($_POST['code']) : '';
        $accepted = false;
        $error = 'That code is not valid.';
        if ($pending['state'] === 'verify') {
            if (preg_match('/^\d{3}\s?\d{3}$/', $code) === 1) {
                $accepted = $store->verifyCode($uid, $code);
            } else {
                $method = 'recovery code';
                $accepted = $store->verifyRecoveryCode($uid, $code);
            }
        } elseif (Totp::verify($pending['secret'], $code) !== null) {
            $codes = $store->enrol($uid, $pending['secret']);
            if ($codes === null) {
                $error = 'The two-factor settings could not be saved. Check that the state directory is writable.';
            } else {
                $store->verifyCode($uid, $code); // spend the confirming code
                AuditLog::record('two-factor-enrolled', '', 'at sign-in', $uid, (string)$pending['name']);
                $gate->update(['state' => 'recovery', 'codes' => $codes]);
                $accepted = true;
            }
        }
        AuditLog::record($accepted ? 'two-factor-passed' : 'two-factor-failed', $pending['state'], $method, $uid, (string)$pending['name']);
        if (!login_throttle_complete($reservation, !$accepted, $accepted ? $buckets[0][0] : null)) {
            $this->showTwoFactor($gate, $token, (string)T("Login", "tooManyAttempts"));
            return;
        }
        if ($accepted && $pending['state'] === 'verify') {
            $this->completeTwoFactor($gate, $pending);
        }
        $this->showTwoFactor($gate, $token, $accepted ? '' : $error);
    }

    /** @param array{uid: int, name: string, hash: string} $pending */
    private function completeTwoFactor(TwoFactorGate $gate, array $pending): void
    {
        $gate->grant($pending['uid']);
        $gate->clear();
        Session::getInstance()->login($pending['uid'], $pending['name'], $pending['hash'], false);
        $this->redirectAfterLogin();
    }

    private function beforeGame(): void
    {
        if (isset($_GET['registered']) && $_GET['registered'] === 'true') {
            $this->view->vars['content'] .= '
<div class="greenbox greenBackground success" style="background-color: #E5EECC;">
        <div class="greenbox-top"></div>
        <div class="greenbox-content">' . T("Login", "registrationSuccessful") . '</div>
        <div class="greenbox-bottom"></div>
        <div class="clear"></div>
    </div>';
            if (!$this->activationEnabled()) {
                $this->view->vars['content'] .= '<br/>
<div class="greenbox greenBackground green" style="background-color: #E5EECC;">
        <div class="greenbox-top"></div>
        <div class="greenbox-content" style="color: red">' . T("EVerify", "LOGIN_BEFORE_GAME_DESCRIPTION_VERIFY") . '</div>
        <div class="greenbox-bottom"></div>
        <div class="clear"></div>
    </div>';
            }
        }
        $config = Config::getInstance();
        $remainingTime = $config->game->start_time - time();
        $remainingTimer = null;
        if ($remainingTime < 86400) {
            $remainingTimer = T(
                "Login",
                "Server will start in"
            ) . ' <span class="date">' . appendTimer($config->game->start_time - time()) . ' ' . T(
                "inGame",
                "Hours"
            ) . '</span>';
        }
        $this->view->vars['content'] .= '
<div id="worldStartInfo">
    <div id="countdownContent">
        <div class="worldStartInfo">
            <div class="countdownContent">
                <div class="startsIn">
                    ' . T("Login", "Server will start in") . '
                    <span class="date">' . TimezoneHelper::autoDate($config->game->start_time, true) . '</span>
                    GMT <span class="timezone">' . getTimeZone() . '</span>
                </div>
                <div class="startsIn">' . $remainingTimer . '</div>
            </div>
        </div>
    </div>
</div>';
    }

    private function activationEnabled(): bool
    {
        $activationEnabled = false;
        $server = GlobalDB::getInstance()->query("SELECT activation FROM gameServers WHERE id=" . getWorldUniqueId());
        if ($server->num_rows) {
            $server = $server->fetch_assoc();
            $activationEnabled = $server['activation'] == 1;
        }
        return $activationEnabled;
    }
}
