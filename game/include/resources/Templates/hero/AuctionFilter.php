<?php
declare(strict_types=1);

$slot = (string)($vars['slot'] ?? 'all');
$showSearch = !empty($vars['showSearch']);
$showSlots = !array_key_exists('showSlots', $vars) || !empty($vars['showSlots']);
$backPath = (string)($vars['backPath'] ?? '');
$wrapperClass = trim('goldenWrapper ' . (string)($vars['wrapperClass'] ?? 'heroV2'));
$locked = !empty($vars['locked']);
$gold = (int)($vars['gold'] ?? 0);
$silver = (int)($vars['availableSilver'] ?? 0);
$searchItems = (array)($vars['searchItems'] ?? []);
$e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$renderCurrencyCoin = static function (string $kind): string {
    $currencyCoinKind = $kind;
    $currencyCoinInteractive = false;

    ob_start();
    require __DIR__ . '/../layout/currencyCoin.php';
    $coin = (string)ob_get_clean();

    return preg_replace('/\sclass="(?:goldCoin|silverCoin)"/', '', $coin, 1) ?? $coin;
};
$goldCoinSvg = $renderCurrencyCoin('gold');
$silverCoinSvg = $renderCurrencyCoin('silver');
$goldButtonCoinSvg = preg_replace('/<svg\b/', '<svg class="goldCoin exchangeCoin"', $goldCoinSvg, 1) ?? $goldCoinSvg;
$goldInputCoinSvg = preg_replace('/<svg\b/', '<svg class="goldCoin inputGold"', $goldCoinSvg, 1) ?? $goldCoinSvg;
$silverInputCoinSvg = preg_replace('/<svg\b/', '<svg class="silverCoin inputSilver"', $silverCoinSvg, 1) ?? $silverCoinSvg;
$goldResultCoinSvg = preg_replace('/<svg\b/', '<svg class="goldCoin resultGold"', $goldCoinSvg, 1) ?? $goldCoinSvg;
$silverResultCoinSvg = preg_replace('/<svg\b/', '<svg class="silverCoin resultSilver"', $silverCoinSvg, 1) ?? $silverCoinSvg;
$exchangeDirectionSvg = '<svg aria-hidden="true"><svg viewBox="0 0 17 13" class="outline"><path d="m4.581 12.184.17.157.17-.158 1.05-.98.197-.184-.2-.183L4.512 9.5H9.5V7.625H4.511L5.97 6.289l.2-.183-.198-.184-1.05-.98-.17-.158-.17.157-3.75 3.437-.201.184.201.185 3.75 3.437Zm7.498-4.126.17.158.17-.157 3.75-3.437.201-.184-.201-.185-3.75-3.437-.17-.157-.17.158-1.05.98-.197.184.2.183L12.488 3.5H7.5v1.875h4.989L11.03 6.711l-.2.183.199.184 1.05.98Z"></path></svg><svg viewBox="0 0 17 13" class="icon"><path d="m4.581 12.184.17.157.17-.158 1.05-.98.197-.184-.2-.183L4.512 9.5H9.5V7.625H4.511L5.97 6.289l.2-.183-.198-.184-1.05-.98-.17-.158-.17.157-3.75 3.437-.201.184.201.185 3.75 3.437Zm7.498-4.126.17.158.17-.157 3.75-3.437.201-.184-.201-.185-3.75-3.437-.17-.157-.17.158-1.05.98-.197.184.2.183L12.488 3.5H7.5v1.875h4.989L11.03 6.711l-.2.183.199.184 1.05.98Z"></path></svg></svg>';
$exchangeDividerSvg = '<svg viewBox="0 0 380 10"><path d="M0 0L186 0L190 3L194 0L380 0"></path><path d="M186 0L190 3L194 0z"></path></svg>';
$exchangePreviewArrowSvg = '<svg viewBox="0 0 16.5 7"><path d="M13 0v2H0v3h13v2l3.5-3.5"></path></svg>';
$goldLabel = (string)T('inGame', 'goldShort');
$silverLabel = (string)T('inGame', 'silverShort');

$slots = [
    'all' => T('Auction', 'Show everything'),
    'leftHand' => T('Auction', 'Show utilities'),
    'rightHand' => T('Auction', 'Show weapons'),
    'helmet' => T('Auction', 'Show head gear'),
    'body' => T('Auction', 'Show armor'),
    'shoes' => T('Auction', 'Show boots'),
    'bag' => T('Auction', 'Show consumables'),
    'horse' => T('Auction', 'Show horses'),
];

