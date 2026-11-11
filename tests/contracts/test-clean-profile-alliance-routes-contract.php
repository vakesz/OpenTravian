<?php

declare(strict_types=1);

/**
 * Player-facing links must use the clean routes. The retired legacy
 * pages (spieler.php, allianz.php, messages.php, options.php, logout.php,
 * dorf3.php and the guest support.php) must neither be emitted nor dispatched.
 */

$root = dirname(__DIR__, 2);
$failures = [];

$retired = [
    'spieler.php',
    'allianz.php',
    'messages.php',
    'options.php',
    'logout.php',
    'dorf3.php',
    'support.php',
];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/game', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    $path = $file->getPathname();
    if (!$file->isFile() || !preg_match('/\.(php|js|tpl)$/', $path)) {
        continue;
    }
    // Local admin panel is not a player-facing route.
    if (str_contains($path, '/game/include/admin/') || str_contains($path, '/vendor/') || str_contains($path, '/deployment/public/js/')) {
        continue;
    }
    $contents = (string) file_get_contents($path);
    // The reference answers the retired /dorf3.php with a redirect to the
    // village statistics route; that one guard is the only allowed mention.
    $contents = str_replace("if (\$requestPath === '/dorf3.php') {", '', $contents);
    foreach ($retired as $needle) {
        // support_form.php etc. are different names; require a path boundary before the retired name.
        if (preg_match('#(?<![\w-])' . preg_quote($needle, '#') . '#', $contents) === 1) {
            $failures[] = substr($path, strlen($root) + 1) . " references retired route {$needle}";
        }
    }
}

$router = (string) file_get_contents($root . '/game/include/mainInclude.php');
foreach ($retired as $needle) {
    if (str_contains($router, "'{$needle}' =>") || str_contains($router, "= '{$needle}'")) {
        $failures[] = "mainInclude.php still maps or aliases {$needle}";
    }
}
foreach (["'profile' => 'ProfileCtrl'", "'alliance' => 'AllianceCtrl'", "'messages' => 'MessagesCtrl'", "'options' => 'OptionCtrl'", "'logout' => 'LogoutCtrl'", "'dorf3' => 'VillageStatisticsCtrl'"] as $entry) {
    if (!str_contains($router, $entry)) {
        $failures[] = "mainInclude.php must dispatch {$entry}";
    }
}
if (!str_contains($router, '#^/profile/(\d+)/?$#') || !str_contains($router, '#^/alliance/(\d+)')) {
    $failures[] = 'mainInclude.php must route /profile/<uid> and /alliance/<aid>';
}
if (!str_contains($router, '#^/options/(game|account|avatar|sitter|vacation)/?$#')) {
    $failures[] = 'mainInclude.php must route /options/game|account|avatar|sitter|vacation';
}
if (!str_contains($router, '#^/messages/(inbox|write|sent|archive|notes|ignore)/?$#')) {
    $failures[] = 'mainInclude.php must route /messages/inbox|write|sent|archive|notes|ignore';
}

// Helpers emit the clean shapes.
require_once $root . '/game/include/functions.general.php';
$expected = [
    profileUrl(2874) => '/profile/2874',
    profileUrl() => '/profile',
    profileUrl(null, ['adminLogin' => 7]) => '/profile?adminLogin=7',
    allianceUrl(56) => '/alliance/56',
    allianceUrl() => '/alliance',
    allianceUrl(9, 'forum', ['tid' => 3]) => '/alliance/9/forum?tid=3',
    villageStatisticsUrl(0) => '/village/statistics/overview',
    villageStatisticsUrl(2) => '/village/statistics/resources',
    villageStatisticsUrl(4) => '/village/statistics/culturepoints',
    villageStatisticsUrl(5) => '/village/statistics/troops',
];
foreach ($expected as $actual => $want) {
    if ($actual !== $want) {
        $failures[] = "helper produced {$actual}, expected {$want}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
echo "Clean profile/alliance/messages/options/logout/village-statistics routes: ok\n";
