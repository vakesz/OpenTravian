<?php

declare(strict_types=1);

function failMessagesWriteContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$translations = file_get_contents($root . '/game/include/resources/Translation/en-US.php');
$write = file_get_contents($root . '/game/include/resources/Templates/messages/write.php');

if ($translations === false || $write === false) {
    failMessagesWriteContract('Messages Write sources must be readable.');
}

if (!str_contains($translations, "\$Definition['Messages']['Addressbook'] = 'Addressbook'")) {
    failMessagesWriteContract('Messages Write must retain the Addressbook dialog title.');
}

if (!str_contains($write, 'T("Messages", "Addressbook")')) {
    failMessagesWriteContract('Messages Write must render its addressbook title from the canonical translation.');
}

fwrite(STDOUT, "Messages Write contract passed.\n");
