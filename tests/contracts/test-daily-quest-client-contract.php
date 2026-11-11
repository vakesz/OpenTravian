<?php

declare(strict_types=1);

function failDailyQuestClientContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/game/include/Controller/Ajax/dailyQuest.php');
$ajaxController = file_get_contents($root . '/game/include/Controller/AjaxCtrl.php');
$rewardTemplate = file_get_contents($root . '/game/include/resources/Templates/dailyQuest/reward.php');
$overviewTemplate = file_get_contents($root . '/game/include/resources/Templates/dailyQuest/questachivements.php')
    . file_get_contents($root . '/game/include/resources/Templates/dailyQuest/track.php');
$layoutTemplate = file_get_contents($root . '/game/include/resources/Templates/layout/Layout.php');
$gameView = file_get_contents($root . '/game/include/resources/View/GameView.php');
$enUs = file_get_contents($root . '/game/include/resources/Translation/en-US.php');
if ($controller === false || $ajaxController === false || $rewardTemplate === false
    || $overviewTemplate === false || $layoutTemplate === false || $gameView === false || $enUs === false) {
    failDailyQuestClientContract('Daily-quest source files must be readable.');
}
if (is_file($root . '/game/include/resources/Templates/layout/sidebarBoxQuestachievements.php')) {
    failDailyQuestClientContract('The obsolete duplicate daily-quest sidebar template must stay removed.');
}

if (!str_contains($controller, "'basic dailyQuestsDialog'")) {
    failDailyQuestClientContract('The daily overview must use the V2 parchment dialog class.');
}
if (str_contains($controller, "'white questTodoList'")
    || str_contains($controller, 'questInformation achievementquest_')
    || str_contains($controller, 'questInformation achievementquestreward_')) {
    failDailyQuestClientContract('Daily quest Ajax views must not use the retired questInformation frame.');
}
if (substr_count($controller, "'basic dailyQuestsDialog'") < 1) {
    failDailyQuestClientContract('The overview Ajax view must keep dailyQuestsDialog (the reward screens are fragments of it).');
}
if (!str_contains($ajaxController, '$rawHtml = !empty($response[\'_rawHtml\']);')
    || !str_contains($ajaxController, 'if ($rawHtml && isset($response[\'html\'])')) {
    failDailyQuestClientContract('Daily-quest dialog views must use the centralized default JSON response.');
}

if (!str_contains($overviewTemplate, 'id="dailyQuests" class="contentV2"')
    || !str_contains($overviewTemplate, 'class="quests"')
    || !str_contains($overviewTemplate, 'taskTrack withSummary')
    || !str_contains($overviewTemplate, 'preventMobileSwipeNavigation')
    || !str_contains($overviewTemplate, 'rewardImage reward')
    || !str_contains($overviewTemplate, 'animationsEnabled')
    || !str_contains($overviewTemplate, "dailyQuestDailyQuest_")) {
    failDailyQuestClientContract('The overview must use native #dailyQuests.contentV2 markup.');
}
if (str_contains($overviewTemplate, 'id="achievementQuestList"')
    || str_contains($overviewTemplate, 'id="dailyQuestsOverviewScreen"')
    || str_contains($overviewTemplate, '❧')) {
    failDailyQuestClientContract('The overview must not keep retired list ids or emoji laurels.');
}

