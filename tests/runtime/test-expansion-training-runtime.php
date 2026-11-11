<?php

declare(strict_types=1);

// The Palace training tab lists the settlers and the chief with the reason they cannot be trained yet (research,
// expansion slot), enables its Train button only when a row can be trained, and a locked row queues nothing.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Expansion training runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/build.php?id=23&gid=26&s=1';
$_SERVER['QUERY_STRING'] = 'id=23&gid=26&s=1';
$_SERVER['PHP_SELF'] = '/build.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Controller\Build\TroopBuilding;
use Core\Database\DBI;
use Core\Helper\WebService;
use Core\Session;
use Core\Village;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $user = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Expansion training runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$kid = $user['kid'];
$setup = static function (int $level, int $chiefResearched) use ($pdo, $kid): void {
    $pdo->run('UPDATE fdata SET f23=?, f23t=26 WHERE kid=?', [$level, $kid]);
    $pdo->run('UPDATE tdata SET u9=? WHERE kid=?', [$chiefResearched, $kid]);
    $pdo->run('UPDATE vdata SET wood=1000000, clay=1000000, iron=1000000, crop=1000000, maxstore=2000000, maxcrop=2000000, lastmupdate=?, expandedfrom=0 WHERE kid=?', [time(), $kid]);
    $pdo->run('UPDATE units SET u9=0, u10=0 WHERE kid=?', [$kid]);
    $pdo->run('DELETE FROM training WHERE kid=?', [$kid]);
};
$_SESSION[WebService::fixSessionPrefix('uid')] = $user['id'];
$_SESSION[WebService::fixSessionPrefix('pw')] = $user['password'];
$token = Session::getInstance()->getChecker();
$claimed = new ReflectionProperty(Session::class, 'claimedFingerprint');
$villageInstance = new ReflectionProperty(Village::class, '_self');
$request = static function (string $method, array $fields = []) use ($claimed, $villageInstance): TroopBuilding {
    // Each request loads the village anew, as a real one does.
    $villageInstance->setValue(null, null);
    $_SERVER['REQUEST_METHOD'] = $method;
    $_POST = $fields;
    $_REQUEST = $fields;
    $claimed->setValue(null, null);
    unset($_SESSION[WebService::fixSessionPrefix('last_action')]);

    return new TroopBuilding(23);
};

$setup(1, 0);
$html = (string)$request('GET')->view->output();
foreach (['trainExpansionUnits', 'class="notResearched"', 'class="noAcademy"', 'class="noExpansionSlot"', 'level 10', 'startTraining disabled'] as $needle) {
    str_contains($html, $needle) || $fail('The locked Palace training tab is missing: ' . $needle . "\n" . substr($html, 0, 600));
}
substr_count($html, 'class="noExpansionSlot"') === 2 || $fail('Both the chief and the settlers need an expansion slot at level 1.');
!str_contains($html, 'class="cta"') || $fail('A locked row must not offer an amount field.');
foreach (['favouriteTroops', 'class="favourite', 'class="level"'] as $gone) {
    !str_contains($html, $gone) || $fail('The Palace training tab must not carry: ' . $gone);
}
$request('POST', ['action' => 'trainTroops', 'checksum' => $token, 's' => '1', 't10' => '3']);
(int)$pdo->fetchScalar('SELECT COALESCE(SUM(num), 0) FROM training WHERE kid=?', [$kid]) === 0 || $fail('A settler without an expansion slot must not be queued.');

$setup(10, 0);
$html = (string)$request('GET')->view->output();
substr_count($html, 'class="noExpansionSlot"') === 0 || $fail('A level 10 Palace has a free expansion slot.');
substr_count($html, 'class="notResearched"') === 1 || $fail('Only the unresearched chief is locked at level 10.');
substr_count($html, 'class="cta"') === 1 || $fail('The settlers must be trainable at level 10.');
!str_contains($html, 'startTraining disabled') || $fail('The Train button is enabled while the settlers can be trained.');
$request('POST', ['action' => 'trainTroops', 'checksum' => $token, 's' => '1', 't10' => '3']);
(int)$pdo->fetchScalar('SELECT COALESCE(SUM(num), 0) FROM training WHERE kid=? AND nr=10', [$kid]) === 3 || $fail('Three settlers must be queued at level 10.');

// Settlers queued in a Command Center count as expansion units, like those of a Residence or Palace.
$pdo->run('DELETE FROM training WHERE kid=?', [$kid]);
$pdo->run('INSERT INTO training (kid, nr, num, item_id, training_time, commence, end_time) VALUES (?, 10, 2, 44, 1, 0, 0), (?, 9, 1, 26, 1, 0, 0)', [$kid, $kid]);
$units = (new \Game\TrainingHelper())->getExpansionUnits($kid);
$units['settlers'] >= 2 && $units['chiefs'] >= 1 || $fail('Expansion units must include Command Center training: ' . json_encode($units));
$pdo->run('DELETE FROM training WHERE kid=?', [$kid]);

echo "Expansion training runtime passed.\n";
