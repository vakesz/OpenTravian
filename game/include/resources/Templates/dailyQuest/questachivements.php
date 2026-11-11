<?php

use Core\Helper\TimezoneHelper;
use Core\Session;
use Model\DailyQuestModel;

require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/track.php';

$model = new DailyQuestModel();
$playerId = Session::getInstance()->getPlayerId();
$quest = $model->getQuest($playerId);
$totalPoints = $model->calcTotalPoints($quest);
$hasCollectible = false;
foreach (DailyQuestModel::MILESTONES as $rewardNumber => $threshold) {
    $hasCollectible = $hasCollectible || ($totalPoints >= $threshold && empty($quest['reward' . $rewardNumber . 'Done']));
}

$nextReset = TimezoneHelper::date('H:i', $model->nextResetAt());

/** @var callable(int): string $questName */
$questName = static function (int $id): string {
    $name = T('DailyQuest', 'questData.' . $id . '.name');
    if ($id === 11) {
        $name = sprintf($name, DailyQuestModel::getAllianceContributionNeededForQuest());
    }

    return htmlspecialchars((string)$name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$questOrder = [11, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
?>
<div id="dailyQuests" class="contentV2">
    <div class="screen active">
        <div>
            <h1><?= T('DailyQuest', 'Receive these free rewards every day!'); ?></h1>
            <div><?= sprintf(T('DailyQuest', 'nextResetDesc'), $nextReset); ?></div>
            <div class="dailyProgress">
                <div class="content">
                    <div class="pointsSource"><div class="inner"></div></div>
                    <?php foreach (DailyQuestModel::MILESTONES as $rewardNumber => $threshold): ?>
                        <?php
                        $done = !empty($quest['reward' . $rewardNumber . 'Done']);
                        $state = $totalPoints >= $threshold ? ($done ? 'completed' : 'achieved') : 'locked';
                        ?>
                        <div class="reward">
                            <?= daily_quest_svg('below'); ?>
                            <div class="rewardImage reward<?= $threshold; ?> <?= $state; ?> animationsEnabled"></div>
                            <?php if ($done): ?><?= daily_quest_svg('check', 'check '); ?><?php endif; ?>
                            <?= daily_quest_svg('above'); ?>
                            <div class="points"><?= $threshold; ?></div>
                        </div>
                    <?php endforeach; ?>
                    <div class="achievedPoints">
                        <?= daily_quest_svg('laurel'); ?>
                        <?= daily_quest_svg('above'); ?>
                        <div class="achieved"><?= (int)$totalPoints; ?></div>
                        <div class="points"><?= T('DailyQuest', 'points'); ?></div>
                    </div>
                </div>
                <div class="backgroundBox">
                    <?php for ($segment = 0; $segment < 4; ++$segment): ?>
                        <?php
                        $segmentStart = $segment * 25;
                        $segmentEnd = $segmentStart + 25;
                        if ($totalPoints >= $segmentEnd) {
                            $fill = 100;
                        } elseif ($totalPoints > $segmentStart) {
                            $fill = (int)round((($totalPoints - $segmentStart) / 25) * 100);
                        } else {
                            $fill = 0;
                        }
                        ?>
                        <div class="progressBar preventMobileSwipeNavigation ">
                            <div class="bar">
                                <?= daily_quest_svg('decoration_start'); ?>
                                <div class="filling primary green" style="width: <?= $fill; ?>%;"></div>
                                <?= daily_quest_svg('decoration_end'); ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="quests">
                <?php foreach ($questOrder as $id): ?>
                    <?php
                    $stepId = (int)$quest['qst' . $id];
                    $rowId = 'dailyQuestDailyQuest_' . sprintf('%02d', $id);
                    ?>
                    <div class="dailyQuest<?= $model->isQuestCompleted($id, $stepId) ? ' completed' : ''; ?>" id="<?= $rowId; ?>">
                        <div class="titleDecoration"></div>
                        <div class="title"><?= $questName($id); ?></div>
                        <?= daily_quest_track($model, $id, $stepId); ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="actionWrapper">
                <button type="button"
                        class="textButtonV2 buttonFramed collectRewards rectangle withText green"
                        <?= $hasCollectible ? '' : 'disabled="disabled"'; ?>>
                    <div><?= T('DailyQuest', 'Collect rewards'); ?></div>
                </button>
            </div>
        </div>
    </div>
    <div class="screen "></div>
    <div class="screen "></div>
</div>
