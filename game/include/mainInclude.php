<?php

use Core\Config;
use Core\Database\GlobalDB;
use Core\Dispatcher;
use Core\Helper\WebService;
use Core\Session;
use Model\ArtefactsModel;

require __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

/**
 * Set a request parameter derived from the matched path. The path is
 * canonical, so a query-string value for the same key never overrides it.
 */
function setRouteParam(string $key, string|int $value): void
{
    $_GET[$key] = $value;
    $_REQUEST[$key] = $value;
}

$uri = $_SERVER['REQUEST_URI'];
$requestPath = parse_url($uri, PHP_URL_PATH) ?: '/';
//die(print_r($uri));
$page = pathinfo(explode('?', $uri)[0], PATHINFO_BASENAME);
//basename($uri, '?' . $_SERVER['QUERY_STRING']);
if ($page == '?' . (string)($_SERVER['QUERY_STRING'] ?? '')) {
    $page = '/';
}
/*$uriCheck = str_replace(['?'.$_SERVER['QUERY_STRING'], '/'], '', $uri) == $page;
if(!$uriCheck){
    $page = '404';
}
print_r($uri);
print_r($_SERVER['QUERY_STRING']);
die();*/

if ($requestPath === '/statistiken.php') {
    WebService::redirect('/statistics');
}
if ($requestPath === '/dorf3.php') {
    WebService::redirect('/village/statistics');
}
if ($requestPath === '/anmelden.php') {
    WebService::redirect('/register');
}
if (in_array($requestPath, ['/index.php', '/reports.php', '/stats.php', '/tgpay.php', '/credits.php', '/HeroAuctionCtrl.php'], true)) {
    $page = '404.php';
} elseif (empty($page) || $page == '/') {
    $page = 'index.php';
}

/*
 * The V2 navigation uses concise, extensionless URLs for these player-facing
 * pages. Keep the URL stable while dispatching to the long-lived controller
 * entries below, rather than making the V2 layout link back to legacy paths.
 */
$cleanPageAliases = [
    'statistics' => 'statistiken.php',
    'report' => 'reports.php',
];
if (isset($cleanPageAliases[$page])) {
    $page = $cleanPageAliases[$page];
}

/*
 * The footer and public menu point at a self-hosted landing route. Keep its
 * public-news and calendar destinations rooted locally as well, rather than
 * letting any nested clean route fall through to the LoginCtrl front
 * controller. The controller receives a finite internal page identifier;
 * player-facing URLs stay aligned with the public reference route shape.
 */
$homepageRoutes = [
    '/homepage' => ['homepagePage' => 'home'],
    '/homepage/' => ['homepagePage' => 'home'],
    '/homepage/news' => ['homepagePage' => 'news'],
    '/homepage/news/' => ['homepagePage' => 'news'],
    '/homepage/news/2026/10/22/winter-time-the-effects-on-your-game/' => [
        'homepagePage' => 'article',
        'homepageArticle' => 'winter-time-the-effects-on-your-game',
    ],
    '/homepage/news/2025/01/06/tournament/' => [
        'homepagePage' => 'article',
        'homepageArticle' => 'tournament',
    ],
    '/homepage/calendar' => ['homepagePage' => 'calendar'],
    '/homepage/calendar/' => ['homepagePage' => 'calendar'],
    '/homepage/tournament' => ['homepagePage' => 'tournament'],
    '/homepage/tournament/' => ['homepagePage' => 'tournament'],
    // Local-only aggregate stats page, reached by direct URL (no footer or menu link).
    '/homepage/world' => ['homepagePage' => 'world'],
    '/homepage/world/' => ['homepagePage' => 'world'],
];
// Dated articles have one canonical URL, like the reference: with a trailing
// slash. The slash-less form redirects there, keeping the transient public locale.
if (preg_match('#^/homepage/news/\d{4}/\d{2}/\d{2}/[a-z0-9-]+$#', $requestPath) === 1
    && isset($homepageRoutes[$requestPath . '/'])) {
    $homepageQuery = (string) parse_url($uri, PHP_URL_QUERY);
    redirect($requestPath . '/' . ($homepageQuery !== '' ? '?' . $homepageQuery : ''), 301);
}
if (isset($homepageRoutes[$requestPath])) {
    foreach ($homepageRoutes[$requestPath] as $parameter => $value) {
        // A matched public path is canonical. Query data must not turn a
        // dated article URL into a different page or article.
        $_GET[$parameter] = $value;
        $_REQUEST[$parameter] = $value;
    }
    $page = 'homepage';
}

