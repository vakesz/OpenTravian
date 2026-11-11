<?php

declare(strict_types=1);

require_once __DIR__ . '/../../dailyQuest/icons.php';

$resourceKeys = ['lumber', 'clay', 'iron', 'crop'];
$resourceAmounts = array_pad(array_map('intval', $vars['resources'] ?? [0, 0, 0, 0]), 4, 0);
$merchantCapacity = (int) $vars['merchantCapacity'];
$merchantsAvailable = (int) $vars['merchantsAvailable'];
$hasAlliance = !empty($vars['hasAlliance']);
$e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$plusSvg = '<svg aria-hidden="true"><svg viewBox="0 0 18 18" class="outline"><path d="M16 10h-6v6H8v-6H2V8h6V2h2v6h6z"></path></svg><svg viewBox="0 0 18 18" class="icon"><path d="M16 10h-6v6H8v-6H2V8h6V2h2v6h6z"></path></svg></svg>';
$minusSvg = '<svg aria-hidden="true"><svg viewBox="0 0 18 6" class="outline"><path d="M16 4H2V2h14z"></path></svg><svg viewBox="0 0 18 6" class="icon"><path d="M16 4H2V2h14z"></path></svg></svg>';

$renderResourcePicker = static function (string $role, int $activeIndex, bool $stockLimited) use (
    $resourceKeys,
    $resourceAmounts,
    $plusSvg,
    $minusSvg
): void {
    ?>
    <div class="resourcePicker" data-offer-role="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>">
        <?php foreach ($resourceKeys as $index => $key): ?>
            <div class="resource <?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="button"
                        class="textButtonV2 buttonFramed selectResource rectangle withIcon toggle activeNotClickable<?= $index === $activeIndex ? ' active orange' : ' grey'; ?>"
                        data-resource="<?= $index; ?>">
                    <i class="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>_small"></i>
                </button>
            </div>
        <?php endforeach; ?>
        <div class="progressBar preventMobileSwipeNavigation">
            <div class="bar">
                <?= daily_quest_svg('decoration_start'); ?>
                <div class="filling primary blue" style="width: 0%;"></div>
                <?= daily_quest_svg('decoration_end'); ?>
            </div>
            <button type="button" class="textButtonV2 buttonFramed plus rectangle withIcon green" data-offer-step="1"><?= $plusSvg; ?></button>
            <button type="button" class="textButtonV2 buttonFramed minus rectangle withIcon green" data-offer-step="-1" disabled><?= $minusSvg; ?></button>
            <div class="slider"><div class="handle blue" style="margin-inline-start: 0%;"></div></div>
        </div>
        <div class="inputRatio stock isLtr">
            <span class="nominator">
                <label class="input amount">
                    <input type="text" inputmode="numeric" autocomplete="off" value="0"<?= $stockLimited ? '' : ''; ?>>
                </label>
            </span>
            <span class="separator">/</span>
            <span class="denominator" data-offer-denominator><?= $stockLimited ? number_format_x($resourceAmounts[$activeIndex]) : '0'; ?></span>
        </div>
    </div>
    <?php
};
?>
<div id="marketplaceOffer" class="contentV2">
    <div class="merchantsInformation">
        <div class="available"><?= T('MarketPlace', 'Merchants'); ?>:&nbsp;<span class="value">&#8237;&#8237;<?= $merchantsAvailable; ?>&#8236;/&#8237;<?= (int) $vars['total_merchants']; ?>&#8236;&#8236;</span></div>
        <div class="capacity"><?= T('MarketPlace', 'Capacity per merchant'); ?>:&nbsp;<span class="value"><?= $merchantCapacity; ?></span></div>
    </div>
    <div class="headerWrapper">
        <h3><?= T('MarketPlace', 'My offers'); ?></h3>
        <button type="button" class="textButtonV2 buttonFramed rectangle withText green" id="openCreateOfferDialog">
            <div><?= T('MarketPlace', 'Create offer'); ?></div>
        </button>
    </div>
    <?php if ($vars['error'] !== ''): ?>
        <p class="error"><?= $vars['error']; ?></p>
    <?php endif; ?>
    <?php if ($vars['success'] !== ''): ?>
        <p class="success"><?= $vars['success']; ?></p>
    <?php endif; ?>
    <?php if ($vars['hasOffers']): ?>
        <table id="sell_overview" cellpadding="1" cellspacing="1">
            <thead>
            <tr>
                <td>&nbsp;</td>
                <td><?= T('MarketPlace', "I'm offering"); ?></td>
                <th><img src="img/spacer.gif" class="ratio" alt="ratio"></th>
                <td><?= T('MarketPlace', "I'm searching"); ?></td>
                <td><?= T('MarketPlace', 'Merchants'); ?></td>
                <td><?= T('MarketPlace', 'Alliance'); ?></td>
                <td><?= T('Global', 'General.duration'); ?></td>
            </tr>
            </thead>
            <tbody>
            <?= $vars['content']; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div id="createOfferDialogTemplate" class="hide" hidden>
    <div class="contentV2">
        <h3><?= T('MarketPlace', 'Create offer'); ?></h3>
        <div id="createOfferForm" class="formV2">
            <h4><?= T('MarketPlace', "I'm offering"); ?></h4>
            <div class="merchantCarryInfo"><?= T('MarketPlace', 'Each of your merchants can carry'); ?> <strong><?= number_format_x($merchantCapacity); ?></strong> <?= T('MarketPlace', 'Each of your merchants can carry resources'); ?>.</div>
            <?php $renderResourcePicker('offer', 0, true); ?>
            <div class="summary"><?= T('MarketPlace', 'Merchants'); ?>: &nbsp;&#8237;<span class="nominator">&#8237;0&#8236;</span>&nbsp;/&nbsp;<span class="denominator">&#8237;<?= $merchantsAvailable; ?>&#8236;</span>&#8236;</div>
            <div class="ratioIndicator offerQuality0"><span class="value">–</span></div>
            <h4><?= T('MarketPlace', "I'm searching"); ?></h4>
            <?php $renderResourcePicker('search', 1, false); ?>
            <div class="maxTransportTime">
                <label class="switch">
                    <input type="checkbox" value="enabled" name="maxTime">
                    <div class="switch"></div>
                    <div class="label"><?= T('MarketPlace', 'Maximum hours of transport'); ?></div>
                </label>
                <label class="input fixed">
                    <input type="text" inputmode="numeric" value="1" disabled>
                </label>
            </div>
            <?php if ($hasAlliance): ?>
                <div class="ownAlliance">
                    <label class="switch">
                        <input type="checkbox" value="enabled" name="ownAlliance">
                        <div class="switch"></div>
                        <div class="label"><?= T('MarketPlace', 'Only alliance'); ?></div>
                    </label>
                </div>
            <?php endif; ?>
            <div class="actionButtons">
                <button type="button" class="textButtonV2 buttonFramed cancel rectangle withText grey"><div><?= T('PaymentWizard', 'Cancel'); ?></div></button>
                <button type="submit" class="textButtonV2 buttonFramed create disabled rectangle withText green" disabled><div><?= T('MarketPlace', 'Create offer'); ?></div></button>
            </div>
        </div>
    </div>
