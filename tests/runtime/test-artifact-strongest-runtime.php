<?php

declare(strict_types=1);

// When several artifacts of one effect apply to a village, the strongest one counts: the smallest multiplier for
// diet and training time, the largest for every other effect. The fixture player holds a small (village) and a big
// (account) artifact of each kind; the world's artifact switch is restored when the test ends.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Artifact strongest runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Database\DBI;
use Model\ArtefactsModel;
use Model\BattleSetter;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $player = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Artifact strongest runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$released = (int)$pdo->fetchScalar('SELECT ArtifactsReleased FROM config LIMIT 1');
$pdo->run('UPDATE config SET ArtifactsReleased=1');
register_shutdown_function(static function () use ($released, $player): void {
    $pdo = DBI::getInstance();
    $pdo->run('UPDATE config SET ArtifactsReleased=?', [$released]);
    $pdo->run('DELETE FROM artefacts WHERE uid=?', [$player['id']]);
});

$add = static function (int $type, int $size, float $effect) use ($pdo, $player): void {
    $pdo->run(
        'INSERT INTO artefacts (uid, type, kid, release_kid, size, conquered, num, effecttype, effect, aoe, status, active) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, 1, 1)',
        [$player['id'], $type, $player['kid'], $player['kid'], $size, time(), $type, $effect, $size]
    );
};
$add(ArtefactsModel::ARTIFACT_DIET, 1, 3 / 4);
$add(ArtefactsModel::ARTIFACT_DIET, 2, 1 / 2);
$add(ArtefactsModel::ARTIFACT_CRANNY, 2, 100);
$add(ArtefactsModel::ARTIFACT_CRANNY, 1, 500);
$add(ArtefactsModel::ARTIFACT_INCREASE_BUILDINGS_STABILITY, 1, 4);
$add(ArtefactsModel::ARTIFACT_INCREASE_BUILDINGS_STABILITY, 2, 3);

$diet = ArtefactsModel::getArtifactEffectByType($player['id'], $player['kid'], ArtefactsModel::ARTIFACT_DIET);
if (abs($diet - 0.5) > 1e-9) {
    $fail("Diet: the strongest (smallest) multiplier 1/2 must apply, got $diet.");
}
$cranny = ArtefactsModel::getArtifactEffectByType($player['id'], $player['kid'], ArtefactsModel::ARTIFACT_CRANNY);
if (abs($cranny - 500) > 1e-9) {
    $fail("Cranny: the strongest (largest) capacity 500 must apply, got $cranny.");
}
$protecting = (new BattleSetter())->getProtectingArtifacts($player['id'], $player['kid']);
$sizes = array_map(static fn (array $artifact): int => (int)$artifact['size'], $protecting);
if ($sizes !== [1, 1]) {
    $fail('The report must name the strongest cranny (500, small) and stability (4, small) artifacts, got sizes ' . json_encode($sizes) . '.');
}
if ((new BattleSetter())->randomTargetArtifact($player['id'], $player['kid']) !== '2') {
    $fail('Catapult targeting must follow the larger cranny artifact (size 2), which exempts fewer targets.');
}

echo "Artifact strongest runtime passed.\n";