/*
 * Reports V2 uses a route per tab. Keep their query-backed controller state
 * internal so every player-facing link can retain the reference URL.
 */
$requestPathForReports = parse_url($uri, PHP_URL_PATH) ?: '/';
$cleanReportRoutes = [
    '/report/overview' => 0,
    '/report/overview/' => 0,
    '/report/offensive' => 1,
    '/report/offensive/' => 1,
    '/report/defensive' => 2,
    '/report/defensive/' => 2,
    '/report/scouting' => 3,
    '/report/scouting/' => 3,
    '/report/other' => 4,
    '/report/other/' => 4,
    '/report/archive' => 5,
    '/report/archive/' => 5,
    '/report/surrounding' => 6,
    '/report/surrounding/' => 6,
];
if (isset($cleanReportRoutes[$requestPathForReports])) {
    setRouteParam('t', $cleanReportRoutes[$requestPathForReports]);
    $page = 'reports.php';
}

$requestPathForSupport = parse_url($uri, PHP_URL_PATH) ?: '/';
if (in_array($requestPathForSupport, ['/support/ticket', '/support/ticket/'], true)) {
    $page = 'support_ticket';
}
// Operator-written footer pages; an unwritten page is a plain 404.
if (preg_match('#^/(rules|terms|imprint|privacy)/?$#D', $requestPathForSupport, $legalRoute) === 1) {
    $_GET['legalPage'] = $legalRoute[1];
    $_REQUEST['legalPage'] = $legalRoute[1];
    $page = \Game\LegalPages::hasBody($legalRoute[1]) ? 'legal_page' : '404.php';
}
if (preg_match('#^/unsubscribe/([0-9]{1,10}\.[A-Za-z]{1,24}\.[a-f0-9]{32})$#D', $requestPathForSupport, $unsubscribeRoute) === 1) {
    $_GET['unsubscribeToken'] = $unsubscribeRoute[1];
    $_REQUEST['unsubscribeToken'] = $unsubscribeRoute[1];
    $page = 'unsubscribe';
}
if ($requestPathForSupport === '/payment/return') {
    $page = 'payment_return';
}

/*
 * Social sign-in. The provider comes only from the matched path, never from
 * the query. Apple's callback is a cross-site form_post, so no CSRF/POST gate
 * may sit in front of it; the controller binds it with its own cookie.
 */
if ($requestPathForSupport === '/auth/logout') {
    $page = 'auth_logout';
}
if ($requestPathForSupport === '/oauth/cancel') {
    $page = 'oauth_cancel';
}
if (preg_match('#^/oauth/(google|facebook|apple)/(start|callback|link|unlink)$#D', $requestPathForSupport, $oauthRoute) === 1) {
    $_GET['oauthProvider'] = $oauthRoute[1];
    $_REQUEST['oauthProvider'] = $oauthRoute[1];
    $page = 'oauth_' . $oauthRoute[2];
}

/*
 * Profile V2 nested routes (/profile, /profile/edit). Applied after the
 * basename alias so /profile/edit is not treated as a bare "edit" page.
 */
$requestPathForProfile = parse_url($uri, PHP_URL_PATH) ?: '/';
$cleanProfileRoutes = [
    '/profile' => ['s' => '1'],
    '/profile/' => ['s' => '1'],
    '/profile/overview' => ['s' => '1'],
    '/profile/overview/' => ['s' => '1'],
    '/profile/edit' => ['s' => '2'],
    '/profile/edit/' => ['s' => '2'],
];
if (isset($cleanProfileRoutes[$requestPathForProfile])) {
    foreach ($cleanProfileRoutes[$requestPathForProfile] as $key => $value) {
        setRouteParam($key, $value);
    }
    $page = 'profile';
} elseif (preg_match('#^/profile/(\d+)/?$#', $requestPathForProfile, $profileMatch) === 1) {
    $_GET['uid'] = $profileMatch[1];
    $_REQUEST['uid'] = $profileMatch[1];
    setRouteParam('s', '1');
    $page = 'profile';
}

/*
 * Preferences tab routes (/options/game, /options/account|/avatar, /options/sitter,
 * /options/vacation) map onto OptionCtrl's numeric tab selector.
 * also serves /options/avatar as the Avatar preferences tab.
 */
