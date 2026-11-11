<?php

declare(strict_types=1);

use Game\AdminUi;
use Game\Hero\HeroItems;

/** @var array $params */
$names = [
    1 => 'Helmets',
    2 => 'Armors and Shields',
    3 => 'Maps and flags',
    4 => 'Swords and spears and lances',
    5 => 'Boots',
    6 => 'Horses',
    7 => 'Small bandage',
    8 => 'Bandage',
    9 => 'Cage',
    10 => 'Inscription',
    11 => 'Ointment',
    12 => 'Water Bucket',
    13 => 'Book of Knowledge',
    14 => 'Law tablet',
    15 => 'Artwork',
];
$itemOptions = [];
$heroItems = new HeroItems();
foreach (($params['items_arr'] ?? []) as $btype => $types) {
    $group = $names[(int) $btype] ?? ('Type ' . $btype);
    foreach ($types as $type) {
        $item = $heroItems->getHeroItemProperties((int) $btype, (int) $type);
        $itemOptions[$btype . ':' . $type] = $group . ': ' . ($item['name'] ?? ($btype . ':' . $type));
    }
}
$notice = isset($params['result']) ? AdminUi::notice((string) $params['result'], 'success') : '';

$body = $notice
    . '<form method="post" action="admin.php?action=heroAddItem">'
    . getCheckerInput()
    . AdminUi::input('uid', 'Player id', (string) ($params['uid'] ?? ''), ['type' => 'number', 'required' => true])
    . AdminUi::select('item', 'Item', $itemOptions, (string) ($params['item'] ?? ''))
    . AdminUi::input('amount', 'Amount', (string) ($params['amount'] ?? '1'), ['type' => 'number', 'min' => '1'])
    . AdminUi::checkbox('delete', 'Remove instead of add', (int) ($params['delete'] ?? 0) === 1)
    . AdminUi::button('Apply', 'green')
    . '</form>';

echo AdminUi::page(
    'Add hero item',
    'Add or remove a hero inventory item for one player.',
    $body
);
