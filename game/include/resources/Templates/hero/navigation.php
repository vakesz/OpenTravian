<?php

$selectedTab = (int)($vars['selectedTab'] ?? 1);
$tabs = [
    1 => ['label' => T('HeroGlobal', 'Inventory'), 'url' => '/hero/inventory'],
    2 => ['label' => T('HeroGlobal', 'Attributes'), 'url' => '/hero/attributes'],
    3 => ['label' => T('HeroGlobal', 'Appear'), 'url' => '/hero/appearance'],
];

$favouriteTitle = sprintf((string)T('inGame', 'Set tab "x" as favourite'), (string)($tabs[$selectedTab]['label'] ?? ''));
?>
<a id="tabFavorButton" class="icon contentTitleButton buttonFramed green withIcon rectangle" title="<?= htmlspecialchars($favouriteTitle, ENT_QUOTES, 'UTF-8'); ?>"></a>
<div>
    <?php
    // the React hero tabs are bare, non-focusable anchors. The local
    // handler retains their observed pointer navigation without restoring hrefs.
    $stripTabs = [];
    foreach ($tabs as $tabId => $tab) {
        $stripTabs[] = ['label' => (string)$tab['label'], 'href' => $tab['url'], 'active' => $selectedTab === $tabId];
    }
    echo \resources\View\PHPBatchView::render('layout/tabStrip', [
        'tabs' => $stripTabs,
        'favor' => true,
        'wrapperClass' => 'tabFavorWrapper',
        'ids' => false,
        'reactState' => true,
        'bareAnchors' => true,
    ]);
    ?>
    <div class="navigationSpacer"></div>
</div>
<script type="text/javascript">
    jQuery(function () {
        var routes = <?= json_encode(array_values(array_column($tabs, 'url'))); ?>;
        jQuery('#heroV2 .contentNavi.subNavi .tabItem').each(function (index) {
            jQuery(this).on('click', function (event) {
                event.preventDefault();
                window.location.href = routes[index];
            });
        });
        jQuery('#tabFavorButton').on('click', function (event) {
            event.preventDefault();
            Travian.api('favourite-tab', {
                data: {name: 'hero', number: '<?=$selectedTab;?>'}
            });
        });
    });
</script>
<?php if (in_array(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), ['/hero', '/hero/'], true)): ?>
<script type="text/javascript">window.history.replaceState(null, '', '/hero/inventory' + window.location.search);</script>
<?php endif; ?>
