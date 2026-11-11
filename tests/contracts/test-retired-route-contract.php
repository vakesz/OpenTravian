<?php

declare(strict_types=1);

function failRetiredRouteContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$routes = file_get_contents($root . '/game/include/mainInclude.php');
$statistics = file_get_contents($root . '/game/include/Controller/StatisticsCtrl.php');
$emailVerification = file_get_contents($root . '/game/include/Game/EmailVerification.php');

if ($routes === false || $statistics === false || $emailVerification === false) {
    failRetiredRouteContract('The route map and active replacements must be readable.');
}

foreach ([
    "'credits.php' =>",
    "'tgpay.php' =>",
    "'stats.php' =>",
    "'HeroAuctionCtrl.php' =>",
    "'anmelden.php' =>",
] as $retiredRoute) {
    if (str_contains($routes, $retiredRoute)) {
        failRetiredRouteContract("The retired route must not remain dispatchable: {$retiredRoute}");
    }
}

foreach ([
    "'/index.php'",
    "'/reports.php'",
    "'/stats.php'",
    "'/tgpay.php'",
    "'/credits.php'",
    "'/HeroAuctionCtrl.php'",
    "if (\$requestPath === '/statistiken.php')",
    "WebService::redirect('/statistics');",
] as $routeGuard) {
    if (!str_contains($routes, $routeGuard)) {
        failRetiredRouteContract("The exact legacy route guard is missing: {$routeGuard}");
    }
}

foreach ([
    $root . '/game/include/Controller/CreditsCtrl.php',
    $root . '/game/include/Controller/TG_PAY.php',
    $root . '/game/include/resources/Templates/credits/main.php',
    $root . '/game/include/resources/Templates/statistics/plus.php',
] as $retiredPath) {
    if (file_exists($retiredPath)) {
        failRetiredRouteContract("The retired runtime source must not remain: {$retiredPath}");
    }
}

if (str_contains($statistics, 'statistics/plus') || str_contains($statistics, 'case 4:') || str_contains($emailVerification, 'TG_PAY')) {
    failRetiredRouteContract('The active statistics and e-mail-verification paths must not retain retired dependencies.');
}

echo "Retired route contract passed.\n";
