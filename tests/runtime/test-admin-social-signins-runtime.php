<?php

declare(strict_types=1);

// The control panel's view of linked social sign-ins against the real tables, for a throwaway fixture player:
// search and filters, the unlink form per row, and the social.* audit rows (per player and for everybody).

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Admin social sign-ins runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/admin.php';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/admin.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Database\DBI;
use Game\Admin\AuditLog;
use Game\SocialAuth\AdminView;
use Game\SocialAuth\DbIdentityRepository;
use Game\SocialAuth\IdentityClaims;
use Game\SocialAuth\LinkedIdentities;
use Game\SocialAuth\Provider;
use Game\SocialAuth\SocialAudit;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $user = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Admin social sign-ins runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$uid = $user['id'];
$name = (string)$pdo->fetchScalar('SELECT name FROM users WHERE id=?', [$uid]);
$subject = 'zzfx-admin-' . getmypid();
register_shutdown_function(static function () use ($pdo, $uid): void {
    $pdo->run('DELETE FROM account_oauth_identity WHERE uid=?', [$uid]);
    $pdo->run("DELETE FROM admin_log WHERE action LIKE 'social.%' AND (admin_uid=? OR target LIKE ?)", [$uid, 'uid=' . $uid . '%']);
});
(new DbIdentityRepository())->link($uid, new IdentityClaims(Provider::Apple, $subject, 'ada@privaterelay.example', true, 'Ada'));

$byPlayer = AdminView::search(['player' => (string)$uid], 1);
if ($byPlayer['total'] !== 1 || $byPlayer['rows'][0]['name'] !== $name || $byPlayer['rows'][0]['provider'] !== 'apple') {
    $fail('The identity must be found by player id: ' . json_encode($byPlayer));
}
foreach ([['player' => $name], ['provider' => 'apple', 'player' => (string)$uid], ['email' => 'privaterelay'], ['player' => (string)$uid, 'email' => 'ada@']] as $filters) {
    if (AdminView::search($filters, 1)['total'] < 1) {
        $fail('Filters must find the identity: ' . json_encode($filters));
    }
}
foreach ([['player' => (string)$uid, 'provider' => 'google'], ['player' => (string)$uid, 'email' => 'nobody'], ['provider' => 'nonsense', 'player' => (string)($uid + 100000)]] as $filters) {
    if (AdminView::search($filters, 1)['total'] !== 0) {
        $fail('Filters must exclude the identity: ' . json_encode($filters));
    }
}

$html = AdminView::identitiesTable($byPlayer['rows'], true);
if (!str_contains($html, 'name="provider" value="apple"') || !str_contains($html, 'name="uid" value="' . $uid . '"') || !str_contains($html, 'action=socialSignIns') || !str_contains($html, 'ada@privaterelay.example')) {
    $fail('The table must carry the unlink form and the address.');
}
if (str_contains($html, $subject)) {
    $fail('The subject must never be shown.');
}
if (str_contains(AdminView::identitiesTable($byPlayer['rows'], false), '<th>Player</th>')) {
    $fail('The per-player table has no player column.');
}

// Events: the player's own, and only theirs (uid=7 must not match uid=70).
SocialAudit::record('link', $uid, $name, Provider::Apple);
AuditLog::record('social.unlink', 'uid=' . $uid . ' provider=apple', 'by an administrator', 1, 'Admin');
$decoy = $uid * 10;
$pdo->run("INSERT INTO admin_log (time, admin_uid, admin_name, action, target, details, ip) VALUES (?, ?, 'Decoy', 'social.link', ?, '', '')", [time(), $decoy, 'uid=' . $decoy . ' provider=apple']);
$mine = AdminView::eventsTable($uid, 20);
if (substr_count($mine, '<tr>') !== 3 || !str_contains($mine, 'social.link') || !str_contains($mine, 'social.unlink') || str_contains($mine, 'Decoy')) {
    $fail('A player must see only their own social events: ' . $mine);
}
$all = AdminView::eventsTable(null, 50);
if (!str_contains($all, 'Decoy') || !str_contains($all, 'social.unlink')) {
    $fail('The all-players view must list every social event.');
}
$pdo->run("DELETE FROM admin_log WHERE admin_uid=? AND admin_name='Decoy'", [$decoy]);
[$where, $params] = AuditLog::buildWhere(['eventPrefix' => 'social.']);
if ($where !== ' WHERE action LIKE ?' || $params !== ['social.%']) {
    $fail('The action prefix filter must be a bound LIKE: ' . json_encode([$where, $params]));
}
if (!str_contains(AuditLog::targetFromRequest([], ['uid' => (string)$uid, 'provider' => 'apple']), 'provider=apple')) {
    $fail('A panel POST must name the provider in its audit target.');
}

// Unlinking by an administrator uses the same removal as the player's.
if (!LinkedIdentities::unlink($uid, Provider::Apple) || AdminView::search(['player' => (string)$uid], 1)['total'] !== 0) {
    $fail('The identity must be gone after the unlink.');
}
echo "Admin social sign-ins runtime passed.\n";
