<?php

declare(strict_types=1);

/**
 * Contract: Hospital/Asclepeion heal rows reuse TroopBuildingTrainingWrapper with
 * a valid troopKey and no favourites (not empty `action troop troop`).
 */

function failHospitalHealWrapperContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$ctrl = file_get_contents($root . '/game/include/Controller/Build/HospitalCtrl.php');
$wrapper = file_get_contents($root . '/game/include/resources/Templates/build/building/TroopBuildingTrainingWrapper.php');

if ($ctrl === false || $wrapper === false) {
    failHospitalHealWrapperContract('Unable to read Hospital heal sources.');
}

foreach ([
    "'troopKey' => 't' . \$nr",
    "'showFavourite' => false",
    "'showLevel' => false",
    'src="/img/spacer.gif"',
] as $needle) {
    if (!str_contains($ctrl, $needle)) {
        failHospitalHealWrapperContract('HospitalCtrl missing: ' . $needle);
    }
}

foreach ([
    "\$vars['troopKey'] ?? ('t' . \$nr)",
    'array_key_exists(\'showLevel\', $vars)',
] as $needle) {
    if (!str_contains($wrapper, $needle)) {
        failHospitalHealWrapperContract('TroopBuildingTrainingWrapper missing: ' . $needle);
    }
}

fwrite(STDOUT, "Hospital heal wrapper contract passed.\n");