if (!str_contains($rewardTemplate, 'id="dailyQuestsRewardScreen"')
    || !str_contains($rewardTemplate, 'class="rewardWrapper"')
    || !str_contains($rewardTemplate, 'class="iconValueBoxWrapper"')
    || !str_contains($rewardTemplate, 'textButtonV2 buttonFramed collect ')
    || !str_contains($rewardTemplate, 'data-text-collected=')
    || !str_contains($rewardTemplate, 'class="collectedCheckmark"')
    || !str_contains($rewardTemplate, '<table class="borderGap">')
    || str_contains($rewardTemplate, 'questButtonGainReward')
    || str_contains($rewardTemplate, 'buttonClicked')) {
    failDailyQuestClientContract('The reward screen must be the reference structure: Collect button inside the four-child #dailyQuestsRewardScreen .rewardWrapper, then the rotation table.');
}
if (!str_contains($rewardTemplate, 'button-container addHoverClick')
    && !str_contains($rewardTemplate, 'button-background')) {
    // The detail templates use the current shared button component instead of
    // retired nested V1 button artwork.
} else {
    failDailyQuestClientContract('Daily reward actions must not retain retired V1 button markup.');
}
if (str_contains($overviewTemplate, 'data-questid') || str_contains($overviewTemplate, 'a class="reward')
    || !str_contains($overviewTemplate, '<div class="reward">')
    || !str_contains($overviewTemplate, "daily_quest_svg('check', 'check ')")) {
    failDailyQuestClientContract('Every chest must be a plain div.reward (any state opens its screen), with the drawn check once collected.');
}
if (str_contains($overviewTemplate, 'id="achievementQuestList"')
    || str_contains($overviewTemplate, 'quests/achievement/kranz.png')) {
    failDailyQuestClientContract('Overview must not keep the retired achievementQuestList / kranz.png chrome.');
}
if (!str_contains($layoutTemplate, 'class="dailyQuests"')
    || substr_count($layoutTemplate, 'Travian.React.openDailyQuestsDialog(); return false;') !== 2
    || str_contains($layoutTemplate, 'questButtonOverviewAchievements: true')) {
    failDailyQuestClientContract('Desktop and mobile headers must use the current Daily Quest React entry point.');
}
if (str_contains($gameView, 'renderDailyQuest(')
    || str_contains($gameView, 'sidebarBoxQuestachievements')) {
    failDailyQuestClientContract('Daily quests must not render a retired duplicate sidebar box.');
}
if (!str_contains($enUs, "'(Next reset at %s. Make sure to collect your reward before!)'")) {
    failDailyQuestClientContract('en-US reset copy must match the reference parenthetical wording.');
}

foreach (['game-client.js'] as $client) {
    $source = file_get_contents($root . '/game/deployment/public/' . $client);
    if ($source === false
        || !str_contains($source, 'Travian.api("daily-quest/award", {')
        || !str_contains($source, 'action: "dailyQuest",')
        || !str_contains($source, 'questId: "DailyQuestsReward_0" + milestone,')
        || !str_contains($source, 'error: function () {')
        || str_contains($source, 'rewardButtonClick')) {
        failDailyQuestClientContract("$client must send daily reward claims to daily-quest/award as the reference does.");
    }
    foreach (['bindDialog: function (dialog) {', 'openReward: function (milestone) {', 'openOverview: function () {', 'openQuestDetails: function (questId) {', 'collectReward: function (button) {', 'showScreen: function (index) {'] as $method) {
        if (!str_contains($source, $method)) {
            failDailyQuestClientContract("$client must handle the daily-quest screens in place ($method).");
        }
    }
    if (!str_contains($source, 'openDailyQuestDialog: function ()')
        || !str_contains($source, 'Travian.React.openDailyQuestsDialog = function ()')
        || !str_contains($source, 'return Travian.Game.Quest.openDailyQuestDialog();')
        || !str_contains($source, 'new Travian.Dialog.Ajax("daily-quest"')
        || !str_contains($source, 'enableBackground: !0,')
        || !str_contains($source, 'draggable: !1,')
        || !str_contains($source, 'cssClass: "basic dailyQuestsDialog",')
        || !str_contains($source, 'buttonOk: !1,')
        || !str_contains($source, 'dialog.overlay.addClass("doNotCoverHeader stickToHeader")')
        || !str_contains($source, 'window.setTimeout(function () {')
        || !str_contains($source, 'dialog.wrapper.scrollTop(0)')) {
        failDailyQuestClientContract("$client must render daily quests below the HUD in the native non-draggable dialog.");
    }
}

$model = file_get_contents($root . '/game/include/Model/DailyQuestModel.php');
if ($model === false
    || !str_contains($model, 'private function normalizeQuestProgress(array $quest): array')
    || !str_contains($model, 'max(0, (int)$stepId)')
    || !str_contains($model, 'return is_array($quest) ? $this->normalizeQuestProgress($quest) : null;')) {
    failDailyQuestClientContract('Daily quest rendering must clamp stale progress to each quest’s maximum step.');
}
if (!str_contains($model, '$this->calcTotalPoints($row)')) {
    failDailyQuestClientContract('Daily reward collection must use normalized progress instead of raw counters.');
}

echo "Daily quest client contract passed.\n";
