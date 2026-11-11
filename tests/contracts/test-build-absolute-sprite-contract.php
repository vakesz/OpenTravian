<?php

declare(strict_types=1);

/**
 * Contract: player-facing unit/artefact/building sprites in build/map/village-statistics/rally
 * queues and tables use absolute /img/spacer.gif (not relative img/spacer.gif).
 */

function failBuildAbsoluteSpriteContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);

$mustContainAbsolute = [
    'AcademyCtrl' => $root . '/game/include/Controller/Build/AcademyCtrl.php',
    'SmithyCtrl' => $root . '/game/include/Controller/Build/SmithyCtrl.php',
    'TreasuryCtrl' => $root . '/game/include/Controller/Build/TreasuryCtrl.php',
    'TreasuryArtefactShow' => $root . '/game/include/resources/Templates/build/building/TreasuryArtefactShow.php',
    'buildingReadyWrapper' => $root . '/game/include/resources/Templates/build/buildingReadyWrapper.php',
    'dorf3 armory' => $root . '/game/include/resources/Templates/village-statistics/armory.php',
    'RallyPointHTML' => $root . '/game/include/Controller/RallyPoint/RallyPointHTML.php',
    'tileDetails' => $root . '/game/include/Controller/Ajax/map/tileDetails.php',
    'MapCtrl' => $root . '/game/include/Controller/MapCtrl.php',
    'VillageStatisticsCtrl' => $root . '/game/include/Controller/VillageStatisticsCtrl.php',
];

foreach ($mustContainAbsolute as $label => $path) {
    $contents = file_get_contents($path);
    if ($contents === false) {
        failBuildAbsoluteSpriteContract("Unable to read {$label}.");
    }
    if (!str_contains($contents, 'src="/img/spacer.gif"')) {
        failBuildAbsoluteSpriteContract("{$label} must use src=\"/img/spacer.gif\".");
    }
}

$forbidRelativeUnit = [
    'AcademyCtrl' => $root . '/game/include/Controller/Build/AcademyCtrl.php',
    'SmithyCtrl' => $root . '/game/include/Controller/Build/SmithyCtrl.php',
    'TreasuryCtrl' => $root . '/game/include/Controller/Build/TreasuryCtrl.php',
    'TreasuryArtefactShow' => $root . '/game/include/resources/Templates/build/building/TreasuryArtefactShow.php',
    'buildingReadyWrapper' => $root . '/game/include/resources/Templates/build/buildingReadyWrapper.php',
    'dorf3 armory' => $root . '/game/include/resources/Templates/village-statistics/armory.php',
    'MapCtrl' => $root . '/game/include/Controller/MapCtrl.php',
];

foreach ($forbidRelativeUnit as $label => $path) {
    $contents = file_get_contents($path);
    if ($contents === false) {
        failBuildAbsoluteSpriteContract("Unable to read {$label}.");
    }
    if (str_contains($contents, 'src="img/spacer.gif"')) {
        failBuildAbsoluteSpriteContract("{$label} must not use relative src=\"img/spacer.gif\".");
    }
}

// Unit/bau emitters in these files must be absolute even if other icons stay relative.
$partial = [
    'RallyPointHTML' => [
        'path' => $root . '/game/include/Controller/RallyPoint/RallyPointHTML.php',
        'needle' => 'class="unit u\' . $u . \'" title="\' . T("Troops",',
        'absolute' => 'src="/img/spacer.gif"',
        'relativeForbiddenNearUnit' => 'class="unit u\' . $u . \'" title="\' . T("Troops",' . "\n" . '                    $u . \'.title\') . \'" alt="\' . T("Troops", $u . \'.title\') . \'" src="img/spacer.gif"',
    ],
    'tileDetails troops' => [
        'path' => $root . '/game/include/Controller/Ajax/map/tileDetails.php',
        'needle' => '<img class="unit u\' . $unit . \'"',
        'absolute' => '<img class="unit u\' . $unit . \'" src="/img/spacer.gif"',
    ],
    'VillageStatisticsCtrl bau' => [
        'path' => $root . '/game/include/Controller/VillageStatisticsCtrl.php',
        'needle' => 'class="bau"',
        'absolute' => 'class="bau" src="/img/spacer.gif"',
    ],
];

foreach ($partial as $label => $spec) {
    $contents = file_get_contents($spec['path']);
    if ($contents === false) {
        failBuildAbsoluteSpriteContract("Unable to read {$label}.");
    }
    if (!str_contains($contents, $spec['needle'])) {
        failBuildAbsoluteSpriteContract("{$label} expected marker missing.");
    }
    if (!str_contains($contents, $spec['absolute'])) {
        failBuildAbsoluteSpriteContract("{$label} must emit absolute sprite src.");
    }
    if (isset($spec['relativeForbiddenNearUnit']) && str_contains($contents, $spec['relativeForbiddenNearUnit'])) {
        failBuildAbsoluteSpriteContract("{$label} still has relative unit sprite.");
    }
    if (preg_match('/class="unit u[^"]*" src="img\/x\.gif"/', $contents)) {
        failBuildAbsoluteSpriteContract("{$label} still has relative class=\"unit\" sprite.");
    }
}

fwrite(STDOUT, "Build absolute sprite contract passed.\n");
