<?php

declare(strict_types=1);

function failAllianceForumButtonsContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$templates = [
    'new_topic' => $root . '/game/include/resources/Templates/alliance/new_topic.php',
    'newPost' => $root . '/game/include/resources/Templates/alliance/newPost.php',
    'newForum' => $root . '/game/include/resources/Templates/alliance/newForum.php',
    'forum_edit' => $root . '/game/include/resources/Templates/alliance/forum_edit.php',
    'editTopic' => $root . '/game/include/resources/Templates/alliance/editTopic.php',
    'showTopic' => $root . '/game/include/resources/Templates/alliance/showTopic.php',
];

foreach ($templates as $key => $path) {
    $contents = file_get_contents($path);
    if ($contents === false) {
        failAllianceForumButtonsContract("Could not read the {$key} forum template.");
    }

    if (str_contains($contents, 'button-container addHoverClick')) {
        failAllianceForumButtonsContract("{$key} must not retain legacy button-container addHoverClick chrome.");
    }

    if (!str_contains($contents, 'textButtonV1 green')
        || !str_contains($contents, 'version="textButtonV1"')
        || !str_contains($contents, '"version": "textButtonV1"')) {
        failAllianceForumButtonsContract("{$key} must use textButtonV1 green buttons with version=textButtonV1.");
    }

    if (str_contains($contents, 'roundV2')) {
        failAllianceForumButtonsContract("{$key} must use h4.round, not roundV2.");
    }

    $templates[$key] = $contents;
}

if (!str_contains($templates['newForum'], 'name="s1"')
    || !str_contains($templates['newForum'], 'id="s1"')
    || !str_contains($templates['newForum'], 'h4 class="round"')) {
    failAllianceForumButtonsContract('newForum must submit via name/id s1 with h4.round.');
}

if (!str_contains($templates['showTopic'], 'name="vote"')
    || !str_contains($templates['showTopic'], 'name="vote_ergebnis"')
    || !str_contains($templates['showTopic'], 'name="vote_abstimmung"')
    || !str_contains($templates['showTopic'], 'id="fbtn_reply"')) {
    failAllianceForumButtonsContract('showTopic must keep vote/reply field names while using textButtonV1.');
}

echo "Alliance forum buttons contract passed.\n";
