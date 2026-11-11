#!/usr/bin/env php
<?php

declare(strict_types=1);

function failDailyQuestAllianceStepsContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$model = file_get_contents($root . '/game/include/Model/DailyQuestModel.php');
$allianceBonus = file_get_contents($root . '/game/include/Model/AllianceBonusModel.php');
$migration = file_get_contents($root . '/game/include/update.php');

if ($model === false || $allianceBonus === false || $migration === false) {
    failDailyQuestAllianceStepsContract('Daily-quest sources and the world migration must be readable.');
}

if (!str_contains($model, "11 => ['reward' => 1, 'stepCount' => 6]")) {
    failDailyQuestAllianceStepsContract('Alliance contribution must grant six daily-quest steps worth one point each.');
}

if (preg_match('~"AchievementQuest_11" => \[(.*?)^        \],$~ms', $model, $questMetadata) !== 1
    || !str_contains($questMetadata[1], '"stepCount"   => 6,')
    || substr_count($questMetadata[1], '"stepId"') !== 6) {
    failDailyQuestAllianceStepsContract('Alliance contribution metadata must expose all six task pills.');
}

if (!str_contains($allianceBonus, 'qst11=LEAST(FLOOR((alliance_contribution+?)/?), 6)')) {
    failDailyQuestAllianceStepsContract('Alliance donations must cap the sixth daily-quest step, not the third.');
}

if (!str_contains($migration, 'if ($currentVersion < 106)')
    || !str_contains($migration, 'UPDATE daily_quest SET qst11=LEAST(qst11*2,6)')) {
    failDailyQuestAllianceStepsContract('Existing daily-quest progress must migrate 3 × 2 points into 6 × 1 points.');
}

echo "Daily-quest alliance-step contract passed.\n";
