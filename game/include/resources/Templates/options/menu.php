<?php
$tabs = [
    ['label' => T('Options', 'Game'), 'href' => '/options/game', 'active' => (int)$vars['selectedTab'] === 1],
    ['label' => T('Options', 'Avatar'), 'href' => '/options/account', 'active' => (int)$vars['selectedTab'] === 2],
    ['label' => T('Options', 'Sitter'), 'href' => '/options/sitter', 'active' => (int)$vars['selectedTab'] === 3],
];
if (Core\Config::getProperty('game', 'vacationDays') > 0) {
    $tabs[] = ['label' => T('Options', 'Vacation'), 'href' => '/options/vacation', 'active' => (int)$vars['selectedTab'] === 4];
}
echo \resources\View\PHPBatchView::render('layout/tabStrip', ['tabs' => $tabs]);
?>
<div class="navigationSpacer"></div>
