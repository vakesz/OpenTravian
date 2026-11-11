<?php

declare(strict_types=1);

/*
 * The item block of an auction item page and of the "Sell item" dialog: tile, name and kind, the item's effects
 * (the dialog only), the selling prices and the 24 hour price chart.
 */

$rarity = 'common';
$rarityIndicator = (string)($vars['rarityIndicator'] ?? '');
$priceRows = [
    [T('Auction', 'itemPriceTableLabelLowest', 'Lowest selling price'), (int)$vars['lowest']],
    [T('Auction', 'itemPriceTableLabelAverage', 'Average selling price'), (int)$vars['average']],
    [T('Auction', 'itemPriceTableLabelHighest', 'Highest selling price'), (int)$vars['highest']],
];
$currencyCoinKind = 'silver';
$currencyCoinInteractive = false;
ob_start();
require __DIR__ . '/../layout/currencyCoin.php';
$silverCoin = (string)ob_get_clean();
$stats = $vars['stats'] ?? [];
?>
<div class="itemTypeDetailedInformation">
    <div class="itemImage heroV2">
        <div class="heroItem <?= $vars['tileClasses']; ?> heroItemV1"<?= $vars['tileTooltip']; ?>>
            <?= $vars['arrows']; ?>
            <div class="item item<?= $vars['type']; ?> neutral"></div>
        </div>
    </div>
    <div class="itemDetails">
        <h4 class="itemTitle"><?= $vars['name']; ?></h4>
        <span class="itemMeta"><?= $vars['attributes']; ?></span>
        <?php if ($stats !== []): ?>
            <span class="divider"></span>
            <?php foreach ($stats as $stat): ?>
                <div class="stat"><div><?= htmlspecialchars((string)$stat['text'], ENT_QUOTES, 'UTF-8'); ?></div><?php if (isset($stat['details'])): ?><div class="details"><?= htmlspecialchars((string)$stat['details'], ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="priceTable">
        <table class="borderGap">
            <tbody>
            <?php foreach ($priceRows as [$label, $price]): ?>
                <tr>
                    <td class="label"><div class="valueWrapper labelRarity <?= $rarity; ?>"><?= $rarityIndicator; ?><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></div></td>
                    <td class="value"><?php if ($price > 0): ?><span class="valueWrapper"><?= $silverCoin; ?><span><?= number_format_x($price); ?></span></span><?php else: ?>-<?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= $vars['chart']; ?>
</div>
