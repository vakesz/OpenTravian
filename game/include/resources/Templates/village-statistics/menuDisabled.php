<?php

use Core\Session;

$premiumTitle = T('villageOverview', 'Village statistics||For this feature you need OpenTravian Plus activated');
$labels = [
    0 => ['slug' => 'overview', 'label' => 'Overview'],
    2 => ['slug' => 'resources', 'label' => 'Resources'],
    4 => ['slug' => 'culturepoints', 'label' => 'CulturePoints'],
    5 => ['slug' => 'troops', 'label' => 'Troops'],
];
$favoriteTab = Session::getInstance()->getStoredFavoriteTab('villageOverview');
$tabs = [];
foreach ($labels as $index => $tab) {
    $premium = $index !== 0;
    $tabs[] = [
        'slug' => $tab['slug'],
        'label' => T('villageOverview', $tab['label']),
        'href' => villageStatisticsUrl($index),
        'active' => !$premium,
        'gold' => $premium,
        'title' => $premium ? $premiumTitle : false,
        'dialog' => $premium ? ['type' => 'plusDialog', 'config' => ['featureKey' => 'villageStatistics', 'cssClass' => 'premiumFeaturePackage premiumFeaturePlus paymentShopV5', 'premiumFeatureDialogVersion' => 2, 'version' => 2]] : null,
    ];
}
echo \resources\View\PHPBatchView::render('layout/tabStrip', [
    'tabs' => $tabs,
    'favor' => true,
    'favorSlug' => $favoriteTab === null ? null : ($labels[$favoriteTab]['slug'] ?? ($favoriteTab === 1 ? 'overview' : null)),
    'favorButton' => [
        'name' => 'village/statistics',
        'key' => 'overview',
        'slug' => 'overview',
        'title' => T('inGame', 'Set tab'),
        'selector' => '.subNavi .favor',
        'successGuard' => false,
    ],
]);
?>
<div class="navigationSpacer"></div>
