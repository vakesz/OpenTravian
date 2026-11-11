<?php

declare(strict_types=1);

use Game\AdminUi;
use Game\Hero\HeroItems;

/** @var array $params */
$btypes = [
    1 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15],
    2 => [82, 83, 84, 85, 86, 87, 88, 89, 90, 91, 92, 93],
    3 => [61, 62, 63, 64, 65, 66, 67, 68, 69, 73, 74, 75, 76, 77, 78, 79, 80, 81],
    4 => [
        16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47,
        48, 49, 50, 51, 52, 53, 54, 55, 56, 57, 58, 59, 60, 115, 116, 117, 118, 119, 120, 121, 122, 123, 124, 125, 126, 127, 128, 129,
        130, 131, 132, 133, 134, 135, 136, 137, 138, 139, 140, 141, 142, 143, 144,
    ],
    5 => [94, 95, 96, 97, 98, 99, 100, 101, 102],
    6 => [103, 104, 105],
    7 => [112], 8 => [113], 9 => [114], 10 => [107], 11 => [106], 12 => [108], 13 => [110], 14 => [109], 15 => [111],
];
$names = [
    1 => 'Helmets', 2 => 'Armors and Shields', 3 => 'Maps and flags', 4 => 'Swords and spears and lances',
    5 => 'Boots', 6 => 'Horses', 7 => 'Small bandage', 8 => 'Bandage', 9 => 'Cage', 10 => 'Inscription',
    11 => 'Ointment', 12 => 'Water Bucket', 13 => 'Book of Knowledge', 14 => 'Law tablet', 15 => 'Artwork',
];
$itemOptions = [];
$heroItems = new HeroItems();
foreach ($btypes as $btype => $types) {
    $group = $names[$btype] ?? ('Type ' . $btype);
    foreach ($types as $type) {
        $item = $heroItems->getHeroItemProperties($btype, $type);
        $itemOptions[$btype . ':' . $type] = $group . ': ' . ($item['name'] ?? ($btype . ':' . $type));
    }
}
$repeatOptions = [];
foreach ([1, 2, 3, 4, 5, 10, 15] as $n) {
    $repeatOptions[(string) $n] = (string) $n;
}
$timeOptions = [
    '60' => '1 min', '120' => '2 min', '300' => '5 min', '600' => '10 min', '1800' => '30 min',
    '3600' => '1 h', '7200' => '2 h', '21600' => '6 h', '28800' => '8 h', '43200' => '12 h', '86400' => '24 h',
];
$notice = !empty($params['result']) ? AdminUi::notice('Item added to the auction.', 'success') : '';

$body = $notice
    . '<form method="post" action="admin.php?action=heroAuction">'
    . getCheckerInput()
    . '<input type="hidden" name="section" value="addBan">'
    . AdminUi::select('item', 'Item', $itemOptions)
    . AdminUi::input('package_amount', 'Package amount', '0', ['type' => 'number', 'min' => '0'])
    . AdminUi::input('silver', 'Silver', '500', ['type' => 'number', 'min' => '1'])
    . AdminUi::select('repeat', 'Repeat times', $repeatOptions, '1')
    . AdminUi::select('time', 'Duration', $timeOptions, '3600')
    . AdminUi::button('Add to auction', 'green')
    . '</form>';

echo AdminUi::page(
    'Hero auction',
    'List a hero item on the auction house for Multihunter stock.',
    $body
);
