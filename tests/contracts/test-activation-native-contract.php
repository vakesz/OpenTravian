<?php

declare(strict_types=1);

function failActivationNativeContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/game/include/Controller/ActivateCtrl.php');
$wizard = file_get_contents($root . '/game/include/resources/Templates/activation/wizard.php');
$bundles = [
    $root . '/game/deployment/public/game-client.js',
];

if (!is_string($controller) || !is_string($wizard)) {
    failActivationNativeContract('The native activation sources must be readable.');
}

if (str_contains($controller, 'useNewActivation')
    || str_contains($controller, "PHPBatchView::render('activation/race'")
    || str_contains($controller, 'new PHPBatchView("activation/sector")')
    || !str_contains($controller, "PHPBatchView::render('activation/wizard'")) {
    failActivationNativeContract('Activation must render only the maintained Gpack flow.');
}

if (!str_contains($wizard, 'foreach ($tribes as $index => $id)')
    || !str_contains($wizard, 'new Travian.Game.Activation()')
    || str_contains($wizard, 'ActivationWithSectors')) {
    failActivationNativeContract('The activation wizard must render playable tribes and initialize the shipped activation component.');
}

if (!str_contains($wizard, 'class="activationWrapper activateWithGameLobbyAccount')
    || !str_contains($wizard, '<form class="formV2" method="post" id="activate"')
    || !str_contains($wizard, 'selectSector')) {
    failActivationNativeContract('The maintained activation wizard must include the starting-position step.');
}

foreach (['activation/race.php', 'activation/sector.php', 'activationNew/race.php', 'activationNew/sector.php', 'activationNew/confirmation.php', 'activationNew/wizard.php'] as $obsoleteTemplate) {
    if (is_file($root . '/game/include/resources/Templates/' . $obsoleteTemplate)) {
        failActivationNativeContract("Obsolete activation template {$obsoleteTemplate} must not remain alongside the current flow.");
    }
}

foreach ($bundles as $bundlePath) {
    $bundle = file_get_contents($bundlePath);
    if (!is_string($bundle) || !str_contains($bundle, 'Travian.Game.Activation = function')) {
        failActivationNativeContract('Both graphics bundles must ship the native activation component.');
    }
}

echo "Native activation contract passed.\n";
