<?php
$reportTabs = [
    0 => ['slug' => 'overview', 'label' => T('Reports', 'Tabs.All'), 'href' => '/report/overview', 'class' => 'overview'],
    1 => ['slug' => 'offensive', 'label' => T('Reports', 'Tabs.Attacks'), 'href' => '/report/offensive'],
    2 => ['slug' => 'defensive', 'label' => T('Reports', 'Tabs.Defense'), 'href' => '/report/defensive'],
    3 => ['slug' => 'scouting', 'label' => T('Reports', 'Tabs.Spy'), 'href' => '/report/scouting'],
    4 => ['slug' => 'other', 'label' => T('Reports', 'Tabs.Other'), 'href' => '/report/other'],
    5 => ['slug' => 'archive', 'label' => T('Reports', 'Tabs.Archive'), 'href' => '/report/archive'],
    6 => ['slug' => 'surrounding', 'label' => T('Reports', 'Tabs.Surrounding'), 'href' => '/report/surrounding'],
];
foreach ($reportTabs as $index => &$reportTab) {
    $reportTab['active'] = (int) $vars['selectedTab'] === $index;
    if ($index === 5 && !$vars['goldClub']) {
        $reportTab['gold'] = true;
        $reportTab['title'] = $vars['ArchiveGoldClubTitle'];
        $reportTab['dialog'] = ['type' => 'goldclubDialog', 'config' => [
            'featureKey' => 'messageArchive',
            'cssClass' => 'premiumFeaturePackage premiumFeatureGoldclub paymentShopV5',
            'premiumFeatureDialogVersion' => 2,
            'version' => 2,
        ]];
    }
}
unset($reportTab);
$favoriteIndex = $vars['favorTabId'] ?? null;
echo \resources\View\PHPBatchView::render('layout/tabStrip', [
    'tabs' => $reportTabs,
    'favor' => true,
    'favorSlug' => $favoriteIndex === null ? null : ($reportTabs[(int) $favoriteIndex]['slug'] ?? null),
    'favorButton' => [
        'name' => 'report',
        'key' => $reportTabs[(int) $vars['selectedTab']]['slug'] ?? 'overview',
        'slug' => $reportTabs[(int) $vars['selectedTab']]['slug'] ?? 'overview',
        'title' => T('inGame', 'Set tab'),
        'selector' => '.subNavi .favor',
        'successGuard' => false,
    ],
]);
?>
