<?php

declare(strict_types=1);

$controller = file_get_contents(dirname(__DIR__, 2) . '/game/include/Controller/Ajax/infobox/read.php');
$model = file_get_contents(dirname(__DIR__, 2) . '/game/include/Model/InfoBoxModel.php');
$template = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/ajax/infoboxCounter.php');

if ($controller === false
    || !str_contains($controller, 'setInfoboxItemsRead')
    || !str_contains($controller, 'messageCounterContent')
    || !str_contains($controller, 'count($infoIds) > 50')) {
    fwrite(STDERR, "Infobox reads must validate IDs, persist read state, and return the replacement counter.\n");
    exit(1);
}

if ($model === false
    || str_contains($model, '$ignore_id[0]')
    || !str_contains($model, 'invalidateUserInfoBoxCache($uid)')) {
    fwrite(STDERR, "Infobox unread counts and cached private infoboxes must update after messages are read.\n");
    exit(1);
}

if ($template === false || !str_contains($template, 'messageShortInfo')) {
    fwrite(STDERR, "Infobox reads must return the client counter markup.\n");
    exit(1);
}

echo "Infobox read API contract passed.\n";
