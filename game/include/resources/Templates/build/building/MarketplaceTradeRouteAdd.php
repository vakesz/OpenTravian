<?php

use Core\Config;

$tradeRouteUrl = '/build.php?id=' . (int)$vars['index'] . '&amp;t=3';
$escape = static fn ($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$resourceNames = [T('inGame', 'resources.r1'), T('inGame', 'resources.r2'), T('inGame', 'resources.r3'), T('inGame', 'resources.r4')];
$resourceFieldNames = ['lumber', 'clay', 'iron', 'crop'];
$resourceValues = [(int)$vars['r1'], (int)$vars['r2'], (int)$vars['r3'], (int)$vars['r4']];
?>
<form class="contentV2 tradeRouteDialog formV2" action="<?= $tradeRouteUrl; ?>" method="post">
    <div class="dialogContainer">
        <h3><?= $vars['edit'] ? T('MarketPlace', 'edit') : T('MarketPlace', 'Create new trade route'); ?></h3>
        <label class="input fixed">
            <select name="did_dest" id="trade_route_destination">
                <?= $vars['did_dest']; ?>
            </select>
            <span class="label"><?= T('MarketPlace', 'Target village'); ?></span>
        </label>

        <div class="destinationOptions" role="radiogroup" aria-label="<?= $escape(T('MarketPlace', 'Target village')); ?>">
            <?php foreach (['all' => 'all', 'only-mine' => 'only mine', 'others' => 'others'] as $filter => $label): ?>
                <a class="textButtonV2 buttonFramed rectangle withText <?= $vars['destination'] === $filter ? 'green' : 'grey'; ?>"
                   href="<?= $tradeRouteUrl; ?>&amp;option=<?= $vars['edit'] ? 2 : 1; ?><?= $vars['edit'] ? '&amp;trid=' . (int)$vars['trid'] : ''; ?>&amp;show-destination=<?= $filter; ?>"
                   role="radio" aria-checked="<?= $vars['destination'] === $filter ? 'true' : 'false'; ?>"><div><?= T('MarketPlace', $label); ?></div></a>
            <?php endforeach; ?>
        </div>

        <div class="merchantCarryInfo">
            <label><?= T('MarketPlace', 'Merchants'); ?>: <strong><?= (int)$vars['available_merchants']; ?>/<?= (int)$vars['total_merchant']; ?></strong></label>
            <label><?= T('MarketPlace', 'Capacity per merchant'); ?>: <strong><?= number_format_x((int)$vars['cap']); ?></strong></label>
        </div>

        <div class="resourceSelector">
            <?php foreach ($resourceValues as $offset => $value): ?>
                <?php
                $number = $offset + 1;
                $fieldName = $resourceFieldNames[$offset];
                ?>
                <div class="resource">
                    <i class="r<?= $number; ?>Big" aria-label="<?= $escape($resourceNames[$offset]); ?>"></i>
                    <label class="input fixed">
                        <input id="<?= $fieldName; ?>" name="<?= $fieldName; ?>" type="text" inputmode="numeric" maxlength="15" value="<?= $value; ?>">
                        <span class="label"><?= $escape($resourceNames[$offset]); ?></span>
                    </label>
                </div>
            <?php endforeach; ?>
            <div class="summary">
                <div><?= T('MarketPlace', 'Total'); ?>: <span class="nominator" data-trade-route-total>0</span></div>
                <div><?= T('MarketPlace', 'Merchants'); ?>: <span data-trade-route-merchants>0</span>/<?= (int)$vars['total_merchant']; ?></div>
            </div>
        </div>

        <div class="timeSelector">
            <label class="input fixed timeInput">
                <select name="hour">
                    <?php if (Config::getInstance()->game->usePeriodicTradeRoutes): ?>
                        <?php foreach ([120, 300, 600, 1200, 1800, 2700, 3600, 7200, 10800, 14400, 18000, 21600, 43200, 86400] as $seconds): ?>
                            <option value="<?= $seconds; ?>"<?= (int)$vars['hour'] === $seconds ? ' selected' : ''; ?>><?= getTradeRouteTimeText($seconds); ?></option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php for ($hour = 0; $hour <= 23; ++$hour): ?>
                            <option value="<?= $hour; ?>"<?= (int)$vars['hour'] === $hour ? ' selected' : ''; ?>><?= sprintf('%02d:00', $hour); ?></option>
                        <?php endfor; ?>
                    <?php endif; ?>
                </select>
                <span class="label"><?= T('MarketPlace', Config::getInstance()->game->usePeriodicTradeRoutes ? 'Send time' : 'Start time'); ?></span>
            </label>
            <label class="input fixed every">
                <select name="repeat" id="repeat">
                    <?php for ($repeat = 1; $repeat <= 3; ++$repeat): ?>
                        <option value="<?= $repeat; ?>"<?= (int)$vars['times'] === $repeat ? ' selected' : ''; ?>><?= $repeat; ?>×</option>
                    <?php endfor; ?>
                </select>
                <span class="label"><?= T('MarketPlace', 'Deliveries'); ?></span>
            </label>
        </div>

        <div class="actionButtons <?= $vars['edit'] ? 'edit' : 'create'; ?>">
            <a class="textButtonV2 buttonFramed rectangle withText grey cancel" href="<?= $tradeRouteUrl; ?>"><div><?= T('MarketPlace', 'cancel'); ?></div></a>
            <button type="submit" class="textButtonV2 buttonFramed rectangle withText green save"><div><?= T('Global', 'General.save'); ?></div></button>
        </div>
        <input type="hidden" name="a" value="1">
        <input type="hidden" name="c" value="<?= $escape($vars['checker']); ?>">
        <?php if (isset($vars['trid'])): ?>
            <input type="hidden" name="trid" value="<?= (int)$vars['trid']; ?>">
        <?php endif; ?>
        <input type="hidden" name="option" value="<?= $vars['edit'] ? 2 : 1; ?>">
    </div>
</form>
<script>
    jQuery(function () {
        var form = jQuery('.tradeRouteDialog');
        var capacity = <?= (int)$vars['cap']; ?>;
        var updateSummary = function () {
            var total = 0;
            form.find('.resourceSelector input').each(function () {
                total += Math.max(0, parseInt(String(this.value).replace(/[^0-9]/g, ''), 10) || 0);
            });
            form.find('[data-trade-route-total]').text(Travian.numberFormat ? Travian.numberFormat(total) : total);
            form.find('[data-trade-route-merchants]').text(Math.ceil(total / capacity));
        };
        form.on('input', '.resourceSelector input', updateSummary);
        updateSummary();
    });
</script>
