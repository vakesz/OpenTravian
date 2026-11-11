<?php if ($vars['showTabs']): ?>
<?php
$selectedTab = (int)$vars['selectedTab'];
$buildingIndex = (int)$vars['index'];
$treasuryTabs = [
    ['index' => 0, 'label' => T('Treasury', 'Management')],
    ['index' => 5, 'label' => T('Treasury', 'Artefacts in your area')],
    ['index' => 1, 'label' => T('Treasury', 'Small artefacts')],
    ['index' => 2, 'label' => T('Treasury', 'Large artefacts')],
];
$tabs = [];
foreach ($treasuryTabs as $tab) {
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
    'favorSlug' => (string)(int)$vars['favor'],
    'wrapperClass' => 'tabFavorWrapper',
    'favorButton' => ['name' => 'buildingTreasury', 'number' => (string)$selectedTab, 'slug' => (string)$selectedTab, 'title' => (string)$vars['favorText']],
]);
?>
<?php endif; ?>

<?= $vars['content']; ?>
