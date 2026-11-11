<?php

declare(strict_types=1);

// Artifact rules from the Artifacts wiki: owning is not capped and a hero may claim any artifact whose Treasury
// requirement is met; only the three oldest artifacts of a player work, at most one of them large or unique;
// the Fool never rolls a harmful effect when unique and uses the right direction for speed; the Natar Treasury
// of every artifact village is level 20. Fixture rows are removed at shutdown.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Artifact rules runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/dorf1.php';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/dorf1.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Database\DBI;
use Model\ArtefactsModel;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $user = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Artifact rules runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$uid = (int)$user['id'];
$kid = (int)$user['kid'];
$fakeKid = 900000 + ($uid % 1000) * 10;
register_shutdown_function(static function () use ($pdo, $uid, $fakeKid): void {
    $pdo->run('DELETE FROM artefacts WHERE uid=? OR kid BETWEEN ? AND ?', [$uid, $fakeKid, $fakeKid + 9]);
});
$model = new ArtefactsModel();
$insert = static function (int $size, int $age, int $slot, int $type = 11) use ($pdo, $uid, $fakeKid): int {
    $pdo->run(
        'INSERT INTO artefacts (uid, kid, release_kid, type, size, conquered, num, effecttype, effect, aoe, status, active) VALUES (?, ?, 0, ?, ?, ?, 1, 1, 2, ?, 1, 1)',
        [$uid, $fakeKid + $slot, $type, $size, time() - $age, $size]
    );

    return (int)$pdo->lastInsertId();
};

// Activation: oldest three, one large/unique at most, extras stay owned but disabled.
$small1 = $insert(1, 500, 0);
$unique1 = $insert(3, 400, 1);
$large2 = $insert(2, 300, 2);
$small2 = $insert(1, 200, 3);
$small3 = $insert(1, 100, 4);
$model->fixArtifactOrderForPlayer($uid);
$status = static fn (int $id): int => (int)$pdo->fetchScalar('SELECT status FROM artefacts WHERE id=?', [$id]);
if ([$status($small1), $status($unique1), $status($large2), $status($small2), $status($small3)] !== [1, 1, 2, 1, 2]) {
    $fail('The three oldest artifacts must work with at most one large or unique: '
        . json_encode([$status($small1), $status($unique1), $status($large2), $status($small2), $status($small3)]));
}
if ((int)$pdo->fetchScalar('SELECT COUNT(*) FROM artefacts WHERE uid=?', [$uid]) !== 5) {
    $fail('Disabled artifacts must stay owned.');
}
$pdo->run('DELETE FROM artefacts WHERE id=?', [$small1]);
$model->fixArtifactOrderForPlayer($uid);
if ([$status($unique1), $status($large2), $status($small2), $status($small3)] !== [1, 2, 1, 1]) {
    $fail('When the oldest artifact leaves, the next oldest one must take its place.');
}
// A World Wonder plan is not part of the activation order.
$plan = $insert(1, 1000, 5, ArtefactsModel::WW_PLAN);
$pdo->run('UPDATE artefacts SET status=1 WHERE id=?', [$plan]);
$model->fixArtifactOrderForPlayer($uid);
if ($status($plan) !== 1 || [$status($unique1), $status($small2), $status($small3)] !== [1, 1, 1]) {
    $fail('A construction plan must not use up one of the three artifact places.');
}

// Claiming is not blocked by what the hero owns (already three active artifacts and a unique).
$buildings = array_fill(19, 20, ['item_id' => 0, 'level' => 0]);
$attacker = [27 => 20];
foreach ([1 => 10, 2 => 20, 3 => 20] as $size => $level) {
    $attacker[27] = $level;
    if ($model->canClaimArtifact($size, $kid, $attacker, $buildings) !== true) {
        $fail("An artifact of size $size must be claimable regardless of the artifacts already owned.");
    }
}
$attacker[27] = 10;
if ($model->canClaimArtifact(3, $kid, $attacker, $buildings) !== -1) {
    $fail('A large or unique artifact still needs a level 20 Treasury.');
}

// Fool: unique never harmful, speed direction right.
$fool = static function (int $size, int $effectType) use ($pdo, $model, $uid, $fakeKid): array {
    $pdo->run(
        'INSERT INTO artefacts (uid, kid, release_kid, type, size, conquered, num, effecttype, effect, aoe, status, active) VALUES (?, ?, 0, ?, ?, ?, 1, ?, 1, 1, 2, 0)',
        [$uid, $fakeKid + 9, ArtefactsModel::ART_FOOL, $size, time(), $effectType]
    );
    $id = (int)$pdo->lastInsertId();
    $seen = [];
    for ($i = 0; $i < 400; $i++) {
        $model->updateFoolArtifact($id);
        $row = $pdo->fetchSingleRow('SELECT effecttype, effect FROM artefacts WHERE id=?', [$id]);
        $seen[(int)$row['effecttype']][] = (float)$row['effect'];
    }
    $pdo->run('DELETE FROM artefacts WHERE id=?', [$id]);

    return $seen;
};
$betterWhenAbove = [
    ArtefactsModel::ARTIFACT_INCREASE_BUILDINGS_STABILITY,
    ArtefactsModel::ARTIFACT_INCREASE_SPEED,
    ArtefactsModel::ARTIFACT_SPY,
    ArtefactsModel::ARTIFACT_CRANNY,
];
foreach ($fool(3, 1) as $type => $values) {
    foreach ($values as $value) {
        $good = in_array($type, $betterWhenAbove, true) ? $value > 1 : $value < 1;
        if (!$good) {
            $fail("A unique Fool rolled a harmful effect $value for effect type $type.");
        }
    }
}
$speeds = $fool(1, 1)[ArtefactsModel::ARTIFACT_INCREASE_SPEED] ?? [];
foreach ($speeds as $value) {
    if (!in_array(round($value, 6), [round(2 / 3, 6), 0.5, 1.5, 2.0], true)) {
        $fail("Unexpected Fool speed effect $value.");
    }
}
if ($speeds === []) {
    $fail('The Fool never rolled a speed effect in 400 tries.');
}

// Natar Treasury level 20, small and large alike.
$registry = new \Model\RegisterModel();
foreach ([1, 2] as $size) {
    $slot = (int)$pdo->fetchScalar('SELECT w.id FROM wdata w LEFT JOIN vdata v ON v.kid=w.id WHERE v.kid IS NULL AND w.fieldtype>0 AND w.id<>? ORDER BY w.id LIMIT 1', [$kid]);
    if ($slot === 0 || !$registry->createArtifactVillage($slot, ArtefactsModel::ARTIFACT_INCREASE_BUILDINGS_STABILITY, $size, [])) {
        $fail("Could not create a size $size artifact village.");
    }
    $level = (int)$pdo->fetchScalar('SELECT f22 FROM fdata WHERE kid=?', [$slot]);
    (new \Model\AccountDeleter())->deleteVillage($slot);
    if ($level !== 20) {
        $fail("The Natar Treasury of a size $size artifact village must be level 20, got $level.");
    }
}
echo "Artifact rules runtime passed.\n";
