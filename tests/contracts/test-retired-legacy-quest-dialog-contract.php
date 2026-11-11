#!/usr/bin/env php
<?php

declare(strict_types=1);

function failRetiredLegacyQuestDialog(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$routes = file_get_contents($root . '/game/deployment/include/api_legacy_routes.php');
$dailyQuest = file_get_contents($root . '/game/include/Controller/Ajax/dailyQuest.php');
$legacyQuestModel = file_get_contents($root . '/game/include/Model/Quest.php');
$clients = [
    'game-client.js' => file_get_contents($root . '/game/deployment/public/game-client.js'),
];

if ($routes === false || $dailyQuest === false || $legacyQuestModel === false || in_array(false, $clients, true)) {
    failRetiredLegacyQuestDialog('Legacy-quest retirement sources must be readable.');
}

foreach (['getQuestData', 'getTutorial', 'transitionTutorial', 'skipTutorial', 'hasNewReward', 'claimRegularReward', 'REGULAR_REWARDS'] as $retiredMember) {
    if (str_contains($legacyQuestModel, $retiredMember)) {
        failRetiredLegacyQuestDialog("Quest model still exposes retired regular-quest member: {$retiredMember}");
    }
}

if (!str_contains($legacyQuestModel, 'function setQuestBitwise')
    || !str_contains($legacyQuestModel, 'function multiply')
    || !str_contains($legacyQuestModel, 'QUEST_FINISHED')) {
    failRetiredLegacyQuestDialog('Active quest-state and multiplier helpers must remain after legacy-dialog retirement.');
}

if (str_contains($routes, "'tasks' => [null, 'tasks']")
    || str_contains($routes, "'quest' => [null, 'quest']")) {
    failRetiredLegacyQuestDialog('Retired regular-quest dialog routes must not remain registered.');
}

foreach (['game/include/Controller/Ajax/tasks.php', 'game/include/Controller/Ajax/quest.php', 'game/include/resources/Templates/quest/TodoList.php', 'game/include/resources/Templates/quest/TutorialLayout.php'] as $path) {
    if (is_file($root . '/' . $path)) {
        failRetiredLegacyQuestDialog("Retired legacy-quest surface remains: {$path}");
    }
}

if (str_contains($dailyQuest, 'questTutorialId') || str_contains($dailyQuest, 'AchievementQuestReward') || str_contains($dailyQuest, 'action')) {
    failRetiredLegacyQuestDialog('Daily quests must accept only their current questId payload.');
}

foreach ($clients as $name => $client) {
    if (str_contains($client, 'new Travian.Dialog.Ajax("tasks"')
        || str_contains($client, 'openTodoListDialog')
        || str_contains($client, 'mentorClick:')
        || str_contains($client, 'questButtonOverview &&')
        || str_contains($client, 'skipButtonClick')
        || str_contains($client, 'questButtonSkipTutorial')) {
        failRetiredLegacyQuestDialog("{$name} still exposes the retired regular-quest dialog flow.");
    }
    if (!str_contains($client, 'new Travian.Dialog.Ajax("daily-quest"')
        || !str_contains($client, 'Travian.api("daily-quest/award"')
        || str_contains($client, 'rewardButtonClick')
        || str_contains($client, 'questButtonGainReward')
        || str_contains($client, 'openDailyQuestDetail')) {
        failRetiredLegacyQuestDialog("{$name} must route daily-quest rewards to daily-quest/award and keep no retired detail or reward-button flow.");
    }
}

echo "Retired legacy-quest dialog contract passed.\n";
