<?php
$tabSlugs = $vars['tabSlugs'] ?? [
    1 => 'lumber',
    2 => 'clay',
    3 => 'iron',
    4 => 'crop',
    5 => 'balance',
];
$tabLabels = [
    1 => T("inGame", "resources.r1"),
    2 => T("inGame", "resources.r2"),
    3 => T("inGame", "resources.r3"),
    4 => T("inGame", "resources.r4"),
    5 => T("productionOverview", "balance"),
];
$tabs = [];
foreach ($tabLabels as $tabIndex => $tabLabel) {
    $tabs[] = [
        'label' => $tabLabel,
        'href' => '/production.php?t=' . $tabSlugs[$tabIndex],
        'active' => $vars['tabIndex'] == $tabIndex,
    ];
}
echo '<div id="productionOverview"><div><h1 class="titleInHeader">' . T("productionOverview", "productionOverview") . '</h1><div>';
echo \resources\View\PHPBatchView::render('layout/tabStrip', [
    'tabs' => $tabs,
    'favor' => true,
    'wrapperClass' => 'tabFavorWrapper',
    'ids' => false,
        'reactState' => true,
]);
echo '<div class="navigationSpacer"></div></div>';
