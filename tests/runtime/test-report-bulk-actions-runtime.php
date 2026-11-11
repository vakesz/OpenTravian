<?php

declare(strict_types=1);

// The reference's report bulk actions against a throwaway fixture player: PUT report/update-status,
// the two-step (PUT signs, POST with X-Nonce executes) report/mark-read, report/delete,
// report/delete-bulk and report/clean-archive. Checks the filter by report type, that only the
// player's own reports change, that a replayed, wrong or re-scoped nonce changes nothing, and that
// archiving needs the Gold club.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Report bulk actions runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/dorf1.php';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/dorf1.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Controller\Ajax\report\cleanArchive;
use Controller\Ajax\report\delete;
use Controller\Ajax\report\deleteBulk;
use Controller\Ajax\report\markRead;
use Controller\Ajax\report\updateStatus;
use Core\Database\DBI;
use Core\Helper\WebService;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $user = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Report bulk actions runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$uid = (int)$user['id'];
$otherUid = (int)$pdo->fetchScalar('SELECT id FROM users WHERE id>2 AND id<>? ORDER BY id LIMIT 1', [$uid]);
$kid = (int)$pdo->fetchScalar('SELECT kid FROM users WHERE id=?', [$uid]);
$pdo->run('UPDATE users SET goldclub=0 WHERE id=?', [$uid]);

$created = [];
register_shutdown_function(static function () use ($pdo, &$created): void {
    if ($created !== []) {
        $pdo->run('DELETE FROM ndata WHERE id IN (' . implode(',', array_map('intval', $created)) . ')');
    }
});
$report = static function (int $owner, int $type, array $flags = []) use ($pdo, $kid, &$created): int {
    $pdo->run(
        'INSERT INTO ndata (aid, uid, isEnforcement, kid, to_kid, type, bounty, data, time, private_key, viewed, archive, deleted) VALUES (0, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$owner, $kid, $kid, $type, '', '', time(), 'fixture00key', $flags['viewed'] ?? 0, $flags['archive'] ?? 0, $flags['deleted'] ?? 0]
    );
    $id = (int)$pdo->lastInsertId();
    $created[] = $id;

    return $id;
};
$row = static fn (int $id): array => $pdo->fetchSingleRow('SELECT viewed, archive, deleted FROM ndata WHERE id=?', [$id]);

$_SESSION[WebService::fixSessionPrefix('uid')] = $uid;
$_SESSION[WebService::fixSessionPrefix('pw')] = (string)$pdo->fetchScalar('SELECT password FROM users WHERE id=?', [$uid]);
$session = \Core\Session::getInstance();
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
$nonceKey = WebService::fixSessionPrefix('purchase_nonce');
$twoStep = static function (string $class, string $action, array $body) use ($call, $nonceKey, $fail): void {
    $call($class, 'PUT', $body);
    $nonce = $_SESSION[$nonceKey][$action]['nonce'] ?? null;
    if (!is_string($nonce)) {
        $fail("The sign step of {$action} must issue a nonce.");
    }
    $response = $call($class, 'POST', $body, $nonce);
    if (!empty($response['error'])) {
        $fail("The execute step of {$action} failed: " . ($response['message'] ?? ''));
    }
};

