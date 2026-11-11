<?php

declare(strict_types=1);

function failProductionOverviewRouteContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/game/include/Controller/ProductionCtrl.php');
$layout = file_get_contents($root . '/game/include/resources/Templates/layout/Layout.php');
$menu = file_get_contents($root . '/game/include/resources/Templates/production/menu.php');
$enUs = file_get_contents($root . '/game/include/resources/Translation/en-US.php');
$goldHelper = file_get_contents($root . '/game/include/Game/GoldHelper.php');

if (in_array(false, [$controller, $layout, $menu, $enUs, $goldHelper], true)) {
    failProductionOverviewRouteContract('Production overview sources must be readable.');
}

foreach ([
    "private const TAB_SLUGS",
    "public static function resolveTabIndex",
    "public static function tabSlug",
    "'lumber' => 1",
    "'clay' => 2",
    "'iron' => 3",
    "'crop' => 4",
    "'balance' => 5",
] as $needle) {
    if (!str_contains($controller, $needle)) {
        failProductionOverviewRouteContract("ProductionCtrl must map alias and legacy numeric tabs: missing {$needle}");
    }
}

foreach ([
    'class="stockBarButton resource1" href="/production.php?t=lumber"',
    'class="stockBarButton resource2" href="/production.php?t=clay"',
    'class="stockBarButton resource3" href="/production.php?t=iron"',
    'class="stockBarButton resource4" href="/production.php?t=crop"',
    'class="stockBarButton" href="/production.php?t=balance"',
] as $needle) {
    if (!str_contains($layout, $needle)) {
        failProductionOverviewRouteContract("Stock bar must use reference resource classes and alias hrefs: missing {$needle}");
    }
}

if (preg_match('/stockBarButton[^>]*(?:t=[1-5]|href="\/production\.php\?t=[1-5]")/', $layout) === 1) {
    failProductionOverviewRouteContract('Stock bar must not emit legacy numeric production tab hrefs.');
}

if (!str_contains($menu, "production.php?t=' . \$tabSlugs[\$tabIndex]")
    && !str_contains($menu, 'production.php?t=\' . $tabSlugs[$tabIndex]')) {
    failProductionOverviewRouteContract('Production menu tabs must emit canonical alias hrefs.');
}

foreach ([
    '"balance" => "Balance"',
    '"production_per_hour" => "Production per hour"',
    '"Oases" => "Oasis"',
    '"productionWithBoost" => "Hourly production with %s production bonus"',
] as $needle) {
    if (!str_contains($enUs, $needle)) {
        failProductionOverviewRouteContract("en-US production overview copy must match the reference: missing {$needle}");
    }
}

if (!str_contains($goldHelper, "T(\"productionOverview\", \$hasBoost ? 'extendNow' : 'activeNow')")) {
    failProductionOverviewRouteContract('Production-page boost buttons must use Activate now / Extend now labels.');
}

$resTemplate = file_get_contents($root . '/game/include/resources/Templates/production/res.php');
if ($resTemplate === false
    || !str_contains($resTemplate, 'compensation_bonus')
    || !str_contains($controller, "'bodyCssClass'] = 'production'")) {
    failProductionOverviewRouteContract('Production overview must use body class production and expose the compensation bonus row.');
}
if (!str_contains($enUs, '"compensation_bonus" => "Compensation bonus"')) {
    failProductionOverviewRouteContract('en-US must define the Compensation bonus label.');
}

echo "Production overview route and stock-bar contract passed.\n";
