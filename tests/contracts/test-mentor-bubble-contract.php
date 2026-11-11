#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: the mentor "!" bubble (sidebar #questmasterButton) and the top-nav
 * daily-quests "!" are shown only for a collectable reward.
 *
 * - Outside the tutorial the bubble comes from TasksModel::hasAvailableReward
 *   for the active village plus the account-wide general tasks, the same source
 *   as the /tasks "Collect" buttons; legacy qst_* guided-quest states never
 *   light it.
 * - The daily-quests "!" is DailyQuestModel::hasClaimableReward.
 */

function failMentorBubble(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$view = (string)file_get_contents($root . '/game/include/resources/View/GameView.php');
$tpl = (string)file_get_contents($root . '/game/include/resources/Templates/layout/sidebarBoxQuestmaster.php');
$layout = (string)file_get_contents($root . '/game/include/resources/Templates/layout/Layout.php');

if (!str_contains($view, '$tasksModel->hasAvailableReward($playerId, $villageId)')
    || !str_contains($view, "\$villageId = (int) \$this->session->getKid();")
    || !str_contains($view, "\$view->vars['hasNewReward'] = \$hasTaskReward;")) {
    failMentorBubble('The sidebar bubble must use TasksModel::hasAvailableReward for the active village.');
}
if (substr_count($tpl, "if (\$vars['hasNewReward'])") !== 1 || !str_contains($tpl, 'newQuestSpeechBubble')) {
    failMentorBubble('The speech bubble must render only under hasNewReward.');
}
if (!str_contains($layout, '!empty($vars["dailyQuestsIndicator"])')) {
    failMentorBubble('The daily-quests indicator must be conditional.');
}

// Live data check (read-only): per-scope availability equals the task board's eligible && unclaimed rows.
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';
$db = Core\Database\DB::getInstance();
$result = $db->query('SELECT id FROM users WHERE id > 1 ORDER BY id LIMIT 1');
$row = $result ? $result->fetch_assoc() : null;
if ($row) {
    $uid = (int)$row['id'];
    $model = new Model\TasksModel();
    $villages = $db->query('SELECT kid FROM vdata WHERE owner=' . $uid);
    while ($villages && ($v = $villages->fetch_assoc())) {
        $kid = (int)$v['kid'];
        $expected = false;
        foreach ([['village', $kid], ['general', 0]] as [$scope, $k]) {
            foreach ($model->getTasks($uid, $scope, $k) as $task) {
                $expected = $expected || ($task['eligible'] && !$task['claimed']);
            }
        }
        if ($model->hasAvailableReward($uid, $kid) !== $expected) {
            failMentorBubble("hasAvailableReward disagrees with the task board for uid {$uid} village {$kid}.");
        }
    }
}

echo "mentor bubble contract passed\n";
