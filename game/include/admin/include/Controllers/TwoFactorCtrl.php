<?php

use Core\Helper\WebService;
use Core\Session;
use Game\Admin\QrCode;
use Game\Admin\Totp;
use Game\Admin\TwoFactorGate;
use Game\Admin\TwoFactorStore;
use Game\AdminUi;

/**
 * Operator page for administrator two-factor sign-in: the mode (administrator
 * account 0 only), and enrolment, recovery codes and removal for the signed-in
 * administrator account. The secret and recovery-code hashes live in the 0600
 * state file (admin-2fa.local.json), never in SQL.
 */
class TwoFactorCtrl
{
    private ?string $error = null;
    private ?string $saved = null;
    /** @var list<string> */
    private array $newCodes = [];
    private TwoFactorStore $store;
    private int $adminId;

    public function __construct()
    {
        $this->store = TwoFactorStore::fromEnvironment();
        $this->adminId = (int) Session::getInstance()->getAdminUid();
        if (WebService::isPost() && Session::validateChecker() && TwoFactorGate::isAdminAccount($this->adminId)) {
            $this->handlePost();
        }
        Dispatcher::getInstance()->appendContent(AdminUi::page(
            'Two-factor sign-in',
            'Require or enrol authenticator codes for control-panel sign-in. Secrets stay in protected state, never SQL.',
            ($this->error !== null ? AdminUi::notice($this->error, 'error') : '')
            . ($this->saved !== null ? AdminUi::notice($this->saved, 'success') : '')
            . $this->recoveryCodes()
            . AdminUi::box('Mode', $this->settings())
            . AdminUi::box('Authenticator for this account', $this->account())
        ));
    }

    private function handlePost(): void
    {
        $action = is_string($_POST['twoFactorAction'] ?? null) ? $_POST['twoFactorAction'] : '';
        $key = WebService::fixSessionPrefix('2fa_panel_secret');
        switch ($action) {
            case 'mode':
                $mode = is_string($_POST['mode'] ?? null) ? $_POST['mode'] : '';
                if ($this->adminId !== 0) {
                    $this->error = 'Only the main administrator account can change the mode.';
                } elseif (!$this->store->setMode($mode)) {
                    $this->error = 'Unable to save the protected two-factor settings. Check that the state directory is writable.';
                } else {
                    $this->saved = 'Two-factor sign-in is now ' . $mode . '.';
                    AdminLog::getInstance()->addLog('Set the two-factor mode to ' . $mode . '.', 'two-factor-mode');
                }
                break;
            case 'start':
                $_SESSION[$key] = Totp::generateSecret();
                break;
            case 'confirm':
                $secret = $_SESSION[$key] ?? null;
                $code = is_string($_POST['code'] ?? null) ? $_POST['code'] : '';
                if (!is_string($secret) || Totp::verify($secret, $code) === null) {
                    $this->error = 'That code is not valid. Check the clock on your device and try again.';
                    break;
                }
                $codes = $this->store->enrol($this->adminId, $secret);
                if ($codes === null) {
                    $this->error = 'Unable to save the protected two-factor settings. Check that the state directory is writable.';
                    break;
                }
                $this->store->verifyCode($this->adminId, $code);
                unset($_SESSION[$key]);
                AdminLog::getInstance()->addLog('Enrolled an authenticator for account ' . $this->adminId . '.', 'two-factor-enrolled');
                $this->newCodes = $codes;
                $this->saved = 'Authenticator enrolled.' . ($this->store->mode() === 'off' ? ' The mode is off, so codes are not asked yet: set it to optional or required.' : '');
                break;
            case 'regenerate':
            case 'disable':
                if (!$this->currentCodeAccepted()) {
                    break;
                }
                if ($action === 'disable') {
                    $this->store->disable($this->adminId);
                    $this->saved = 'The authenticator was removed from this account.';
                    AdminLog::getInstance()->addLog('Removed the authenticator from account ' . $this->adminId . '.', 'two-factor-removed');
                } else {
                    $this->newCodes = $this->store->regenerateRecoveryCodes($this->adminId) ?? [];
                    $this->saved = 'New recovery codes created; the old ones no longer work.';
                    AdminLog::getInstance()->addLog('Created new recovery codes for account ' . $this->adminId . '.', 'two-factor-recovery-codes');
                }
                break;
        }
    }

