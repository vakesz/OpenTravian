<?php

declare(strict_types=1);

/**
 * Contract: alliance forum BB toolbar window-button class order matches write.php /.
 */

function failAllianceForumBbToolbarContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$templates = [
    'newPost.php' => $root . '/game/include/resources/Templates/alliance/newPost.php',
    'new_topic.php' => $root . '/game/include/resources/Templates/alliance/new_topic.php',
];

$needles = [
    'bbButton bbResource bbWin{resources}',
    'bbButton bbSmilies bbWin{smilies}',
    'bbButton bbTroops bbWin{troops}',
];

foreach ($templates as $label => $path) {
    $content = file_get_contents($path);
    if ($content === false) {
        failAllianceForumBbToolbarContract("Unable to read {$label}.");
    }

    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            failAllianceForumBbToolbarContract("{$label} missing BB order: {$needle}");
        }
    }

    if (str_contains($content, 'bbWin{resources} bbButton')
        || str_contains($content, 'bbWin{smilies} bbButton')
        || str_contains($content, 'bbWin{troops} bbButton')) {
        failAllianceForumBbToolbarContract("{$label} still puts bbWin before bbButton.");
    }
}

echo "Alliance forum BB toolbar contract passed.\n";
