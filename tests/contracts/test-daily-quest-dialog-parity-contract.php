<?php

declare(strict_types=1);

function failDailyQuestDialogParityContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$overview = file_get_contents($root . '/game/include/resources/Templates/dailyQuest/questachivements.php')
    . file_get_contents($root . '/game/include/resources/Templates/dailyQuest/track.php');
$reward = file_get_contents($root . '/game/include/resources/Templates/dailyQuest/reward.php');
$icons = file_get_contents($root . '/game/include/resources/Templates/dailyQuest/icons.php');
$controller = file_get_contents($root . '/game/include/Controller/Ajax/dailyQuest.php');
$clientContract = file_get_contents($root . '/tests/contracts/test-daily-quest-client-contract.php');
$fixes = file_get_contents($root . '/assets/gpack/travian/mainPage/lang/overrides.css');

if ($overview === false || $reward === false || $icons === false || $controller === false || $fixes === false) {
    failDailyQuestDialogParityContract('Daily-quest dialog templates must be readable.');
}

foreach ([
    'id="dailyQuests" class="contentV2"' => $overview,
    'class="quests"' => $overview,
    'class="actionWrapper"' => $overview,
    'collectRewards' => $overview,
    'filling primary green' => $overview,
    'animationsEnabled' => $overview,
    'taskTrack withSummary' => $overview,
    'dailyQuestDailyQuest_' => $overview,
    "daily_quest_svg('laurel')" => $overview,
    "daily_quest_svg('laurelIcon')" => $overview,
] as $needle => $haystack) {
    if (!str_contains($haystack, $needle)) {
        failDailyQuestDialogParityContract("Overview must include reference chrome marker: {$needle}");
    }
}

if (str_contains($overview, 'id="achievementQuestList"')
    || str_contains($overview, 'id="dailyQuestsOverviewScreen"')
    || str_contains($overview, 'quests/achievement/kranz.png')
    || str_contains($overview, 'laurelIcon" aria-hidden="true">❧')) {
    failDailyQuestDialogParityContract('Overview must not retain the pre-contentV2 achievement list markup.');
}

$backArrow = '<svg viewBox="0 0 10.2 16.5"><path d="m.1 8.1-.1.2.1.2L7 15.9c.7.7 1.9.8 2.6.1.7-.7.8-1.8.1-2.5L5 8.3l4.7-5.2c.7-.7.7-1.9-.1-2.6-.7-.7-1.9-.7-2.6.1l-.1.1L.1 8.1z"></path></svg>';
if (!str_contains($reward, $backArrow)
    || str_contains($reward, '<span aria-hidden="true"></span>')) {
    failDailyQuestDialogParityContract('The reward Back control must keep the visible inline arrow.');
}

if (!str_contains($reward, 'id="dailyQuestsRewardScreen"')
    || !str_contains($reward, 'class="rewardWrapper"')
    || !str_contains($reward, 'class="titleDecoration"')
    || !str_contains($reward, 'class="iconValueBoxWrapper"')
    || !str_contains($reward, 'collectedCheckmark')
    || !str_contains($reward, '<table class="borderGap">')) {
    failDailyQuestDialogParityContract('Reward screen must be the reference #dailyQuestsRewardScreen with its rewardWrapper children and rotation table.');
}

if (substr_count($controller, "'basic dailyQuestsDialog'") < 1) {
    failDailyQuestDialogParityContract('The overview Ajax response must use basic dailyQuestsDialog.');
}

$svgDir = $root . '/game/include/resources/Templates/dailyQuest/svg';
foreach (['laurel.svg', 'laurel-icon.svg', 'below.svg', 'above.svg', 'check.svg', 'decoration-start.svg', 'decoration-end.svg'] as $file) {
    if (!is_file($svgDir . '/' . $file) || filesize($svgDir . '/' . $file) < 200) {
        failDailyQuestDialogParityContract("Missing recovered daily-quest SVG: {$file}");
    }
}

if (!str_contains($icons, 'function daily_quest_svg')) {
    failDailyQuestDialogParityContract('SVG helper daily_quest_svg() must be available to templates.');
}

$gpackReward = $root . '/assets/gpack/travian/mainPage/img_ltr/views/daily-quests/reward25.png';
if (!is_file($gpackReward)) {
    failDailyQuestDialogParityContract('Gpack DailyQuests reward25.png must exist for rewardImage backgrounds.');
}

if ($clientContract === false || !str_contains($clientContract, 'dailyQuestsDialog')) {
    failDailyQuestDialogParityContract('Client contract must remain alongside the dialog parity contract.');
}

if (preg_match('~#dailyQuests\\.contentV2\\s*\\{[^}]*\\b(?:max-height|overflow-y|overscroll-behavior)\\s*:~s', $fixes) === 1) {
    failDailyQuestDialogParityContract('Daily-quest content must not retain the local scroll box; the reference dialog wrapper owns scrolling.');
}

echo "Daily quest dialog parity contract passed.\n";
