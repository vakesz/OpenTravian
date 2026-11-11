<?php

$selectedTab = (int)$vars['selectedTab'];
$buildingIndex = (int)$vars['index'];
$labels = [
    [0, T('MarketPlace', 'Management'), false],
];
if ((int)$vars['level'] > 0) {
    $labels[] = [5, T('MarketPlace', 'SendResources'), false];
    $labels[] = [3, T('MarketPlace', 'Trade routes'), !(bool)$vars['hasGoldClub']];
    $labels[] = [1, T('MarketPlace', 'Buy'), false];
    $labels[] = [2, T('MarketPlace', 'Offer'), false];
}
$tabs = [];
foreach ($labels as [$index, $label, $gold]) {
    $tab = [
        'label' => $label,
        'href' => buildTabUrl($buildingIndex, (int)$vars['gid'], 't', $index),
        'active' => $selectedTab === $index,
        'slug' => (string)$index,
        'title' => $label,
    ];
    if ($gold) {
        $tab['gold'] = true;
        $tab['goldHref'] = 'javascript:void(0)';
        $tab['title'] = $label . '|| ' . T('RallyPoint', 'needClubToBeActive');
        $tab['dialog'] = ['type' => 'goldclubDialog', 'config' => ['featureKey' => 'tradeRoute', 'cssClass' => 'premiumFeaturePackage premiumFeatureGoldclub paymentShopV5', 'premiumFeatureDialogVersion' => 2, 'version' => 2]];
    }
    $tabs[] = $tab;
}
echo \resources\View\PHPBatchView::render('layout/tabStrip', [
    'tabs' => $tabs,
    'favor' => true,
    'favorSlug' => $vars['favor'] === null ? null : (string)(int)$vars['favor'],
    'favorButton' => ['name' => 'buildingMarket', 'key' => (string)$selectedTab, 'slug' => (string)$selectedTab, 'title' => T('inGame', 'Set tab'), 'selector' => '.subNavi .favor', 'successGuard' => false],
]);
?>
<div class="navigationSpacer"></div>
