<?php

declare(strict_types=1);

function failMessageAddressBookApiContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$compose = file_get_contents($root . '/game/include/resources/Templates/messages/write.php');
$messages = file_get_contents($root . '/game/include/Controller/MessagesCtrl.php');
$addressBook = file_get_contents($root . '/game/include/Controller/Ajax/addressbook.php');
$addressBookTemplate = file_get_contents($root . '/game/include/resources/Templates/ajax/addressbook.php');
$routes = file_get_contents($root . '/game/deployment/include/api_legacy_routes.php');

if (in_array(false, [$compose, $messages, $addressBook, $addressBookTemplate, $routes], true)) {
    failMessageAddressBookApiContract('Message Addressbook API sources must be readable.');
}

foreach ([
    'id="adbook"',
    'onclick="Travian.Game.AddressBook.show()"',
] as $needle) {
    if (!str_contains($compose, $needle)) {
        failMessageAddressBookApiContract('Compose must open the native Addressbook API dialog: ' . $needle);
    }
}

foreach ([
    "'addressbook' => [null, 'addressbook']",
    "'addressbook/accept' => ['addressbook', 'accept']",
    "'addressbook/add' => ['addressbook', 'add']",
    "'addressbook/delete' => ['addressbook', 'delete']",
] as $needle) {
    if (!str_contains($routes, $needle)) {
        failMessageAddressBookApiContract('Addressbook API route missing: ' . $needle);
    }
}

foreach ([
    'id="adressbook"',
    'Travian.Game.Messages.addRecipient(',
    'Travian.Game.AddressBook.hide(); return false;',
] as $needle) {
    if (!str_contains($addressBook . $addressBookTemplate, $needle)) {
        failMessageAddressBookApiContract('Addressbook dialog must retain the native recipient interaction: ' . $needle);
    }
}

foreach ([
    'renderAddressBook()',
    'showAddressBook',
    'sbmtype',
    'sbmvalue',
    'addfriends',
    "window.location.href = '/messages/write?sbmtype=",
] as $legacy) {
    if (str_contains($messages, $legacy)) {
        failMessageAddressBookApiContract('Messages controller must not retain the obsolete Addressbook fallback: ' . $legacy);
    }
}

fwrite(STDOUT, "Message Addressbook API contract passed.\n");
