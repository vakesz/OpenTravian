<?php

use Core\Caching\Caching;
use Core\Config;
use Core\ErrorHandler;
use Core\Helper\TimezoneHelper;
use Core\Helper\WebService;
use Core\Locale;
use Core\Security\LoginAttemptLimiter;
use Core\Session;
use Model\DailyQuestModel;
use Model\Quest;

/**
 * @param int $width
 * @param int $height
 * @return (bool|\GdImage)
 */
function imagecreatetransparent($width, $height)
{
    $block = imagecreatetruecolor($width, $height);
    imagealphablending($block, false);
    imagecolortransparent($block, imagecolorallocate($block, 0, 0, 0));
    $background = imagecolorallocatealpha($block, 255, 255, 255, 127);
    imagefilledrectangle($block, 0, 0, $width, $height, $background);
    imagealphablending($block, true);
    return $block;
}

/**
 * @param string $name
 * @return mixed
 */
function getDisplay($name)
{
    $config = Config::getInstance();
    return property_exists($config->display, $name) ? $config->display->$name : false;
}

/**
 * @param string $name
 * @return mixed
 */
function getCustom($name)
{
    $config = Config::getInstance();
    return property_exists($config->custom, $name) ? $config->custom->$name : false;
}

/**
 * @param string $name
 * @return mixed
 */
function getGame($name)
{
    $config = Config::getInstance();
    return property_exists($config->game, $name) ? $config->game->$name : false;
}

/**
 * @param string $error
 * @param array<mixed>|list<int|string>|list<mixed> $parameters
 * @return mixed
 */
function logError($error, $parameters = [])
{
    $text = $parameters === [] ? $error : vsprintf($error, $parameters);
    ErrorHandler::getInstance()->log($text);
    return $text;
}

/**
 * @param array<int, string> $list
 * @return mixed
 */
function shuffle_assoc($list)
{
    if (!is_array($list)) {
        return $list;
    }

    $keys = array_keys($list);
    shuffle($keys);
    $random = [];
    foreach ($keys as $key) {
        $random[$key] = $list[$key];
    }
    return $random;
}

/**
 * @return (float|int)
 */
function getGameElapsedSeconds()
{
    $config = Config::getInstance();
    return time() - $config->game->start_time;
}

/**
 * @return (float|int)
 */
function getGameElapsedMiliSeconds()
{
    $config = Config::getInstance();
    return miliseconds() - $config->game->start_time * 1000;
}

/**
 * @param string $text
 * @return bool|string
 */
function ConvertToUTF8($text)
{
    $encoding = mb_detect_encoding($text, mb_detect_order(), false);
    if ($encoding == 'UTF-8') {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }
    $out = iconv(mb_detect_encoding($text, mb_detect_order(), false), 'UTF-8//IGNORE', $text);
    return $out;
}

/**
 * Local abuse throttling (replaces the former captcha hooks).
 * Buckets are [key, maxFailures] pairs; counters live in Redis for $window seconds.
 *
 * @param mixed $scope
 * @param string $kind
 * @param bool|string $subject
 */
function throttle_key($scope, $kind, $subject): string
{
    return 'thr:' . $scope . ':' . $kind . ':' . sha1(strtolower((string) $subject));
}

/**
 * @param string $scope
 * @param int|null $account
 * @param mixed $unknownIdentity
 * @return list<list<mixed>>
 */
function login_throttle_buckets($scope, $account, $unknownIdentity = null)
{
    $limits = $scope === 'admin' ? ['pair' => 3, 'ip' => 5] : ['pair' => 5, 'ip' => 20];
    $ip = WebService::ipAddress();
    $buckets = [[throttle_key('login-' . $scope, 'ip', $ip), $limits['ip']]];
    // A resolved immutable user ID makes every case/Unicode spelling that the
    // database accepts share one pair bucket. Unknown identities retain a
    // Unicode-aware pair bucket to keep repeated fake names from consuming the
    // whole IP allowance.
    if ($account !== null) {
        array_unshift($buckets, [throttle_key('login-' . $scope, 'pair', (int) $account . '|' . $ip), $limits['pair']]);
    } elseif ($unknownIdentity !== null) {
        $identity = LoginAttemptLimiter::canonicalIdentity((string) $unknownIdentity);
        array_unshift($buckets, [
            throttle_key('login-' . $scope, 'pair', 'unknown:' . $identity . '|' . $ip),
            $limits['pair'],
        ]);
    }
    return $buckets;
}

/**
 * Reserve an authentication attempt before password verification. Redis
 * failure deliberately denies the attempt instead of disabling the limit.
 *
 * @param array<mixed> $buckets
 * @return array<string, array<int, list<int|string>>|string>|bool
 */
function login_throttle_reserve(array $buckets)
{
    try {
        return LoginAttemptLimiter::reserve(Caching::singleton(), $buckets);
    } catch (\Throwable $e) {
        error_log('login throttle unavailable: ' . $e->getMessage());
        return false;
    }
}

/**
 * @param array<mixed> $reservation
 * @param bool $failed
 * @param mixed $clearPairKey
 */
function login_throttle_complete(array $reservation, $failed, $clearPairKey = null): bool
{
    try {
        return LoginAttemptLimiter::complete(Caching::singleton(), $reservation, (bool) $failed, 900, $clearPairKey);
    } catch (\Throwable $e) {
        error_log('login throttle unavailable: ' . $e->getMessage());
        return false;
    }
}

/**
 * @param array<mixed> $buckets
 * @param bool $failClosed
 */
function throttle_is_limited(array $buckets, $failClosed = true): bool
{
    try {
        $cache = Caching::getInstance();
        foreach ($buckets as $bucket) {
            if ((int) $cache->get($bucket[0]) >= $bucket[1]) {
                return true;
            }
        }
    } catch (\Exception $e) {
        error_log('throttle unavailable: ' . $e->getMessage());
        return (bool) $failClosed;
    }
    return false;
}

/**
 * @param array<mixed> $buckets
 * @param int $window
 */