$disabledAttr = $locked ? ' disabled' : '';
?>
<div class="<?= $e($wrapperClass); ?>" data-auction-keep="filter">
    <?php if ($backPath !== ''): ?>
        <button type="button"
                class="textButtonV2 buttonSecondary backButton withTextAndIcon rectangle withText green"
                onclick="window.location.href='<?= $e($backPath); ?>';">
            <div><svg viewBox="0 0 9 16" aria-hidden="true"><path d="M8.22 1.08 7.1 0 0 8l7.1 8 1.12-1.08L2.08 8l6.14-6.92Z"></path></svg><span><?= $e((string)T('DailyQuest', 'Back')); ?></span></div>
        </button>
    <?php endif; ?>
    <?php if ($showSearch): ?>
        <div class="itemTypeSearch formV2 heroV2 closed">
            <button type="button" class="openSearch"<?= $disabledAttr; ?>>
                <svg viewBox="0 0 21.77 22" aria-hidden="true">
                    <path d="M19.09 2.48a8.82 8.82 0 1 0 .19 12.46 8.81 8.81 0 0 0-.19-12.46Zm-10.66 11a6.51 6.51 0 1 1 9.2-.14 6.51 6.51 0 0 1-9.2.14Zm-2.88 2.29.57.55a1.58 1.58 0 0 1 0 2.22l-2.9 3a1.55 1.55 0 0 1-2.21 0L.48 21a1.55 1.55 0 0 1 0-2.21l2.9-3a1.56 1.56 0 0 1 2.17-.02Z"></path>
                    <path class="horizontal" d="M15.62 9.8h-5.33a.85.85 0 0 1-.85-.8v-.33a.85.85 0 0 1 .85-.85h5.33a.85.85 0 0 1 .85.85V9a.85.85 0 0 1-.85.8Z"></path>
                    <path class="vertical" d="M14 6.15v5.32a.86.86 0 0 1-.85.86h-.29a.86.86 0 0 1-.85-.86V6.15a.85.85 0 0 1 .85-.85h.29a.85.85 0 0 1 .85.85Z"></path>
                </svg>
            </button>
            <label class="input search">
                <input type="text">
                <button type="button" class="cancelSearch">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M0 17.01L7.01 10 .14 3.13 3.13.14 10 7.01 17.01 0 20 2.99 12.99 10l6.87 6.87-2.99 2.99L10 12.99 2.99 20 0 17.01z"></path></svg>
                </button>
                <div class="results" style="max-height: calc(100vh - 344px);">
                    <?php foreach ($searchItems as $item): ?>
                        <?php
                        $btype = (int)($item['btype'] ?? 0);
                        $type = (int)($item['type'] ?? 0);
                        $slotClass = (string)($item['slot'] ?? 'bag');
                        $name = (string)($item['name'] ?? '');
                        $count = (int)($item['count'] ?? 0);
                        $itemClass = $btype >= 7
                            ? 'heroItem consumable neutral inventory heroItemV1'
                            : 'heroItem tier1 neutral ' . $slotClass . ' heroItemV1';
                        ?>
                        <div class="autoCompleteResult typeAuctionItem"
                             data-search-label="<?= $e($name); ?>"
                             data-btype="<?= $btype; ?>"
                             data-type="<?= $type; ?>">
                            <div class="<?= $e($itemClass); ?>"><div class="item item<?= $type; ?> neutral"></div></div>
                            <span><?= $e($name); ?> (<?= $count; ?>)</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </label>
        </div>
    <?php endif; ?>
    <?php if ($showSlots): ?>
    <div class="heroItemSlotFilter filterSlotsRow">
        <?php foreach ($slots as $key => $label): ?>
            <?php $active = $slot === $key; ?>
            <div class="<?= $e('filterSlot ' . $key . ($active ? ' active' : '')); ?>"
                 data-key="<?= $e($key); ?>"
                 title="<?= $e((string)$label); ?>"
                 aria-label="<?= $e((string)$label); ?>"
                 aria-pressed="<?= $active ? 'true' : 'false'; ?>"
                 role="button"
                 tabindex="0">
                <i aria-hidden="true"></i>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="exchangeWidget" title="<?= $locked ? $e((string)T('Auction', '10AdventuresError')) : ''; ?>">
        <button class="exchangeGold"<?= $disabledAttr; ?> title="<?= $e((string)T('Auction', 'Exchange gold for silver')); ?>">
            <?= $goldCoinSvg; ?>
            <span><?= number_format_x($gold); ?></span>
        </button>
        <button class="textButtonV2 buttonFramed openExchangeOffice round withIcon green" type="button"<?= $disabledAttr; ?> title="<?= $e((string)T('Auction', 'Open exchange office')); ?>">
            <?= $exchangeDirectionSvg; ?>
        </button>
        <button class="exchangeSilver"<?= $disabledAttr; ?> title="<?= $e((string)T('Auction', 'Exchange silver for Gold')); ?>">
            <?= $silverCoinSvg; ?>
            <span><?= number_format_x($silver); ?></span>
        </button>
    </div>
