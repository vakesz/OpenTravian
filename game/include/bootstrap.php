<?php
use Core\Caching\Caching;
use Core\Config;
use Core\Database\DB;
use Core\Database\GlobalDB;
use Core\Helper\WebService;
if (!function_exists("geoip_country_code_by_name")) {
    /**
     * @param string $hostname
     */
    function geoip_country_code_by_name($hostname): bool
    {
        return false;
    }
}
if (!extension_loaded("redis")) {
    die("Redis extension not available.");
}
$start_time = microtime(true);
if (php_sapi_name() != 'cli') {
    set_time_limit(120);
    ob_start();
    // Dependency outages (cache or database down, e.g. while the stack restarts)
    // get a plain 503; anything else is logged and answered with a generic 500.
    set_exception_handler(function ($exception) {
        $unavailable = $exception instanceof \Core\Caching\CacheUnavailableException
            || ($exception instanceof \mysqli_sql_exception
                && in_array((int)$exception->getCode(), [1040, 1203, 2002, 2003, 2006, 2013], true));
        if (function_exists('logError')) {
            logError(get_class($exception) . ': ' . $exception->getMessage() . ' at ' . $exception->getFile() . ':' . $exception->getLine());
        } else {
            error_log((string)$exception);
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code($unavailable ? 503 : 500);
            header('Content-Type: text/plain; charset=UTF-8');
            if ($unavailable) {
                header('Retry-After: 5');
            }
        }
        echo $unavailable ? 'Server unavailable, try again shortly.' : 'Internal server error.';
    });
    // Machine endpoints (payment webhooks) authenticate by signature and must not open a session.
    if (session_status() !== PHP_SESSION_ACTIVE && !defined('TRAVIAN_WITHOUT_SESSION')) {
        if (function_exists('travian_session_cookie_params')) {
            session_set_cookie_params(travian_session_cookie_params());
            if (function_exists('travian_ensure_session_dir')) {
                travian_ensure_session_dir();
            }
        }
        // A request without a session cookie is a first visit or a cookieless crawler. Its Redis entry
        // lasts a day; the next request, which carries the cookie, renews it for the full lifetime.
        $firstContact = !isset($_COOKIE[session_name()]);
        if (!session_start($firstContact ? ['gc_maxlifetime' => 86400] : [])) {
            logError("Could not start session.");
            die("Couldn't start session.");
        }
    }
}
define("ROOT_PATH", dirname(__DIR__) . DIRECTORY_SEPARATOR);
define("PUBLIC_INTERNAL_PATH", dirname(__DIR__) . "/deployment/public/");
define("INCLUDE_PATH", __DIR__ . DIRECTORY_SEPARATOR);
define("RESOURCES_PATH", INCLUDE_PATH . "resources" . DIRECTORY_SEPARATOR);
define("LOCALE_PATH", RESOURCES_PATH . "Translation" . DIRECTORY_SEPARATOR);
define("TEMPLATES_PATH", RESOURCES_PATH . "Templates" . DIRECTORY_SEPARATOR);
define("SVG_TEMPLATES", RESOURCES_PATH . "Svg" . DIRECTORY_SEPARATOR);
define("SVG_COMMON_TEMPLATES", RESOURCES_PATH . "Svg" . DIRECTORY_SEPARATOR. "svg.php");
define("SVG_FIELD_TEMPLATES", RESOURCES_PATH . "Svg" . DIRECTORY_SEPARATOR. "resourceFieldSvg.php");
define("SVG_BUILDING_TEMPLATES", RESOURCES_PATH . "Svg" . DIRECTORY_SEPARATOR. "buildFieldSvg.php");
require_once INCLUDE_PATH . "Core" . DIRECTORY_SEPARATOR . 'Autoloader.php';
require_once INCLUDE_PATH . "functions.general.php";
// Counts web requests for the metrics endpoint (best effort; not for the command line).
\Core\RequestCounter::register();
// Developer request timing: a no-op unless TRAVIAN_DEV_TOOLS=1 and the browser opted in with ?devFooter=1.
\Core\Dev\RequestTiming::start();
global $config;
$config = Config::getInstance();
if (!property_exists($config, 'db')) {
    die("Installation is not completed.");
}
$db = DB::getInstance();
{
    $result = $db->query("SELECT * FROM config");
    if (!$result->num_rows) {
        logError("No config row found.");
        exit("We are having issues, please try again in a moment. E1");
    }
    $config->dynamic = (object)$result->fetch_assoc();
    if (property_exists($config, 'startTime')) {
        logError("No column found in config row.");
        exit("We are having issues, please try again in a moment. E2");
    }
    $config->game->start_time = $config->dynamic->startTime;
    $config->settings->worldUniqueId = $config->dynamic->worldUniqueId;
    define("MAP_SIZE", $config->dynamic->map_size);
}
if (property_exists($config->dynamic, 'isRestore') && $config->dynamic->isRestore && !defined("IS_UPDATE")) {
    exit("We are having issues, please try again in a moment. E3");
}
require(INCLUDE_PATH . "config/config.after.php");
if (!$config->dynamic->installed) {
    $config->dynamic->maintenance = true;
}
function check_ip_access(): void
{
    $ip = \Core\Helper\IPTracker::normalize(WebService::ipAddress());
    if ($ip === null) {
        return;
    }
    $cache = Caching::getInstance();
    // A new ban can take up to the cache TTL (seconds) to apply.
    $banned = $cache->get("IPBan:$ip");
    if ($banned === false) {
        $banned = GlobalDB::getInstance()->fetchScalar('SELECT 1 FROM banIP WHERE ip=? AND (blockTill=0 OR blockTill > ?) LIMIT 1', [$ip, time()]) !== false ? 1 : 0;
        $cache->set("IPBan:$ip", $banned, 1440);
    }
    if ($banned === 1) {
        exit("You are not allowed to access here.");
    }
}

if (php_sapi_name() != 'cli') {
    Caching::getInstance();
    check_ip_access();
}
