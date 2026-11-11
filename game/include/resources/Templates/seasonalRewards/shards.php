<?php

declare(strict_types=1);

/** @var callable $e */
/** @var callable $t */
/** @var array $board */
/** @var \Model\SeasonPassModel $model */
/** @var string $message */
/** @var string $messageText */

$shards = $board['shards'];

$filterSlots = [
    'all' => $t('filterAll', 'All'),
    'buildTimeShortening' => $t('filterBuildTime', 'Build time'),
    'videofeatureSkip' => $t('filterVideoSkip', 'Video skip'),
    'productionBoost' => $t('filterProduction', 'Production'),
    'plus' => $t('filterPlus', 'Plus'),
    'exchangeResources' => $t('filterExchange', 'Exchange'),
];
?>
<div class="shardsInformation tabInformation">
    <h1><?=$e($t('tabShards', 'Shards')); ?></h1>
    <p class="description"><?=$e($t('shardsDescription', 'Collected shards from Season Pass rewards. Local-world inventory (not lobby-shared).')); ?></p>
    <?php if ($messageText !== ''): ?>
        <div class="alertBox<?=$message === 'unavailable' ? '' : ' success'; ?>"><?=$e($messageText); ?></div>
    <?php endif; ?>
</div>

<div class="inventoryFilter">
    <div class="shardsFilter filterSlotsRow" role="group" aria-label="<?=$e($t('tabShards', 'Shards')); ?>">
        <?php foreach ($filterSlots as $filter => $label): ?>
            <button type="button"
                    class="filterSlot <?=$e($filter); ?><?=$filter === 'all' ? ' active' : ''; ?>"
                    data-filter="<?=$e($filter); ?>"
                    title="<?=$e($label); ?>"
                    aria-label="<?=$e($label); ?>"
                    aria-pressed="<?=$filter === 'all' ? 'true' : 'false'; ?>">
                <i aria-hidden="true"></i>
            </button>
        <?php endforeach; ?>
    </div>
</div>

<div class="inventory<?=$shards === [] ? ' empty' : ''; ?>">
    <?php if ($shards === []): ?>
        <p class="description"><?=$e($t('shardsEmpty', 'No shards collected yet. Claim a free Season Pass reward to receive a shard stub.')); ?></p>
    <?php else: ?>
        <?php foreach ($shards as $shard): ?>
            <?php $icon = $model->shardIconClass((string)$shard['shard_key'], 'huge'); ?>
            <div class="shard <?=$e((string)$shard['shard_key']); ?>">
                <i class="<?=$e($icon); ?>" aria-hidden="true"></i>
                <div class="amount"><?=$e((string)(int)$shard['amount']); ?></div>
                <div class="shardValue"><?=$e((string)$shard['shard_key']); ?></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