</div>
<?php if (!$locked): ?>
<script type="text/javascript" data-auction-once="filter">
jQuery(function () {
    var search = jQuery('#auctions .itemTypeSearch');
    var searchInput = search.find('input[type="text"]');
    search.on('click', '.openSearch', function () {
        search.removeClass('closed').addClass('opened');
        searchInput.trigger('focus');
    });
    search.on('click', '.autoCompleteResult', function () {
        searchInput.val(jQuery(this).attr('data-search-label')).trigger('focus');
    });
    search.on('click', '.cancelSearch', function () {
        searchInput.val('');
        search.removeClass('opened').addClass('closed');
    });

    var openButtons = jQuery('#auctions .openExchangeOffice, #auctions .exchangeGold, #auctions .exchangeSilver, #auctions .bidInput button[data-exchange-gold]');
    if (!openButtons.length || typeof Travian === 'undefined' || !Travian.Dialog) {
        return;
    }
    openButtons.on('click', function () {
        if (this.disabled) {
            return;
        }
        var presetGold = parseInt(jQuery(this).attr('data-exchange-gold'), 10) || 0;
        var dialog = new Travian.Dialog.Dialog({
            version: 2,
            preventFormSubmit: true,
            buttonOk: false,
            cssClass: 'basic exchangeOfficeDialog'
        });
        dialog.setContent(jQuery('#auctionExchangeOfficeTemplate').html() || jQuery('#auctionExchangeOfficeTemplate').text());
        dialog.show();
        var root = jQuery('#dialogContent #exchangeOffice');
        if (!root.length) {
            return;
        }
        var direction = 'GoldToSilver';
        var controls = root.find('.changeDirection');
        var goldDirection = controls.find('.exchangeGold');
        var silverDirection = controls.find('.exchangeSilver');
        var directionSwitch = controls.find('.openExchangeOffice');
        var amountInput = root.find('input.exchangeValue');
        var convertLabel = <?= json_encode((string)T('Auction', 'Convert'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        var exchangeLabel = <?= json_encode((string)T('Auction', 'Exchange'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        function number(value) {
            return Math.max(0, parseInt(value, 10) || 0);
        }

        function formatNumber(value) {
            return new Intl.NumberFormat(document.documentElement.lang || 'en-US').format(value);
        }

        function sync() {
            var gold = number(root.attr('data-gold'));
            var silver = number(root.attr('data-silver'));
            var defaultAmount = direction === 'GoldToSilver' ? 1 : 200;
            // As in the reference the amount is not cut to the balance: the preview shows what would be left and the
            // exchange itself answers "Not enough silver" or "Not enough gold".
            var amount = number(amountInput.val()) || defaultAmount;
            var exchanged = direction === 'GoldToSilver' ? amount * 100 : Math.floor(amount / 200);
            var newGold = direction === 'GoldToSilver' ? gold - amount : gold + exchanged;
            var newSilver = direction === 'GoldToSilver' ? silver + exchanged : silver - (exchanged * 200);

            root.attr('data-direction', direction);
            controls.attr('data-direction', direction);
            if (direction === 'GoldToSilver') {
                goldDirection.insertBefore(directionSwitch);
                silverDirection.insertAfter(directionSwitch);
            } else {
                silverDirection.insertBefore(directionSwitch);
                goldDirection.insertAfter(directionSwitch);
            }
            amountInput.val(amount);
            root.find('.exchangeRate .from').text(direction === 'GoldToSilver' ? 1 : 200);
            root.find('.exchangeRate .to').text(direction === 'GoldToSilver' ? 100 : 1);
            root.find('.inputGold, .resultSilver').toggle(direction === 'GoldToSilver');
            root.find('.inputSilver, .resultGold').toggle(direction === 'SilverToGold');
            root.find('.resultValue').text(formatNumber(exchanged));
            root.find('.previewGoldCurrent').text(formatNumber(gold));
            root.find('.previewGoldNew').text(formatNumber(newGold));
            root.find('.previewSilverCurrent').text(formatNumber(silver));
            root.find('.previewSilverNew').text(formatNumber(newSilver));
            root.find('button.exchange')
                .toggleClass('gold', direction === 'GoldToSilver')
                .toggleClass('green', direction === 'SilverToGold')
                .prop('disabled', exchanged === 0);
            root.find('.exchangeLabel').text(direction === 'GoldToSilver' ? convertLabel : exchangeLabel);
            root.find('.exchangeCoin, .goldValue').toggle(direction === 'GoldToSilver');
            root.find('.goldValue').text(formatNumber(amount));
        }
        function setDirection(nextDirection) {
            direction = nextDirection;
            amountInput.val(direction === 'GoldToSilver' ? 1 : 200);
            sync();
        }
        goldDirection.on('click', function () {
            setDirection('GoldToSilver');
        });
        silverDirection.on('click', function () {
            setDirection('SilverToGold');
        });
        directionSwitch.on('click', function () {
            setDirection(direction === 'GoldToSilver' ? 'SilverToGold' : 'GoldToSilver');
        });
        amountInput.on('input change', sync);
        var tight = function (svg) { return svg.replace(/>\s+</g, '><').trim(); };
        var coinSvg = {gold: <?= json_encode($goldCoinSvg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, silver: <?= json_encode($silverCoinSvg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>};
        coinSvg.gold = tight(coinSvg.gold);
        coinSvg.silver = tight(coinSvg.silver);
        var confirmText = <?= json_encode((string)T('Auction', 'goldToSilverConfirmation'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        var redeemLabel = <?= json_encode((string)T('Auction', 'goldToSilverRedeem'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        var cancelLabel = <?= json_encode((string)T('HeroInventory', 'buttonCancel'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        var successText = <?= json_encode((string)T('Auction', 'silverExchangeSuccess'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        function exchange(payload) {
            Travian.api('silver/exchange', {
                data: payload,
                success: function (data) {
                    if (data && data.message && data.message.type === 'error') {
                        Travian.Game.Toast.show({type: 'error', text: jQuery('<div>').html(data.message.message).text()});
                        return;
                    }
                    Travian.Game.Toast.show({type: 'success', text: successText});
                    if (data && typeof data.newGold !== 'undefined') {
                        root.attr('data-gold', data.newGold);
                        jQuery('#auctions .exchangeWidget > .exchangeGold span').text(formatNumber(data.newGold));
                        jQuery('.ajaxReplaceableGoldAmount').html(Travian.i18n.Number(data.newGold, {grouping: true}));
                    }
                    if (data && typeof data.newSilver !== 'undefined') {
                        root.attr('data-silver', data.newSilver);
                        jQuery('#auctions .exchangeWidget > .exchangeSilver span').text(formatNumber(data.newSilver));
                        jQuery('.ajaxReplaceableSilverAmount').html(Travian.i18n.Number(data.newSilver, {grouping: true}));
                        Travian.Tip.resetAjaxTipsByType('free-silver');
                    }
                    sync();
                },
                error: function (response) {
                    var error = response && response.message;
                    var text = error && typeof error === 'object' ? jQuery('<div>').html(error.message || '').text() : (typeof error === 'string' ? error : '');
                    Travian.Game.Toast.show({type: 'error', text: text});
                }
            });
        }
        root.find('button.exchange').on('click', function (event) {
            event.preventDefault();
            var button = this;
            var payload = direction === 'GoldToSilver'
                ? {exTyp: 'GoldToSilver', g: amountInput.val(), s: 0}
                : {exTyp: 'SilverToGold', s: amountInput.val(), g: 0};
            if (direction !== 'GoldToSilver') {
                exchange(payload);
                return;
            }
            // Gold is spent, so the reference asks for a confirmation first.
            var gold = number(amountInput.val());
            var confirmation = new Travian.Dialog.Dialog({
                version: 2,
                cssClass: 'plain exchangeGoldToSilverConfirmation',
                buttonOk: false,
                buttonCancel: false,
                preventFormSubmit: true,
                enableBackground: true,
                relativeTo: jQuery(button)
            });
            var parts = confirmText.split('[GOLD]').join('\u0001').split('[SILVER]').join('\u0002');
            var html = jQuery('<div>').text(parts).html()
                .replace('\u0001', coinSvg.gold + '<span class="value">' + gold + '</span>')
                .replace('\u0002', coinSvg.silver + '<span class="value">' + gold * 100 + '</span>');
            confirmation.setContent('<span>' + html + '</span>'
                + '<button class="textButtonV2 buttonFramed rectangle withText grey" type="button"><div>' + cancelLabel + '</div></button>'
                + '<button class="textButtonV2 buttonFramed rectangle withText gold" type="button"><div>' + redeemLabel + coinSvg.gold + '&nbsp;<span class="goldValue">' + gold + '</span></div></button>');
            confirmation.show();
            var popup = jQuery('.exchangeGoldToSilverConfirmation');
            popup.find('button.grey').on('click', function () {
                confirmation.close();
            });
            popup.find('button.gold').on('click', function () {
                confirmation.close();
                exchange(payload);
            });
        });
        if (presetGold > 0) {
            amountInput.val(presetGold);
        }
        sync();
    });
});
</script>
<script type="text/template" id="auctionExchangeOfficeTemplate" data-auction-template>
    <div id="exchangeOffice" data-direction="GoldToSilver" data-gold="<?= $gold; ?>" data-silver="<?= $silver; ?>">
        <h3><?= $e((string)T('Auction', 'silverExchange')); ?></h3>
        <div class="changeDirection" data-direction="GoldToSilver">
            <button type="button" class="exchangeGold"><div class="goldCoin"><?= $goldCoinSvg; ?></div><span><?= $e($goldLabel); ?></span></button>
            <button type="button" class="textButtonV2 buttonFramed openExchangeOffice round withIcon green">
                <?= $exchangeDirectionSvg; ?>
            </button>
            <button type="button" class="exchangeSilver"><div class="silverCoin"><?= $silverCoinSvg; ?></div><span><?= $e($silverLabel); ?></span></button>
        </div>
        <div class="exchangeRate"><span class="from">1</span><span>:</span><span class="to">100</span></div>
        <div class="formV2 valueInput">
            <?= $exchangeDividerSvg; ?>
            <label class="input fixed"><input inputmode="numeric" maxlength="6" class="exchangeValue" type="text" value="1"><?= $goldInputCoinSvg; ?><?= $silverInputCoinSvg; ?></label>
            <span class="equals">=</span>
            <div class="result"><?= $silverResultCoinSvg; ?><?= $goldResultCoinSvg; ?><span class="resultValue">100</span></div>
            <?= $exchangeDividerSvg; ?>
        </div>
        <table class="preview transparent">
            <thead><tr><td colspan="2" class="current"><?= $e((string)T('Auction', 'Current')); ?></td><td></td><td colspan="2" class="new"><?= $e((string)T('Auction', 'New')); ?></td></tr></thead>
            <tbody>
                <tr><td><?= $goldCoinSvg; ?></td><td class="current previewGoldCurrent"><?= number_format_x($gold); ?></td><td class="arrow"><?= $exchangePreviewArrowSvg; ?></td><td><?= $goldCoinSvg; ?></td><td class="new previewGoldNew"><?= number_format_x(max(0, $gold - 1)); ?></td></tr>
                <tr><td><?= $silverCoinSvg; ?></td><td class="current previewSilverCurrent"><?= number_format_x($silver); ?></td><td class="arrow"><?= $exchangePreviewArrowSvg; ?></td><td><?= $silverCoinSvg; ?></td><td class="new previewSilverNew"><?= number_format_x($silver + 100); ?></td></tr>
            </tbody>
        </table>
        <button type="submit" class="textButtonV2 buttonFramed exchange rectangle withText gold"><div><span class="exchangeLabel"><?= $e((string)T('Auction', 'Convert')); ?></span><?= $goldButtonCoinSvg; ?>&nbsp;<span class="goldValue">1</span></div></button>
    </div>
</script>
<?php endif; ?>
