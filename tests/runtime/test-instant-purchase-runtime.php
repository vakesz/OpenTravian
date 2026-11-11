<?php

declare(strict_types=1);

// Runs the two-step Finish now / Demolish now endpoints (PUT signs, POST with X-Nonce executes)
// against a throwaway fixture village: success, replay, wrong and stale nonces, a missing
// balance, and a Palace-only queue with Residence/Palace gold completion off.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Instant purchase runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/dorf1.php';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/dorf1.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Controller\Ajax\premium\instantCompletion;
use Controller\Ajax\premium\instantDemolition;
use Core\Config;
use Core\Database\DBI;
use Core\Helper\WebService;
use Core\Village;
use Game\FinishConstruction;
use Game\GoldHelper;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();

if (($argv[1] ?? '') !== 'child') {
    try {
        $user = fixtureVillageCreate();
    } catch (RuntimeException $exception) {
        echo "Instant purchase runtime passed (no fixture village: {$exception->getMessage()}).\n";
        exit(0);
    }
    $pdo->run('UPDATE users SET last_login_time=?, last_owner_login_time=? WHERE id=?', [time(), time(), $user['id']]);
    foreach (['normal', 'palace'] as $phase) {
        passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' child ' . $phase . ' ' . (int)$user['id'], $status);
        if ($status !== 0) {
            $fail("Instant purchase phase '{$phase}' failed.");
        }
    }
    echo "Instant purchase runtime passed.\n";
    exit(0);
}

// ---- child: one process per phase, because Village and Session are singletons ----
$phase = (string)$argv[2];
$uid = (int)$argv[3];
$kid = (int)$pdo->fetchScalar('SELECT kid FROM users WHERE id=?', [$uid]);
// The queue rows are due a year from now (the tables hold seconds at every speed), so the live engine never completes them.
$due = time() + 365 * 86400;
$pdo->run('DELETE FROM building_upgrade WHERE kid=?', [$kid]);
$pdo->run('DELETE FROM research WHERE kid=?', [$kid]);
$pdo->run('DELETE FROM demolition WHERE kid=?', [$kid]);
if ($phase === 'palace') {
    $pdo->run('UPDATE fdata SET f39=5, f39t=26 WHERE kid=?', [$kid]);
    $pdo->run('INSERT INTO building_upgrade (kid, building_field, isMaster, start_time, commence) VALUES (?, 39, 0, ?, ?)', [$kid, time(), $due]);
} else {
    $pdo->run('INSERT INTO building_upgrade (kid, building_field, isMaster, start_time, commence) VALUES (?, 1, 0, ?, ?)', [$kid, time(), $due]);
}
// A Granary of level 1 in slot 24 for the demolition (a fresh world's village has nothing there); set before the village loads.
$pdo->run('UPDATE fdata SET f24=1, f24t=11 WHERE kid=?', [$kid]);
$_SESSION[WebService::fixSessionPrefix('uid')] = $uid;
$_SESSION[WebService::fixSessionPrefix('pw')] = (string)$pdo->fetchScalar('SELECT password FROM users WHERE id=?', [$uid]);
\Core\Session::getInstance();
$village = Village::getInstance();

$setGold = static function (int $gold) use ($pdo, $uid): void {
    $pdo->run('UPDATE users SET bought_gold=?, gift_gold=0 WHERE id=?', [$gold, $uid]);
    GoldHelper::syncSessionGold($uid);
};
$gold = static fn (): int => (int)$pdo->fetchScalar('SELECT bought_gold+gift_gold FROM users WHERE id=?', [$uid]);
$queued = static fn (): int => (int)$pdo->fetchScalar('SELECT COUNT(*) FROM building_upgrade WHERE kid=?', [$kid]);
$nonceKey = WebService::fixSessionPrefix('purchase_nonce');
$call = static function (string $class, string $method, array $body, ?string $nonce = null): array {
    $response = ['reload' => false, 'error' => false, 'success' => true, 'message' => ''];
    $_SERVER['REQUEST_METHOD'] = $method;
    unset($_SERVER['HTTP_X_NONCE']);
    if ($nonce !== null) {
        $_SERVER['HTTP_X_NONCE'] = $nonce;
    }
    $_POST = $body;
    (new $class($response))->dispatch();

    return $response;
};
$price = (int)Config::getInstance()->gold->finishNowGold;
$demolishPrice = (int)Config::getInstance()->gold->completeDemolishGold;
$completion = ['villageId' => $kid];

if ($phase === 'palace') {
    Config::getInstance()->dynamic->finishConstruction = 0; // this process only
    if (FinishConstruction::isEnabled()) {
        $fail('Residence/Palace gold completion must be off for the Palace-only phase.');
    }
    $setGold(100);
    if ((new GoldHelper())->finishNowButton() !== '') {
        $fail('A Palace-only queue must render no Finish now button.');
    }
    $response = $call(instantCompletion::class, 'PUT', $completion);
    if (empty($response['error']) || isset($response['html']) || isset($_SESSION[$nonceKey]['instant-completion'])) {
        $fail('A Palace-only queue must refuse the sign step and issue no nonce.');
    }
    exit(0);
}

