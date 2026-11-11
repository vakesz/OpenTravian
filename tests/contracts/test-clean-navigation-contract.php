<?php

declare(strict_types=1);

function failCleanNavigationContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$router = file_get_contents($root . '/game/include/mainInclude.php');
$layout = file_get_contents($root . '/game/include/resources/Templates/layout/Layout.php');
if ($router === false || $layout === false) {
    failCleanNavigationContract('Navigation sources must be readable.');
}

$aliases = [
    "'options' => 'OptionCtrl'",
    "'profile' => 'ProfileCtrl'",
    "'statistics' => 'statistiken.php'",
    "'report' => 'reports.php'",
    "'messages' => 'MessagesCtrl'",
    "'alliance' => 'AllianceCtrl'",
    "'logout' => 'LogoutCtrl'",
];
foreach ($aliases as $alias) {
    if (!str_contains($router, $alias)) {
        failCleanNavigationContract('The V2 clean URL aliases must dispatch to their existing controllers.');
    }
}

foreach ([
    "'/statistics/player'",
    "'/statistics/player/overview'",
    "'/statistics/player/attackers'",
    "'/statistics/alliance'",
    "'/statistics/wonderoftheworld'",
] as $statisticsRoute) {
    if (!str_contains($router, $statisticsRoute)) {
        failCleanNavigationContract("Nested statistics route {$statisticsRoute} must dispatch to StatisticsCtrl.");
    }
}

if (!str_contains($router, "'referAFriend' => 'ReferAFriendCtrl'")) {
    failCleanNavigationContract('The /referAFriend route must dispatch to ReferAFriendCtrl.');
}

foreach ([
    'href="/options"',
    'href="/profile"',
    'href="/statistics"',
    'href="/report"',
    'href="/messages"',
    'href="/referAFriend"',
    'Session::logoutUrl()',
] as $url) {
    if (!str_contains($layout, $url)) {
        failCleanNavigationContract('The player-facing shell must expose clean navigation URLs.');
    }
}

if (preg_match('/href="spieler\.php"/', $layout) === 1
    || preg_match('/href="options\.php"/', $layout) === 1) {
    failCleanNavigationContract('Shell profile and options links must use clean aliases, not legacy .php hrefs.');
}

if (!str_contains($layout, 'class="referAFriend"')) {
    failCleanNavigationContract('The mobile menu must include the Refer a friend entry.');
}

foreach (
    [
        "'/village/statistics'",
        "'/village/statistics/resources'",
        "'/village/statistics/culturepoints'",
        "'/village/statistics/troops'",
        "'/alliance/reports'",
        "'/report/surrounding'",
    ] as $linkListTarget
) {
    if (!str_contains($router, $linkListTarget)) {
        failCleanNavigationContract("Link-list target {$linkListTarget} must dispatch to its existing controller.");
    }
}

echo "Clean navigation contract passed.\n";
