<?php
require_once dirname(__DIR__) . '/include/env.php';
$gameInclude = dirname(__DIR__, 2) . '/include/Game/';
foreach (['AdminView', 'WorldSpeedProfile', 'FinishConstruction', 'Tribes', 'AdminUi', 'WorldOverrides', 'WorldSchemaVersion', 'SupportContact', 'SmtpConfig', 'Installer/AppDatabaseGrants', 'Installer/InstallInput', 'Installer/WorldInstaller'] as $gameClass) {
    require_once $gameInclude . $gameClass . '.php';
}

use Game\Installer\InstallInput;
use Game\Installer\WorldInstaller;

function setup_escape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function setup_start_session()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (function_exists('travian_session_cookie_params')) {
        session_set_cookie_params(travian_session_cookie_params());
        if (function_exists('travian_ensure_session_dir')) {
            travian_ensure_session_dir();
        }
    }
    if (!session_start()) {
        throw new RuntimeException('Could not start the setup confirmation session.');
    }
}

/**
 * File-based brute-force throttle for the setup token (Redis may not be configured yet).
 * Returns the seconds left in a lockout, or 0 when attempts are allowed.
 */
function setup_throttle_state($mutate)
{
    $path = rtrim(STATE_DIR, '/') . '/setup-throttle.json';
    $handle = @fopen($path, 'c+');
    if ($handle === false) {
        return 0;
    }
    flock($handle, LOCK_EX);
    $state = json_decode((string)stream_get_contents($handle), true);
    $state = is_array($state) ? $state : [];
    $state = $mutate($state);
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($state));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    @chmod($path, 0600);
    return $state;
}

function setup_throttle_remaining($ip)
{
    $limit = 5;
    $window = 900;
    $remaining = 0;
    $state = setup_throttle_state(function ($state) use ($ip, $limit, $window, &$remaining) {
        $now = time();
        $key = 'ip:' . $ip;
        // Old versions wrote a shared global bucket. It must never gate a
        // correct high-entropy setup token, so discard it when encountered.
        unset($state['global']);
        if (isset($state[$key]) && $now - $state[$key]['first'] >= $window) {
            unset($state[$key]);
        }
        if (isset($state[$key]) && $state[$key]['count'] >= $limit) {
            $remaining = max($remaining, $state[$key]['first'] + $window - $now);
        }
        return $state;
    });
    return $remaining;
}

function setup_throttle_record($ip, $success)
{
    setup_throttle_state(function ($state) use ($ip, $success) {
        $now = time();
        $key = 'ip:' . $ip;
        unset($state['global']);
        if ($success) {
            unset($state[$key]);
            return $state;
        }
        if (!isset($state[$key]) || $now - $state[$key]['first'] >= 900) {
            $state[$key] = ['count' => 0, 'first' => $now];
        }
        $state[$key]['count']++;
        return $state;
    });
}

const SETUP_TOKEN_MIN_LENGTH = 24;
$setupToken = trim((string)(getenv('TRAVIAN_SETUP_TOKEN') ?: ''));
$setupTokenTooShort = $setupToken !== '' && strlen($setupToken) < SETUP_TOKEN_MIN_LENGTH;
$appDatabaseUser = InstallInput::appDatabaseUser();
$repositoryRoot = WorldInstaller::repositoryRoot();
$clientIp = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$defaults = InstallInput::defaults();
$values = $defaults;
$view = \Game\AdminView::resolve($_COOKIE, $_GET, \Game\AdminView::SETUP_COOKIE, 'mode');
$values['setup_mode'] = $view['mode'];
if ($view['remember'] !== null && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    \Game\AdminView::remember($view['remember'], \Game\AdminView::SETUP_COOKIE);
}
$values['create_starter_player'] = '1';
$values['playable_tribes'] = \Game\Tribes::DEFAULT_PLAYABLE;
$values['profile_input'] = [];
$values['override_input'] = [];
$values['profile'] = [];
$values['overrides'] = [];
$values['smtp_password'] = '';
$errors = [];
$clearConfirmationDatabases = [];
$clearConfirmationNonce = '';
$clearConfirmed = false;
$setupTribeNames = [1 => 'Romans', 2 => 'Teutons', 3 => 'Gauls', 6 => 'Egyptians', 7 => 'Huns', 8 => 'Spartans', 9 => 'Vikings'];
$finished = is_file(SETUP_LOCK_FILE) || WorldInstaller::installationComplete();