$cleanOptionsTabs = ['game' => '1', 'account' => '2', 'avatar' => '2', 'sitter' => '3', 'vacation' => '4'];
if (preg_match('#^/options/(game|account|avatar|sitter|vacation)/?$#', parse_url($uri, PHP_URL_PATH) ?: '/', $optionsMatch) === 1) {
    setRouteParam('s', $cleanOptionsTabs[$optionsMatch[1]]);
    $page = 'options';
}

/*
 * Messages tab routes (/messages/inbox, /write, /sent, /archive, /notes,
 * /ignore) map onto MessagesCtrl's numeric tab selector.
 */
$cleanMessageTabs = ['inbox' => '0', 'write' => '1', 'sent' => '2', 'archive' => '3', 'notes' => '4', 'ignore' => '5'];
if (preg_match('#^/messages/(inbox|write|sent|archive|notes|ignore)/?$#', parse_url($uri, PHP_URL_PATH) ?: '/', $messagesMatch) === 1) {
    setRouteParam('t', $cleanMessageTabs[$messagesMatch[1]]);
    $page = 'messages';
}

/*
 * Nested statistics routes mirror the reference path set
 * (/statistics/player/overview, /statistics/alliance/attackers, …).
 * Map them onto the existing StatisticsCtrl id/idSub query contract.
 */
$requestPathForStats = parse_url($uri, PHP_URL_PATH) ?: '/';
$cleanStatisticsRoutes = [
    '/statistics/player' => ['id' => '0', 'idSub' => '0'],
    '/statistics/player/overview' => ['id' => '0', 'idSub' => '0'],
    '/statistics/player/attackers' => ['id' => '0', 'idSub' => '1'],
    '/statistics/player/defenders' => ['id' => '0', 'idSub' => '2'],
    '/statistics/player/top10' => ['id' => '0', 'idSub' => '3'],
    '/statistics/alliance' => ['id' => '1', 'idSub' => '0'],
    '/statistics/alliance/overview' => ['id' => '1', 'idSub' => '0'],
    '/statistics/alliance/attackers' => ['id' => '1', 'idSub' => '1'],
    '/statistics/alliance/defenders' => ['id' => '1', 'idSub' => '2'],
    '/statistics/alliance/top10' => ['id' => '1', 'idSub' => '3'],
    '/statistics/village' => ['id' => '2'],
    '/statistics/hero' => ['id' => '3'],
    '/statistics/general' => ['id' => '5'],
    '/statistics/wonderoftheworld' => ['id' => '6'],
    '/statistics/bonus' => ['id' => '7'],
    '/statistics/farm' => ['id' => '8'],
];
if (isset($cleanStatisticsRoutes[$requestPathForStats])) {
    foreach ($cleanStatisticsRoutes[$requestPathForStats] as $key => $value) {
        $_GET[$key] = $value;
        $_REQUEST[$key] = $value;
    }
    $page = 'statistiken.php';
}

/*
 * Link-list recommended targets and related V2 shortcuts. Map the reference
 * path set onto the existing Dorf3 / Alliance query contracts.
 */
