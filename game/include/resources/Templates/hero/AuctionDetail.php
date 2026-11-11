<?php

declare(strict_types=1);

$layout = (($vars['layout'] ?? 'detailed') === 'compact') ? 'compact' : 'detailed';
?>
<?= $vars['filterBar']; ?>
<div class="itemTypeDetailContainer">
    <?= \resources\View\PHPBatchView::render('hero/AuctionItemInformation', $vars); ?>
    <div class="listHeadline">
        <div class="tooltipHelpText">Place a <span class="tooltipHelpAnchor">bid</span> on items, with a set minimum price.</div>
    </div>
    <div class="listControl">
        <?php if ($vars['batchSizes'] !== []): ?>
        <div class="controlSection batchSize">
            <button type="button" class="textButtonV2 buttonSecondary batchSize_all<?= $vars['batchSize'] === null ? ' active' : ''; ?> rectangle withIcon toggle brown" title="<?= T('Auction', 'all batch sizes'); ?>" data-auction-batch="">
                <svg viewBox="0 0 14 7" aria-hidden="true"><path d="M3.208 6.917c-.894 0-1.652-.311-2.275-.934C.311 5.361 0 4.603 0 3.708c0-.894.311-1.652.933-2.275C1.556.811 2.313.5 3.208.5a2.875 2.875 0 0 1 1.925.73l.992.903-.875.788-.904-.817a1.814 1.814 0 0 0-.525-.32 1.634 1.634 0 0 0-.613-.117c-.564 0-1.045.199-1.443.598-.399.398-.598.88-.598 1.443 0 .564.199 1.045.598 1.444.398.399.88.598 1.443.598.214 0 .418-.039.613-.117.194-.077.37-.184.525-.32l4.52-4.084A2.875 2.875 0 0 1 10.792.5c.895.001 1.653.312 2.276.934.622.623.933 1.38.933 2.275s-.311 1.653-.933 2.275c-.623.623-1.38.934-2.275.934a2.874 2.874 0 0 1-1.925-.73l-.992-.904.875-.787.904.816c.156.137.33.244.525.321.195.078.399.117.613.117.564 0 1.045-.2 1.443-.598.399-.399.598-.88.598-1.444s-.199-1.045-.598-1.443a1.967 1.967 0 0 0-1.443-.598c-.214 0-.418.039-.613.116-.194.078-.37.185-.525.321l-4.52 4.083a2.875 2.875 0 0 1-1.925.73Z"></path></svg>
            </button>
            <?php foreach ($vars['batchSizes'] as $size): $label = "\u{202D}\u{202D}" . $size . "\u{202C}\u{00D7}\u{202C}"; ?>
            <button type="button" class="textButtonV2 buttonSecondary batchSize_<?= (int)$size; ?><?= $vars['batchSize'] === $size ? ' active' : ''; ?> rectangle withIcon toggle brown" title="<?= htmlspecialchars(sprintf((string)T('Auction', 'batch size title'), $label), ENT_QUOTES, 'UTF-8'); ?>" data-auction-batch="<?= (int)$size; ?>"><span><?= $label; ?></span></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="controlSection listLayout">
            <button type="button" class="textButtonV2 buttonSecondary listLayout_detailed<?= $layout === 'detailed' ? ' active' : ''; ?> rectangle withIcon toggle brown" title="<?= T('Auction', 'detailed list'); ?>" data-auction-layout="detailed" data-auction-list="detail">
                <svg viewBox="0 0 14 8" aria-hidden="true"><path d="M1 8a1 1 0 0 1-1-1V5.8a1 1 0 0 1 1-1h1.2a1 1 0 0 1 1 1V7a1 1 0 0 1-1 1H1Zm4.8 0a1 1 0 0 1-1-1V5.8a1 1 0 0 1 1-1H13a1 1 0 0 1 1 1V7a1 1 0 0 1-1 1H5.8ZM1 3.2a1 1 0 0 1-1-1V1a1 1 0 0 1 1-1h1.2a1 1 0 0 1 1 1v1.2a1 1 0 0 1-1 1H1Zm4.8 0a1 1 0 0 1-1-1V1a1 1 0 0 1 1-1H13a1 1 0 0 1 1 1v1.2a1 1 0 0 1-1 1H5.8Z"></path></svg>
            </button>
            <button type="button" class="textButtonV2 buttonSecondary listLayout_compact<?= $layout === 'compact' ? ' active' : ''; ?> rectangle withIcon toggle brown" title="<?= T('Auction', 'compact list'); ?>" data-auction-layout="compact" data-auction-list="detail">
                <svg viewBox="0 0 14 10" aria-hidden="true"><path d="M3.556 2.333V.556h10.445v1.777H3.556Zm0 3.556V4.111h10.445V5.89H3.556Zm0 3.556V7.667h10.445v1.778H3.556ZM.89 2.333a.86.86 0 0 1-.634-.255.86.86 0 0 1-.255-.633A.86.86 0 0 1 .256.81.86.86 0 0 1 .89.556a.86.86 0 0 1 .633.255.86.86 0 0 1 .256.634.86.86 0 0 1-.256.633.86.86 0 0 1-.633.255Zm0 3.556a.86.86 0 0 1-.634-.256A.86.86 0 0 1 .001 5a.86.86 0 0 1 .255-.633.86.86 0 0 1 .634-.256.86.86 0 0 1 .633.256.86.86 0 0 1 .256.633.86.86 0 0 1-.256.633.86.86 0 0 1-.633.256Zm0 3.556a.86.86 0 0 1-.634-.256.86.86 0 0 1-.255-.633.86.86 0 0 1 .255-.634.86.86 0 0 1 .634-.255.86.86 0 0 1 .633.255.86.86 0 0 1 .256.634.86.86 0 0 1-.256.633.86.86 0 0 1-.633.256Z"></path></svg>
            </button>
        </div>
        <div class="controlSection reload">
            <button class="textButtonV2 buttonSecondary reload rectangle withIcon toggle brown" type="button" title="<?= T('Auction', 'reload'); ?>" data-auction-reload data-auction-list="detail">
                <svg viewBox="0 0 14 14" aria-hidden="true"><path d="M7 14c-1.954 0-3.61-.678-4.966-2.034C.678 10.609 0 8.954 0 7c0-1.954.678-3.61 2.034-4.966C3.391.678 5.046 0 7 0c1.006 0 1.969.208 2.887.623a6.67 6.67 0 0 1 2.363 1.783V0H14v6.125H7.875v-1.75h3.675A5.107 5.107 0 0 0 9.636 2.45 5.187 5.187 0 0 0 7 1.75c-1.458 0-2.698.51-3.719 1.531C2.261 4.302 1.75 5.541 1.75 7c0 1.458.51 2.698 1.531 3.719C4.302 11.739 5.541 12.25 7 12.25a5.13 5.13 0 0 0 3.04-.963 5.082 5.082 0 0 0 1.904-2.537h1.837a6.909 6.909 0 0 1-2.494 3.784C10.034 13.511 8.604 14 7 14Z"></path></svg>
            </button>
        </div>
    </div>
    <div class="auctionListWrapper">
        <table class="itemType <?= $layout; ?>"<?= \Game\Hero\AuctionSort::tableAttributes('', $vars['sortBy'], $vars['sortOrder']); ?>>
            <?= \Game\Hero\AuctionSort::bidTableHead($layout, [
                'name' => 'ITEM_NAME', 'price' => 'CURRENT_BID', 'bidder' => 'CURRENT_BIDDER_NAME',
                'alliance' => 'CURRENT_BIDDER_ALLIANCE_NAME', 'bids' => 'BIDS_COUNT', 'finish' => 'REMAINING_TIME',
            ], $vars['sortBy'], $vars['sortOrder']); ?>
            <tbody><?= $vars['rows']; ?></tbody>
        </table>
    </div>
    <?= $vars['pagination']; ?>
</div>
<script type="text/javascript" src="/auction-lists.js?v=<?= filemtime(PUBLIC_INTERNAL_PATH . 'auction-lists.js'); ?>" data-auction-once="lists"></script>
