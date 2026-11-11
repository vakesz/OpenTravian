#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: #sidebarBoxVillageList row chrome matches (drag handle SVG, iconAndNameWrapper, incoming-troops tooltip payload).
 */

function failVillageListItemChrome(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$svg = (string) file_get_contents($root . '/game/include/resources/Svg/svg.php');
$view = (string) file_get_contents($root . '/game/include/resources/View/GameView.php');
$layoutButton = (string) file_get_contents($root . '/game/include/Controller/Ajax/tooltip/layoutButton.php');

$expectedHandle =
    '<svg viewBox="0 0 7 13" class="handle"><circle cx="1.5" cy="1.5" r="1.5"></circle><circle cx="5.5" cy="1.5" r="1.5"></circle><circle cx="1.5" cy="6.5" r="1.5"></circle><circle cx="5.5" cy="6.5" r="1.5"></circle><circle cx="1.5" cy="11.5" r="1.5"></circle><circle cx="5.5" cy="11.5" r="1.5"></circle></svg>';

if (!str_contains($svg, $expectedHandle)) {
    failVillageListItemChrome('village_drag_n_drop must use the six-dot drag handle SVG.');
}

foreach ([
    '<div class="iconAndNameWrapper">',
    '<div class="iconAndNameWrapper"><span class="name" data-group-name>',
    "'villageIds' => [(int) \$row['kid']]",
    '<span class="name" data-did="',
    'data-load-tooltip="incomingTroops"',
    '$linkHref = $active === \'active\'',
] as $needle) {
    if (!str_contains($view, $needle)) {
        failVillageListItemChrome("GameView village list missing {$needle}.");
    }
}

if (str_contains($view, 'draggable="true"') && str_contains($view, 'listEntry village')) {
    failVillageListItemChrome('Village list rows must not use legacy draggable="true" on .listEntry.village.');
}

if (str_contains($view, "json_encode(['villageId' =>")) {
    failVillageListItemChrome('Incoming-troops tooltip must post villageIds[], not villageId.');
}

if (!str_contains($layoutButton, 'resolveIncomingTroopsVillageId')
    || !str_contains($layoutButton, "\$_POST['villageIds']")) {
    failVillageListItemChrome('layoutButton must resolve incoming-troops villageIds from the reference tooltip payload.');
}

echo "Village list item chrome contract passed.\n";