$requestPathForLinkTargets = parse_url($uri, PHP_URL_PATH) ?: '/';
$cleanLinkListTargetRoutes = [
    '/village/statistics' => ['page' => 'dorf3', 'query' => ['s' => '0']],
    '/village/statistics/' => ['page' => 'dorf3', 'query' => ['s' => '0']],
    '/village/statistics/overview' => ['page' => 'dorf3', 'query' => ['s' => '0']],
    '/village/statistics/overview/' => ['page' => 'dorf3', 'query' => ['s' => '0']],
    '/village/statistics/resources' => ['page' => 'dorf3', 'query' => ['s' => '2']],
    '/village/statistics/resources/' => ['page' => 'dorf3', 'query' => ['s' => '2']],
    '/village/statistics/culturepoints' => ['page' => 'dorf3', 'query' => ['s' => '4']],
    '/village/statistics/culturepoints/' => ['page' => 'dorf3', 'query' => ['s' => '4']],
    '/village/statistics/troops' => ['page' => 'dorf3', 'query' => ['s' => '5']],
    '/village/statistics/troops/' => ['page' => 'dorf3', 'query' => ['s' => '5']],
    '/village/statistics/resources/resources' => ['page' => 'dorf3', 'query' => ['s' => '2', 'sr' => '0']],
    '/village/statistics/resources/warehouse' => ['page' => 'dorf3', 'query' => ['s' => '2', 'sr' => '1']],
    '/village/statistics/resources/production' => ['page' => 'dorf3', 'query' => ['s' => '2', 'sr' => '2']],
    '/village/statistics/resources/capacity' => ['page' => 'dorf3', 'query' => ['s' => '2', 'sr' => '3']],
    '/village/statistics/troops/own' => ['page' => 'dorf3', 'query' => ['s' => '5', 'su' => '0']],
    '/village/statistics/troops/support' => ['page' => 'dorf3', 'query' => ['s' => '5', 'su' => '1']],
    '/village/statistics/troops/smithy' => ['page' => 'dorf3', 'query' => ['s' => '5', 'su' => '2']],
    '/village/statistics/troops/hospital' => ['page' => 'dorf3', 'query' => ['s' => '5', 'su' => '3']],
    '/village/statistics/troops/training' => ['page' => 'dorf3', 'query' => ['s' => '5', 'su' => '4']],
    '/alliance/reports' => ['page' => 'alliance', 'query' => ['s' => '3']],
    '/alliance/reports/' => ['page' => 'alliance', 'query' => ['s' => '3']],
];
// The reference renders the overview for any other path below /village/statistics, without redirecting.
if (!isset($cleanLinkListTargetRoutes[$requestPathForLinkTargets]) && str_starts_with($requestPathForLinkTargets, '/village/statistics/')) {
    $cleanLinkListTargetRoutes[$requestPathForLinkTargets] = ['page' => 'dorf3', 'query' => ['s' => '0']];
}
if (isset($cleanLinkListTargetRoutes[$requestPathForLinkTargets])) {
    $route = $cleanLinkListTargetRoutes[$requestPathForLinkTargets];
    foreach ($route['query'] as $key => $value) {
        setRouteParam($key, $value);
    }
    $page = $route['page'];
}

/*
 * Nested alliance routes mirror the reference path set
 * (/alliance/{id}/profile, /alliance/{id}/forum, … and own-alliance
 * /alliance/overview, /alliance/reports, …).
 */
$requestPathForAlliance = parse_url($uri, PHP_URL_PATH) ?: '/';
$allianceSectionToTab = [
    'overview' => '7',
    'profile' => '1',
    'attacks' => '3',
    'reports' => '3',
    'forum' => '2',
    'options' => '5',
    'bonuses' => '8',
];
$allianceOptionAliases = [
    'name' => '100',
    'description' => '3',
    'info' => '7',
    'rights' => '1',
    'forum' => '5',
    'invite' => '4',
    'diplomacy' => '6',
    'kick' => '2',
];
if (preg_match('#^/alliance/options/banner/(create|my|gallery)/?$#', $requestPathForAlliance, $bannerMatch) === 1) {
    setRouteParam('s', '5');
    setRouteParam('bannerScreen', $bannerMatch[1]);
    $page = 'alliance';
} elseif (preg_match('#^/alliance/banner/?$#', $requestPathForAlliance) === 1) {
    // Serves the alliance overview shell for bare /alliance/banner (not a 404).
    setRouteParam('s', $allianceSectionToTab['overview']);
    setRouteParam('action', 'internalProfile');
    $page = 'alliance';
} elseif (preg_match('#^/alliance/(overview|profile|forum|attacks|reports|bonuses|options)(?:/(internalProfile|top10|description|members|name|info|rights|forum|invite|diplomacy|kick))?/?$#', $requestPathForAlliance, $ownAllianceMatch) === 1) {
    $section = $ownAllianceMatch[1];
    if ($section === 'attacks') {
        // The reference has no attacks route of its own: it renders the overview shell (Attacks lives at /alliance/reports).
        $section = 'overview';
    }
    setRouteParam('s', $allianceSectionToTab[$section] ?? '1');
    if (isset($ownAllianceMatch[2])) {
        $sub = $ownAllianceMatch[2];
        if ($section === 'overview' && ($sub === 'internalProfile' || $sub === 'top10')) {
            setRouteParam('action', $sub);
        } elseif ($section === 'profile' && ($sub === 'description' || $sub === 'members')) {
            setRouteParam('action', $sub);
        } elseif ($section === 'options' && isset($allianceOptionAliases[$sub])) {
            setRouteParam('o', $allianceOptionAliases[$sub]);
        }
    } elseif ($section === 'overview') {
        setRouteParam('action', 'internalProfile');
    }
    $page = 'alliance';
} elseif (preg_match('#^/alliance/(\d+)(?:/(profile|forum|overview|attacks|reports|bonuses|options)(?:/(description|members|internalProfile|top10))?)?/?$#', $requestPathForAlliance, $allianceMatch) === 1) {
    setRouteParam('aid', $allianceMatch[1]);
    $section = $allianceMatch[2] ?? 'profile';
    setRouteParam('s', $allianceSectionToTab[$section] ?? '1');
    if (isset($allianceMatch[3])) {
        setRouteParam('action', $allianceMatch[3]);
    } elseif ($section === 'overview') {
        setRouteParam('action', 'internalProfile');
    }
    $page = 'alliance';
}

