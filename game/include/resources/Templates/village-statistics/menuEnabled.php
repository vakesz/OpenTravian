<?php

use Core\Session;

$labels = [
    0 => ['slug' => 'overview', 'label' => 'Overview'],
    2 => ['slug' => 'resources', 'label' => 'Resources'],
    4 => ['slug' => 'culturepoints', 'label' => 'CulturePoints'],
    5 => ['slug' => 'troops', 'label' => 'Troops'],
];
$selectedTab = (int)$vars['selectedTabId'];
$favoriteTab = Session::getInstance()->getStoredFavoriteTab('villageOverview');
$tabs = [];
foreach ($labels as $index => $tab) {
    $tabs[] = [
        'slug' => $tab['slug'],
        'label' => T('villageOverview', $tab['label']),
        'href' => villageStatisticsUrl($index),
        'active' => $selectedTab === $index,
    ];
}
echo \resources\View\PHPBatchView::render('layout/tabStrip', [
    'tabs' => $tabs,
    'favor' => true,
    // A fresh account has no stored favourite. Avoid indexing the labels with
    // null: PHP 8.5 promotes that warning to the game's fatal-error page.
    'favorSlug' => $favoriteTab === null ? null : ($labels[$favoriteTab]['slug'] ?? null),
    'favorButton' => [
        'name' => 'village/statistics',
        'key' => $labels[$selectedTab]['slug'] ?? 'overview',
        'slug' => $labels[$selectedTab]['slug'] ?? 'overview',
        'title' => T('inGame', 'Set tab'),
        'selector' => '.subNavi .favor',
        'successGuard' => false,
    ],
]);

?>
<div class="navigationSpacer"></div>
