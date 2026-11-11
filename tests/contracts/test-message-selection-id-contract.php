<?php

declare(strict_types=1);

function failMessageSelectionIdContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$list = file_get_contents($root . '/game/include/resources/Templates/messages/list.php');
$stylesheets = [
    $root . '/assets/gpack/travian/mainPage/css_ltr/imports.css',
    $root . '/assets/gpack/travian/mainPage/css_rtl/imports.css',
];

// renders the select-all wrapper as #markAll in both the top and bottom footer of the
// shared inbox/sent/archive list, with the two checkboxes keeping distinct IDs.
if ($list === false
    || substr_count($list, '<div id="markAll">') !== 1
    || !str_contains($list, 'foreach ([1, 2] as $position)')
    || !str_contains($list, 'id="sAll<?= $position === 1 ? 1 : 2; ?>"')
    || preg_match('/markAll(Inbox|Sent|Archive)(Top|Bottom)/', $list) === 1) {
    failMessageSelectionIdContract('The shared message list must render the #markAll wrapper in both footers.');
}

foreach ($stylesheets as $stylesheetPath) {
    $stylesheet = file_get_contents($stylesheetPath);
    if ($stylesheet === false || !str_contains($stylesheet, 'div.messages #markAll')) {
        failMessageSelectionIdContract('Message select-all controls must retain their direction-aware styling.');
    }
}

echo "Message selection ID contract passed.\n";
