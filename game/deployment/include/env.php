<?php
define("PUBLIC_PATH", dirname(__DIR__) . "/public/");

/**
 * Directory for generated state (config, lock, logs, backups).
 * Docker sets TRAVIAN_STATE_DIR to a named volume; without it the legacy
 * in-tree layout is used.
 */
function travian_state_dir()
{
    $dir = getenv('TRAVIAN_STATE_DIR');
    return ($dir !== false && $dir !== '') ? rtrim($dir, '/') : null;
}

/**
 * Session cookie parameters shared by every place that starts or refreshes
 * the session cookie. A secure public origin must not force a Secure cookie
 * onto a distinct plain-HTTP local origin: browsers then discard that cookie
 * and the next navigation appears to log the player out. Direct HTTPS still
 * wins, and a configured HTTPS public host is recognized by its Host header
 * when TLS terminates before this container.
 */
function travian_session_cookie_params()
{
    $baseUrl = (string)getenv('TRAVIAN_BASE_URL');
    $httpsRequest = !empty($_SERVER['HTTPS'])
        && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    $baseHost = strtolower((string)parse_url($baseUrl, PHP_URL_HOST));
    $requestHost = strtolower((string)parse_url(
        '//' . (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''),
        PHP_URL_HOST
    ));
    $secure = $httpsRequest
        || (stripos($baseUrl, 'https://') === 0
            && $baseHost !== ''
            && $requestHost !== ''
            && hash_equals($baseHost, $requestHost));
    return [
        // Persistent cookie: logins survive browser restarts (see README, Sessions).
        'lifetime' => 30 * 86400,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

/**
 * File-handler sessions need a directory. Redis sessions (the container
 * default) do not; creating a directory from a tcp:// save path would not
 * be a directory at all.
 */
function travian_ensure_session_dir()
{
    if (ini_get('session.save_handler') !== 'files') {
        return;
    }
    $path = session_save_path();
    if (($semi = strrpos($path, ';')) !== false) {
        $path = substr($path, $semi + 1);
    }
    if ($path !== '' && !is_dir($path)) {
        @mkdir($path, 0700, true);
    }
}

$travianStateDir = travian_state_dir();
if ($travianStateDir !== null) {
    $localConnectionFile = $travianStateDir . '/connection.local.php';
    define("STATE_DIR", $travianStateDir . '/');
    define("ERROR_LOG_FILE", $travianStateDir . '/error_log.log');
    define("GLOBAL_CONFIG_FILE", getenv('TRAVIAN_GLOBAL_CONFIG') ?: $travianStateDir . '/globalConfig.php');
    define("SMTP_CONFIG_FILE", getenv('TRAVIAN_SMTP_CONFIG') ?: $travianStateDir . '/smtp.local.php');
    define("BACKUP_PATH", $travianStateDir . '/backups/');
} else {
    $localConnectionFile = __DIR__ . '/connection.local.php';
    define("STATE_DIR", __DIR__ . '/');
    define("ERROR_LOG_FILE", __DIR__ . '/error_log.log');
    define("GLOBAL_CONFIG_FILE", getenv('TRAVIAN_GLOBAL_CONFIG') ?: dirname(__DIR__, 3) . '/globalConfig.php');
    define("SMTP_CONFIG_FILE", getenv('TRAVIAN_SMTP_CONFIG') ?: dirname(__DIR__, 3) . '/smtp.local.php');
    define("BACKUP_PATH", dirname(__DIR__) . '/backups/');
}
define("CONNECTION_FILE", is_file($localConnectionFile) ? $localConnectionFile : __DIR__ . '/connection.php');
define("CONNECTION_LOCAL_FILE", $localConnectionFile);
define("SETUP_LOCK_FILE", rtrim(STATE_DIR, '/') . '/setup.lock');
define("CONFIG_CUSTOM_FILE", __DIR__ . '/config.custom.php');
define("FILTERING_PATH", dirname(__DIR__, 3) . '/config/filters/');