function throttle_hit(array $buckets, $window = 900): void
{
    try {
        $cache = Caching::getInstance();
        foreach ($buckets as $bucket) {
            $cache->set($bucket[0], (int) $cache->get($bucket[0]) + 1, $window);
        }
    } catch (\Exception $e) {
        error_log('throttle unavailable: ' . $e->getMessage());
    }
}

/**
 * @param mixed $key
 */
function throttle_clear($key): void
{
    try {
        Caching::getInstance()->delete($key);
    } catch (\Exception $e) {
        error_log('throttle unavailable: ' . $e->getMessage());
    }
}

/**
 * Limit for public forms (support, ban appeal): $max submissions per $window seconds per IP.
 * Returns true when the request is allowed and counts it.
 *
 * @param string $scope
 * @param int $max
 * @param int $window
 */
function throttle_public_form($scope, $max = 5, $window = 3600): bool
{
    try {
        return Caching::getInstance()->incrementWithinLimit(
            throttle_key($scope, 'ip', WebService::ipAddress()),
            (int) $max,
            (int) $window
        );
    } catch (\Throwable $e) {
        error_log('throttle unavailable: ' . $e->getMessage());
        return false;
    }
}

/**
 * @return mixed
 */
function getCheckerInput()
{
    return Session::getCheckerInput();
}

/**
 * @param string $string
 * @return array<string>|string|null
 */
function clean_string_from_white($string)
{
    $s = trim($string);
    $s = ConvertToUTF8($s); // drop all non utf-8 characters
    // this is some bad utf-8 byte sequence that makes mysql complain - control and formatting i think
    $s = preg_replace(
        '/(?>[\x00-\x1F]|\xC2[\x80-\x9F]|\xE2[\x80-\x8F]{2}|\xE2\x80[\xA4-\xA8]|\xE2\x81[\x9F-\xAF])/',
        ' ',
        $s
    );
    $s = preg_replace('/\s+/', ' ', $s); // reduce all multiple whitespace to a single space
    return $s;
}

function make_seed(): int
{
    $seed = hrtime(true);
    if (!is_int($seed)) {
        $seed = (int) (microtime(true) * 1000000);
    }
    // mt_srand() requires an integer seed. Keeping it inside the signed
    // 32-bit range also works on older PHP builds and avoids PHP 8.5's
    // float-to-int deprecation warning in the automation loop.
    return $seed & 0x7fffffff;
}

function delete_session_cookie(): void
{
    if (ini_get('session.use_cookies')) {
        $params = function_exists('travian_session_cookie_params')
            ? travian_session_cookie_params()
            : session_get_cookie_params();
        unset($params['lifetime']);
        setcookie(session_name(), '', $params + ['expires' => time() - 42000]);
    }
}

/**
 * @param list<int> $resources
 */
function buildResourcesReward($resources): string
{
    $resources = Quest::getInstance()->multiply($resources);
    $stockStatus = [
        1 => sprintf('[STOCK_CSS_%s_%s]', 1, $resources[0]),
        sprintf('[STOCK_CSS_%s_%s]', 2, $resources[1]),
        sprintf('[STOCK_CSS_%s_%s]', 3, $resources[2]),
        sprintf('[STOCK_CSS_%s_%s]', 4, $resources[3]),
    ];

    $HTML = null;
    $HTML .= '<div class="inlineIconList resourceWrapper">';
    for ($i = 1; $i <= 4; ++$i) {
        $HTML .= '<div class="inlineIcon resources" title="">';
        $HTML .=
            '<i class="r' .
            $i .
            ' questRewardTypeResource questRewardType' .
            [1 => 'Wood', 'Clay', 'Iron', 'Crop'][$i] .
            '"></i>';
        $HTML .=
            '<span class="value questRewardValue ' .
            $stockStatus[$i] .
            '">' .
            number_format_x($resources[$i - 1]) .
            '</span>';
        $HTML .= '</div>';
    }
    $HTML .= '</div>';
    return $HTML;
}

/**
 * @param mixed $x
 * @param mixed $after
 * @return mixed
 */
function number_format_x($x, $after = 1e15)
{
    //    if (abs($x) > $after) {
    //        return number_to_string($x);
    //    }
    if (!getDisplay('separateNumbers3By3') || !is_numeric($x) || $x == '?') {
        return $x;
    }
    return number_format($x, 0, ',', ',');
}

/**
 * Formats a value for fixed-width HUD cells without losing normal Travian
 * number grouping at ordinary world speeds.  At large local speed profiles,
 * raw billions no longer fit in a stock-bar cell; compact suffixes preserve
 * both the value's order of magnitude and the surrounding layout.
 *
 * @param mixed $number
 * @param mixed $threshold
 * @return mixed
 */
function number_format_compact($number, $threshold = 1e6)
{
    if (!is_numeric($number) || abs((float) $number) < $threshold) {
        return number_format_x($number);
    }

    return number_to_string($number, $threshold);
}

/**
 * @return mixed
 */
function isInstantFinishEnabled()
{
    return Config::getInstance()->extraSettings->generalOptions->finishTraining->enabled;
}

function buildingBenefitTitleAttribute(string $icon): string
{
    static $known = [
        'culturePoints_medium', 'population_medium', 'constructionTime_medium', 'warehouseCap_medium',
        'granaryCap_medium', 'crannyCap_small', 'infantryBonusTime_medium', 'troopVisibility_small',
        'defenceBonus_medium', 'stabilityBonus_small', 'cavalryBonusTime_medium', 'siegeTime_small',
        'merchantCap_small', 'maxTraps_medium', 'troopSpeed_medium', 'healTime_medium', 'lumberBonus_small',
        'clayBonus_small', 'ironBonus_small', 'cropBonus_medium',
    ];

    return in_array($icon, $known, true)
        ? ' title="' . htmlspecialchars((string)T('Buildings', 'benefit.' . $icon), ENT_QUOTES, 'UTF-8') . '"'
        : '';
}

/**
 * @param mixed $seconds
 * @param int $type
 * @param bool $noTimerOnTimeout
 * @param bool $critical
 */
