<?php

declare(strict_types=1);

// The mail service for a throwaway fixture player: opt-in and eligibility, the rate limit, the queued row (footer,
// unsubscribe link and header URL), the newsletter's recipient list, and signed unsubscribe tokens. Nothing is
// sent: rows are only queued, and the test removes them.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Mail service runtime skipped outside the php container.\n";
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
use Core\Database\GlobalDB;
use Core\Helper\WebService;
use Core\Session;
use Game\Mail\EmailKind;
use Game\Mail\EmailPreferences;
use Game\Mail\MailService;
use Game\Mail\Newsletter;
use Game\Mail\UnsubscribeToken;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $user = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Mail service runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$uid = $user['id'];
$email = 'zzfx-' . getmypid() . '@example.invalid';
$global = GlobalDB::getInstance();
register_shutdown_function(static function () use ($global, $pdo, $uid): void {
    $global->run("DELETE FROM mailServer WHERE toEmail LIKE 'zzfx-%@example.invalid'");
    $pdo->run('DELETE FROM email_preference WHERE uid=?', [$uid]);
    $pdo->run('DELETE FROM deleting WHERE uid=?', [$uid]);
});
$_SESSION[WebService::fixSessionPrefix('uid')] = $user['id'];
$_SESSION[WebService::fixSessionPrefix('pw')] = $user['password'];
Session::getInstance();
$setUser = static function (array $values) use ($pdo, $uid): void {
    foreach ($values as $column => $value) {
        $pdo->run("UPDATE users SET {$column}=? WHERE id=?", [$value, $uid]);
    }
};
$queued = static fn (): array => $global->run('SELECT * FROM mailServer WHERE toEmail=?', [(string)$pdo->fetchScalar('SELECT email FROM users WHERE id=?', [$uid])])->get_result()->fetch_all(MYSQLI_ASSOC);
$recipients = static fn (): array => array_column(Newsletter::recipients(), 'uid');
$setUser(['email' => $email, 'email_verified' => 1, 'newsletter' => 0, 'access' => 1]);
$pdo->run('DELETE FROM deleting WHERE uid=?', [$uid]);

// Signed unsubscribe tokens.
$token = UnsubscribeToken::issue($uid, 'attack');
if (UnsubscribeToken::verify($token) !== ['uid' => $uid, 'scope' => 'attack'] || UnsubscribeToken::verify(UnsubscribeToken::issue($uid, 'all')) !== ['uid' => $uid, 'scope' => 'all']) {
    $fail('A token must verify to its player and scope.');
}
[$tuid, $tscope, $mac] = explode('.', $token);
foreach ([($uid + 1) . ".$tscope.$mac", "$uid.message.$mac", "$uid.all.$mac", "$uid.$tscope." . strrev($mac), "$uid.nonsense." . $mac, "$uid.$tscope", '', 'x'] as $forged) {
    if (UnsubscribeToken::verify($forged) !== null) {
        $fail("A forged token must be refused: {$forged}");
    }
}
if (!str_contains(UnsubscribeToken::url($uid, EmailKind::Message), '/unsubscribe/' . $uid . '.message.')) {
    $fail('The unsubscribe URL must carry the player and the kind.');
}

// Opt-in and eligibility.
if (MailService::queue($uid, EmailKind::Attack, 'subject', 'body') || $queued() !== []) {
    $fail('Nobody is mailed without an opt-in.');
}
EmailPreferences::set($uid, EmailKind::Attack, true);
if (!EmailPreferences::enabled($uid, EmailKind::Attack) || EmailPreferences::enabled($uid, EmailKind::Message)) {
    $fail('Opt-ins are per kind.');
}
foreach ([['email_verified' => 0], ['access' => 0], ['email' => '']] as $change) {
    $before = array_intersect_key(['email_verified' => 1, 'access' => 1, 'email' => $email], $change);
    $setUser($change);
    if (MailService::queue($uid, EmailKind::Attack, 'subject', 'body')) {
        $fail('An unverified, banned or address-less player must not be mailed: ' . json_encode($change));
    }
    $setUser($before);
}
$pdo->run('INSERT INTO deleting (uid, time) VALUES (?, ?)', [$uid, time() + 86400]);
if (MailService::queue($uid, EmailKind::Attack, 'subject', 'body')) {
    $fail('A player in deletion must not be mailed.');
}
$pdo->run('DELETE FROM deleting WHERE uid=?', [$uid]);
if ($queued() !== []) {
    $fail('Nothing may be queued so far.');
}

