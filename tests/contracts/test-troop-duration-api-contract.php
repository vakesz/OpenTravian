<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = $root . '/game/include/Controller/Ajax/troop/distanceSpeedAndDuration.php';
$routes = $root . '/game/deployment/include/api_legacy_routes.php';
$source = file_get_contents($controller);

if ($source === false
    || !str_contains($source, 'final class distanceSpeedAndDuration extends AjaxBase')
    || !str_contains($source, "'durationString' =>")
    || !str_contains($source, "'offset' =>")
    || !str_contains($source, "\$_POST['origin']")
    || !str_contains($source, 'TimezoneHelper::getTimezone')
    || !str_contains($source, 'ArtefactsModel::ARTIFACT_INCREASE_SPEED')
    || !str_contains($source, 'VillageModel::getTournamentSquireLevel')
    || !str_contains($source, '$calculator->isCavalryOnly($unitIds)')
    || !str_contains($source, 'setHunnicMountedArmySpeedBonus')
    || !str_contains($source, 'if (array_sum($selectedTroops) > 1)')) {
    fwrite(STDERR, "Troop duration endpoint must use the shared movement-speed rules and client response contract.\n");
    exit(1);
}

$legacyRoutes = require $routes;
if (($legacyRoutes['routes']['POST troop/distanceSpeedAndDuration'] ?? null) !== ['troop', 'distance-speed-and-duration']) {
    fwrite(STDERR, "Troop duration API route is missing.\n");
    exit(1);
}
foreach ($legacyRoutes['patterns'] ?? [] as $pattern) {
    if (str_contains((string)($pattern['pattern'] ?? ''), 'distanceSpeedAndDuration')) {
        fwrite(STDERR, "The id-in-path troop duration route is obsolete.\n");
        exit(1);
    }
}

echo "Troop duration API contract passed.\n";
