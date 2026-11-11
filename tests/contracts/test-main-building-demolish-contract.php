<?php

declare(strict_types=1);

/**
 * Contract: Main Building demolish keeps a native form POST for timed
 * demolition and InstantDemolition → demolishNow that posts {gid|slotId}
 * as additionalData (no nested additionalData wrapper, no unused GoldHelper
 * demolishComplete shim). Dialog matches #demolishNowDialog + Use.
 */

function failMainBuildingDemolishContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$template = file_get_contents($root . '/game/include/resources/Templates/build/mainBuilding.php');
$premium = file_get_contents($root . '/game/include/Controller/Ajax/premium/instantDemolition.php');
$goldHelper = file_get_contents($root . '/game/include/Game/GoldHelper.php');
$village = file_get_contents($root . '/game/include/Core/Village.php');
$mainCtrl = file_get_contents($root . '/game/include/Controller/Build/MainBuildingCtrl.php');
$popup = file_get_contents($root . '/game/include/resources/Templates/gold/demolishNowPopup.php');
$en = file_get_contents($root . '/game/include/resources/Translation/en-US.php');
$crypt = file_get_contents($root . '/game/deployment/public/game-client.js');

foreach ([$template, $premium, $goldHelper, $village, $mainCtrl, $popup, $en, $crypt] as $source) {
    if ($source === false) {
        failMainBuildingDemolishContract('Unable to read Main Building demolish sources.');
    }
}

foreach ([
    'form class="demolish_building"',
    'method="post"',
    'name="abriss"',
    'type="submit" value="Demolish"',
    'name="action" value="demolishBuilding"',
    'function getGid()',
    'function getDemolishData()',
    "return {gid: jQuery('#demolish').val()};",
] as $needle) {
    if (!str_contains($template, $needle)) {
        failMainBuildingDemolishContract('Main Building must keep a native demolish form and flat getGid()/getDemolishData() payload.');
    }
}

if (!str_contains($goldHelper, 'InstantDemolition.confirmDialog(getDemolishData())')) {
    failMainBuildingDemolishContract('Demolish completely must open InstantDemolition.confirmDialog(getDemolishData()).');
}

if (str_contains($template, 'additionalData: {gid')) {
    failMainBuildingDemolishContract('getGid must not nest additionalData; wayOfPayment already wraps the callback result.');
}

if (!str_contains($mainCtrl, "\$village->demolishBuilding((int)\$_POST['abriss'])")) {
    failMainBuildingDemolishContract('Timed demolish must apply the posted building field from abriss.');
}

foreach ([
    "\$_POST['slotId']",
    "\$village->demolishBuilding(\$target['slotId'], true)",
    '$db->begin_transaction()',
    'GoldHelper::decreaseGold(',
    'GoldHelper::syncSessionGold($uid)',
] as $needle) {
    if (!str_contains($premium, $needle)) {
        failMainBuildingDemolishContract('Instant demolish must read the posted slotId and apply the demolish inside one gold purchase transaction.');
    }
}

if (str_contains($goldHelper, 'function demolishComplete')) {
    failMainBuildingDemolishContract('Remove the unused GoldHelper::demolishComplete shim; premium/instantDemolition owns instant demolish.');
}

if (substr_count($village, 'if ($complete) {') > 1
    && str_contains($village, 'if ($complete) {' . "\n" . '            $demolishTime = 0;')) {
    failMainBuildingDemolishContract('Village::demolishBuilding must not keep dead complete-queue branches.');
}

if (!str_contains($template, 'action="/build.php?gid=15"')) {
    failMainBuildingDemolishContract('Demolish form action must be root-absolute under <base href="/">.');
}

if (!str_contains($popup, 'id="demolishNowDialog"')
    || !str_contains($popup, "T('demolishNowPopup', 'Use')")
    || !str_contains($popup, 'InstantDemolition.confirm()')
    || str_contains($popup, 'Redeem')
    || str_contains($popup, 'goldFeatureConfirmation')) {
    failMainBuildingDemolishContract('demolishNowPopup must match #demolishNowDialog + Use [coins].');
}

if (!str_contains($en, "\$Definition['demolishNowPopup']['Use'] = 'Use'")
    || !str_contains($en, "Immediately demolish the selected building? It will be removed from your village.")
    || str_contains($en, "\$Definition['demolishNowPopup']['Redeem']")) {
    failMainBuildingDemolishContract('en-US demolishNowPopup copy must match Use + Immediate demolish wording.');
}

if (!str_contains($crypt, 'buyWithConfirmation("instant-demolition")')
    || !str_contains($crypt, 'confirmDialog: function')
    || str_contains($crypt, 'demolish-now-popup')) {
    failMainBuildingDemolishContract('game-client.js must expose PremiumFeature.InstantDemolition as the two-step instant-demolition purchase.');
}

echo "Main Building demolish contract passed.\n";
