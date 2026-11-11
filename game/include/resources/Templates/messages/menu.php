<?php
$messageTabs = [
    0 => ['slug' => 'inbox', 'label' => T('Messages', 'Inbox'), 'href' => '/messages/inbox'],
    1 => ['slug' => 'write', 'label' => T('Messages', 'Write'), 'href' => '/messages/write'],
    2 => ['slug' => 'sent', 'label' => T('Messages', 'Sent'), 'href' => '/messages/sent'],
    3 => ['slug' => 'archive', 'label' => T('Messages', 'Archive'), 'href' => '/messages/archive'],
    4 => ['slug' => 'notes', 'label' => T('Messages', 'Notes'), 'href' => '/messages/notes'],
    5 => ['slug' => 'ignore', 'label' => T('Messages', 'Ignored players'), 'href' => '/messages/ignore'],
];
foreach ($messageTabs as $index => &$messageTab) {
    $messageTab['active'] = (int) $vars['selectedTab'] === $index;
    if ($index === 3 && !$vars['goldClub']) {
        $messageTab['gold'] = true;
        $messageTab['title'] = $vars['ArchiveGoldClubTitle'];
        $messageTab['dialog'] = ['type' => 'goldclubDialog', 'config' => [
            'featureKey' => 'messageArchive',
            'cssClass' => 'premiumFeaturePackage premiumFeatureGoldclub paymentShopV5',
            'premiumFeatureDialogVersion' => 2,
            'version' => 2,
        ]];
    }
}
unset($messageTab);
$favoriteIndex = $vars['favorTabId'] ?? null;
echo \resources\View\PHPBatchView::render('layout/tabStrip', [
    'tabs' => $messageTabs,
    'favor' => true,
    'favorSlug' => $favoriteIndex === null ? null : ($messageTabs[(int) $favoriteIndex]['slug'] ?? null),
    'favorButton' => [
        'name' => 'messages',
        'key' => $messageTabs[(int) $vars['selectedTab']]['slug'] ?? 'inbox',
        'slug' => $messageTabs[(int) $vars['selectedTab']]['slug'] ?? 'inbox',
        'title' => T('inGame', 'Set tab'),
        'selector' => '.subNavi .favor',
        'successGuard' => false,
    ],
]);
?>
<div class="navigationSpacer"></div>
<script data-cmp-info="6">
	function messagesFormSelectAll(checkbox) {
		jQuery('#messagesForm').find('input[type=checkbox]').each(function (_, element) {
			element.checked = checkbox.checked;
		}, checkbox);
	}
</script>
