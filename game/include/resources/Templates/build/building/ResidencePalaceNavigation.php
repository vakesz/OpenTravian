<?php

$selectedTab = (int)$vars['selectedTabId'];
$buildingIndex = (int)$vars['buildingIndex'];
$palaceTabs = [
    ['index' => 0, 'label' => T('ResidencePalace', 'Management')],
];
if ((int)$vars['buildingLevel'] >= 1) {
    $palaceTabs = array_merge($palaceTabs, [
        ['index' => 1, 'label' => T('ResidencePalace', 'Train')],
        ['index' => 2, 'label' => T('ResidencePalace', 'CulturePoints')],
        ['index' => 3, 'label' => T('ResidencePalace', 'Loyalty')],
        ['index' => 4, 'label' => T('ResidencePalace', 'Expansion')],
    ]);
}
$tabs = [];
foreach ($palaceTabs as $tab) {
    $tabs[] = [
        'label' => $tab['label'],
        'href' => buildTabUrl($buildingIndex, (int)$vars['gid'], 's', $tab['index']),
        'active' => $selectedTab === $tab['index'],
        'slug' => (string)$tab['index'],
    ];
}
echo \resources\View\PHPBatchView::render('layout/tabStrip', [
    'tabs' => $tabs,
    'favor' => true,
    'favorSlug' => $vars['favor'] === null ? null : (string)$vars['favor'],
    'favorButton' => ['name' => 'buildingExpansion', 'key' => (string)$selectedTab, 'slug' => (string)$selectedTab, 'title' => T('inGame', 'Set tab'), 'selector' => '.subNavi .favor', 'successGuard' => false],
]);
?>
<div class="navigationSpacer"></div>