function appendTimer($seconds, $type = 0, $noTimerOnTimeout = false, $critical = false): string
{
    if ($noTimerOnTimeout && $seconds <= 0) {
        return 'N/A';
    }
    // A persisted order can be due for up to one worker cadence before it is
    // consumed. Countdown UI must show that boundary as zero, never as the
    // malformed negative clock generated by secondsToString(-1).
    if ($type == 0 && is_numeric($seconds) && $seconds < 0) {
        $seconds = 0;
    }
    if ($type == 0) {
        return '<span class="timer' .
            ($critical ? ' crit' : '') .
            '" counting="down" value="' .
            $seconds .
            '" data-value="' .
            $seconds .
            '">' .
            secondsToString($seconds) .
            '</span>';
    }
    // Server-time clock (Layout #servertime) emits format="24h".
    return '<span format="24h" class="timer' .
        ($critical ? ' crit' : '') .
        '" counting="up" value="' .
        $seconds .
        '">' .
        secondsToString($seconds) .
        '</span>';
}

/**
 * Converts a positive troop/trap/healing duration from the active training
 * clock (seconds, milliseconds, or nanoseconds) to countdown seconds.
 *
 * A live sub-second queue remains visible as one second in whole-second HUD
 * chrome. Zero still means that no training row exists.
 *
 * @param (float|int)|float|int $duration
 */
function trainingTimeDisplaySeconds($duration): int
{
    if (!is_numeric($duration) || (int)$duration <= 0) {
        return 0;
    }

    $rate = Config::getInstance()->game->useNanoseconds
        ? 1_000_000_000
        : (Config::getInstance()->game->useMilSeconds ? 1_000 : 1);
    $duration = (int)$duration;

    return max(1, intdiv($duration + $rate - 1, $rate));
}

/**
 * @return mixed
 */
function getDirection()
{
    return get_language_properties('direction');
}

/**
 * Root-absolute building tab URL:
 * `/build.php?id=<slot>&gid=<gid>&<param>=<tab>` (raw, escape on output).
 * The root-absolute path keeps the link independent of the global <base>.
 *
 * @param int|string $tab
 */
function buildTabUrl(int $slot, int $gid, string $param, $tab): string
{
    return '/build.php?id=' . $slot . '&gid=' . $gid . '&' . $param . '=' . $tab;
}

/**
 * Embassy tab URL: `/build.php?gid=18&tt=<info|villages_nearby|join>` (no slot id).
 *
 * @param array<mixed> $extra
 */
function embassyTabUrl(string $tab, array $extra = []): string
{
    return '/build.php?gid=18&tt=' . $tab . ($extra ? '&' . http_build_query($extra) : '');
}

/**
 * Player profile route: /profile/<uid>; /profile is the own profile.
 * Extra query parameters (e.g. adminLogin) are appended verbatim.
 *
 * @param array<string, mixed> $query
 */
function profileUrl(?int $uid = null, array $query = []): string
{
    $url = '/profile' . ($uid !== null ? '/' . $uid : '');
    return $query === [] ? $url : $url . '?' . http_build_query($query);
}

/**
 * Alliance route: /alliance/<aid>; /alliance is the own alliance.
 * An optional section ("forum", "reports", "bonuses", ...) goes after the id.
 *
 * @param array<string, int>|array<string, mixed> $query
 */
function allianceUrl(?int $aid = null, string $section = '', array $query = []): string
{
    $url = '/alliance' . ($aid !== null && $aid > 0 ? '/' . $aid : '') . ($section !== '' ? '/' . $section : '');
    return $query === [] ? $url : $url . '?' . http_build_query($query);
}

/**
 * Village statistics (/village/statistics). Overview and warehouse have
 * their own paths; the remaining tabs keep the ?s= selector.
 */
function villageStatisticsUrl(int $tab = 0): string
{
    return match ($tab) {
        2 => '/village/statistics/resources',
        4 => '/village/statistics/culturepoints',
        5 => '/village/statistics/troops',
        default => '/village/statistics/overview',
    };
}

function getForumUrl(): string
{
    return '/alliance/forum';
}

/** The operator-configured https Discord URL, or '' when unset (every Discord link is then hidden). */
function getDiscordUrl(): string
{
    return \Game\DiscordLink::url();
}

function getHomepageUrl(): string
{
    return '/homepage';
}

function getSupportUrl(): string
{
    return '/support/ticket';
}

/** Public lobby/menu support entry. */
function getSupportTicketUrl(): string
{
    return getSupportUrl();
}

/**
 * @return mixed
 */
function getGameSpeed()
{
    return getGame('speed');
}

/**
 * @return mixed
 */
function getWorldUniqueId()
{
    return Config::getInstance()->settings->worldUniqueId;
}

/**
 * @return mixed
 */
function getWorldId()
{
    return Config::getInstance()->settings->worldId;
}

/**
 * @return mixed
 */
function isServerFinished()
{
    return Config::getInstance()->dynamic->serverFinished;
}

/**
 * @param bool $trim
 * @return bool|string
 */
