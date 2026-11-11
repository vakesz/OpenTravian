#!/usr/bin/env php
<?php

declare(strict_types=1);

use Game\Tasks\TaskBoard;

function failTaskDetailNavigation(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$taskBoard = $root . '/game/include/Game/Tasks/TaskBoard.php';
$main = file_get_contents($root . '/game/include/resources/Templates/tasks/main.php');
$detail = file_get_contents($root . '/game/include/resources/Templates/tasks/detail.php');
$reload = file_get_contents($root . '/game/include/Controller/Ajax/progressiveTasks/reload.php');
$script = file_get_contents($root . '/game/deployment/public/tasks-board.js');
if ($main === false || $detail === false || $reload === false || $script === false || !is_file($taskBoard)) {
    failTaskDetailNavigation('Task-detail sources must be readable.');
}

require_once $taskBoard;

$group = [
    'milestones' => [
        ['milestone' => 1, 'claimed' => true, 'eligible' => true],
        ['milestone' => 2, 'claimed' => false, 'eligible' => true],
        ['milestone' => 3, 'claimed' => false, 'eligible' => true],
        ['milestone' => 4, 'claimed' => false, 'eligible' => false],
        ['milestone' => 5, 'claimed' => false, 'eligible' => false],
    ],
];
$levels = array_column(TaskBoard::detailMilestones($group), 'milestone');
if ($levels !== [2, 3, 4, 1]) {
    failTaskDetailNavigation('Detail state must show claimable milestones, one future milestone, then completed history.');
}
$completedLevels = array_column(TaskBoard::detailMilestones([
    'milestones' => [
        ['milestone' => 1, 'claimed' => true, 'eligible' => true],
        ['milestone' => 2, 'claimed' => true, 'eligible' => true],
        ['milestone' => 3, 'claimed' => true, 'eligible' => true],
    ],
]), 'milestone');
if ($completedLevels !== [3, 2, 1]) {
    failTaskDetailNavigation('Completed detail history must be ordered newest-first.');
}

foreach ([
    'data-task-detail-template',
    "PHPBatchView::render('tasks/detail'",
] as $needle) {
    if (!str_contains($main, $needle)) {
        failTaskDetailNavigation("Task overview must retain {$needle} for in-place detail navigation.");
    }
}
foreach ([
    'class="taskDetailview"',
    'class="taskDescription"',
    'class="taskImage description"',
    'class="task <?= $state; ?> level',
    'data-task-detail-page',
    'taskDetailPagination',
    'backToOverview',
    "PHPBatchView::render('tasks/rewardBonus'",
] as $needle) {
    if (!str_contains($detail, $needle)) {
        failTaskDetailNavigation("Task detail must use the native {$needle} surface.");
    }
}
if (str_contains($detail, '<form') || str_contains($detail, 'taskOpenLink')) {
    failTaskDetailNavigation('Task detail must use the existing client reward action and in-place navigation, not a legacy form route.');
}
foreach ([
    "\$this->response['details'] = \$details;",
    "PHPBatchView::render('tasks/detail'",
    "'heroBonus' => \$heroBonus",
] as $needle) {
    if (!str_contains($reload, $needle)) {
        failTaskDetailNavigation("Reload must return refreshed detail markup: {$needle}");
    }
}
foreach ([
    'function showDetail(key)',
    'function showOverview()',
    'function switchDetailPage(button)',
    'taskDetailPagination button[data-task-detail-page]',
    '#tasks .task.group[data-task-key]',
    'event.target.closest("button, a, input, label")',
    'replaceDetailTemplates(details)',
] as $needle) {
    if (!str_contains($script, $needle)) {
        failTaskDetailNavigation("Task client navigation is missing {$needle}.");
    }
}
if (str_contains($script, 'taskOpenLink') || str_contains($script, 'window.location.assign("/tasks?task=')) {
    failTaskDetailNavigation('Task details must remain an in-place state, not a detail route.');
}

echo "Task detail navigation contract passed.\n";