/*
 * Hero V2 uses the concise player-facing routes from the current game.  The
 * established HeroDivider remains the runtime entry point; route values are
 * normalised here before the dispatcher is selected so links, reloads and
 * server-side state all use the same controller.
 */
$requestPath = parse_url($uri, PHP_URL_PATH) ?: '/';
/*
 * The reference serves /hero with the inventory (HTTP 200) and its client then rewrites the address to
 * /hero/inventory (hero/navigation.php does the same), so /hero is not a redirect.
 */
if ($requestPath === '/hero' || $requestPath === '/hero/') {
    unset($_GET['view'], $_GET['t'], $_GET['flagAttributesBoxOpen'], $_REQUEST['view'], $_REQUEST['t'], $_REQUEST['flagAttributesBoxOpen']);
    $requestPath = '/hero/inventory';
}
/*
 * The reference redirects both bare entries once: the auction hub opens on its
 * buy tab and the task board opens on the village tab.
 */
$bareRedirects = ['/auctions' => '/auctions/buy', '/auctions/' => '/auctions/buy'];
if (isset($bareRedirects[$requestPath])) {
    WebService::redirect($bareRedirects[$requestPath]);
}
if (($requestPath === '/tasks' || $requestPath === '/tasks/') && !isset($_GET['t'])) {
    WebService::redirect('/tasks?t=village');
}
$cleanHeroRoutes = [
    '/hero/inventory' => ['t' => '1'],
    '/hero/attributes' => ['t' => '1', 'view' => 'attributes'],
    '/hero/appearance' => ['t' => '2'],
    '/hero/adventures' => ['t' => '3'],
    '/auctions/buy' => ['t' => '4', 'action' => 'buy'],
    '/auctions/sell' => ['t' => '4', 'action' => 'sell'],
    '/auctions/overview' => ['t' => '4', 'action' => 'bids'],
];
if (preg_match('#^/auctions/buy/(?P<type>[1-9][0-9]*)$#D', $requestPath, $auctionDetailMatch) === 1) {
    setRouteParam('t', '4');
    setRouteParam('action', 'buy');
    setRouteParam('auctionType', $auctionDetailMatch['type']);
    $page = 'hero.php';
}
if (isset($cleanHeroRoutes[$requestPath])) {
    foreach ($cleanHeroRoutes[$requestPath] as $key => $value) {
        setRouteParam($key, $value);
    }
    $page = 'hero.php';
}

define('LOADED_PAGE', $page);