if (!$finished && $_SERVER['REQUEST_METHOD'] === 'POST') {
    setup_start_session();
    $submitted = $_POST;
    if (isset($_POST['setup_action']) && $_POST['setup_action'] === 'confirm_clear') {
        $pending = $_SESSION['travian_setup_clear'] ?? null;
        $nonce = isset($_POST['clear_nonce']) ? (string)$_POST['clear_nonce'] : '';
        unset($_SESSION['travian_setup_clear']);
        if (!is_array($pending) || !isset($pending['values'], $pending['nonce'], $pending['expires']) ||
            !is_array($pending['values']) || !is_string($pending['nonce']) ||
            !hash_equals($pending['nonce'], $nonce) || (int)$pending['expires'] < time()) {
            $errors[] = 'The database-clear confirmation expired or was invalid. Enter the setup details again.';
        } else {
            $submitted = $pending['values'];
            $clearConfirmed = true;
        }
    }

    [$values, $inputErrors] = InstallInput::fromSubmission($submitted, $defaults);
    $providedToken = isset($submitted['setup_token']) ? trim((string)$submitted['setup_token']) : '';

    if (!$errors) {
        $lockedOut = setup_throttle_remaining($clientIp);
        if ($setupTokenTooShort) {
            $errors[] = 'TRAVIAN_SETUP_TOKEN must be at least ' . SETUP_TOKEN_MIN_LENGTH . ' characters long. Generate one with `openssl rand -hex 32`.';
        } elseif ($lockedOut > 0) {
            $errors[] = 'Too many incorrect setup tokens. Try again in ' . (int)ceil($lockedOut / 60) . ' minute(s).';
        } elseif ($setupToken === '' || !hash_equals($setupToken, $providedToken)) {
            setup_throttle_record($clientIp, false);
            $errors[] = 'The setup token is missing or incorrect. Set TRAVIAN_SETUP_TOKEN in the server environment and enter that value here.';
        } else {
            setup_throttle_record($clientIp, true);
            $errors = $inputErrors;
        }
    }

    if (!$errors && !$clearConfirmed) {
        try {
            $clearConfirmationDatabases = WorldInstaller::nonEmptyDatabases(
                $values['database_host'],
                $values['database_user'],
                $values['database_password'],
                [$values['account_database'], $values['world_database']]
            );
            if ($clearConfirmationDatabases) {
                $clearConfirmationNonce = bin2hex(random_bytes(32));
                $_SESSION['travian_setup_clear'] = [
                    'values' => array_merge($submitted, ['setup_token' => $providedToken]),
                    'nonce' => $clearConfirmationNonce,
                    'expires' => time() + 600,
                ];
            }
        } catch (Throwable $exception) {
            $errors[] = 'Could not inspect the configured databases. Check the database credentials and try again.';
        }
    }

    if (!$errors && !$clearConfirmationDatabases) {
        try {
            WorldInstaller::install($values, $appDatabaseUser, $clearConfirmed);
            header('Location: /setup/');
            exit;
        } catch (Throwable $exception) {
            $values['database_password'] = '';
            $errors[] = $clearConfirmed
                ? 'Setup did not finish. Check the PHP error log. The confirmed database clear remains in effect, and everything this retry created was removed.'
                : 'Setup did not finish. Check the PHP error log. Everything this attempt created was removed, so you can retry; existing database tables are never overwritten.';
        }
    }
}

header('Content-Type: text/html; charset=UTF-8');
require dirname(__DIR__) . '/include/setup-view.php';
