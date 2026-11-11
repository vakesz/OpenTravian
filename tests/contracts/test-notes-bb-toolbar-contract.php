<?php

declare(strict_types=1);

/**
 * Contract: notes BB toolbar window-button class order matches write.php /.
 */

function failNotesBbToolbarContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$notes = file_get_contents($root . '/game/include/resources/Templates/messages/note.php');
$write = file_get_contents($root . '/game/include/resources/Templates/messages/write.php');
$toolbar = file_get_contents($root . '/game/include/resources/Templates/messages/bbToolbar.php');
if ($notes === false || $write === false || $toolbar === false) {
    failNotesBbToolbarContract('Unable to read message templates.');
}

foreach (['note.php' => $notes, 'write.php' => $write] as $name => $template) {
    if (!str_contains($template, "'messages/bbToolbar'")) {
        failNotesBbToolbarContract("{$name} must render the shared messages/bbToolbar partial.");
    }
}

foreach ([
    ['bbResource', 'resources'],
    ['bbSmilies', 'smilies'],
    ['bbTroops', 'troops'],
] as [$class, $window]) {
    if (!str_contains($toolbar, "bbButton <?= \$class; ?> bbWin{<?= \$window; ?>}")) {
        failNotesBbToolbarContract("bbToolbar.php missing BB order for {$class}.");
    }
}
if (str_contains($toolbar, 'bbWin{resources} bbButton')) {
    failNotesBbToolbarContract('bbToolbar.php still puts bbWin before bbButton.');
}

echo "Notes BB toolbar contract passed.\n";
