<?php

declare(strict_types=1);

// Runs the alliance bonus endpoints the page script posts to ("alliance/bonus/contribute" and the
// two-step "premium/contribute-resources-x3") against a throwaway fixture village inside a throwaway
// alliance: a plain donation, a donation over the daily limit, a tripled donation signed and booked
// with its nonce, a replayed nonce, and a missing balance.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Alliance bonus contribution runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/dorf1.php';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/dorf1.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Controller\Ajax\alliance\bonusContribute;
use Controller\Ajax\alliance\bonusFormGold;
use Controller\Ajax\alliance\bonusOverview;
use Controller\Ajax\premium\contributeResourcesX3;
use Core\Config;
use Core\Database\DBI;
use Core\Helper\WebService;
use Core\Village;
use Game\Formulas;
use Game\GoldHelper;
use Model\AllianceModel;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();

if (($argv[1] ?? '') !== 'child') {
    try {
        $user = fixtureVillageCreate();
    } catch (RuntimeException $exception) {
        echo "Alliance bonus contribution runtime passed (no fixture village: {$exception->getMessage()}).\n";
        exit(0);
    }
    $pdo->run('UPDATE users SET last_login_time=?, last_owner_login_time=? WHERE id=?', [time(), time(), $user['id']]);
    $aid = (new AllianceModel())->createAlliance((int)$user['id'], 'zzfxbonus' . getmypid(), 'zz' . (getmypid() % 100000));
    if ($aid === false) {
        $fail('The fixture alliance could not be created.');
    }
    register_shutdown_function(static function () use ($pdo, $aid): void {
        $pdo->run('DELETE FROM alliance_bonus_upgrade_queue WHERE aid=?', [$aid]);
        $pdo->run('DELETE FROM alidata WHERE id=?', [$aid]);
    });
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' child x ' . (int)$user['id'], $status);
    if ($status !== 0) {
        $fail('Alliance bonus contribution child failed.');
    }
    echo "Alliance bonus contribution runtime passed.\n";
    exit(0);
}

// ---- child: Village and Session are singletons, so the checks run in their own process ----
$uid = (int)$argv[3];
$kid = (int)$pdo->fetchScalar('SELECT kid FROM users WHERE id=?', [$uid]);
$aid = (int)$pdo->fetchScalar('SELECT aid FROM users WHERE id=?', [$uid]);
$pdo->run('UPDATE vdata SET wood=100000, clay=100000, iron=100000, crop=100000, maxstore=200000, maxcrop=200000 WHERE kid=?', [$kid]);
$pdo->run('UPDATE users SET alliance_contributions=0 WHERE id=?', [$uid]);
$_SESSION[WebService::fixSessionPrefix('uid')] = $uid;
$_SESSION[WebService::fixSessionPrefix('pw')] = (string)$pdo->fetchScalar('SELECT password FROM users WHERE id=?', [$uid]);
\Core\Session::getInstance();
Village::getInstance();

$wood = static fn (): int => (int)$pdo->fetchScalar('SELECT wood FROM vdata WHERE kid=?', [$kid]);
// production adds a few units between two reads, so spent amounts are compared with a margin
$spent = static fn (int $before, int $amount): bool => $wood() >= $before - $amount && $wood() <= $before - $amount + 1000;
$contributed = static fn (): int => (int)$pdo->fetchScalar('SELECT alliance_contributions FROM users WHERE id=?', [$uid]);
$gold = static fn (): int => (int)$pdo->fetchScalar('SELECT bought_gold+gift_gold FROM users WHERE id=?', [$uid]);
$setGold = static function (int $amount) use ($pdo, $uid): void {
    $pdo->run('UPDATE users SET bought_gold=?, gift_gold=0 WHERE id=?', [$amount, $uid]);
    GoldHelper::syncSessionGold($uid);
};
$call = static function (string $class, string $method, array $body, ?string $nonce = null): array {
    $response = ['reload' => false, 'error' => false, 'success' => true, 'message' => ''];
    $_SERVER['REQUEST_METHOD'] = $method;
    unset($_SERVER['HTTP_X_NONCE']);
    if ($nonce !== null) {
        $_SERVER['HTTP_X_NONCE'] = $nonce;
    }
    $_POST = $body;
    http_response_code(200);
    (new $class($response))->dispatch();

    return $response;
};
$donation = static fn (int $lumber, string $bonus = 'CPProduction'): array => [
    'bonus' => $bonus,
    'resources' => ['lumber' => $lumber, 'clay' => 0, 'iron' => 0, 'crop' => 0],
];
$nonceKey = WebService::fixSessionPrefix('purchase_nonce');
$cost = (int)Config::getProperty('gold', 'allianceBonus3xGold');

