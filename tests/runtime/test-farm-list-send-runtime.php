<?php

declare(strict_types=1);

// Starts farm list raids as a throwaway fixture player: started raids become movements and take the troops
// along, every other target comes back with the reference's reason.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Farm list send runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/build.php';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/build.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Database\DBI;
use Core\Helper\WebService;
use Core\Session;
use Game\FarmList\FarmListSender;
use Model\MovementsModel;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $user = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Farm list send runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$kid = $user['kid'];
$natar = (int)$pdo->fetchScalar('SELECT kid FROM vdata WHERE owner=1 AND kid<>? ORDER BY kid LIMIT 1', [$kid]);
if ($natar === 0) {
    $fail('The fixture needs a Natar village.');
}
$pdo->run('UPDATE units SET u1=10, u2=0, u3=0, u4=0, u5=0, u6=0, u7=0, u8=0, u9=0, u10=0 WHERE kid=?', [$kid]);
$pdo->run('UPDATE fdata SET f39=1, f39t=16 WHERE kid=?', [$kid]);
$pdo->run('UPDATE users SET protection=0, vacationActiveTil=0 WHERE id=?', [$user['id']]);
$pdo->run('INSERT INTO farmlist (kid, owner, name, sortIndex) VALUES (?, ?, ?, 1)', [$kid, $user['id'], 'Send']);
$listId = (int)$pdo->fetchScalar('SELECT LAST_INSERT_ID()');
register_shutdown_function(static function () use ($pdo, $listId, $kid): void {
    $pdo->run('DELETE FROM raidlist WHERE lid=?', [$listId]);
    $pdo->run('DELETE FROM farmlist WHERE id=?', [$listId]);
    $pdo->run('DELETE FROM movement WHERE kid=?', [$kid]);
});
$slot = static function (int $target, int $troops, int $active = 1) use ($pdo, $listId): int {
    $pdo->run('INSERT INTO raidlist (lid, kid, distance, isActive, u1, u2, u3, u4, u5, u6, u7, u8, u9, u10) VALUES (?, ?, 1, ?, ?, 0, 0, 0, 0, 0, 0, 0, 0, 0)', [$listId, $target, $active, $troops]);

    return (int)$pdo->fetchScalar('SELECT LAST_INSERT_ID()');
};
$ok = $slot($natar, 4);
$tooMany = $slot($natar, 99);
$inactive = $slot($natar, 1, 0);
$own = $slot($kid, 1);
$_SESSION[WebService::fixSessionPrefix('uid')] = $user['id'];
$_SESSION[WebService::fixSessionPrefix('pw')] = $user['password'];
Session::getInstance();

$sender = new FarmListSender();
$text = static fn (string $key): string => (string)T('FarmList', $key);
$result = $sender->send([['id' => $listId, 'targets' => [$ok, $tooMany, $inactive, $own, 999999999]]]);
$targets = $result['lists'][0]['targets'];
$byId = array_column($targets, null, 'id');
if (isset($byId[$ok]['error'])) {
    $fail('The affordable target must start: ' . json_encode($byId[$ok]));
}
if (($byId[$tooMany]['error'] ?? null) !== $text('current_player_has_not_enough_troops')) {
    $fail('A target without enough troops must say so: ' . json_encode($byId[$tooMany] ?? null));
}
if (($byId[$inactive]['error'] ?? null) !== $text('errorDataChange') || ($byId[999999999]['error'] ?? null) !== $text('errorDataChange')) {
    $fail('An inactive or unknown target must be refused.');
}
if (($byId[$own]['error'] ?? null) !== $text('error_start_village')) {
    $fail('The own village must be refused.');
}
$movements = (int)$pdo->fetchScalar('SELECT COUNT(*) FROM movement WHERE kid=? AND to_kid=? AND attack_type=? AND u1=4', [$kid, $natar, MovementsModel::ATTACKTYPE_RAID]);
$pdo->run('UPDATE movement SET start_time=?, end_time=? WHERE kid=?', [(time() + 86400) * 1000, (time() + 86500) * 1000, $kid]);
if ($movements !== 1 || (int)$pdo->fetchScalar('SELECT u1 FROM units WHERE kid=?', [$kid]) !== 6) {
    $fail('A started raid must be one movement that takes its troops.');
}
if ((int)$pdo->fetchScalar('SELECT lastStartedTime FROM farmlist WHERE id=?', [$listId]) <= 0) {
    $fail('Starting must record the time.');
}

// Without a rally point nothing starts, with the village named.
$pdo->run('UPDATE fdata SET f39=0 WHERE kid=?', [$kid]);
$again = $sender->send([['id' => $listId, 'targets' => [$ok]]]);
if (!str_contains((string)$again['lists'][0]['targets'][0]['error'], (string)$pdo->fetchScalar('SELECT name FROM vdata WHERE kid=?', [$kid]))) {
    $fail('Without a rally point the refusal must name the village.');
}
// Beginner's protection keeps the player away from other players' villages but not from Natars.
$pdo->run('UPDATE fdata SET f39=1 WHERE kid=?', [$kid]);
$pdo->run('UPDATE users SET protection=? WHERE id=?', [time() + 3600, $user['id']]);
Session::getInstance();
try {
    (new FarmListSender())->send([['id' => 999999999, 'targets' => [1]]]);
    $fail('An unknown list must be refused.');
} catch (DomainException $exception) {
    if ($exception->getCode() !== 404) {
        $fail('An unknown list must answer 404.');
    }
}
echo "Farm list send runtime passed.\n";
