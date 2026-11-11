#!/usr/bin/env php
<?php

declare(strict_types=1);

// The words of the daily-quest reward screen are the reference's (read from its achievementQuests and tasks catalogues),
// every reward the model can grant has a line and an icon, and the copy of the retired reward dialog is gone.

function expectRewardCopy(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__, 2);
// The DailyQuest section of the catalogue, evaluated on its own (the file as a whole needs the game bootstrap).
$catalogue = (string)file_get_contents($root . '/game/include/resources/Translation/en-US.php');
$start = strpos($catalogue, "//DailyQuest\n");
$end = strpos($catalogue, "\n//", (int)$start + 5);
expectRewardCopy($start !== false && $end !== false, 'The DailyQuest section of en-US.php was not found.');
$Definition = [];
eval(substr($catalogue, (int)$start, (int)$end - (int)$start));
$group = $Definition['DailyQuest'] ?? [];

$reference = [
    'Keep collecting points!' => 'Keep collecting points!',
    'Reward already claimed!' => 'Reward already claimed!',
    'Congratulations! You have collected enough points to get a reward!' => 'Congratulations! You have collected enough points to get a reward!',
    'Claim your reward for collecting %s points today. This reward is chosen based on the current reward rotation.' => 'Claim your reward for collecting %s points today. This reward is chosen based on the current reward rotation.',
    'lockedRewardDescription' => "You currently have %s points needed for today's reward. Continue solving daily quests to earn more points!",
    'collectedRewardDescription' => "You've already collected your reward for reaching %s points today. Check back tomorrow for a new opportunity",
    'Reward rotation' => 'Reward rotation',
    'Next claim' => 'Next claim',
    'Today (until %s)' => 'Today (until %s)',
    'Tomorrow (until %s)' => 'Tomorrow (until %s)',
    'Collect' => 'Collect',
    'Collected' => 'Collected',
    'Back' => 'Back',
    'day' => 'day',
];
foreach ($reference as $key => $text) {
    expectRewardCopy(($group[$key] ?? null) === $text, "DailyQuest.$key must read exactly: $text");
}

$lines = [
    'allResources' => '%s resources of each type',
    'xp' => '%s hero experience',
    'amount' => '%s culture points',
    'randomResource' => '%s resources of one random type',
    'duration' => '%s day with the Plus Account',
    'resource_lumber' => '%s day(s) with %s lumber production',
    'resource_clay' => '%s day(s) with %s clay production',
    'resource_iron' => '%s day(s) with %s iron production',
    'resource_crop' => '%s day(s) with %s crop production',
    'heroItem_ointment' => '%s ointments',
    'heroItem_tabletOfLaw' => '%s tablets of law',
    'heroItem_bandage25' => '%s small bandages',
    'heroItem_cage' => '%s cages',
    'getExtraAdventures' => '%s additional adventure(s)',
];
expectRewardCopy(($group['rewardLines'] ?? null) === $lines, 'DailyQuest.rewardLines must be the reference reward_* lines.');