// a plain donation spends the resources and counts toward the daily limit
$woodBefore = $wood();
$response = $call(bonusContribute::class, 'POST', $donation(100));
if (!empty($response['error']) || !$spent($woodBefore, 100) || $contributed() !== 100) {
    $fail('A contribution must spend its resources and be credited once: ' . json_encode($response));
}
foreach ([$donation(0), $donation(100, 'unknown')] as $invalid) {
    if (empty($call(bonusContribute::class, 'POST', $invalid)['error'])) {
        $fail('An empty or unknown donation must be refused.');
    }
}

// the overview the page refreshes from carries the form, the limit bar and the totals
$overview = $call(bonusOverview::class, 'POST', []);
$limit = Formulas::getAllianceBonusDonationLimit(0);
if (!is_string($overview['overview'] ?? null) || !is_string($overview['contributionForm'] ?? null)
    || !is_string($overview['limitBar'] ?? null) || ($overview['total'] ?? null) !== 100 || ($overview['limit'] ?? null) !== $limit) {
    $fail('The overview must answer the overview, contribution form, limit bar, total and limit: ' . json_encode(array_keys($overview)));
}
$form = $call(bonusFormGold::class, 'POST', $donation(100));
if (!str_contains((string)($form['form'] ?? ''), 'bonus-donation-gold') || !str_contains($form['form'], '<strong>300</strong>')) {
    $fail('The gold form must show the tripled amount.');
}

// over the daily limit: refused, nothing spent
$pdo->run('UPDATE users SET alliance_contributions=? WHERE id=?', [$limit - 50, $uid]);
$_SESSION[WebService::fixSessionPrefix('alliance_contributions')] = $limit - 50;
\Core\Session::getInstance()->set('alliance_contributions', $limit - 50);
$woodBefore = $wood();
if (empty($call(bonusContribute::class, 'POST', $donation(100))['error']) || !$spent($woodBefore, 0) || $contributed() !== $limit - 50) {
    $fail('A donation over the daily limit must be refused and change nothing.');
}

// Contribute x3: signed, then booked with the nonce
$pdo->run('UPDATE users SET alliance_contributions=0 WHERE id=?', [$uid]);
\Core\Session::getInstance()->set('alliance_contributions', 0);
$body = $donation(200);
$setGold(0);
$response = $call(contributeResourcesX3::class, 'PUT', $body);
if (($response['error'] ?? null) !== 'notEnoughGold' || isset($_SESSION[$nonceKey]['contribute-resources-x3'])) {
    $fail('Signing without gold must answer notEnoughGold and issue no nonce.');
}
$setGold(10);
$response = $call(contributeResourcesX3::class, 'PUT', $body);
$nonce = $_SESSION[$nonceKey]['contribute-resources-x3']['nonce'] ?? null;
if (!empty($response['error']) || !is_string($nonce)) {
    $fail('Signing must store a nonce: ' . json_encode($response));
}
if (empty($call(contributeResourcesX3::class, 'POST', $donation(300), $nonce)['error']) || $gold() !== 10) {
    $fail('A nonce signed for one donation must not book another.');
}
$call(contributeResourcesX3::class, 'PUT', $body);
$nonce = (string)$_SESSION[$nonceKey]['contribute-resources-x3']['nonce'];
$woodBefore = $wood();
$response = $call(contributeResourcesX3::class, 'POST', $body, $nonce);
if (!empty($response['error']) || $gold() !== 10 - $cost || !$spent($woodBefore, 200) || $contributed() !== 600) {
    $fail('A booked x3 donation must spend the price and the resources and credit three times: ' . json_encode([$response, $gold(), 10 - $cost, $wood(), $woodBefore - 200, $contributed()]));
}
if (empty($call(contributeResourcesX3::class, 'POST', $body, $nonce)['error']) || $gold() !== 10 - $cost || $contributed() !== 600) {
    $fail('A replayed nonce must be refused and spend nothing.');
}
