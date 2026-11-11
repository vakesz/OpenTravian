<?php

declare(strict_types=1);

function failSubTabsContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2) . '/game/include';
$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);
    if ($content === false) {
        failSubTabsContract("{$path} must be readable.");
    }

    return $content;
};
$main = $read('mainInclude.php');
$api = $read('Controller/Ajax/favouriteTab.php');
$economy = $read('resources/Templates/village-statistics/resourceEconomy.php');
$hospital = $read('resources/Templates/village-statistics/hospital.php');
$training = $read('resources/Templates/village-statistics/training.php');
$subTabs = $read('resources/Templates/village-statistics/subTabs.php');
$auctionMenu = $read('resources/Templates/hero/AuctionMenu.php');
$session = $read('Core/Session.php');

foreach ([
    'resources/warehouse' => "'sr' => '1'",
    'resources/production' => "'sr' => '2'",
    'resources/capacity' => "'sr' => '3'",
    'troops/hospital' => "'su' => '3'",
    'troops/training' => "'su' => '4'",
] as $path => $query) {
    if (!str_contains($main, "'/village/statistics/{$path}' => ['page' => 'dorf3', 'query' => ['s' => ") || !str_contains($main, $query)) {
        failSubTabsContract("Route /village/statistics/{$path} must map to dorf3 with {$query}.");
    }
}

// table ids, row classes and cell classes.
foreach (['id="warehouse"', 'id="production"', 'id="capacity"', 'class="max123"', 'class="max4 lc"', 'class="vil fc"', '<tr class="sum">', 'class="total"'] as $needle) {
    if (!str_contains($economy, $needle)) {
        failSubTabsContract("resourceEconomy.php must keep {$needle}.");
    }
}
foreach (['class="under_progress"', 'class="inProgress"', 'Wounded troops', 'class="villageName"'] as $needle) {
    if (!str_contains($hospital, $needle)) {
        failSubTabsContract("hospital.php must keep {$needle}.");
    }
}
foreach (['class="building_small tribe', 'class="dot"', '<span class="none">-</span>'] as $needle) {
    if (!str_contains($training, $needle)) {
        failSubTabsContract("training.php must keep {$needle}.");
    }
}

// Nested favourite buttons and persistence.
if (!str_contains($subTabs, 'id="nestedTabFavorButton"') || !str_contains($subTabs, "key: '")) {
    failSubTabsContract('subTabs.php must render a#nestedTabFavorButton posting a named key.');
}
if (str_contains($subTabs, 'title="<?= $escape($title); ?>"')
    || str_contains($subTabs, "Select x as favor tab")) {
    failSubTabsContract('nested favourite controls must not retain a local-only title tooltip.');
}
foreach (['villageStatisticsResources', 'villageOverviewTroops', "'auctions'"] as $name) {
    if (!str_contains($api, $name) || !str_contains($session, $name)) {
        failSubTabsContract("favourite-tab API and Session must both know {$name}.");
    }
}
if (!str_contains($auctionMenu, 'id="tabFavorButton"') || !str_contains($auctionMenu, "name: 'auctions'")) {
    failSubTabsContract('The auctions tab strip must render a#tabFavorButton wired to the auctions favourite.');
}

// Pure mapping checks: every nested key list must round-trip through its stored position.
require_once $root . '/Controller/Ajax/AjaxBase.php';
require_once $root . '/Controller/Ajax/favouriteTab.php';
$expected = [
    'villageStatisticsResources' => ['resources', 'warehouse', 'production', 'capacity'],
    'villageOverviewTroops' => ['own', 'support', 'smithy', 'hospital', 'training'],
    'auctions' => ['buy', 'sell', 'bids'],
];
if (\Controller\Ajax\favouriteTab::NESTED_KEYS !== $expected) {
    failSubTabsContract('Nested favourite key lists changed; update the stored-position contract deliberately.');
}

echo "Village statistics sub-tabs and nested favourite contract passed.\n";