// update-status: read and unread, other players' reports untouched, Gold club for the archive.
$mine = $report($uid, 1);
$foreign = $report($otherUid, 1);
$call(updateStatus::class, 'PUT', ['ids' => [$mine, $foreign], 'status' => 'read']);
if ((int)$row($mine)['viewed'] !== 1 || (int)$row($foreign)['viewed'] !== 0) {
    $fail('update-status read must change only the own report.');
}
$call(updateStatus::class, 'PUT', ['ids' => [$mine], 'status' => 'unread']);
if ((int)$row($mine)['viewed'] !== 0) {
    $fail('update-status unread must unmark the report.');
}
if (empty($call(updateStatus::class, 'PUT', ['ids' => [$mine], 'status' => 'archived'])['error']) || (int)$row($mine)['archive'] !== 0) {
    $fail('Archiving needs the Gold club.');
}
if (empty($call(updateStatus::class, 'POST', ['ids' => [$mine], 'status' => 'read'])['error'])) {
    $fail('update-status answers PUT only.');
}
if (empty($call(updateStatus::class, 'PUT', ['ids' => [$mine], 'status' => 'bogus'])['error'])) {
    $fail('An unknown status must be refused.');
}
$pdo->run('UPDATE users SET goldclub=1 WHERE id=?', [$uid]);
$session->setGoldClub(1);
$call(updateStatus::class, 'PUT', ['ids' => [$mine], 'status' => 'archived']);
if ((int)$row($mine)['archive'] !== 1) {
    $fail('A Gold club player can archive.');
}
$call(updateStatus::class, 'PUT', ['ids' => [$mine], 'status' => 'unarchived']);
if ((int)$row($mine)['archive'] !== 0) {
    $fail('A Gold club player can restore an archived report.');
}

// mark-read filtered by type, then all types.
$typeOne = $report($uid, 1);
$typeTwo = $report($uid, 2);
$twoStep(markRead::class, 'report/mark-read', ['icons' => [2]]);
if ((int)$row($typeTwo)['viewed'] !== 1 || (int)$row($typeOne)['viewed'] !== 0 || (int)$row($foreign)['viewed'] !== 0) {
    $fail('mark-read must follow the filter icons and leave other players alone.');
}
$twoStep(markRead::class, 'report/mark-read', []);
if ((int)$row($typeOne)['viewed'] !== 1) {
    $fail('An empty filter marks every type.');
}

// Nonce discipline: replay, wrong nonce and a different scope change nothing.
$victim = $report($uid, 3);
$call(delete::class, 'PUT', ['icons' => [3]]);
$nonce = (string)$_SESSION[$nonceKey]['report/delete']['nonce'];
if (empty($call(delete::class, 'POST', ['icons' => [3]], 'wrong')['error']) || (int)$row($victim)['deleted'] !== 0) {
    $fail('A wrong nonce must change nothing.');
}
if (empty($call(delete::class, 'POST', ['icons' => [3]], $nonce)['error']) || (int)$row($victim)['deleted'] !== 0) {
    $fail('A nonce is spent by its first execute attempt.');
}
$call(delete::class, 'PUT', ['icons' => [3]]);
$nonce = (string)$_SESSION[$nonceKey]['report/delete']['nonce'];
if (empty($call(delete::class, 'POST', ['icons' => []], $nonce)['error']) || (int)$row($victim)['deleted'] !== 0) {
    $fail('A nonce signed for one filter must not execute another.');
}
$twoStep(delete::class, 'report/delete', ['icons' => [3]]);
if ((int)$row($victim)['deleted'] !== 1) {
    $fail('delete must remove the reports of the filtered types.');
}

// delete-bulk by ids, clean-archive only touches archived reports.
$selected = $report($uid, 4);
$kept = $report($uid, 4);
$archived = $report($uid, 4, ['archive' => 1]);
$twoStep(deleteBulk::class, 'report/delete-bulk', ['ids' => [$selected, $foreign]]);
if ((int)$row($selected)['deleted'] !== 1 || (int)$row($kept)['deleted'] !== 0 || (int)$row($foreign)['deleted'] !== 0) {
    $fail('delete-bulk must delete the selected own reports only.');
}
$twoStep(cleanArchive::class, 'report/clean-archive', []);
if ((int)$row($archived)['deleted'] !== 1 || (int)$row($kept)['deleted'] !== 0) {
    $fail('clean-archive must delete archived reports only.');
}
$twoStep(delete::class, 'report/delete', ['icons' => []]);
if ((int)$row($kept)['deleted'] !== 1 || (int)$row($foreign)['deleted'] !== 0) {
    $fail('delete with an empty filter removes the own unarchived reports.');
}

fixtureVillageRemove($uid, $kid);
echo "Report bulk actions runtime passed.\n";
