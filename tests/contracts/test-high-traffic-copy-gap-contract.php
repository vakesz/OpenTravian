#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: active high-traffic copy keeps these English strings:
 * - js/en-US/allianz.json
 * - js/en-US/berichte.json
 * - js/en-US/gid17.json
 * - js/en-US/spieler.json
 */

function failHighTrafficCopyGapContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$translation = file_get_contents($root . '/game/include/resources/Translation/en-US.php');
$allianceController = file_get_contents($root . '/game/include/Controller/AllianceCtrl.php');
$allianceForum = file_get_contents($root . '/game/include/Controller/AllianceForum.php');
$allianceEditTopic = file_get_contents($root . '/game/include/resources/Templates/alliance/editTopic.php');
$allianceForumEdit = file_get_contents($root . '/game/include/resources/Templates/alliance/forum_edit.php');
$reportsController = file_get_contents($root . '/game/include/Controller/ReportsCtrl.php');
$reportsNewVillage = file_get_contents($root . '/game/include/resources/Templates/reports/newVillage.php');
$marketController = file_get_contents($root . '/game/include/Controller/Build/MarketPlaceCtrl.php');
$marketPrepare = file_get_contents($root . '/game/include/Controller/Ajax/marketplace/prepare.php');
$optionsController = file_get_contents($root . '/game/include/Controller/OptionCtrl.php');
$optionsGame = file_get_contents($root . '/game/include/resources/Templates/options/Game.php');

if (in_array(false, [
    $translation,
    $allianceController,
    $allianceForum,
    $allianceEditTopic,
    $allianceForumEdit,
    $reportsController,
    $reportsNewVillage,
    $marketController,
    $marketPrepare,
    $optionsController,
    $optionsGame,
], true)) {
    failHighTrafficCopyGapContract('Unable to read the high-traffic copy sources.');
}

foreach ([
    "\$Definition['Alliance']['The player x does`t exists'] = 'The player %s does not exist.';",
    "\$Definition['Alliance']['no thread created'] = 'No thread has been created yet.';",
    "\$Definition['Alliance']['edit topic'] = 'edit topic';",
    "\$Definition['Alliance']['ally_list_headline'] = 'Open for more alliances:';",
    "\$Definition['Reports']['newVillageCreatedSuccussFully'] = 'New village was settled successfully at';",
    "\$Definition['Reports']['randomTargetsWereChosen'] = 'Random target was selected.';",
    "\$Definition['Reports']['x reinforced y'] = '%s reinforces %s';",
    "\$Definition['Reports']['None of your spies returned'] = 'None of your scouts returned.';",
    "\$Definition['MarketPlace']['not enough merchants'] = 'Not enough merchants.';",
    "\$Definition['MarketPlace']['not enough resources'] = 'Not enough resources.';",
    "\$Definition['Options']['language settings'] = 'Language settings';",
] as $expected) {
    if (!str_contains($translation, $expected)) {
        failHighTrafficCopyGapContract('Missing copy: ' . $expected);
    }
}

foreach ([
    [$allianceController, "T(\"Alliance\", 'The player x does`t exists')"],
    [$allianceForum, '"no thread created"'],
    [$allianceEditTopic, 'T("Alliance", "edit topic")'],
    [$allianceForumEdit, 'T("Alliance", "ally_list_headline")'],
    [$reportsController, "T('Reports', 'x reinforced y')"],
    [$reportsController, "T('Reports', 'randomTargetsWereChosen')"],
    [$reportsController, "T('Reports', 'None of your spies returned')"],
    [$reportsNewVillage, 'T("Reports", "newVillageCreatedSuccussFully")'],
    [$marketController . $marketPrepare, 'T("MarketPlace", "not enough resources")'],
    [$marketController . $marketPrepare, "T('MarketPlace', 'not enough merchants')"],
    [$optionsController, 'T("Options", "Name black listed")'],
    [$optionsGame, '"language settings"'],
] as [$source, $needle]) {
    if (!str_contains($source, $needle)) {
        failHighTrafficCopyGapContract('The covered copy no longer has an active rendering path: ' . $needle);
    }
}

echo "High-traffic copy-gap contract passed.\n";
