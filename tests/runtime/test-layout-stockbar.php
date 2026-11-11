<?php

$root = dirname(__DIR__, 2);
$layout = file_get_contents($root . '/game/include/resources/Templates/layout/Layout.php');
$tasks = file_get_contents($root . '/game/include/Controller/TasksCtrl.php');

if (!is_string($layout) || !is_string($tasks)) {
    fwrite(STDERR, "Required layout sources could not be read.\n");
    exit(1);
}

foreach (['lbar1', 'lbar2', 'lbar3', 'lbar4'] as $resourceBar) {
    if (substr_count($layout, 'id="' . $resourceBar . '"') !== 1) {
        fwrite(STDERR, "Stock bar {$resourceBar} must have exactly one DOM id.\n");
        exit(1);
    }
}

if (!str_contains($tasks, "['bodyCssClass'] = 'tasks'")) {
    fwrite(STDERR, "The task board must use the reference tasks body shell.\n");
    exit(1);
}

echo "Layout stock-bar and task-shell checks passed.\n";
