<?php

declare(strict_types=1);

/** @var array<string, mixed> $vars */

$e = static fn ($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$t = static fn (string $key, string $fallback): string => T('SeasonPass', $key, $fallback);
$tab = (string)$vars['tab'];
$theme = preg_replace('/[^a-z]/', '', (string)$vars['theme']) ?: 'green';
$board = $vars['board'];
/** @var \Model\SeasonPassModel $model */
$model = $vars['model'];
$checker = $e($vars['checker']);
$isAdmin = (bool)$vars['isAdmin'];
$message = (string)$vars['message'];
$governorKey = (string)($vars['governorKey'] ?? '');
$rootClass = match ($tab) {
    'shards' => 'shards',
    'governors' => 'governors',
    default => 'seasonPass',
};

$messageText = match ($message) {
    'claimed' => $t('claimedFeedback', 'Reward collected.'),
    'premium' => $t('premiumFeedback', 'Premium Season Pass unlocked for this world.'),
    'rerolled' => $t('rerolledFeedback', 'Random mission rerolled.'),
    'rerollFailed' => $t('rerollFailedFeedback', 'Reroll unavailable. Needs an unused Video Skip shard and an unfinished random mission.'),
    'unavailable' => $t('unavailableFeedback', 'That Season Pass action is not available.'),
    default => '',
};

$tabs = [
    ['id' => 'seasonPass', 'label' => $t('tabSeasonPass', 'Season Pass'), 'href' => '/seasonalRewards?t=seasonPass'],
    ['id' => 'shards', 'label' => $t('tabShards', 'Shards'), 'href' => '/seasonalRewards?t=shards'],
    ['id' => 'governors', 'label' => $t('tabGovernors', 'Governors'), 'href' => '/seasonalRewards?t=governors'],
];
?>
<?= \resources\View\PHPBatchView::render('layout/tabStrip', [
    'tabs' => array_map(static fn (array $tabItem): array => [
        'label' => $tabItem['label'],
        'href' => $tabItem['href'],
        'active' => $tab === $tabItem['id'],
    ], $tabs),
    'ids' => false,
]); ?>

<div id="seasonalRewards" class="<?=$e($rootClass); ?> <?=$e($theme); ?>">
    <?php if ($tab === 'shards'): ?>
        <?php require __DIR__ . '/shards.php'; ?>
    <?php elseif ($tab === 'governors'): ?>
        <?php require __DIR__ . '/governors.php'; ?>
    <?php else: ?>
        <?php require __DIR__ . '/seasonPass.php'; ?>
    <?php endif; ?>
</div>