</div>

<form id="createOfferPostForm" class="hide" method="POST" action="/build.php" hidden>
    <input type="hidden" name="gid" value="17">
    <input type="hidden" name="t" value="2">
    <input type="hidden" name="a" value="4">
    <input type="hidden" name="c" value="<?= $e((string) $vars['checker']); ?>">
    <input type="hidden" name="m1" value="0">
    <input type="hidden" name="m2" value="0">
    <input type="hidden" name="rid1" value="1">
    <input type="hidden" name="rid2" value="2">
    <input type="hidden" name="d1" value="">
    <input type="hidden" name="d2" value="1">
    <input type="hidden" name="ally" value="">
</form>

<script>
jQuery(function () {
    var openButton = jQuery('#openCreateOfferDialog');
    var template = jQuery('#createOfferDialogTemplate');
    var postForm = jQuery('#createOfferPostForm');
    if (!openButton.length || !template.length || typeof Travian === 'undefined' || !Travian.Dialog) {
        return;
    }

    var stock = <?= json_encode($resourceAmounts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var merchantCapacity = <?= (int) $merchantCapacity; ?>;
    var merchantsAvailable = <?= (int) $merchantsAvailable; ?>;

    function integer(value) {
        var parsed = parseInt(String(value || '').replace(/[^0-9]/g, ''), 10);
        return Number.isFinite(parsed) ? Math.max(0, parsed) : 0;
    }

    openButton.on('click', function () {
        var dialog = new Travian.Dialog.Dialog({
            preventFormSubmit: true,
            buttonOk: false,
            cssClass: 'basic createOfferDialog'
        });
        dialog.setContent(template.html());
        dialog.show();

        var root = jQuery('#dialogContent #createOfferForm');
        if (!root.length) {
            return;
        }

        var state = {
            offerType: 0,
            searchType: 1,
            offerAmount: 0,
            searchAmount: 0
        };

        function picker(role) {
            return root.find('.resourcePicker[data-offer-role="' + role + '"]');
        }

        function requiredMerchants() {
            return state.offerAmount > 0 ? Math.ceil(state.offerAmount / merchantCapacity) : 0;
        }

        function ratioValue() {
            if (state.offerAmount <= 0 || state.searchAmount <= 0) {
                return null;
            }
            return state.searchAmount / state.offerAmount;
        }

        function ratioClass(ratio) {
            if (ratio === null) {
                return 'offerQuality0';
            }
            if (ratio <= 1) {
                return 'offerQuality1';
            }
            if (ratio < 2) {
                return 'offerQuality2';
            }
            return 'offerQuality0';
        }

        function clampOffer(amount) {
            var maxByMerchants = merchantsAvailable * merchantCapacity;
            return Math.min(Math.max(0, amount), stock[state.offerType] || 0, maxByMerchants);
        }

        function clampSearch(amount) {
            if (state.offerAmount <= 0) {
                return Math.max(0, amount);
            }
            return Math.min(Math.max(0, amount), state.offerAmount * 2);
        }

        function renderPicker(role) {
            var type = role === 'offer' ? state.offerType : state.searchType;
            var amount = role === 'offer' ? state.offerAmount : state.searchAmount;
            var node = picker(role);
            var denom = role === 'offer' ? (stock[type] || 0) : Math.max(state.offerAmount * 2, amount, 0);
            var ratio = denom > 0 ? (amount / denom) * 100 : 0;
            var percentage = Math.min(100, Math.max(0, ratio)) + '%';
            node.find('.selectResource').each(function () {
                var index = parseInt(jQuery(this).attr('data-resource'), 10);
                var active = index === type;
                jQuery(this)
                    .toggleClass('active orange', active)
                    .toggleClass('grey', !active);
            });
            node.find('.filling').css('width', percentage);
            node.find('.slider .handle').css('margin-inline-start', percentage);
            node.find('input[type="text"]').val(amount);
            node.find('[data-offer-denominator]').text(role === 'offer' ? denom.toLocaleString() : (state.offerAmount > 0 ? denom.toLocaleString() : '0'));
            node.find('button.minus').prop('disabled', amount === 0);
            node.find('button.plus').prop('disabled', role === 'offer'
                ? amount >= Math.min(stock[type] || 0, merchantsAvailable * merchantCapacity)
                : (state.offerAmount > 0 && amount >= state.offerAmount * 2));
        }

        function render() {
            state.offerAmount = clampOffer(state.offerAmount);
            state.searchAmount = clampSearch(state.searchAmount);
            renderPicker('offer');
            renderPicker('search');

            var required = requiredMerchants();
            root.find('.summary .nominator').text('\u202D' + required + '\u202C');
            root.find('.summary .denominator').text('\u202D' + merchantsAvailable + '\u202C');

            var ratio = ratioValue();
            var indicator = root.find('.ratioIndicator');
            indicator.attr('class', 'ratioIndicator ' + ratioClass(ratio));
            indicator.find('.value').text(ratio === null ? '–' : (Math.round(ratio * 10) / 10).toLocaleString());

            var hoursEnabled = root.find('input[name="maxTime"]').is(':checked');
            root.find('.maxTransportTime input[type="text"]').prop('disabled', !hoursEnabled);

            var sameResource = state.offerType === state.searchType;
            var ratioOk = ratio !== null && ratio <= 2 && (1 / ratio) <= 2;
            var canCreate = state.offerAmount > 0
                && state.searchAmount > 0
                && !sameResource
                && ratioOk
                && required <= merchantsAvailable
                && state.offerAmount <= (stock[state.offerType] || 0);
            root.find('button.create')
                .prop('disabled', !canCreate)
                .toggleClass('disabled', !canCreate);
        }

        root.on('click', '.selectResource', function (event) {
            event.preventDefault();
            var role = jQuery(this).closest('.resourcePicker').data('offer-role');
            var index = parseInt(jQuery(this).attr('data-resource'), 10);
            if (role === 'offer') {
                state.offerType = index;
                state.offerAmount = clampOffer(state.offerAmount);
            } else {
                state.searchType = index;
            }
            render();
        });

        root.on('click', '[data-offer-step]', function (event) {
            event.preventDefault();
            if (jQuery(this).prop('disabled')) {
                return;
            }
            var role = jQuery(this).closest('.resourcePicker').data('offer-role');
            var step = parseInt(jQuery(this).attr('data-offer-step'), 10) || 0;
            var delta = step > 0 ? Math.max(1, Math.floor(merchantCapacity / 10)) : -Math.max(1, Math.floor(merchantCapacity / 10));
            if (role === 'offer') {
                state.offerAmount = clampOffer(state.offerAmount + delta);
            } else {
                state.searchAmount = clampSearch(state.searchAmount + delta);
            }
            render();
        });

        root.on('input change', '.resourcePicker input[type="text"]', function () {
            var role = jQuery(this).closest('.resourcePicker').data('offer-role');
            var amount = integer(jQuery(this).val());
            if (role === 'offer') {
                state.offerAmount = clampOffer(amount);
            } else {
                state.searchAmount = clampSearch(amount);
            }
            render();
        });

        root.on('change', 'input[name="maxTime"]', function () {
            render();
        });

        root.on('click', 'button.cancel', function (event) {
            event.preventDefault();
            if (typeof dialog.close === 'function') {
                dialog.close();
            }
        });

        root.on('click', 'button.create', function (event) {
            event.preventDefault();
            if (jQuery(this).hasClass('disabled') || jQuery(this).prop('disabled')) {
                return;
            }
            postForm.find('input[name="m1"]').val(state.offerAmount);
            postForm.find('input[name="m2"]').val(state.searchAmount);
            postForm.find('input[name="rid1"]').val(state.offerType + 1);
            postForm.find('input[name="rid2"]').val(state.searchType + 1);
            if (root.find('input[name="maxTime"]').is(':checked')) {
                postForm.find('input[name="d1"]').val('1');
                postForm.find('input[name="d2"]').val(Math.max(1, integer(root.find('.maxTransportTime input[type="text"]').val()) || 1));
            } else {
                postForm.find('input[name="d1"]').val('');
                postForm.find('input[name="d2"]').val('1');
            }
            if (root.find('input[name="ownAlliance"]').is(':checked')) {
                postForm.find('input[name="ally"]').val('1');
            } else {
                postForm.find('input[name="ally"]').val('');
            }
            postForm.trigger('submit');
        });

        render();
    });
});
</script>