// The queued row.
if (!MailService::queue($uid, EmailKind::Attack, MailService::subject(EmailKind::Attack), '<p>Body text</p>')) {
    $fail('An opted-in, verified player must be mailed.');
}
$rows = $queued();
if (count($rows) !== 1 || $rows[0]['status'] !== 'queued') {
    $fail('Exactly one queued row expected.');
}
$row = $rows[0];
$name = (string)$pdo->fetchScalar('SELECT name FROM users WHERE id=?', [$uid]);
if (!str_contains($row['subject'], 'Incoming attacks') || !str_contains($row['html'], 'Body text') || !str_contains($row['html'], $name)
    || !str_contains($row['html'], T('Mail', 'You are receiving this email because you opted in on this game world.')) || !str_contains($row['html'], T('Mail', 'Unsubscribe'))) {
    $fail('The mail must carry subject, body, greeting and footer: ' . $row['subject']);
}
if (preg_match('#/unsubscribe/' . $uid . '\.attack\.[a-f0-9]{32}$#', $row['unsubscribeUrl']) !== 1 || !str_contains($row['html'], 'href="' . htmlspecialchars($row['unsubscribeUrl'], ENT_QUOTES) . '"')) {
    $fail('The footer link and the queue row must carry the same signed URL: ' . $row['unsubscribeUrl']);
}
if (MailService::queue($uid, EmailKind::Attack, 'again', 'body') || count($queued()) !== 1) {
    $fail('A second mail of the same kind inside its window must be dropped.');
}

// The newsletter: opted-in, verified, not banned, not in deletion.
if (in_array($uid, $recipients(), true)) {
    $fail('A player with the newsletter off is not a recipient.');
}
EmailPreferences::set($uid, EmailKind::Newsletter, true);
if (!in_array($uid, $recipients(), true)) {
    $fail('A player with the newsletter on is a recipient.');
}
foreach ([['email_verified' => 0, 'email_verified_back' => 1], ['access' => 0, 'access_back' => 1]] as $change) {
    $column = array_key_first($change);
    $setUser([$column => $change[$column]]);
    if (in_array($uid, $recipients(), true)) {
        $fail("{$column} must exclude a recipient.");
    }
    $setUser([$column => $change[$column . '_back']]);
}
$pdo->run('INSERT INTO deleting (uid, time) VALUES (?, ?)', [$uid, time() + 86400]);
if (in_array($uid, $recipients(), true)) {
    $fail('A player in deletion is not a recipient.');
}
$pdo->run('DELETE FROM deleting WHERE uid=?', [$uid]);
$result = Newsletter::queueTo([['uid' => $uid, 'name' => $name, 'email' => $email]], 'Newsletter subject', '<p>News</p>');
if ($result !== ['queued' => 1, 'skipped' => 0] || count($queued()) !== 2) {
    $fail('The newsletter must queue one mail for the recipient: ' . json_encode($result));
}
$again = Newsletter::queueTo([['uid' => $uid, 'name' => $name, 'email' => $email]], 'Newsletter subject', '<p>News</p>');
if ($again !== ['queued' => 0, 'skipped' => 1]) {
    $fail('A second newsletter inside its window must be skipped.');
}

// Unsubscribing: a kind, or everything.
EmailPreferences::set($uid, EmailKind::Message, true);
EmailPreferences::unsubscribe($uid, 'attack');
if (EmailPreferences::enabled($uid, EmailKind::Attack) || !EmailPreferences::enabled($uid, EmailKind::Message) || !EmailPreferences::enabled($uid, EmailKind::Newsletter)) {
    $fail('Unsubscribing a kind switches off only that kind.');
}
EmailPreferences::unsubscribe($uid, 'all');
if (EmailPreferences::enabled($uid, EmailKind::Message) || EmailPreferences::enabled($uid, EmailKind::Newsletter)) {
    $fail('Unsubscribing from all switches everything off.');
}
echo "Mail service runtime passed.\n";
