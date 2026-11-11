<?php

declare(strict_types=1);

/**
 * Contract: Messages Notes tab matches form action/button/save field.
 */

function failMessagesNotesContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$note = file_get_contents($root . '/game/include/resources/Templates/messages/note.php');
if ($note === false) {
    failMessagesNotesContract('Unable to read messages/note.php');
}

foreach ([
    'action="/messages/notes"',
    'name="save"',
    'T("Global", "General.save")',
] as $needle) {
    if (!str_contains($note, $needle)) {
        failMessagesNotesContract('Notes template missing piece: ' . $needle);
    }
}

foreach (['action="/messages"', 'name="speichern"', 'General.ok'] as $legacy) {
    if (str_contains($note, $legacy)) {
        failMessagesNotesContract('Notes template must not keep legacy piece: ' . $legacy);
    }
}

fwrite(STDOUT, "Messages notes contract passed.\n");
