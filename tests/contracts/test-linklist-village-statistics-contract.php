<?php

declare(strict_types=1);

function failB8Contract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2) . '/game/include';
$linkList = file_get_contents($root . '/Controller/LinkListCtrl.php');
$dorf3 = file_get_contents($root . '/Controller/VillageStatisticsCtrl.php');
$overview = file_get_contents($root . '/resources/Templates/village-statistics/overview.php');
$menu = file_get_contents($root . '/resources/Templates/village-statistics/menuEnabled.php');
$favouriteHandler = file_get_contents($root . '/Controller/Ajax/favouriteTab.php');
if ($linkList === false || $dorf3 === false || $overview === false || $menu === false || $favouriteHandler === false) {
    failB8Contract('Link-list and village-statistics sources must be readable.');
}

// sends accounts without OpenTravian Plus from /linklist.php back to /dorf1.php (verified 2026-11-07).
if (!str_contains($linkList, 'hasPlus') || !str_contains($linkList, "redirect('/dorf1.php')")) {
    failB8Contract('LinkListCtrl must redirect non-Plus players to /dorf1.php.');
}
if (!str_contains($linkList, "'contentCssClass'] = 'linklist'")) {
    failB8Contract('The link-list content class must stay linklist.');
}
if (!str_contains($linkList, 'MAX_LINKS') || !str_contains($linkList, 'modifyLink') || !str_contains($linkList, 'addLink')) {
    failB8Contract('The link-list save handler must keep add, modify and the link cap.');
}

// Empty village-statistics values are span.none "-"; without Plus the reference shows span.none "?" for the three hidden columns.
if (str_contains($dorf3, 'errorMessage')) {
    failB8Contract('VillageStatisticsCtrl must not emit span.errorMessage placeholders.');
}
if (substr_count($dorf3, '<span class="none">?</span>') !== 3) {
    failB8Contract('VillageStatisticsCtrl must show "?" only for the three columns hidden without Plus.');
}
if (substr_count($dorf3, '<span class="none">-</span>') < 3) {
    failB8Contract('VillageStatisticsCtrl must render empty cells as span.none "-".');
}
if (!str_contains($dorf3, "gid=17&t=5\">")) {
    failB8Contract('The overview merchants link must carry t=5.');
}
foreach (["'village3 village3' . match", "2 => 'Resources'", "4 => 'Culturepoints'", "5 => 'Troops'", "default => 'Overview'"] as $needle) {
    if (!str_contains($dorf3, $needle)) {
        failB8Contract("The village-statistics content class must be village3 village3<Tab> per tab (missing {$needle}).");
    }
}
if (!str_contains($overview, 'id="overview"')) {
    failB8Contract('The overview table must keep id="overview".');
}

foreach ([
    "'name' => 'village/statistics'",
    "'key' => \$labels[\$selectedTab]['slug'] ?? 'overview'",
    "'selector' => '.subNavi .favor'",
    "'successGuard' => false",
] as $needle) {
    if (!str_contains($menu, $needle)) {
        failB8Contract("Village Statistics must use the keyed favourite callback: {$needle}.");
    }
}
foreach ([
    "'village/statistics' => [",
    "'culturepoints' => 4,",
    "'village/statistics' => 'villageOverview',",
] as $needle) {
    if (!str_contains($favouriteHandler, $needle)) {
        failB8Contract("The favourite API must map the Village Statistics key: {$needle}.");
    }
}
if (str_contains($favouriteHandler, "case 'villageOverview':")) {
    failB8Contract('Village Statistics must not retain the obsolete numeric favourite callback.');
}

echo "Link-list access and village-statistics markup contract passed.\n";
