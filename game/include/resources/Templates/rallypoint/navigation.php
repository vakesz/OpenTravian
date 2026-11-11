<?php

$selectedTab = (int)$vars['tt'];
$tabUrl = static fn (int $tab): string => buildTabUrl(39, 16, 'tt', $tab);
$tabs = [];
foreach (!empty($vars['show']) ? [0, 1, 2, 3] : [0] as $tab) {
    $tabs[] = [
        'label' => (string)$vars['tabs'][$tab]['text'],
        'href' => $tabUrl($tab),
        'active' => $selectedTab === $tab,
        'slug' => (string)$tab,
        'title' => (string)$vars['tabs'][$tab]['text'],
        'wrapperClass' => $tab === 1 ? 'overviewRallyPoint' : '',
    ];
}
$farmListTab = $vars['tabs'][99];
$farmList = [
    'label' => (string)$farmListTab['text'],
    'href' => $tabUrl(99),
    'active' => $selectedTab === 99,
    'slug' => '99',
    'title' => (string)$farmListTab['text'],
];
if (empty($farmListTab['hasClub'])) {
    $farmList['gold'] = true;
    $farmList['title'] = (string)$farmListTab['title'];
    $farmList['dialog'] = ['type' => 'goldclubDialog', 'config' => ['featureKey' => 'farmList', 'cssClass' => 'premiumFeaturePackage premiumFeatureGoldclub paymentShopV5', 'premiumFeatureDialogVersion' => 2, 'version' => 2]];
}
$tabs[] = $farmList;
echo \resources\View\PHPBatchView::render('layout/tabStrip', [
    'tabs' => $tabs,
    'favor' => true,
    'favorSlug' => $vars['favorTabId'] === null ? null : (string)(int)$vars['favorTabId'],
    'favorButton' => [
        'name' => 'buildingRallyPoint',
        'key' => (string)$selectedTab,
        'slug' => (string)$selectedTab,
        'title' => T('inGame', 'Set tab'),
        'selector' => '.subNavi .favor',
        'successGuard' => false,
    ],
]);
?>
<div class="navigationSpacer"></div>