    /** A current authenticator or recovery code, rate limited like the sign-in. */
    private function currentCodeAccepted(): bool
    {
        $buckets = login_throttle_buckets('admin2fa', $this->adminId);
        $reservation = login_throttle_reserve($buckets);
        if ($reservation === false) {
            $this->error = 'Too many attempts. Try again later.';

            return false;
        }
        $code = is_string($_POST['code'] ?? null) ? trim($_POST['code']) : '';
        $accepted = $this->store->isEnrolled($this->adminId) && (
            preg_match('/^\d{3}\s?\d{3}$/', $code) === 1
                ? $this->store->verifyCode($this->adminId, $code)
                : $this->store->verifyRecoveryCode($this->adminId, $code)
        );
        login_throttle_complete($reservation, !$accepted, $accepted ? $buckets[0][0] : null);
        if (!$accepted) {
            $this->error = 'Enter a current authenticator code or a recovery code.';
        }

        return $accepted;
    }

    private function recoveryCodes(): string
    {
        if ($this->newCodes === []) {
            return '';
        }

        return AdminUi::box(
            'Recovery codes',
            '<p>Keep these somewhere safe. Each works once if you lose your authenticator, and they are not shown again.</p>'
            . '<pre>' . AdminUi::esc(implode("\n", $this->newCodes)) . '</pre>'
        );
    }

    private function settings(): string
    {
        $stored = $this->store->storedMode();
        $effective = $this->store->mode();
        $form = '<p>Administrator accounts sign in with a password and, when enabled, a code from an authenticator app (RFC 6238, no external service). '
            . '<strong>Off</strong>: no codes. <strong>Optional</strong>: an administrator who enrolls is always asked for a code. '
            . '<strong>Required</strong>: every administrator must enroll at the next sign-in. Sitters can never sign in to a protected administrator account.</p>';
        if ($this->store->path() === '') {
            $form .= AdminUi::notice('No state directory is configured, so two-factor settings cannot be stored.', 'error');
        }
        if ($effective !== $stored) {
            $form .= AdminUi::notice('The TRAVIAN_ADMIN_TWO_FACTOR environment setting lowers the mode to ' . $effective . ' (stored: ' . $stored . '). It can only lower the mode, as an emergency break-glass; remove it to apply the stored mode.', 'error');
        }
        if ($this->adminId !== 0) {
            return $form . '<p>Current mode: <strong>' . AdminUi::esc($effective) . '</strong>. Only the main administrator account can change it.</p>';
        }

        return $form . '<form method="post" action="admin.php?action=twoFactor" class="adminInline">' . Session::getCheckerInput()
            . '<input type="hidden" name="twoFactorAction" value="mode">'
            . AdminUi::select('mode', 'Mode', ['off' => 'Off', 'optional' => 'Optional', 'required' => 'Required'], $stored)
            . AdminUi::button('Save', 'green') . '</form>';
    }

    private function account(): string
    {
        if (!TwoFactorGate::isAdminAccount($this->adminId)) {
            return '<p>Two-factor sign-in covers the administrator accounts only.</p>';
        }
        $action = 'admin.php?action=twoFactor';
        $post = static fn (string $name): string => '<form method="post" action="' . $action . '" class="adminInline">'
            . Session::getCheckerInput() . '<input type="hidden" name="twoFactorAction" value="' . $name . '">';
        if ($this->store->isEnrolled($this->adminId)) {
            $left = $this->store->recoveryCodesLeft($this->adminId);

            return '<p>An authenticator is enrolled for this account. Recovery codes left: <strong>' . $left . '</strong>.</p>'
                . $post('regenerate') . AdminUi::input('code', 'Current code', '', ['autocomplete' => 'one-time-code', 'inputmode' => 'numeric'])
                . AdminUi::button('New recovery codes', 'green') . '</form>'
                . $post('disable') . AdminUi::input('code', 'Current code', '', ['autocomplete' => 'one-time-code', 'inputmode' => 'numeric'])
                . AdminUi::button('Remove the authenticator', 'grey') . '</form>';
        }
        $secret = $_SESSION[WebService::fixSessionPrefix('2fa_panel_secret')] ?? null;
        if (!is_string($secret)) {
            return '<p>No authenticator is enrolled for this account.</p>' . $post('start') . AdminUi::button('Set up an authenticator', 'green') . '</form>';
        }
        $uri = Totp::uri($secret, 'admin-' . $this->adminId, 'OpenTravian');

        return '<p>Scan the code with an authenticator app, or type the secret into it, then enter the 6-digit code it shows.</p>'
            . '<div class="twoFactorQr">' . QrCode::svg($uri, 5) . '</div>'
            . '<p>Secret: <code>' . AdminUi::esc(implode(' ', str_split($secret, 4))) . '</code></p>'
            . $post('confirm') . AdminUi::input('code', 'Code', '', ['autocomplete' => 'one-time-code', 'inputmode' => 'numeric'])
            . AdminUi::button('Confirm', 'green') . '</form>';
    }
}
