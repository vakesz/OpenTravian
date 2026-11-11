<?php

$tradeRouteUrl = '/build.php?id=' . (int)$vars['index'] . '&amp;t=3';
$escape = static fn ($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div id="tradeRoutes" class="contentV2">
    <div class="tradeRoutesInfo">
        <h3><?= T('MarketPlace', 'Trade routes'); ?></h3>
        <p><?= T('MarketPlace', 'tradeRouteDesc'); ?></p>
        <?php if ($vars['tradeRoutesInfo'] !== ''): ?>
            <p><?= $escape($vars['tradeRoutesInfo']); ?></p>
        <?php endif; ?>
    </div>
    <div class="createNewTradeRoute">
        <a class="textButtonV2 buttonFramed rectangle withText green" href="<?= $tradeRouteUrl; ?>&amp;option=1&amp;show-destination=all"><div><?= T('MarketPlace', 'Create new trade route'); ?></div></a>
    </div>

    <?php if (!$vars['groups']): ?>
        <div class="tradeRouteCollection">
            <div class="headline"><div><?= T('MarketPlace', 'Trade routes'); ?></div></div>
            <table><tbody><tr><td class="noData"><?= T('MarketPlace', 'noRoute'); ?></td></tr></tbody></table>
        </div>
    <?php endif; ?>

    <?php foreach ($vars['groups'] as $group): ?>
        <section class="tradeRouteCollection">
            <div class="headline">
                <div><?= $escape(sprintf(T('MarketPlace', 'Trade route to x'), $group['target'])); ?></div>
                <div class="expandCollapse expanded" role="button" tabindex="0" aria-expanded="true">
                    <svg viewBox="0 0 12 7.41" aria-hidden="true"><path d="M1.41 0L6 4.58 10.59 0 12 1.41l-6 6-6-6z"></path></svg>
                </div>
            </div>
            <table>
                <thead><tr><th><?= T('MarketPlace', 'Resources'); ?></th><th class="departure"><?= T('MarketPlace', 'Start'); ?></th><th class="merchants"><?= T('MarketPlace', 'Merchants'); ?></th><th><?= T('MarketPlace', 'Action'); ?></th><th class="selection"><?= T('MarketPlace', 'Enabled'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($group['routes'] as $route): ?>
                    <tr class="<?= $route['enabled'] ? '' : 'deactivated'; ?>">
                        <td class="resources"><div><?php foreach ($route['resources'] as $offset => $amount): ?><div><i class="r<?= $offset + 1; ?>"></i><span><?= number_format_x($amount); ?></span></div><?php endforeach; ?></div></td>
                        <td class="departure"><div><span class="deliveries"><?= (int)$route['deliveries']; ?>×</span><span class="departure"><?= $escape($route['start']); ?></span></div></td>
                        <td class="merchants"><span class="inlineIcon"><i class="merchantCap_small"></i><span><?= (int)$route['merchants']; ?></span></span></td>
                        <td class="action"><a class="textButtonV2 buttonFramed rectangle withText grey" href="<?= $escape($route['editUrl']); ?>"><div><?= T('MarketPlace', 'edit'); ?></div></a><a class="textButtonV2 buttonFramed rectangle withText grey" href="<?= $escape($route['deleteUrl']); ?>"><div><?= T('MarketPlace', 'Delete'); ?></div></a></td>
                        <td class="selection"><label class="switch"><input type="checkbox"<?= $route['enabled'] ? ' checked' : ''; ?> onchange="Travian.Game.Marketplace.toggleTradeRoutes(<?= (int)$route['id']; ?>, this)"><span class="switch" aria-hidden="true"></span></label></td>
                    </tr>
                <?php endforeach; ?>
                <tr><td class="addRoute" colspan="5"><a href="<?= $tradeRouteUrl; ?>&amp;option=1&amp;show-destination=all" aria-label="<?= $escape(T('MarketPlace', 'Create new trade route')); ?>"><svg viewBox="0 0 18 18" aria-hidden="true"><path class="outline" d="M9 0a9 9 0 1 0 0 18A9 9 0 0 0 9 0z"></path><path class="icon" d="M8 3h2v4h4v2h-4v4H8V9H4V7h4z"></path></svg></a></td></tr>
                </tbody>
            </table>
        </section>
    <?php endforeach; ?>
</div>
<script>
    jQuery(function () {
        jQuery('#tradeRoutes').on('click keydown', '.expandCollapse', function (event) {
            if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') {
                return;
            }
            event.preventDefault();
            var control = jQuery(this);
            var expanded = control.toggleClass('expanded').hasClass('expanded');
            control.attr('aria-expanded', expanded ? 'true' : 'false');
            control.closest('.tradeRouteCollection').find('table').toggle(expanded);
        });
    });
</script>
