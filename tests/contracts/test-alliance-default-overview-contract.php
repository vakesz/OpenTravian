<?php

declare(strict_types=1);

/**
 * Contract: own-alliance /alliance defaults to Overview (tab 7), matching.
 */

function failAllianceDefaultOverviewContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$ctrl = file_get_contents($root . '/game/include/Controller/AllianceCtrl.php');
$session = file_get_contents($root . '/game/include/Core/Session.php');
if ($ctrl === false || $session === false) {
    failAllianceDefaultOverviewContract('Unable to read alliance default sources.');
}

if (!str_contains($session, "'alliance' => 7")) {
    failAllianceDefaultOverviewContract('Session favorTabs default for alliance must be Overview tab 7.');
}

if (str_contains($session, "'alliance' => 1,")) {
    failAllianceDefaultOverviewContract('Session must not default alliance favorite to Profile tab 1.');
}

foreach ([
    '$isMyAlliance ? 7 : 1',
    "getFavoriteTab('alliance')",
] as $needle) {
    if (!str_contains($ctrl, $needle)) {
        failAllianceDefaultOverviewContract('AllianceCtrl missing own-alliance Overview default: ' . $needle);
    }
}

fwrite(STDOUT, "Alliance default overview contract passed.\n");