if (isset($_GET['summary'])) {
    require __DIR__ . '/report.php';
    exit();
}
if (isset($_GET['errors']) && Session::getInstance()->isAdmin()) {
    header('Content-Type: text/plain');
    echo file_get_contents(ERROR_LOG_FILE);
    exit();
}
if ($uri == '/info') {
    $httpMethod = strtoupper($_SERVER['REQUEST_METHOD']);
    if ($httpMethod == 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header(
            'Access-Control-Allow-Headers: X-Requested-With, Content-Type, Origin, Authorization, Accept, Client-Security-Token, Accept-Encoding'
        );
        http_response_code(200);
        return;
    }
    header('Content-Type: application/json');
    $config = Config::getInstance();
    echo json_encode([
        'worldId' => getWorldId(),
        'worldUniqueId' => (int) getWorldUniqueId(),
        'name' => Config::getProperty('settings', 'serverName'),
        'speed' => getGame('speed'),
        'movement_speed_increase' => getGame('movement_speed_increase'),
        'storage_multiplier' => getGame('storage_multiplier'),
        'cranny_multiplier' => getGame('cranny_multiplier'),
        'trap_multiplier' => getGame('trap_multiplier'),
        'cage_multiplier' => getGame('cage_multiplier'),
        'dailyQuestInterval' => getGame('dailyQuestInterval'),
        'heroRegenerationMultiplier' => getGame('heroRegenerationMultiplier'),
        'oasisAnimalCap' => getGame('oasisAnimalCap'),
        'oasisRespawnInterval' => getGame('oasisRespawnInterval'),
        'starvation' => getGame('starvation'),
        'map_size' => (int) MAP_SIZE,
        'start_time' => date('Y-m-d H:i:s', getGame('start_time')),
        'round_length' => getGame('round_length'),
        'ArtifactsReleaseTime' => date('Y-m-d H:i:s', $config->timers->ArtifactsReleaseTime),
        'WWPlansReleaseTime' => date('Y-m-d H:i:s', $config->timers->wwPlansReleaseTime),
        'WWConstructStartTime' => date('Y-m-d H:i:s', $config->timers->WWConstructStartTime),
        'WWUpLvlInterval' => $config->timers->WWUpLvlInterval,
        'AutoFinishTime' => date('Y-m-d H:i:s', $config->timers->AutoFinishTime),
        'finishTrainingEnabled' => Config::getProperty('extraSettings', 'generalOptions', 'finishTraining', 'enabled'),
    ]);
    return;
}
$pages = [
    'detect.php' => '!',
    '403.php' => 'PermissionDeniedCtrl',
    '404.php' => 'PageNotFoundCtrl',
    'activate.php' => 'ActivateCtrl',
    'register' => 'RegisterCtrl',
    'admin.php' => 'AdminCtrl',
    'ajax.php' => 'AjaxCtrl',
    'alliance' => 'AllianceCtrl',
    'reports.php' => 'ReportsCtrl',
    'build.php' => 'BuildCtrl',
    'cropfinder.php' => 'CropfinderCtrl',
    'dorf1.php' => 'ResourceFieldsCtrl',
    'dorf2.php' => 'VillageCenterCtrl',
    'dorf3' => 'VillageStatisticsCtrl',
    'hero.php' => 'HeroDivider',
    'homepage' => 'HomepageCtrl',
    'support_ticket' => 'SupportTicketCtrl',
    'legal_page' => 'LegalPageCtrl',
    'payment_return' => 'PaymentReturnCtrl',
    'unsubscribe' => 'UnsubscribeCtrl',
    'oauth_start' => 'OAuthStartCtrl',
    'oauth_callback' => 'OAuthCallbackCtrl',
    'oauth_link' => 'OAuthLinkCtrl',
    'oauth_unlink' => 'OAuthUnlinkCtrl',
    'oauth_cancel' => 'OAuthCancelCtrl',
    'hero_v2_image.php' => 'HeroV2ImageCtrl',
    'index.php' => 'LoginCtrl',
    'karte.php' => 'MapCtrl',
    'linklist.php' => 'LinkListCtrl',
    'login.php' => 'LoginCtrl',
    'auth_logout' => 'AuthLogoutCtrl',
    'logout' => 'LogoutCtrl',
    'map_block.php' => 'MapBlockCtrl',
    'map_mark.php' => 'MapMarkCtrl',
    'minimap.php' => 'MinimapCtrl',
    'messages' => 'MessagesCtrl',
    'options' => 'OptionCtrl',
    'respawn' => 'RespawnCtrl',
    'password.php' => 'PasswordCtrl',
    'position_details.php' => 'PositionDetailsCtrl',
    'production.php' => 'ProductionCtrl',
    'profile' => 'ProfileCtrl',
    'statistiken.php' => 'StatisticsCtrl',
    'tasks' => 'TasksCtrl',
    'seasonalRewards' => 'SeasonalRewardsCtrl',
    'referAFriend' => 'ReferAFriendCtrl',
    'verify.php' => 'EmailVerificationCtrl',
    'verify-url.php' => 'EmailVerificationUrlCtrl',
];

if (isset($pages[$page])) {
    if ($page == 'admin.php') {
        require __DIR__ . '/admin/include/bootstrap.php';
        \Dispatcher::getInstance();
        return;
    } elseif ($page == 'detect.php') {
        require __DIR__ . '/detect.php';
        return;
    }
    $dispatcher = new Dispatcher();
    $dispatcher->dispatch($pages[$page]);
} else {
    switch ($page) {
        default:
            $dispatcher = new Dispatcher();
            $dispatcher->dispatch($pages['404.php']);
            break;
    }
}
