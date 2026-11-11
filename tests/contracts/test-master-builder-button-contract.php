<?php

declare(strict_types=1);

/**
 * Contract: the master-builder control keeps the modern button markup and
 * root-absolute self-describing action URL.  The interaction spends gold, so
 * this deliberately verifies the server/action contract without invoking it.
 */

function failMasterBuilderContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2) . '/game/include';
$build = file_get_contents($root . '/Controller/BuildCtrl.php');
$goldHelper = file_get_contents($root . '/Game/GoldHelper.php');
$dorf1 = file_get_contents($root . '/Controller/ResourceFieldsCtrl.php');
$dorf2 = file_get_contents($root . '/Controller/VillageCenterCtrl.php');

foreach ([$build, $goldHelper, $dorf1, $dorf2] as $source) {
    if ($source === false) {
        failMasterBuilderContract('Unable to read the master-builder sources.');
    }
}

foreach ([
    "'/dorf' . \$pageNamePostfix . '.php?id=' . \$fieldId",
    ". '&gid=' . \$item_id",
    ". '&action=build&checksum=' . \$session->getChecker()",
    ". '&buildmaster'",
    'getMasterBuilderButton($fieldId, $item_id',
] as $needle) {
    if (!str_contains($build, $needle)) {
        failMasterBuilderContract("BuildCtrl is missing the canonical master-builder URL fragment: {$needle}");
    }
}

foreach ([
    'this.disabled = true; window.location.href =',
    'value="' . "' . htmlspecialchars(\$label",
    '<i class="goldIcon"></i><span class="goldValue">',
    'coins="' . "' . \$gold",
    'version="textButtonV1"',
] as $needle) {
    if (!str_contains($goldHelper, $needle)) {
        failMasterBuilderContract("Master-builder button markup is missing {$needle}.");
    }
}
if (str_contains($goldHelper, "\$link . '&b=1'")) {
    failMasterBuilderContract('Master-builder buttons must no longer emit the legacy &b=1 URL.');
}

$request = file_get_contents($root . '/Game/BuildRequest.php');
if ($request === false) {
    failMasterBuilderContract('Unable to read BuildRequest.');
}
foreach ([
    "(\$_GET['action'] ?? null) !== 'build'",
    "array_key_exists('buildmaster', \$_GET)",
    "\$_GET['checksum']",
    "\$_GET['gid']",
    'Session::claimAction($checksum)',
    'upgradeBuilding($slot, $master',
] as $needle) {
    if (!str_contains($request, $needle)) {
        failMasterBuilderContract("BuildRequest must verify and process canonical build requests ({$needle}).");
    }
}
foreach (['ResourceFieldsCtrl' => $dorf1, 'VillageCenterCtrl' => $dorf2] as $name => $controller) {
    if (!str_contains($controller, 'BuildRequest::handle(')) {
        failMasterBuilderContract("{$name} must route build requests through BuildRequest.");
    }
    if (str_contains($controller, "\$_GET['a']") && str_contains($controller, "\$_GET['b']")) {
        failMasterBuilderContract("{$name} must not keep the legacy a/c/b build branch.");
    }
}

echo "Master builder button contract passed.\n";