// The quest details screens: the reference's name and sentence for each quest, and a tip for exactly 2, 8 and 9
// (quest 11's tip needs a next contribution threshold, which this world does not have: one fixed amount).
$questCopy = [
    1 => ['Complete an adventure', 'Send your hero on an adventure. You\'ll complete this quest as soon as they arrive – success or failure doesn\'t matter.'],
    2 => ['Raid an unoccupied oasis', 'Raid an unoccupied oasis with your troops. The quest completes upon arrival, even if you don\'t win. Remember, using cages won\'t count for this quest.', 'Tip: For safety, you can still use cages when raiding an empty oasis. This won\'t give you progress on the daily task if nature troops have respawned, but it will protect your troops.'],
    3 => ['Raid/attack a Natarian village', 'Send your troops to raid or attack a Natarian village. The quest completes upon arrival, win or lose.'],
    4 => ['Win an auction', 'Win any auction to complete this quest. It\'s a double win – you get the item and the quest points.'],
    5 => ['Gain or spend gold', 'Gain or spend some Gold – it\'s your choice. Any amount counts towards completing this quest.'],
    6 => ['Upgrade a building', 'Upgrade an existing building or construct a new one. Quest completes when construction finishes.'],
    7 => ['Upgrade a resource field', 'Upgrade an existing resource field or build a new one. Quest completes when construction finishes.'],
    8 => ['Build 20 infantry units of one type at once', 'Train 20 infantry units of the same type at once in your barracks.', 'Note: Units already in the queue don\'t count.'],
    9 => ['Build 20 cavalry units of one type at once', 'Train 20 cavalry units of the same type at once in your stable.', 'Note: Units already in the queue don\'t count.'],
    10 => ['Hold a small or big celebration', 'Hold a small or big celebration in your town hall. Quest completes when the celebration begins.'],
    11 => ['Contribute %s resources to an alliance bonus', 'Contribute %s resources to any alliance bonus. Your support helps unlock powerful benefits for your alliance.'],
];
foreach ($questCopy as $id => $copy) {
    $entry = $group['questData'][$id] ?? [];
    expectRewardCopy(array_keys($entry) === (count($copy) === 3 ? ['name', 'description', 'additionalInformation'] : ['name', 'description']), "DailyQuest.questData.$id must hold exactly " . (count($copy) === 3 ? 'name, description and additionalInformation' : 'name and description') . '.');
    expectRewardCopy(array_values($entry) === $copy, "DailyQuest.questData.$id must carry the reference's words.");
}
expectRewardCopy(array_keys($group['questData']) === range(1, 11), 'questData must describe the 11 daily quests, not the retired video quests.');

// Every reward the model can grant is announced: a line and an icon for its id, in the box and in the table.
$model = (string)file_get_contents($root . '/game/include/Model/DailyQuestModel.php');
preg_match_all("/=> \\['([A-Za-z_0-9]+)', \\d+, '(?:res|exp|cp|plus|productionBoost|item|adv)'\\]/", $model, $granted);
$css = (string)file_get_contents($root . '/assets/gpack/travian/mainPage/css_ltr/imports.css');
expectRewardCopy(count(array_unique($granted[1])) === count($lines), 'The grant table must name the reference reward ids only (found ' . implode(', ', array_unique($granted[1])) . ').');
foreach (array_unique($granted[1]) as $id) {
    expectRewardCopy(isset($lines[$id]), "Reward $id has no line.");
    expectRewardCopy(str_contains($css, ".iconValueBox i.$id {") && str_contains($css, "#dailyQuests #dailyQuestsRewardScreen table i.$id {"), "Reward $id has no icon in the Gpack.");
}

// The retired reward dialog's copy is gone, from every locale.
$retired = ['Click for details', 'Overview', 'This account is banned', 'Quest is complete for today', 'Quest is still open', 'Difficulty', 'Difficulties', 'Requirement',
    'This quest is worth + x points', 'Points granted for this quest: + x / y', 'The points for this quest can be achieved x times per day',
    'VoteRewardDesc', 'Earn free gold!!!', 'Click on Consultant to open Hints page', 'if you abuse the vote system you will be banned', 'voteQuestDescription',
    'Collect reward', 'x points achieved', 'Your Reward:', 'you collected x points today!', 'reward_25%_desc', 'reward_25%_rows', 'reward_100%_rows',
    'For collecting x points today, you receive the following reward', 'For collecting x points today, you can now collect your reward', 'Your daily reward is determined randomly from these options'];
foreach (glob($root . '/game/include/resources/Translation/*.php') ?: [] as $file) {
    $source = (string)file_get_contents($file);
    foreach ($retired as $key) {
        expectRewardCopy(!str_contains($source, "['DailyQuest']['$key']"), basename($file) . " still defines the retired DailyQuest.$key.");
    }
}
expectRewardCopy(!str_contains((string)file_get_contents($root . '/game/include/functions.general.php'), 'calculate_dailyquest_bonus'), 'The catalogue-only calculate_dailyquest_bonus() must stay deleted.');

echo "Daily quest reward copy contract passed.\n";