function generate_guid($trim = true)
{
    // Windows
    if (function_exists('com_create_guid') === true) {
        if ($trim === true) {
            return trim(com_create_guid(), '{}');
        } else {
            return com_create_guid();
        }
    }

    // OSX/Linux
    if (function_exists('openssl_random_pseudo_bytes') === true) {
        $data = openssl_random_pseudo_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // set version to 0100
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    // Fallback (PHP 4.2+)
    mt_srand(make_seed());
    $charid = strtolower(md5(uniqid((string) rand(), true)));
    $hyphen = chr(45); // "-"
    $lbrace = $trim ? '' : chr(123); // "{"
    $rbrace = $trim ? '' : chr(125); // "}"
    $guidv4 =
        $lbrace .
        substr($charid, 0, 8) .
        $hyphen .
        substr($charid, 8, 4) .
        $hyphen .
        substr($charid, 12, 4) .
        $hyphen .
        substr($charid, 16, 4) .
        $hyphen .
        substr($charid, 20, 12) .
        $rbrace;
    return $guidv4;
}

/**
 * @param string $base
 * @param mixed $Code
 * @param bool|string $Default
 * @return mixed
 */
function T($base, $Code, $Default = false)
{
    $Locale = Locale::getInstance();
    if ($Locale) {
        return $Locale->Translate($base, $Code, $Default);
    } else {
        return $Default;
    }
}

/**
 * @param mixed $rate
 * @param int $type
 */
function multiply_packages($rate, $type = 7): void
{
    global $config;
    $config->settings->multiplyCalled = true;
    if ($type & 1 && $config->extraSettings->buyResources['enabled']) {
        $config->extraSettings->buyResources['packages'] = array_map(function ($x) use ($rate) {
            $x['resources'] = array_map(function ($x) use ($rate) {
                return ceil(($x * $rate) / 1000) * 1000;
            }, $x['resources']);
            return $x;
        }, $config->extraSettings->buyResources['packages']);
    }
    if ($type & 2 && $config->extraSettings->buyAnimal['enabled']) {
        $config->extraSettings->buyAnimal['packages'] = array_map(function ($x) use ($rate) {
            $x['units'] = array_map(function ($x) use ($rate) {
                return ceil(($x * $rate) / 100) * 100;
            }, $x['units']);
            return $x;
        }, $config->extraSettings->buyAnimal['packages']);
    }
    if ($type & 4 && $config->extraSettings->buyTroops['enabled']) {
        $buyTroops = &$config->extraSettings->buyTroops['packages'];
        foreach ($buyTroops as $race => $racePackageArray) {
            foreach ($racePackageArray as $index => $package) {
                $buyTroops[$race][$index]['units'] = array_map(function ($x) use ($rate) {
                    return ceil(($x * $rate) / 100) * 100;
                }, $package['units']);
            }
        }
    }
}

/**
 * @return int
 * @param bool $fixed
 */
function miliseconds($fixed = false)
{
    if ($fixed) {
        return time() * 1000;
    }
    $microtime = microtime();
    $comps = explode(' ', $microtime);

    return sprintf('%d%03d', $comps[1] + $comps[0], $comps[0] * 1e3);
}

/**
 * @param int $miliseconds
 */
function getDifMilisecondsToSeconds($miliseconds): float
{
    return ceil(($miliseconds - miliseconds()) / 1000);
}

function nanoseconds(): string
{
    /*if (function_exists('exec')) {
        exec('date +%s%N', $nano);
        return trim($nano[0]);
    }*/
    $microtime = microtime();
    $comps = explode(' ', $microtime);
    return sprintf('%d%09d', $comps[1] + $comps[0], $comps[0] * 1e9);
}

/**
 * @param bool|int $bytes
 * @param int $decimals
 */
function human_filesize($bytes, $decimals = 2): string
{
    $size = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    $factor = floor((strlen((string) $bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . $size[(int) $factor];
}

/**
 * @param (float|int)|float $value
 */
function round5($value): float
{
    return round($value / 5) * 5;
}

/**
 * @param mixed $n
 * @param int $x
 */
function roundUpToAny($n, $x = 5): float
{
    return ceil($n) % $x === 0 ? ceil($n) : round(($n + $x / 2) / $x) * $x;
}

/**
 * @param (float|int) $value
 */
function round10($value): float
{
    return round($value / 10) * 10;
}

/**
 * @param mixed $nr
 * @param mixed $tribe
 * @return (float|int)|int
 */
function nrToUnitId($nr, $tribe)
{
    // Exeption for the hero: Every time id 98
    if ($nr == 11) {
        return 98;
    }
    if ($nr == 99) {
        return 99;
    }

    return ($tribe - 1) * 10 + $nr * 1;
}

/**
 * @param mixed $unitId
 */
function unitIdToTribe($unitId): int
{
    if ($unitId <= 10) {
        return 1;
    }
    if ($unitId <= 20) {
        return 2;
    }
    if ($unitId <= 30) {
        return 3;
    }
    if ($unitId <= 40) {
        return 4;
    }
    if ($unitId <= 50) {
        return 5;
    }
    if ($unitId <= 60) {
        return 6;
    }
    if ($unitId <= 70) {
        return 7;
    }
    if ($unitId <= 80) {
        return 8;
    }
    if ($unitId <= 90) {
        return 9;
    }
    return 0;
}

/**
 * @param mixed $a
 */
function unitIdToNr($a): int
{
    if ($a == 99) {
        return 99;
    }
    if ($a == 98 || $a == 'hero') {
        return 11;
    }

    return 1 + (($a - 1) % 10);
}

/**
 * @param array<string, mixed> $button
 * @param array<string, array<string, array<string, int|string>|bool|string>>|array<string, array<string, bool|string>>|array<string, array<string, mixed>> $js
 * @param null $content
 */
function getAButton($button, $js, $content = null): string
{
    require SVG_COMMON_TEMPLATES;
    if (!isset($button['id'])) {
        $button['id'] = get_button_id();
    }
    $output = null;
    if (!isset($js['data'])) {
        $js['data'] = [];
    }
    if (isset($js['data']['onclick'])) {
        $button['onclick'] = $js['onclick'] = $js['data']['onclick'];
    }
    if (isset($js['data']['onfocus'])) {
        $button['onfocus'] = $js['onfocus'] = $js['data']['onfocus'];
    }
    if (strpos($button['class'], 'disabled') !== false) {
        if (!isset($js['onclick']) && !isset($button['onclick']) && !isset($js['data']['onclick'])) {
            $button['onclick'] = $js['onclick'] =
                "if(jQuery(this).hasClass('disabled')){event.stopPropagation(); return false;} else {}";
        }
        if (!isset($js['onfocus']) && !isset($button['onfocus']) && !isset($js['data']['onfocus'])) {
            $button['onfocus'] = $js['onfocus'] =
                "jQuery('button', 'input[type!=hidden]', 'select').trigger('focus'); event.stopPropagation(); return false;";
        }
    }

    $output = '<a';
    if (isset($button['href']) && $button['href'] != '#') {
        $output .= ' href="' . $button['href'] . '"';
    } else {
        $output .= ' href="#"';
        $button['onclick'] = 'event.preventDefault();';
    }
    if (strpos((string) ($button['class'] ?? ''), 'layoutButton') !== false && isset($js['data']['boxId'], $js['data']['buttonId'])) {
        $button['data-load-tooltip'] = 'layoutButton';
        $button['data-load-tooltip-data'] = htmlspecialchars(
            json_encode(['boxId' => $js['data']['boxId'], 'buttonId' => $js['data']['buttonId']]),
            ENT_QUOTES,
            'UTF-8'
        );
    } elseif (isset($js['title'], $js['title_section'], $js['title_action'])) {
        $button['data-load-tooltip'] = 'layoutButton';
        $button['data-load-tooltip-data'] = htmlspecialchars(
            json_encode(['boxId' => $js['title_section'], 'buttonId' => $js['title_action']]),
            ENT_QUOTES,
            'UTF-8'
        );
    }
    foreach ($button as $k => $v) {
        if ($k == 'value' || is_array($v)) {
            continue;
        }
        if ($k == 'title') {
            $js['data'][$k] = $v;
        }
        if ($k == 'coins' && $v <= 0) {
            continue;
        }
        if (strtoupper(substr($k, 0, 2)) == 'ON') {
            $k = strtolower($k);
            $js['data'][$k] = $v;
        }
        $output .= ' ' . $k . '="' . $v . '"';
    }
    $output .= '>';

    if (isset($button['svg']) && isset($svg[$button['svg']])) {
        $output .= $svg[$button['svg']];
    } elseif (isset($button['svg'])) {
        $output .= '---missing svg ' . $button['svg'] . ' definition---';
    }

    $output .=
        '</a>
    <script type="text/javascript" id="' .
        $button['id'] .
        '_script">';
    $js['data']['id'] = $button['id'];
    $payloadClass = trim((string) ($js['data']['class'] ?? ''));
    if ($payloadClass === '' || $payloadClass === 'green' || $payloadClass === 'gold') {
        $js['data']['class'] = (string) ($button['class'] ?? '');
    }
    $output .=
        "
    jQuery(function()
        {
          jQuery('#{$button['id']}').click(function (event)
          {
            jQuery(window).trigger('buttonClicked', [event.delegateTarget, " .
        json_encode($js['data']) .
        "]);
          });
        });
    ";
    $output .= '</script>';

    return $output;
}

/**
 * @param array<string, int|string>|array<string, mixed>|array<string, string> $button
 * @param mixed $js
 * @param mixed $content
 */
function getButton($button, $js, $content = null): string
{
    require SVG_COMMON_TEMPLATES;

    $buttonClass = trim((string) ($button['class'] ?? ''));
    $usesFramedButton = str_contains($buttonClass, 'buttonFramed') || str_contains($buttonClass, 'textButtonV2');
    if (
        !str_contains($buttonClass, 'layoutButton') &&
        !str_contains($buttonClass, 'textButtonV1') &&
        !$usesFramedButton
    ) {
        $buttonClass = trim('textButtonV1 ' . $buttonClass);
        $button['class'] = $buttonClass;
    }

    if (!isset($button['id'])) {
        $button['id'] = get_button_id();
    }
    if (str_contains($buttonClass, 'textButtonV1') && !isset($button['version'])) {
        $button['version'] = 'textButtonV1';
    }
    $output = null;
    if (!isset($js['data'])) {
        $js['data'] = [];
    }
    if (isset($js['data']['onclick'])) {
        $button['onclick'] = $js['onclick'] = $js['data']['onclick'];
    }
    if (isset($js['data']['onfocus'])) {
        $button['onfocus'] = $js['onfocus'] = $js['data']['onfocus'];
    }
    if (str_contains($buttonClass, 'disabled')) {
        if (!isset($js['onclick']) && !isset($button['onclick']) && !isset($js['data']['onclick'])) {
            $button['onclick'] = $js['onclick'] =
                "if(jQuery(this).hasClass('disabled')){event.stopPropagation(); return false;} else {}";
        }
        if (!isset($js['onfocus']) && !isset($button['onfocus']) && !isset($js['data']['onfocus'])) {
            $button['onfocus'] = $js['onfocus'] =
                "jQuery('button', 'input[type!=hidden]', 'select').trigger('focus'); event.stopPropagation(); return false;";
        }
    }

    $output = '<button';
    foreach ($button as $k => $v) {
        if (is_array($v)) {
            continue;
        }
        if ($k === 'value') {
            // Submit-button values are part of the form contract.  In
            // particular, Rally Point uses `edit=edit` to return from the
            // confirmation step without sending troops.  Omitting this
            // attribute silently turns a text-button action into a submit
            // with the browser default value.
            $output .= ' value="' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '"';
            continue;
        }
        if ($k == 'title') {
            $js['data'][$k] = $v;
        }
        if ($k == 'coins' && $v <= 0) {
            continue;
        }
        if (strtoupper(substr($k, 0, 2)) == 'ON') {
            $k = strtolower($k);
            $js['data'][$k] = $v;
        }
        $output .= ' ' . $k . '="' . $v . '"';
    }
    $output .= '>';
    if (str_contains($buttonClass, 'layoutButton')) {
        $output .= <<<HTML
<div class="button-container addHoverClick">
		<i></i>
	</div>
HTML;
    } else {
        if (isset($button['coins']) && $button['coins'] > 0) {
            if (str_contains($buttonClass, 'textButtonV1')) {
                $content .=
                    '<i class="goldIcon"></i><span class="goldValue">' .
                    $button['coins'] .
                    '</span>';
            }
            if (str_contains($buttonClass, 'textButtonV2')) {
                /** @var array<string, string> $svg */
                $content .= $svg['gold_button_small'] . '&nbsp;<span class="goldValue">' . $button['coins'] . '</span>';
            }
            $js['data']['coins'] = $button['coins'];
        }
        // ButtonV1 is a flat <button> with label/coins as direct children; a V2 text button
        // keeps label, coin and price in one inner div.
        if (str_contains($buttonClass, 'textButtonV2') && str_contains($buttonClass, 'withText') && !str_starts_with(ltrim((string)$content), '<div')) {
            $content = '<div>' . $content . '</div>';
        }
        $output .= $content;
    }
    if (isset($js['speechBubble']) && $js['speechBubble'] > 0) {
        $output .=
            '<div class="speechBubbleContainer ">
              <div class="speechBubbleBackground"><div class="start"><div class="end"><div class="middle"></div></div></div></div>
              <div class="speechBubbleContent">' .
            $js['speechBubble'] .
            '</div>
            </div><div class="clear"></div>';
    }
    $output .=
        '</button>
    <script type="text/javascript" id="' .
        $button['id'] .
        '_script">';
    if (isset($js['title'])) {
        $output .=
            "
        jQuery(function()
        {
          var button = jQuery('#{$button['id']}');
          if (button)
          {
            var titleFunction = function()
            {
              button.removeEvent('" .
            $js['title_act'] .
            "', titleFunction);
              Travian.Game.Layout.loadLayoutButtonTitle(button, '" .
            $js['title_section'] .
            "', '" .
            $js['title_action'] .
            "');
            };
            button.addEvent('" .
            $js['title_act'] .
            "', titleFunction);
          }
        });
      ";
    }
    $js['data']['id'] = $button['id'];
    // Mirror the rendered button class into buttonClicked payloads.
    $payloadClass = trim((string) ($js['data']['class'] ?? ''));
    if ($payloadClass === '' || $payloadClass === 'green' || $payloadClass === 'gold') {
        $js['data']['class'] = (string) ($button['class'] ?? $buttonClass);
    }
    $output .=
        "
    jQuery(function()
        {
          jQuery('#{$button['id']}').click(function (event)
          {
            jQuery(window).trigger('buttonClicked', [this, " .
        json_encode($js['data']) .
        "]);
          });
        });
    ";
    $output .= '</script>';

    return $output;
}

function get_button_id(): string
{
    //$encode = get_random_string(13);
    static $id = 0;
    static $alpha = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    ++$id;
    return 'button' . $alpha[mt_rand(0, 51)] . $id;
}

/**
 * @param int $length
 */
function get_random_string($length): string
{
    // Character List to Pick from
    $chrList = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    // Minimum/Maximum times to repeat character List to seed from
    $chrRepeatMin = 1; // Minimum times to repeat the seed string
    $chrRepeatMax = 10; // Maximum times to repeat the seed string
    // Length of Random String returned
    $chrRandomLength = $length;

    // The ONE LINE random command with the above variables.
    return substr(str_shuffle(str_repeat($chrList, mt_rand($chrRepeatMin, $chrRepeatMax))), 1, $chrRandomLength);
}

/**
 * @param mixed $seconds
 * @param bool $isTrainingTime
 * @return mixed
 */
function secondsToString($seconds, $isTrainingTime = false)
{
    if (is_string($seconds)) {
        return $seconds;
    }
    // Training/healing/ship/revive clocks are stored in ticks (s/ms/ns). Player chrome shows whole
    // seconds only — never a "(+N ms)" remainder — matching the reference and dorf3/quick-link path.
    if ($isTrainingTime) {
        return secondsToString(trainingTimeDisplaySeconds($seconds));
    }
    if ($seconds == time()) {
        return TimezoneHelper::date('H:i:s');
    }
    $h = (int) floor($seconds / 3600);
    $m = (int) floor(($seconds % 3600) / 60);
    $s = (int) floor($seconds % 60);
    return $h . ':' . ($m < 10 ? '0' : '') . $m . ':' . ($s < 10 ? '0' : '') . $s;
}

/**
 * @param mixed $ms
 * @return mixed
 */
function milisecondsToString($ms)
{
    if ($ms % 1000 == 0) {
        return secondsToString($ms / 1000);
    }
    $sec = (int) floor($ms / 1000);
    $ms = $ms % 1000;
    $min = (int) floor($sec / 60);
    $sec = $sec % 60;
    $hr = (int) floor($min / 60);
    $min = $min % 60;
    $res = sprintf('%d:%02d:%02d (+%d ' . T('Global', 'ms') . ')', $hr, $min, $sec, $ms);
    return $res;
}

/**
 * @param mixed $ns
 * @return mixed
 */
function nanosecondsToString($ns)
{
    if ($ns % 1e9 == 0) {
        return secondsToString($ns / 1e9);
    }
    $sec = (int) floor($ns / 1e9);
    $ns = $ns % 1e9;
    $min = (int) floor($sec / 60);
    $sec = $sec % 60;
    $hr = (int) floor($min / 60);
    $min = $min % 60;
    $res = sprintf('%d:%02d:%02d (+%s ' . T('Global', 'ms') . ')', $hr, $min, $sec, round($ns / 1e6, 3));
    return $res;
}

function get_gpack_cdn_base_url(): string
{
    global $globalConfig;
    if (!empty($globalConfig['staticParameters']['gpackBaseUrl'])) {
        return rtrim($globalConfig['staticParameters']['gpackBaseUrl'], '/') . '/';
    }
    return '/gpack/';
}

/**
 * @return mixed
 */
function get_gpack_cdn_base_url_with_protocol()
{
    $baseUrl = get_gpack_cdn_base_url();
    if (strpos($baseUrl, '//') === 0) {
        return 'http:' . $baseUrl;
    }
    if (strpos($baseUrl, '/') === 0) {
        return rtrim(WebService::getIndexUrl(), '/') . $baseUrl;
    }
    return $baseUrl;
}

/**
 * The shipped game Gpack registry (assets/gpack/registry.php). It is read from the code, not from
 * the generated config of an installed world, so renaming the pack never strands a world that was
 * installed under the old name.
 *
 * @return array{default: string, list: array<string, array<string, mixed>>}
 */
function gpack_registry(): array
{
    static $registry = null;

    return $registry ??= require ROOT_PATH . '../assets/gpack/registry.php';
}

/**
 * @param mixed $default
 * @return mixed
 */
function get_gpack_version($default = false)
{
    return gpack_registry()['default'];
}

/**
 * @param mixed $version
 * @return mixed
 */
function get_gpack_folder($version)
{
    $gpackList = gpack_registry()['list'];
    return isset($gpackList[$version]['hash']) ? $gpackList[$version]['hash'] : $version;
}

/**
 * Path of a Gpack image below mainPage/ for a text direction. An image that is byte-identical in
 * both directions lives once in img_common; the others sit in img_ltr or img_rtl.
 *
 * @param string|null $direction LTR or RTL (any case); null is the current language's
 */
function gpack_image_path(string $relative, ?string $direction = null): string
{
    static $common = [];
    if (!isset($common[$relative])) {
        $common[$relative] = is_file(ROOT_PATH . '../assets/gpack/' . get_gpack_folder(get_gpack_version()) . '/mainPage/img_common/' . $relative);
    }
    if ($common[$relative]) {
        return 'img_common/' . $relative;
    }

    return 'img_' . (strtoupper($direction ?? getDirection()) === 'RTL' ? 'rtl' : 'ltr') . '/' . $relative;
}

/**
 * @param bool $default
 */
function get_gpack_cdn_url($default = false): string
{
    return get_gpack_cdn_base_url() . get_gpack_folder(get_gpack_version($default)) . '/';
}

/**
 * @param bool $default
 */
function get_gpack_cdn_mainPage_url($default = false): string
{
    return get_gpack_cdn_base_url() . get_gpack_folder(get_gpack_version($default)) . '/mainPage/';
}

/**
 * Location headers are not resolved against <base href="/">, so a relative
 * target sent from a nested clean route (/hero/attributes) would land under
 * that route. Make local targets root-absolute; leave absolute URLs, query-only
 * and fragment-only targets untouched.
 *
 * @param mixed $url
 */
function normalizeRedirectTarget($url): string
{
    $url = (string)$url;
    if ($url === '' || $url[0] === '/' || $url[0] === '?' || $url[0] === '#'
        || preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
        return $url;
    }
    return '/' . $url;
}

/**
 * @param string $url
 * @param int $code
 */
function redirect($url, $code = 302): void
{
    if (!$url) {
        return;
    }
    if (!in_array($code, [301, 302])) {
        $code = 302;
    }
    header('Location: ' . normalizeRedirectTarget($url), true, $code);
    exit();
}

/**
 * One map coordinate as the reference prints it: forced left-to-right, and a
 * negative value carries a true minus sign (U+2212) rather than a hyphen.
 *
 * @param mixed $value
 */
/**
 * A stored player-entered name as HTML text. Names are stored already HTML-escaped (the writers run htmlspecialchars,
 * with an apostrophe turned into a backtick), so printing one through htmlspecialchars again shows "&amp;" for an
 * ampersand. Decoding first makes the output right for stored-escaped and for raw names alike.
 */
function stored_name_html(string $stored): string
{
    return htmlspecialchars(htmlspecialchars_decode($stored, ENT_QUOTES), ENT_QUOTES, 'UTF-8');
}

function coordinateNumberHTML($value): string
{
    $number = (int)$value;
    $lro = "\u{202D}";
    $pdf = "\u{202C}";
    return $number < 0
        ? $lro . "\u{2212}" . $lro . abs($number) . $pdf . $pdf
        : $lro . $number . $pdf;
}

/**
 * @param mixed $x
 * @param mixed $y
 */
function getCoordinatesHTML($x, $y): string
{
    $HTML = null;
    $HTML .= '<span class="coordinates coordinatesWrapper">';
    $HTML .= '<span class="coordinateX">(' . coordinateNumberHTML($x) . '</span>';
    $HTML .= '<span class="coordinatePipe">|</span>';
    $HTML .= '<span class="coordinateY">' . coordinateNumberHTML($y) . ')</span>';
    $HTML .= '</span>';
    return $HTML;
}

/**
 * @param mixed $title
 * @param string $html
 * @param bool $centered
 */
function createBoxTitleHTML($title, $html, $centered = false): string
{
    return '<div class="roundedCornersBox big">
            <h4><div class="statusMessage">' .
        $title .
        '</div></h4>
            <div id="contractSpacer"></div>
            <div id="contract" class="contractWrapper">
                <div class="contractLink ' .
        ($centered ? 'centeredText' : '') .
        '">
                    <div>' .
        $html .
        '</div>
                    <br />
                    <div class="clear"></div>
                </div>
                <div class="clear"></div>
            </div>
            <div class="clear"></div>
        </div>';
}

/**
 * Cache-busting token for a static asset: a short hash of its content, so it
 * only changes when the bytes change (an image rebuild that re-minifies the same
 * source or resets mtimes keeps the URL, and browser caches, valid). The hash is
 * memoised per request and, when APCu is available, across requests keyed by
 * path + mtime + size so an unchanged file is not re-read.
 */
function asset_content_version(string $path, string $fallback = '0'): string
{
    static $memo = [];
    if (isset($memo[$path])) {
        return $memo[$path];
    }
    $mtime = @filemtime($path);
    $size = @filesize($path);
    if ($mtime === false || $size === false) {
        return $fallback;
    }
    $key = 'asset_ver:' . $path . ':' . $mtime . ':' . $size;
    if (function_exists('apcu_fetch') && ($cached = apcu_fetch($key)) !== false) {
        return $memo[$path] = (string) $cached;
    }
    $hash = @hash_file('xxh3', $path);
    if ($hash === false) {
        return $fallback;
    }
    $version = substr($hash, 0, 12);
    if (function_exists('apcu_store')) {
        apcu_store($key, $version);
    }
    return $memo[$path] = $version;
}

function get_crypt_js_link(): string
{
    return 'game-client.js?v=' . asset_content_version(__DIR__ . '/../deployment/public/game-client.js');
}

/**
 * The per-locale client string bundle,
 * served like the reference's /js/<locale>/ files.
 */
function get_client_strings_link(): string
{
    $locale = get_locale();

    return '/js/' . $locale . '/strings.js?v='
        . asset_content_version(__DIR__ . '/../deployment/public/js/' . $locale . '/strings.js');
}

function getTimeZone(): string
{
    $seconds = date('O');
    if (strstr($seconds, '-')) {
        $type = 2;
    } else {
        $type = 1;
    }
    $seconds = preg_replace('/[^0-9]/', '', $seconds);
    $h = substr($seconds, 0, 2);
    $m = substr($seconds, 2, 2);
    return ($type == 1 ? '+' : '-') . "$h:$m";
}

/**
 * @param mixed $uri
 * @param mixed $cacheKey
 * @return mixed
 */
function filemtime_remote_key($uri, $cacheKey)
{
    $cacheKey = 'fmt:' . $cacheKey;
    $cache = Caching::getInstance();
    if ($_cache = $cache->get($cacheKey)) {
        return $_cache;
    }
    $uri = parse_url($uri);
    $handle = @fsockopen($uri['host'], 80);
    if (!$handle) {
        return 0;
    }
    fputs($handle, "GET {$uri['path']} HTTP/1.1\r\nHost: {$uri['host']}\r\n\r\n");
    $result = 0;
    while (!feof($handle)) {
        $line = fgets($handle, 1024);
        if (!trim($line)) {
            break;
        }
        $col = strpos($line, ':');
        if ($col !== false) {
            $header = trim(substr($line, 0, $col));
            $value = trim(substr($line, $col + 1));
            if (strtolower($header) == 'last-modified') {
                $result = strtotime($value);
                break;
            }
        }
    }
    fclose($handle);
    $result = substr(sha1((string) $result), -5);
    $cache->add($cacheKey, $result, 7200);
    return $result;
}

/**
 * @param string|null $key
 * @return mixed
 */
function get_language_properties($key = null)
{
    $config = Config::getInstance();
    $lang = Session::getInstance()->getLanguage();
    $langProperties = $config->settings->availableLanguages->$lang;
    if (!is_null($key)) {
        return $langProperties->$key;
    }
    return $langProperties;
}

/**
 * @return mixed
 */
function get_locale()
{
    return get_language_properties('locale');
}

/**
 * @param string $fileName
 * @param bool $useLang
 */
function get_gpack_link_and_hash($fileName, $useLang = false, ?string $directionOverride = null): string
{
    $version = get_gpack_version();
    $pack = gpack_registry()['list'][$version];
    if ($useLang) {
        $fileName = get_gpack_cdn_mainPage_url() . 'lang/' . get_language_properties('locale') . '/' . $fileName;
        $localCssDirectory = 'lang/' . get_language_properties('locale');
    } else {
        $cssDirection = strtoupper((string) ($directionOverride ?? get_language_properties('direction')));
        $cssDirectory = !empty($pack['cssDirection'])
            ? 'css_' . ($cssDirection === 'RTL' ? 'rtl' : 'ltr')
            : 'css';
        $fileName = get_gpack_cdn_mainPage_url() . $cssDirectory . '/' . $fileName;
        $localCssDirectory = $cssDirectory;
    }

    $localGpackFile =
        dirname(__DIR__, 2) .
        '/assets/gpack/' .
        get_gpack_folder($version) .
        '/mainPage/' .
        $localCssDirectory .
        '/' .
        basename($fileName);

    return $fileName . '?v=' . asset_content_version($localGpackFile);
}

/**
 * @param float|int|string|null $value
 */
function getTradeRouteTimeText($value): string
{
    if ($value < 3600) {
        $text = sprintf(T('MarketPlace', 'every %s minutes'), floor($value / 60));
    } elseif ($value < 86400) {
        $text = sprintf(T('MarketPlace', 'every %s hours'), floor($value / 3600));
    } else {
        $text = sprintf(T('MarketPlace', 'every %s days'), floor($value / 86400));
    }
    return $text;
}

/**
 * @param mixed $row
 * @return array<int, mixed>
 */
function array_filter_units($row)
{
    $units = [];
    for ($i = 1; $i <= 11; ++$i) {
        if (!isset($row['u' . $i])) {
            continue;
        }
        $units[$i] = $row['u' . $i];
    }

    return $units;
}

/**
 * @return mixed
 */
function is_new_gpack()
{
    return gpack_registry()['list'][get_gpack_version()]['isNew'];
}

/**
 * @return (float|int)
 */
function game_progress()
{
    $rate = getGameElapsedSeconds() / (getGame('round_length') * 86400);
    if ($rate < 0) {
        $rate = 0;
    } elseif ($rate > 1) {
        $rate = 1;
    }
    return $rate;
}

/**
 * @param mixed $speed
 * @return (float|int)
 */
function getGameLengthDays($speed)
{
    return 250 / $speed + (1 / $speed) * 100;
}

/**
 * @return string|null
 */
function detect_season()
{
    if (getDisplay('disableSeasons')) {
        return null;
    }
    //get current month
    $currentMonth = date('m');
    //retrieve season
    if ($currentMonth >= '03' && $currentMonth <= '05') {
        $season = 'spring';
    } elseif ($currentMonth >= '06' && $currentMonth <= '08') {
        $season = 'summer';
    } elseif ($currentMonth >= '09' && $currentMonth <= '11') {
        $season = 'fall';
    } else {
        $season = 'winter';
    }
    return $season;
}

/**
 * @param float|int|string $number
 * @param mixed $after
 * @return mixed
 */
function number_to_string($number, $after = 0)
{
    $abs = abs($number);
    if ($abs > $after) {
        if ($abs < 1e3) {
            return $number;
        } elseif ($abs < 1e6) {
            return round($number / 1e3, 3) . 'K'; //Kilo
        } elseif ($abs < 1e9) {
            return round($number / 1e6, 3) . 'M'; //Million
        } elseif ($abs < 1e12) {
            return round($number / 1e9, 3) . 'B'; //Billion
        } elseif ($abs < 1e15) {
            return round($number / 1e12, 3) . 'T'; //Trillion
        } elseif ($abs < 1e18) {
            return round($number / 1e15, 3) . 'Q'; //Quadrillion
        } elseif ($abs < 1e21) {
            return round($number / 1e18, 3) . 'Qi'; //Quintillion
        } elseif ($abs < 1e24) {
            return round($number / 1e21, 3) . 'S'; //Sextillion
        }
    }
    return number_format_x($number);
}

function is_my_ip(): bool
{
    return WebService::ipAddress() == '188.99.108.8';
}
