<?php

declare(strict_types=1);

/**
 * Contract: player-facing getButton / submit controls use textButtonV1, not bare green/gold.
 */

function failTextButtonV1CallersContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$paths = [
    'game/include/Controller/Build/AcademyCtrl.php',
    'game/include/Controller/Build/SmithyCtrl.php',
    'game/include/Controller/Build/BreweryCtrl.php',
    'game/include/Controller/Build/TownhallCtrl.php',
    'game/include/Controller/AllianceForum.php',
    'game/include/Controller/BannedCtrl.php',
    'game/include/Controller/MapCtrl.php',
    'game/include/Controller/RallyPoint/RallyPoint.php',
    'game/include/Controller/RallyPoint/RallyPointCtrl.php',
    'game/include/resources/Templates/ajax/vacationConfirmation.php',
    'game/include/resources/Templates/alliance/bonuses/donateForm.php',
    'game/include/resources/Templates/alliance/bonuses/donateWithGold.php',
];

foreach ($paths as $relative) {
    $contents = file_get_contents($root . '/' . $relative);
    if ($contents === false) {
        failTextButtonV1CallersContract("Unable to read {$relative}");
    }
    if (preg_match('/["\']class["\']\s*=>\s*["\']green(?:\s|["\'])/', $contents) === 1
        || preg_match('/["\']class["\']\s*=>\s*["\']gold(?:\s|["\'])/', $contents) === 1) {
        failTextButtonV1CallersContract("{$relative} still assigns bare green/gold button classes without textButtonV1.");
    }
    if (!str_contains($contents, 'textButtonV1')) {
        failTextButtonV1CallersContract("{$relative} must use textButtonV1.");
    }
}

echo "TextButtonV1 callers contract passed.\n";
