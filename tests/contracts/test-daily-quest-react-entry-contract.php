#!/usr/bin/env php
<?php

declare(strict_types=1);

function failDailyQuestReactEntryContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$layout = file_get_contents($root . '/game/include/resources/Templates/layout/Layout.php');
if ($layout === false) {
    failDailyQuestReactEntryContract('The shared layout must be readable.');
}
$reward = file_get_contents($root . '/game/include/resources/Templates/dailyQuest/reward.php');
if ($reward === false) {
    failDailyQuestReactEntryContract('The maintained Daily Quest reward template must be readable.');
}

$referenceHandler = 'Travian.React.openDailyQuestsDialog(); return false;';
if (substr_count($layout, $referenceHandler) !== 2) {
    failDailyQuestReactEntryContract('Desktop and mobile Daily Quest headers must call the current React entry point.');
}
if (str_contains($layout, 'questButtonOverviewAchievements: true')) {
    failDailyQuestReactEntryContract('The shared Daily Quest headers must not route through the retired generic button dispatcher.');
}
if (str_contains($reward, 'questButtonOverviewAchievements') || str_contains($reward, '<script')) {
    failDailyQuestReactEntryContract('The reward screen must carry no script of its own: the dialog binds Back, the chests and Collect.');
}

foreach (['game-client.js'] as $client) {
    $source = file_get_contents($root . '/game/deployment/public/' . $client);
    if ($source === false
        || !str_contains($source, 'Travian.React.openDailyQuestsDialog = function ()')
        || !str_contains($source, 'return Travian.Game.Quest.openDailyQuestDialog();')) {
        failDailyQuestReactEntryContract("{$client} must expose the Daily Quest dialog through Travian.React.");
    }

    if (!preg_match(
        '~openDailyQuestDialog: function \(\) \{(?<body>.*?)\n    \},\n    /\* The dialog~s',
        $source,
        $overview
    )
        || !str_contains($overview['body'], 'buttonOk: !1,')
        || str_contains($overview['body'], 'buttonTextOk:')
        || str_contains($overview['body'], 'buttonCloseOnClickOk:')
        || !str_contains($overview['body'], 'this.dialog.dailyQuest.displayButtonOk(!1)')
        || !str_contains($overview['body'], 'e.bindDialog(dialog);')) {
        failDailyQuestReactEntryContract("{$client} must keep the Daily Quest overview footerless, like the reference React dialog, and bind its screens once.");
    }
    if (str_contains($source, 'i.questButtonOverviewAchievements')
        || str_contains($source, '? Travian.Game.Quest.openDailyQuestDialog()')) {
        failDailyQuestReactEntryContract("{$client} must not keep the retired generic Daily Quest overview dispatcher.");
    }
}

echo "Daily Quest React header-entry contract passed.\n";
