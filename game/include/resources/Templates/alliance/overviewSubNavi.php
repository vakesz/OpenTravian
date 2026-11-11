<?php
/** @var array<string, mixed> $vars Injected by resources\View\PHPBatchView::display(). */
$action = (string) ($vars['overviewAction'] ?? 'internalProfile');
$favorAction = (string) ($vars['favorAction'] ?? '');
$prefix = !empty($vars['ownAllianceRoutes']) ? '/alliance/overview' : ('/alliance/' . (int) $vars['aid'] . '/overview');
?>
<div class="contentNavi tabNavi">
    <div title="" class="container <?= $action === 'internalProfile' ? 'active' : 'normal' ?>">
        <div class="background-start">&nbsp;</div>
        <div class="background-end">&nbsp;</div>
        <div class="content favor <?= $favorAction === 'internalProfile' ? 'favorActive ' : '' ?>favorKeyinternalProfile">
            <a id="<?= get_button_id(); ?>" class="tabItem" href="<?= htmlspecialchars($prefix . '/internalProfile', ENT_QUOTES, 'UTF-8') ?>">
                <?= T("Alliance", "Internal profile"); ?>
                <?php if ($favorAction === 'internalProfile'): ?><img src="/img/spacer.gif" class="favorIcon" alt="<?= T("inGame", "This tab is selected as your fav tab"); ?>"><?php endif; ?>
            </a>
        </div>
    </div>
    <div title="" class="container <?= $action === 'top10' ? 'active' : 'normal' ?>">
        <div class="background-start">&nbsp;</div>
        <div class="background-end">&nbsp;</div>
        <div class="content favor <?= $favorAction === 'top10' ? 'favorActive ' : '' ?>favorKeytop10">
            <a id="<?= get_button_id(); ?>" class="tabItem" href="<?= htmlspecialchars($prefix . '/top10', ENT_QUOTES, 'UTF-8') ?>">
                <?= T("Alliance", "Top 10"); ?>
                <?php if ($favorAction === 'top10'): ?><img src="/img/spacer.gif" class="favorIcon" alt="<?= T("inGame", "This tab is selected as your fav tab"); ?>"><?php endif; ?>
            </a>
        </div>
    </div>
    <a type="submit" id="nestedTabFavorButton" class="icon nestedTabRowButton " title="<?= T("inGame", "Set tab"); ?>" onclick="Travian.api('favourite-tab', {data: {name: 'allianceOverview',key: '<?= $action ?>'},success: function(data) {jQuery('.tabNavi .favor').removeClass('favorActive');jQuery('.favor.favorKey<?= $action ?>').addClass('favorActive');}});return false;" useicon=""><img src="/img/spacer.gif" class="&nbsp;" alt="" /></a>
    <div class="clear"></div>
</div>
