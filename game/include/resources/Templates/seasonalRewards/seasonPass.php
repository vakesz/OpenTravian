<?php

declare(strict_types=1);

/** @var callable $e */
/** @var callable $t */
/** @var array $board */
/** @var \Model\SeasonPassModel $model */
/** @var string $checker */
/** @var bool $isAdmin */
/** @var string $theme */
/** @var string $message */
/** @var string $messageText */

$season = $board['season'];
$progress = $board['progress'];
$missions = $board['missions'];
$rewards = $board['rewards'];
$points = (int)$progress['points'];
$hasPremium = (int)$progress['has_premium'] === 1;
$missionsOpen = (bool)$board['missionsOpen'];
$claimsOpen = (bool)$board['claimsOpen'];
$pointColor = in_array($theme, ['blue', 'green', 'orange', 'red'], true) ? $theme : 'green';

$claimableCount = 0;
foreach ($rewards as $reward) {
    if (!empty($reward['claimable'])) {
        $claimableCount++;
    }
}

$rewardShapePaths = require __DIR__ . '/rewardShapePaths.php';
$renderRewardShape = static function (string $gradientId) use ($rewardShapePaths): string {
    // Exact single-plaque paths from live Gpack 692 js/bundle/main.js (viewBox 168×213).
    $innerId = 'rewardInnerShape-' . $gradientId;
    $clipId = 'rewardShapeClipPath-' . $gradientId;
    $inner = htmlspecialchars((string)$rewardShapePaths['inner'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $frame = htmlspecialchars((string)$rewardShapePaths['frame'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $pattern = htmlspecialchars((string)$rewardShapePaths['shapePattern'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $viewBox = htmlspecialchars((string)$rewardShapePaths['viewBox'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    return '<svg class="rewardShape" viewBox="' . $viewBox . '" aria-hidden="true">'
        . '<defs>'
        . '<linearGradient id="' . $gradientId . '" x1="84" y1="0" x2="84" y2="213" gradientUnits="userSpaceOnUse">'
        . '<stop class="colorStop1" offset="0"/>'
        . '<stop class="colorStop2" offset="1"/>'
        . '</linearGradient>'
        . '<path id="' . $innerId . '" d="' . $inner . '"/>'
        . '<clipPath id="' . $clipId . '"><use href="#' . $innerId . '"/></clipPath>'
        . '</defs>'
        . '<path class="frame" d="' . $frame . '"/>'
        . '<use href="#' . $innerId . '" class="inner" style="fill:var(--linearGradient)"/>'
        . '<path clip-path="url(#' . $clipId . ')" class="shapePattern" d="' . $pattern . '"/>'
        . '</svg>';
};

$renderProgressState = static function (bool $unlocked, bool $claimed): string {
    if ($claimed || $unlocked) {
        $class = 'progressState completed';
        $icon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l5 5L19 7" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    } else {
        $class = 'progressState locked';
        $icon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10V8a5 5 0 0 1 10 0v2" fill="none" stroke="currentColor" stroke-width="2"/><rect x="5" y="10" width="14" height="11" rx="2" fill="currentColor"/></svg>';
    }

    return '<div class="' . $class . '">' . $icon . '</div>';
};
?>
<div class="seasonPassInformation tabInformation">
    <p class="owner"><?=$e($t('localWorldScope', 'Local world Season Pass (no lobby SSO)')); ?></p>
    <h1>
        <span class="seasonPassBookmark <?=$e($theme); ?>">
            <svg class="bannerShape" viewBox="0 0 28 32" aria-hidden="true">
                <path class="highlight" d="M4 2h20v20l-10 6-10-6z"></path>
                <path class="shape" d="M2 0h24v24l-12 8-12-8z"></path>
            </svg>
            <svg class="seasonPassIcon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 2l3 7h7l-5.5 4.5L18 22l-6-4-6 4 1.5-8.5L2 9h7z"></path>
            </svg>
        </span>
        <span><?=$e((string)$season['title']); ?></span>
    </h1>
    <p class="description clamped"><?=$e($t('description', 'Complete missions to earn Season Pass Points, then claim free or premium rewards including shards and Governor Stars.')); ?></p>
    <p class="available">
        <?=$e($t('pointsLabel', 'Season Pass Points')); ?>:
        <strong><?=$e((string)$points); ?></strong>
        <i class="seasonPassPoint <?=$e($pointColor); ?>" aria-hidden="true"></i>
        <?php if ($hasPremium): ?>
            · <?=$e($t('premiumActive', 'Premium track unlocked')); ?>
        <?php else: ?>
            · <?=$e($t('premiumLocked', 'Premium track locked')); ?>
        <?php endif; ?>
        · <?=$e($t('governorStars', 'Governor Stars')); ?>: <?=$e((string)(int)$progress['governor_stars']); ?>
    </p>
    <?php if ($messageText !== ''): ?>
        <div class="alertBox<?=$message === 'unavailable' ? '' : ' success'; ?>"><?=$e($messageText); ?></div>
    <?php endif; ?>
    <?php if ($isAdmin && !$hasPremium): ?>
        <form method="post" action="/seasonalRewards" class="premiumGrant">
            <input type="hidden" name="c" value="<?=$checker; ?>">
            <input type="hidden" name="action" value="grantPremium">
            <input type="hidden" name="t" value="seasonPass">
            <button type="submit" class="textButtonV2 buttonFramed rectangle withText green">
                <div><?=$e($t('grantPremium', 'Unlock premium (Multihunter)')); ?></div>
            </button>
        </form>
    <?php endif; ?>
</div>

<div class="missions">
    <div class="title">
        <h3><?=$e($t('missions', 'Missions')); ?></h3>
        <span><?=$e($missionsOpen ? $t('missionsReset', 'Resets at midnight') : $t('missionsClosed', 'Mission period ended')); ?></span>
    </div>
    <div class="missionsList">
        <?php foreach ($missions as $mission): ?>
            <?php
            $completed = (int)$mission['points_granted'] === 1 || (int)$mission['progress'] >= (int)$mission['target'];
            $isRandom = $mission['mission_type'] === 'random';
            $typeClass = $isRandom ? 'typeRandom' : 'typeDaily';
            $label = $model->missionLabel((string)$mission['mission_key']);
            $missionClasses = 'mission ' . $typeClass . ($completed ? ' completed' : '');
            $canReroll = $isRandom
                && $missionsOpen
                && (int)$mission['progress'] === 0
                && (int)$mission['points_granted'] === 0
                && (int)($mission['rerolled'] ?? 0) === 0;
            ?>
            <div class="<?=$e($missionClasses); ?>">
                <div class="iconWrapper">
                    <?php if ($canReroll): ?>
                        <form method="post" action="/seasonalRewards" class="missionActionForm">
                            <input type="hidden" name="c" value="<?=$checker; ?>">
                            <input type="hidden" name="action" value="rerollMission">
                            <input type="hidden" name="missionId" value="<?=$e((string)(int)$mission['id']); ?>">
                            <input type="hidden" name="t" value="seasonPass">
                            <button type="submit"
                                    class="textButtonV2 buttonFramed round iconButton layoutButton missionAction"
                                    title="<?=$e($t('rerollMission', 'Reroll (uses Video Skip shard)')); ?>"
                                    aria-label="<?=$e($t('rerollMission', 'Reroll (uses Video Skip shard)')); ?>">
                                <div>
                                    <i class="typeRandom" aria-hidden="true"></i>
                                </div>
                            </button>
                        </form>
                    <?php else: ?>
                        <i class="<?=$e($typeClass); ?>" aria-hidden="true"></i>
                    <?php endif; ?>
                </div>
                <div class="missionBody">
                    <div class="info">
                        <div class="titleWrapper">
                            <h4 class="title"><?=$e($label); ?></h4>
                        </div>
                        <div class="tasks"><?=$e((int)$mission['progress'] . '/' . (int)$mission['target']); ?></div>
                    </div>
                    <div class="seasonPassPoints">
                        <span class="points"><?=$e((string)(int)$mission['points']); ?></span>
                        <i class="seasonPassPoint <?=$e($pointColor); ?>" aria-hidden="true"></i>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="rewardsSummary">
    <div class="collectableSummary">
        <span><?=$e($t('rewards', 'Rewards')); ?></span>
        <span class="collectableCount"><?=$e((string)$claimableCount); ?> <?=$e($t('collectable', 'collectable')); ?></span>
    </div>
</div>

<div class="rewardsWrapper">
    <div class="rewards">
        <?php foreach ($rewards as $index => $reward): ?>
            <?php
            $trackClass = $reward['track'] === 'premium' ? 'premium' : 'free';
            $icon = $model->shardIconClass((string)$reward['reward_key'], 'large');
            $claimable = !empty($reward['claimable']) && $claimsOpen;
            $claimed = !empty($reward['claimed']);
            $unlocked = !empty($reward['unlocked']);
            $locked = !$unlocked;
            $required = max(1, (int)$reward['points_required']);
            $progressPercent = (int)min(100, (int)floor(($points / $required) * 100));
            $gradientId = 'seasonPassRewardGradient_' . (int)$reward['id'];
            ?>
            <div class="reward <?=$e($trackClass); ?>" style="--linearGradient:url(#<?=$e($gradientId); ?>)">
                <?=$renderRewardShape($gradientId); ?>
                <?php if ($reward['track'] === 'premium' && !$hasPremium): ?>
                    <i class="premiumLocked" aria-hidden="true"></i>
                <?php endif; ?>
                <div class="details">
                    <i class="type <?=$e($icon); ?>" aria-hidden="true"></i>
                    <div class="amount"><span>×<?=$e((string)(int)$reward['amount']); ?></span></div>
                    <div class="shardName"><?=$e((string)($reward['label'] !== '' ? $reward['label'] : $reward['reward_key'])); ?></div>
                    <div class="shardDescription">
                        <?=$e($reward['track'] === 'premium' ? $t('premiumTrack', 'Premium') : $t('freeTrack', 'Free')); ?>
                    </div>
                    <div class="seasonPassPoints">
                        <span class="points"><?=$e((string)(int)$reward['points_required']); ?></span>
                        <i class="seasonPassPoint <?=$e($pointColor); ?>" aria-hidden="true"></i>
                    </div>
                    <?php if ($claimed): ?>
                        <div class="collected"><?=$e($t('collected', 'Collected')); ?></div>
                    <?php elseif ($locked): ?>
                        <div class="locked"><?=$e($t('locked', 'Locked')); ?></div>
                    <?php elseif ($claimable): ?>
                        <div class="rewardAction">
                            <form method="post" action="/seasonalRewards">
                                <input type="hidden" name="c" value="<?=$checker; ?>">
                                <input type="hidden" name="action" value="claimReward">
                                <input type="hidden" name="rewardId" value="<?=$e((string)(int)$reward['id']); ?>">
                                <input type="hidden" name="t" value="seasonPass">
                                <button type="submit" class="textButtonV2 buttonFramed rectangle withText green">
                                    <div><?=$e($t('claim', 'Claim')); ?></div>
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="locked"><?=$e($t('claimsClosed', 'Claims closed')); ?></div>
                    <?php endif; ?>
                    <div class="progressBar">
                        <div class="bar">
                            <div class="filling" style="width:<?=$e((string)$progressPercent); ?>%"></div>
                            <?php if ($index === 0): ?>
                                <div class="decoration start"></div>
                            <?php endif; ?>
                            <div class="decoration"></div>
                        </div>
                    </div>
                </div>
                <?=$renderProgressState($unlocked, $claimed); ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
