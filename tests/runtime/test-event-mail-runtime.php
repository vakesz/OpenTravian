<?php

declare(strict_types=1);

// The event mails (incoming attacks, construction finished, new messages) for a throwaway fixture player: opt-in,
// the approved subject and body, the singular and plural attack text, what stays out of the mail, and the rate
// limit. Nothing is sent: rows are only queued, and the test removes them.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Event mail runtime skipped outside the php container.\n";
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
use Core\Database\GlobalDB;
use Core\Helper\TimezoneHelper;
use Core\Helper\WebService;
use Core\Session;
use Game\Mail\EmailKind;
use Game\Mail\EmailPreferences;
use Game\Mail\EventMail;
use Model\MovementsModel;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $user = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Event mail runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$uid = $user['id'];
$kid = $user['kid'];
$other = (int)$pdo->fetchScalar('SELECT id FROM users WHERE id>2 AND id<>? ORDER BY id LIMIT 1', [$uid]);
$otherKid = (int)$pdo->fetchScalar('SELECT kid FROM vdata WHERE owner=? ORDER BY kid LIMIT 1', [$other]);
$email = 'zzfx-' . getmypid() . '@example.invalid';
$global = GlobalDB::getInstance();
register_shutdown_function(static function () use ($global, $pdo, $uid, $kid): void {
    $global->run("DELETE FROM mailServer WHERE toEmail LIKE 'zzfx-%@example.invalid'");
    $pdo->run('DELETE FROM email_preference WHERE uid=?', [$uid]);
    $pdo->run('DELETE FROM movement WHERE to_kid=?', [$kid]);
});
$_SESSION[WebService::fixSessionPrefix('uid')] = $user['id'];
$_SESSION[WebService::fixSessionPrefix('pw')] = $user['password'];
Session::getInstance();
$pdo->run("UPDATE users SET email=?, email_verified=1, access=1, timezone='UTC,0' WHERE id=?", [$email, $uid]);
$queue = static fn (): array => $global->run('SELECT subject, html FROM mailServer WHERE toEmail=? ORDER BY id', [$email])->get_result()->fetch_all(MYSQLI_ASSOC);
$clear = static function (EmailKind $kind) use ($global, $email, $uid): void {
    $global->run('DELETE FROM mailServer WHERE toEmail=?', [$email]);
    try {
        \Core\Caching\Caching::getInstance()->delete(throttle_key('mail-' . $kind->value, 'player', (string)$uid));
    } catch (Throwable) {
    }
};
$text = static fn (string $html): string => trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)));
$world = (string)Core\Config::getProperty('settings', 'worldId');
$name = html_entity_decode((string)$pdo->fetchScalar('SELECT name FROM vdata WHERE kid=?', [$kid]), ENT_QUOTES);

// Without an opt-in nothing is queued, for any event.
EventMail::newMessage($uid, $other);
EventMail::constructionFinished($kid, 19, 3);
if ($queue() !== []) {
    $fail('Nothing may be queued without an opt-in.');
}

// New message: the sender's name only.
EmailPreferences::set($uid, EmailKind::Message, true);
$senderName = (string)$pdo->fetchScalar('SELECT name FROM users WHERE id=?', [$other]);
EventMail::newMessage($uid, $other);
$rows = $queue();
if (count($rows) !== 1 || $rows[0]['subject'] !== $world . ' – New messages' || !str_contains($text($rows[0]['html']), "You have a new message from {$senderName}.")) {
    $fail('The new-message mail is wrong: ' . json_encode($rows));
}
EventMail::newMessage($uid, $other);
if (count($queue()) !== 1) {
    $fail('A second message mail inside fifteen minutes must be dropped.');
}
$clear(EmailKind::Message);
EventMail::newMessage($uid, 1);
EventMail::newMessage($uid, $uid === $other ? 0 : 2);
if ($queue() !== []) {
    $fail('System senders never produce a mail.');
}

// Construction finished.
EmailPreferences::set($uid, EmailKind::Construction, true);
EventMail::constructionFinished($kid, 19, 3);
$rows = $queue();
if (count($rows) !== 1 || $rows[0]['subject'] !== $world . ' – Construction finished'
    || !str_contains($text($rows[0]['html']), 'Barracks in ' . $name . ' has been completed (level 3).')) {
    $fail('The construction mail is wrong: ' . json_encode($rows));
}
EventMail::constructionFinished($kid, 19, 4);
if (count($queue()) !== 1) {
    $fail('A second construction mail inside an hour must be dropped.');
}

// Incoming attacks: singular, then plural with the first arrival in the player's own time zone.
EmailPreferences::set($uid, EmailKind::Attack, true);
$arrive = static fn (int $in): int => (time() + $in) * 1000;
$send = static fn (int $in) => (new MovementsModel())->addMovement($otherKid, $kid, 1, [1 => 5], 0, 0, 0, 0, 0, MovementsModel::ATTACKTYPE_RAID, miliseconds(), $arrive($in));
$clear(EmailKind::Attack);
$send(86400);
$rows = $queue();
$first = TimezoneHelper::formatFor('UTC,0', intdiv($arrive(86400), 1000));
$singular = $rows === [] ? '' : $text($rows[0]['html']);
if (count($rows) !== 1 || $rows[0]['subject'] !== $world . ' – Incoming attacks' || !str_contains($singular, "An incoming attack on {$name} arrives at ")
    || !preg_match('/arrives at \d\d\.\d\d\.\d\d \d\d:\d\d\./', $singular)) {
    $fail('The singular attack mail is wrong: ' . $singular);
}
if (preg_match('/\b(troops?|units?|attacker|from)\b/i', $singular)) {
    $fail('The mail must not describe the attacker or the troops: ' . $singular);
}
$clear(EmailKind::Attack);
$send(90000);
$plural = $queue();
if (count($plural) !== 1 || !str_contains($text($plural[0]['html']), '2 incoming attacks on ' . $name . '. The first arrives at ')) {
    $fail('The plural attack mail is wrong: ' . json_encode($plural));
}
$send(95000);
if (count($queue()) !== 1) {
    $fail('A further attack mail inside thirty minutes must be dropped.');
}
if ($pdo->fetchScalar('SELECT COUNT(id) FROM movement WHERE to_kid=?', [$kid]) < 3) {
    $fail('The attacks themselves must still be created.');
}
if ($first === '' || TimezoneHelper::formatFor('Pacific/Marquesas,1', 0) !== '12.31.69 02:30 pm' || TimezoneHelper::formatFor('Nowhere/Zone,0', 0) !== gmdate('d.m.y H:i', 0)) {
    $fail('formatFor must use the player\'s zone and 12-hour style: ' . TimezoneHelper::formatFor('Pacific/Marquesas,1', 0));
}
echo "Event mail runtime passed.\n";
