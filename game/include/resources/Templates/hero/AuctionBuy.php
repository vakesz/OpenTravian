<div class="groupAndControls">
    <div class="formV2 buyViewSwitch">
        <label class="switch">
            <input type="checkbox" value="hide" name="buyView"<?= $vars['grouped'] ? ' checked' : ''; ?> data-auction-grouped>
            <div class="switch"></div><div class="label">grouped</div>
        </label>
    </div>
    <div class="listControl">
        <div class="controlSection listLayout">
            <button type="button"
                    class="textButtonV2 buttonSecondary listLayout_detailed<?= $vars['layout'] === 'detailed' ? ' active' : ''; ?> rectangle withIcon toggle brown"
                    title="<?= T('Auction', 'detailed list'); ?>"
                    data-auction-layout="detailed" data-auction-list="buy">
                <svg viewBox="0 0 14 8" aria-hidden="true"><path d="M1 8a1 1 0 0 1-1-1V5.8a1 1 0 0 1 1-1h1.2a1 1 0 0 1 1 1V7a1 1 0 0 1-1 1H1Zm4.8 0a1 1 0 0 1-1-1V5.8a1 1 0 0 1 1-1H13a1 1 0 0 1 1 1V7a1 1 0 0 1-1 1H5.8ZM1 3.2a1 1 0 0 1-1-1V1a1 1 0 0 1 1-1h1.2a1 1 0 0 1 1 1v1.2a1 1 0 0 1-1 1H1Zm4.8 0a1 1 0 0 1-1-1V1a1 1 0 0 1 1-1H13a1 1 0 0 1 1 1v1.2a1 1 0 0 1-1 1H5.8Z"></path></svg>
            </button>
            <button type="button"
                    class="textButtonV2 buttonSecondary listLayout_compact<?= $vars['layout'] === 'compact' ? ' active' : ''; ?> rectangle withIcon toggle brown"
                    title="<?= T('Auction', 'compact list'); ?>"
                    data-auction-layout="compact" data-auction-list="buy">
                <svg viewBox="0 0 14 10" aria-hidden="true"><path d="M3.556 2.333V.556h10.445v1.777H3.556Zm0 3.556V4.111h10.445V5.89H3.556Zm0 3.556V7.667h10.445v1.778H3.556ZM.89 2.333a.86.86 0 0 1-.634-.255.86.86 0 0 1-.255-.633A.86.86 0 0 1 .256.81.86.86 0 0 1 .89.556a.86.86 0 0 1 .633.255.86.86 0 0 1 .256.634.86.86 0 0 1-.256.633.86.86 0 0 1-.633.255Zm0 3.556a.86.86 0 0 1-.634-.256A.86.86 0 0 1 .001 5a.86.86 0 0 1 .255-.633.86.86 0 0 1 .634-.256.86.86 0 0 1 .633.256.86.86 0 0 1 .256.633.86.86 0 0 1-.256.633.86.86 0 0 1-.633.256Zm0 3.556a.86.86 0 0 1-.634-.256.86.86 0 0 1-.255-.633.86.86 0 0 1 .255-.634.86.86 0 0 1 .634-.255.86.86 0 0 1 .633.255.86.86 0 0 1 .256.634.86.86 0 0 1-.256.633.86.86 0 0 1-.633.256Z"></path></svg>
            </button>
        </div>
        <div class="controlSection reload">
            <button class="textButtonV2 buttonSecondary reload rectangle withIcon toggle brown" type="button" title="<?= T('Auction', 'reload'); ?>" data-auction-reload>
                <svg viewBox="0 0 14 14" aria-hidden="true"><path d="M7 14c-1.954 0-3.61-.678-4.966-2.034C.678 10.609 0 8.954 0 7c0-1.954.678-3.61 2.034-4.966C3.391.678 5.046 0 7 0c1.006 0 1.969.208 2.887.623a6.67 6.67 0 0 1 2.363 1.783V0H14v6.125H7.875v-1.75h3.675A5.107 5.107 0 0 0 9.636 2.45 5.187 5.187 0 0 0 7 1.75c-1.458 0-2.698.51-3.719 1.531C2.261 4.302 1.75 5.541 1.75 7c0 1.458.51 2.698 1.531 3.719C4.302 11.739 5.541 12.25 7 12.25a5.13 5.13 0 0 0 3.04-.963 5.082 5.082 0 0 0 1.904-2.537h1.837a6.909 6.909 0 0 1-2.494 3.784C10.034 13.511 8.604 14 7 14Z"></path></svg>
            </button>
        </div>
    </div>
</div>
<?= $vars['filterBar']; ?>
<div class="itemTypeListWrapper">
    <table class="itemType <?= $vars['layout']; ?> entriesClickable"<?= \Game\Hero\AuctionSort::tableAttributes('', $vars['sortBy'], $vars['sortOrder']); ?>>
        <?php if ($vars['grouped']): ?>
        <thead>
        <tr>
            <?= \Game\Hero\AuctionSort::header('itemInformation', 'Item', 'NAME', 'ASC', $vars['sortBy'], $vars['sortOrder'], ' colspan="2"'); ?>
            <?= \Game\Hero\AuctionSort::header('auctionsCount', 'Current auctions', 'AUCTIONS_COUNT', 'ASC', $vars['sortBy'], $vars['sortOrder']); ?>
            <?= \Game\Hero\AuctionSort::header('nextFinishAt', 'Next finish', 'NEXT_FINISH_AT', 'ASC', $vars['sortBy'], $vars['sortOrder']); ?>
            <?= \Game\Hero\AuctionSort::header('nextPrice', 'Next price', 'NEXT_PRICE', 'ASC', $vars['sortBy'], $vars['sortOrder']); ?>
        </tr>
        </thead>
        <?php else: ?>
        <?= \Game\Hero\AuctionSort::bidTableHead($vars['layout'], [
            'name' => 'ITEM_NAME', 'price' => 'CURRENT_BID', 'bidder' => 'CURRENT_BIDDER_NAME',
            'alliance' => 'CURRENT_BIDDER_ALLIANCE_NAME', 'bids' => 'BIDS_COUNT', 'finish' => 'REMAINING_TIME',
        ], $vars['sortBy'], $vars['sortOrder']); ?>
        <?php endif; ?>
        <tbody>
        <?= $vars['results']; ?>
        </tbody>
    </table>
</div>
<?= $vars['pagination']; ?>
<script type="text/javascript" src="/auction-lists.js?v=<?= filemtime(PUBLIC_INTERNAL_PATH . 'auction-lists.js'); ?>" data-auction-once="lists"></script>
