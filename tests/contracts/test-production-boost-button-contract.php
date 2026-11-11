<?php

declare(strict_types=1);

function failProductionBoostButtonContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$dorf1Controller = file_get_contents($root . '/game/include/Controller/ResourceFieldsCtrl.php');
$client = file_get_contents($root . '/game/deployment/public/game-client.js');

if ($dorf1Controller === false || $client === false) {
    failProductionBoostButtonContract('Production-boost button sources must be readable.');
}

// Opens the V5 payment wizard advantages tab from the +25% control.
$v1Class = 'textButtonV1 gold productionBoostButton';
$openWizard = "Travian.React.openPaymentWizard({activeTab:'advantages'}); return false;";
if (substr_count($dorf1Controller, $v1Class) !== 2
    || str_contains($dorf1Controller, 'textButtonV2')
    || !str_contains($dorf1Controller, "'version' => 'textButtonV1'")
    || !str_contains($dorf1Controller, $openWizard)
    || str_contains($dorf1Controller, 'productionBoostDialog')) {
    failProductionBoostButtonContract('The Dorf1 production-bonus opener must use V1 chrome and openPaymentWizard(advantages) only.');
}

if (!str_contains($client, 'Travian.React.openPaymentWizard = function')
    || !str_contains($client, 'startPaymentWizard')) {
    failProductionBoostButtonContract('game-client.js must expose Travian.React.openPaymentWizard onto the V5 startPaymentWizard path.');
}

echo "Production-boost button contract passed.\n";
