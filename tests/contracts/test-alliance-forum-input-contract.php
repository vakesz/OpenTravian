<?php

declare(strict_types=1);

function failAllianceForumInputContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$sources = [
    'forum controller' => $root . '/game/include/Controller/AllianceForum.php',
    'forum model' => $root . '/game/include/Model/ForumModel.php',
    'new topic template' => $root . '/game/include/resources/Templates/alliance/new_topic.php',
];

foreach ($sources as $name => $path) {
    $source = file_get_contents($path);
    if ($source === false) {
        failAllianceForumInputContract("Could not read the $name.");
    }
    $sources[$name] = $source;
}

if (str_contains($sources['forum controller'], 'FILTER_SANITIZE_STRING')
    || !str_contains($sources['forum controller'], 'private function getPostPlainText(string $key): string')
    || !str_contains($sources['forum controller'], "getPostInt('for_sitters')")
    || !str_contains($sources['forum model'], 'private function positiveIntegerList(mixed $values): array')
    || !str_contains($sources['forum model'], "'INSERT INTO forum_post (aid, uid, forumId, topicId, post, time, deleted) VALUES (?, ?, ?, ?, ?, ?, 0)'")
    || !str_contains($sources['forum model'], "'INSERT INTO forum_topic (forumId, thread, close, SurveyStartTime, Survey, end_time) VALUES (?, ?, ?, ?, ?, ?)'")
    || substr_count($sources['new topic template'], "htmlspecialchars(\$vars['option_") !== 8
    || !str_contains($sources['new topic template'], "htmlspecialchars(\$vars['thema'], ENT_QUOTES, 'UTF-8')")) {
    failAllianceForumInputContract('Alliance Forum must validate text input and encode its form values.');
}

echo "Alliance Forum input contract passed.\n";