// no balance: refused, no nonce
$queue = $village->onLoadBuildings['normal'];
$setGold(0);
$response = $call(instantCompletion::class, 'PUT', $completion);
if ($response['error'] !== 'notEnoughGold' || isset($_SESSION[$nonceKey]['instant-completion'])) {
    $fail('An unaffordable sign step must answer notEnoughGold and issue no nonce.');
}

$setGold(100);
$sign = static function () use ($call, $completion, $nonceKey, $fail): string {
    $response = $call(instantCompletion::class, 'PUT', $completion);
    $nonce = $_SESSION[$nonceKey]['instant-completion']['nonce'] ?? null;
    if (!empty($response['error']) || !is_string($response['html'] ?? null) || !is_string($nonce)) {
        $fail('The sign step must answer the dialog html and store a nonce: ' . json_encode($response));
    }

    return $nonce;
};

// a missing and a wrong nonce execute nothing, and spend the stored one
$sign();
if (!$call(instantCompletion::class, 'POST', $completion)['error'] || $gold() !== 100 || $queued() !== 1) {
    $fail('A POST without a nonce must be refused.');
}
$sign();
if (!$call(instantCompletion::class, 'POST', $completion, str_repeat('0', 32))['error'] || $gold() !== 100) {
    $fail('A POST with a wrong nonce must be refused.');
}

// a nonce signed for one action is no good for another
$nonce = $sign();
$response = $call(instantDemolition::class, 'POST', ['villageId' => $kid, 'slotId' => 24], $nonce);
if (empty($response['error']) || $gold() !== 100) {
    $fail('A completion nonce must not execute a demolition.');
}

// the queue the dialog showed changed: the nonce is stale
$nonce = $sign();
$pdo->run('INSERT INTO research (kid, nr, mode, end_time) VALUES (?, 2, 0, ?)', [$kid, $due]);
if (!$call(instantCompletion::class, 'POST', $completion, $nonce)['error'] || $gold() !== 100 || $queued() !== 1) {
    $fail('A nonce must go stale when the queue it was signed for changes.');
}
$pdo->run('DELETE FROM research WHERE kid=?', [$kid]);

// the balance dropped after signing: refused, nothing changes
$nonce = $sign();
$setGold(1);
$response = $call(instantCompletion::class, 'POST', $completion, $nonce);
if ($response['error'] !== 'notEnoughGold' || $gold() !== 1 || $queued() !== 1) {
    $fail('A purchase that can no longer be paid must change nothing.');
}
$village->onLoadBuildings['normal'] = $queue; // the rolled-back purchase had already dropped it in memory
$setGold(100);

// success, then replay
$nonce = $sign();
$response = $call(instantCompletion::class, 'POST', $completion, $nonce);
if (!empty($response['error']) || $gold() !== 100 - $price || $queued() !== 0) {
    $fail('A signed purchase must complete the queue and spend exactly the price.');
}
if (!$call(instantCompletion::class, 'POST', $completion, $nonce)['error'] || $gold() !== 100 - $price) {
    $fail('A replayed POST must be refused and spend nothing.');
}

// Demolish now: same contract, slot 24 of the clone holds a building
$setGold(100);
$demolish = ['villageId' => $kid, 'slotId' => 24];
$levelBefore = (int)$pdo->fetchScalar('SELECT f24 FROM fdata WHERE kid=?', [$kid]);
if ($levelBefore < 1) {
    $fail('The fixture village needs a building in slot 24.');
}
$response = $call(instantDemolition::class, 'PUT', $demolish);
$nonce = $_SESSION[$nonceKey]['instant-demolition']['nonce'] ?? null;
if (!empty($response['error']) || !is_string($nonce)) {
    $fail('The demolition sign step must store a nonce.');
}
if (!$call(instantDemolition::class, 'POST', ['villageId' => $kid, 'slotId' => 21], $nonce)['error'] || $gold() !== 100) {
    $fail('A demolition nonce must be bound to its slot.');
}
$call(instantDemolition::class, 'PUT', $demolish);
$nonce = (string)$_SESSION[$nonceKey]['instant-demolition']['nonce'];
$response = $call(instantDemolition::class, 'POST', $demolish, $nonce);
if (!empty($response['error']) || $gold() !== 100 - $demolishPrice || (int)$pdo->fetchScalar('SELECT f24 FROM fdata WHERE kid=?', [$kid]) !== 0) {
    $fail('A signed demolition must remove the building and spend exactly the price.');
}
if (!$call(instantDemolition::class, 'POST', $demolish, $nonce)['error'] || $gold() !== 100 - $demolishPrice) {
    $fail('A replayed demolition must be refused.');
}
