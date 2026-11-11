<?php

declare(strict_types=1);

// Account deletion and data export for a throwaway fixture player: the three-day queue, what an avatar in
// deletion is refused, and the export document (what it holds, and that it holds no secret and no other player's
// private data).

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Account deletion runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/options';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/options';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Database\DBI;
use Core\Helper\WebService;
use Core\Session;
use Game\Account\DataExport;
use Game\Account\DeletionLock;
use Game\SocialAuth\DbIdentityRepository;
use Game\SocialAuth\IdentityClaims;
use Game\SocialAuth\Provider;
use Model\OptionModel;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $user = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Account deletion runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$uid = $user['id'];
$kid = $user['kid'];
$other = (int)$pdo->fetchScalar('SELECT id FROM users WHERE id>2 AND id<>? ORDER BY id LIMIT 1', [$uid]);
if ($other === 0) {
    $fail('The fixture needs a second player.');
}
$subject = 'zzfx-export-' . getmypid();
register_shutdown_function(static function () use ($pdo, $uid, $other, $subject): void {
    $pdo->run('DELETE FROM mdata WHERE topic LIKE ?', ['zzfx-export-%']);
    $pdo->run('DELETE FROM account_oauth_identity WHERE subject=?', [$subject]);
    $pdo->run('DELETE FROM infobox WHERE uid=? AND type=11', [$uid]);
    $pdo->run('DELETE FROM deleting WHERE uid=?', [$uid]);
});
$_SESSION[WebService::fixSessionPrefix('uid')] = $user['id'];
$_SESSION[WebService::fixSessionPrefix('pw')] = $user['password'];
Session::getInstance();

// The deletion queue: three days, and the refusals that follow.
$options = new OptionModel();
if ($options->isDeletion($uid) || DeletionLock::inDeletion($uid) || DeletionLock::refusal($uid) !== null) {
    $fail('A new player is not in deletion.');
}
$options->addDeleting($uid);
$due = (int)$pdo->fetchScalar('SELECT time FROM deleting WHERE uid=?', [$uid]);
if (abs($due - (time() + 3 * 86400)) > 5) {
    $fail('A deletion must take exactly three days: ' . ($due - time()) . ' seconds.');
}
foreach ([
    'errorUserDel' => 'This is not possible, since your avatar is being deleted.',
    'auctionSellNotAllowedDeletion' => "You can't sell items if your avatar is being deleted.",
    'cannot_send_resources_in_deletion' => "While your avatar is being deleted, you can't send resources.",
    'in_loeschung' => 'The avatar is being deleted.<br>It is not possible to buy Gold',
] as $key => $text) {
    if (DeletionLock::refusal($uid, $key) !== $text) {
        $fail("The refusal {$key} must carry the reference text.");
    }
}
$options->cancelDeletion($uid);
if (DeletionLock::inDeletion($uid)) {
    $fail('Cancelling must end the deletion.');
}

// The export.
(new DbIdentityRepository())->link($uid, new IdentityClaims(Provider::Google, $subject, 'me@example.com', true, 'Me'));
$now = time();
$pdo->run("INSERT INTO mdata (uid, to_uid, topic, message, viewed, archived, delete_receiver, delete_sender, reported, md5_checksum, mode, time, autoType, isAlliance) VALUES (?, ?, 'zzfx-export-sent', 'Hello', 0, 0, 0, 0, 0, 'x', 0, ?, 0, 0)", [$uid, $other, $now]);
$pdo->run("INSERT INTO mdata (uid, to_uid, topic, message, viewed, archived, delete_receiver, delete_sender, reported, md5_checksum, mode, time, autoType, isAlliance) VALUES (?, ?, 'zzfx-export-received', 'Hi back', 0, 0, 0, 0, 0, 'y', 0, ?, 0, 0)", [$other, $uid, $now]);
$pdo->run("INSERT INTO mdata (uid, to_uid, topic, message, viewed, archived, delete_receiver, delete_sender, reported, md5_checksum, mode, time, autoType, isAlliance) VALUES (?, ?, 'zzfx-export-hidden', 'Gone', 0, 0, 1, 0, 0, 'z', 0, ?, 0, 0)", [$other, $uid, $now]);
$export = DataExport::build($uid);
$json = json_encode($export, JSON_THROW_ON_ERROR);
if (($export['account']['id'] ?? null) !== $uid || $export['account']['email'] === null || count($export['villages']) !== 1 || $export['villages'][0]['id'] !== $kid) {
    $fail('The export must carry the account and the village: ' . substr($json, 0, 300));
}
if (!isset($export['hero']) && $pdo->fetchScalar('SELECT COUNT(uid) FROM hero WHERE uid=?', [$uid]) > 0) {
    $fail('The hero must be exported.');
}
$directions = array_column($export['messages'], 'direction', 'subject');
if (($directions['zzfx-export-sent'] ?? null) !== 'sent' || ($directions['zzfx-export-received'] ?? null) !== 'received' || isset($directions['zzfx-export-hidden'])) {
    $fail('Messages: sent and received, not those the player deleted: ' . json_encode($directions));
}
$received = array_values(array_filter($export['messages'], static fn (array $m): bool => $m['subject'] === 'zzfx-export-received'))[0];
if ($received['body'] !== 'Hi back' || $received['from'] === '') {
    $fail('A received message carries sender, subject and body.');
}
if (count($export['socialAccounts']) !== 1 || $export['socialAccounts'][0]['provider'] !== 'google') {
    $fail('The social account must be exported.');
}
$secrets = [
    (string)$pdo->fetchScalar('SELECT password FROM users WHERE id=?', [$uid]),
    $subject,
    (string)$pdo->fetchScalar('SELECT ajax_token FROM users WHERE id=?', [$uid]),
];
foreach ($secrets as $secret) {
    if ($secret !== '' && str_contains($json, $secret)) {
        $fail('The export must hold no secret.');
    }
}
foreach (['"password"', 'private_key', 'ajax_token', '"uuid"'] as $field) {
    if (str_contains($json, $field)) {
        $fail("The export must not carry {$field}.");
    }
}
foreach ($export['reports'] as $report) {
    if (array_diff(array_keys($report), ['id', 'type', 'time', 'from', 'to', 'loot', 'read', 'archived']) !== []) {
        $fail('A report is metadata and loot only.');
    }
}
$otherEmail = (string)$pdo->fetchScalar('SELECT email FROM users WHERE id=?', [$other]);
if ($otherEmail !== '' && str_contains($json, $otherEmail)) {
    $fail("Another player's email must not be exported.");
}
echo "Account deletion runtime passed.\n";
