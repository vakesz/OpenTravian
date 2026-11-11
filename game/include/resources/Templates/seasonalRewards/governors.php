<?php

declare(strict_types=1);

/** @var callable $e */
/** @var callable $t */
/** @var array $board */
/** @var \Model\SeasonPassModel $model */
/** @var string $message */
/** @var string $messageText */
/** @var string $governorKey */

$governors = is_array($board['governors'] ?? null) ? $board['governors'] : [];
$selected = null;
foreach ($governors as $governor) {
    if ($governor['key'] === $governorKey) {
        $selected = $governor;
        break;
    }
}
?>
<?php if ($selected === null): ?>
    <div class="seasonPassInformation tabInformation">
        <h1><?=$e($t('governorsTitle', 'Governors')); ?></h1>
        <p class="description"><?=$e($t('governorsDescription', 'Governor Stars from the Season Pass unlock Maximum Levels. Assignment and experience live in the Main Building on each village (local-world stars, not lobby SSO).')); ?></p>
        <p class="dualsInfo"><?=$e($t('governorsDualsInfo', 'Stars are governor-specific. Cleopatra, Apollodorus, and Teutobod each track their own Maximum Level.')); ?></p>
        <?php if ($messageText !== ''): ?>
            <div class="alertBox<?=$message === 'unavailable' || $message === 'rerollFailed' ? '' : ' success'; ?>"><?=$e($messageText); ?></div>
        <?php endif; ?>
    </div>

    <div class="governorsBookWrapper">
        <div class="governorsBookList">
            <?php foreach ($governors as $governor): ?>
                <?php
                $cardClass = 'governorBookCard ' . $governor['key'] . ($governor['locked'] ? ' locked' : '');
                $href = '/seasonalRewards?t=governors&g=' . rawurlencode((string)$governor['key']);
                $progressPercent = (int)min(100, (int)floor(($governor['current'] / max(1, $governor['threshold'])) * 100));
                ?>
                <a class="<?=$e($cardClass); ?>" href="<?=$e($href); ?>">
                    <div class="governorPortrait" aria-hidden="true"></div>
                    <h2><?=$e((string)$governor['label']); ?></h2>
                    <div class="governorLevelWrapper">
                        <div class="governorLevel">
                            <i class="governorStarsIcon <?=$e((string)$governor['key']); ?>Stars_large" aria-hidden="true"></i>
                            <div class="maxLevel">
                                <?=$e($t('governorMaxLevel', 'Maximum Level')); ?>:
                                <?=$e($governor['locked'] ? $t('governorLocked', 'Locked') : (string)(int)$governor['maxLevel']); ?>
                            </div>
                            <div class="progressBar">
                                <div class="bar">
                                    <div class="filling" style="width:<?=$e((string)$progressPercent); ?>%"></div>
                                    <div class="decoration"></div>
                                </div>
                            </div>
                            <div class="starsCounter">
                                <span><?=$e((string)(int)$governor['current']); ?>/<?=$e((string)(int)$governor['threshold']); ?></span>
                                <i class="starIcon" aria-hidden="true"></i>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php else: ?>
    <div class="governorDetail">
        <a class="backButton textButtonV2 buttonFramed rectangle withText grey" href="/seasonalRewards?t=governors">
            <div>
                <svg viewBox="0 0 11.8 20" aria-hidden="true" width="9" height="16">
                    <path d="M1.8 20 0 18.2 8.2 10 0 1.8 1.8 0l10 10-10 10z" transform="scale(-1,1) translate(-11.8,0)"></path>
                </svg>
                <span><?=$e($t('governorBack', 'Back')); ?></span>
            </div>
        </a>
        <div class="governorDetailInner">
            <div class="character <?=$e((string)$selected['key']); ?>" aria-hidden="true"></div>
            <div class="infoBoxWrapper governorDisplay">
                <div class="infoBox <?=$e((string)$selected['key']); ?>">
                    <div class="infoBoxHeading">
                        <h2><?=$e((string)$selected['label']); ?></h2>
                        <div class="governorStateInfo">
                            <?php if ($selected['locked']): ?>
                                <i class="lockIcon" aria-hidden="true"></i>
                                <span><?=$e($t('governorLocked', 'Locked')); ?></span>
                            <?php else: ?>
                                <span><?=$e($t('governorMaxLevel', 'Maximum Level')); ?> <?=$e((string)(int)$selected['maxLevel']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="governorLevelWrapper">
                        <div class="governorLevel">
                            <i class="governorStarsIcon <?=$e((string)$selected['key']); ?>Stars_large" aria-hidden="true"></i>
                            <div class="maxLevel"><?=$e((string)(int)$selected['stars']); ?> <?=$e($t('governorStars', 'Governor Stars')); ?></div>
                            <div class="progressBar">
                                <?php $detailPercent = (int)min(100, (int)floor(($selected['current'] / max(1, $selected['threshold'])) * 100)); ?>
                                <div class="bar">
                                    <div class="filling" style="width:<?=$e((string)$detailPercent); ?>%"></div>
                                    <div class="decoration"></div>
                                </div>
                            </div>
                            <div class="starsCounter">
                                <span><?=$e((string)(int)$selected['current']); ?>/<?=$e((string)(int)$selected['threshold']); ?></span>
                                <i class="starIcon" aria-hidden="true"></i>
                            </div>
                        </div>
                    </div>
                    <div class="infoBlockWrapper">
                        <h3><?=$e($t('governorMaxLevel', 'Maximum Level')); ?></h3>
                        <div class="infoBlock maxLevelsWrapper">
                            <div class="maxLevels">
                                <?php for ($level = 1; $level <= 5; $level++): ?>
                                    <?php
                                    $lockedLevel = $level > (int)$selected['maxLevel'];
                                    $threshold = \Model\SeasonPassModel::GOVERNOR_STAR_THRESHOLDS[$level];
                                    ?>
                                    <div class="maxLevelBadge<?=$lockedLevel ? ' locked' : ''; ?>">
                                        <div class="label"><?=$lockedLevel ? '' : $e((string)$level); ?></div>
                                        <div class="starsAmount">
                                            <i class="starIcon" aria-hidden="true"></i>
                                            <span><?=$e((string)$threshold); ?></span>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                    <div class="infoBlockWrapper">
                        <h3><?=$e($t('governorAbilities', 'Abilities')); ?></h3>
                        <div class="infoBlock governorAbilities">
                            <div class="abilityList">
                                <p><?=$e($t('governorAbilitiesHint', 'Village bonuses apply after you assign this Governor in the Main Building. Local worlds track Maximum Level from Season Pass stars only.')); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <a class="mainBuildingButton textButtonV2 buttonFramed rectangle withText green" href="/build.php?gid=15">
                    <div><?=$e($t('governorGoToMainBuilding', 'Go to Main Building')); ?></div>
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>
