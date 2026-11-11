<?php
$tabs = [
    'buy' => ['label' => 'Buy', 'path' => '/auctions/buy'],
    'sell' => ['label' => 'Sell', 'path' => '/auctions/sell'],
    'bids' => ['label' => T('Auction', 'overview'), 'path' => '/auctions/overview'],
];
$favouriteAction = $vars['favouriteAction'] ?? null;
$tabNumbers = array_flip(array_keys($tabs));
$favouriteTitle = sprintf((string)T('inGame', 'Set tab "x" as favourite'), (string)($tabs[$vars['action']]['label'] ?? ''));
?>
<a id="tabFavorButton" class="icon contentTitleButton buttonFramed green withIcon rectangle" title="<?= htmlspecialchars($favouriteTitle, ENT_QUOTES, 'UTF-8'); ?>"
   onclick="Travian.api('favourite-tab', {data: {name: 'auctions',number: '<?= $tabNumbers[$vars['action']] ?? 0; ?>'},success: function(data) {if (data.success) { jQuery('.favor').removeClass('favorActive');jQuery('.favor.favorKey<?= htmlspecialchars((string)$vars['action'], ENT_QUOTES, 'UTF-8'); ?>').addClass('favorActive');}}});return false;"></a>
<div>
<div class="contentNavi subNavi tabFavorWrapper">
    <button type="button" class="scrollFrom" disabled></button>
    <div class="scrollingContainer">
        <?php foreach ($tabs as $action => $tab): ?>
            <div class="content favor favorKey<?= $action; ?><?= $favouriteAction === $action ? ' favorActive' : ''; ?>">
                <a class="tabItem <?= $vars['action'] === $action ? 'active' : ''; ?>"
                   href="<?= $tab['path']; ?>"><?= $tab['label']; ?></a>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="scrollTo" disabled></button>
</div>
<div class="navigationSpacer"></div>
</div>
