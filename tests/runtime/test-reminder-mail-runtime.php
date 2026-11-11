<?php

declare(strict_types=1);

// Vacation and sitter mail for a throwaway fixture player: the started and ending reminders (once per vacation, only
// within a day of the end, only for players who opted in) and the sitter sign-in mail. Nothing is sent: rows are only
// queued, and the test removes them.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Reminder mail runtime skipped outside the php container.\n";
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
use Game\Mail\Reminders;
use Model\OptionModel;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $user = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Reminder mail runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$uid = $user['id'];
// A sitter whose password differs from the fixture's (a second fixture, cloned from the same player, with its own password).
$sitter = fixtureVillageCreate();
$other = $sitter['id'];
$pdo->run('UPDATE users SET password=? WHERE id=?', [password_hash('zzfx-sitter-' . getmypid(), PASSWORD_DEFAULT), $other]);
if ((string)$pdo->fetchScalar('SELECT password FROM users WHERE id=?', [$other]) === $user['password']) {
    $fail('The sitter fixture needs a different password.');
}
$email = 'zzfx-' . getmypid() . '@example.invalid';
$global = GlobalDB::getInstance();
register_shutdown_function(static function () use ($global, $pdo, $uid): void {
    $global->run("DELETE FROM mailServer WHERE toEmail LIKE 'zzfx-%@example.invalid'");
    $pdo->run('DELETE FROM email_preference WHERE uid=?', [$uid]);
    $pdo->run('DELETE FROM email_reminder_sent WHERE uid=?', [$uid]);
});
// A player who has logged in before: the clone of a fresh world's player has not, and the session would redirect away.
$pdo->run('UPDATE users SET last_login_time=?, last_owner_login_time=? WHERE id=?', [time(), time(), $uid]);
$_SESSION[WebService::fixSessionPrefix('uid')] = $user['id'];
$_SESSION[WebService::fixSessionPrefix('pw')] = $user['password'];
Session::getInstance();
$pdo->run("UPDATE users SET email=?, email_verified=1, access=1, timezone='UTC,0', vacationActiveTil=0 WHERE id=?", [$email, $uid]);
$queue = static fn (): array => $global->run('SELECT subject, html FROM mailServer WHERE toEmail=? ORDER BY id', [$email])->get_result()->fetch_all(MYSQLI_ASSOC);
$text = static fn (string $html): string => trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)));
$clear = static function (EmailKind $kind) use ($global, $email, $uid): void {
    $global->run('DELETE FROM mailServer WHERE toEmail=?', [$email]);
    try {
        \Core\Caching\Caching::getInstance()->delete(throttle_key('mail-' . $kind->value, 'player', (string)$uid));
    } catch (Throwable) {
    }
};
$world = (string)Core\Config::getProperty('settings', 'worldId');

// Vacation started: opt-in only, the end time in the player's zone.
(new OptionModel())->enterVacationMode($uid, 1);
if ($queue() !== []) {
    $fail('Vacation mail needs an opt-in.');
}
$pdo->run('UPDATE users SET vacationActiveTil=0 WHERE id=?', [$uid]);
EmailPreferences::set($uid, EmailKind::VacationStarted, true);
(new OptionModel())->enterVacationMode($uid, 1);
$till = (int)$pdo->fetchScalar('SELECT vacationActiveTil FROM users WHERE id=?', [$uid]);
$rows = $queue();
if (count($rows) !== 1 || $rows[0]['subject'] !== $world . ' – Vacation started'
    || !str_contains($text($rows[0]['html']), 'Your vacation mode has started. It ends at ' . gmdate('d.m.y H:i', $till) . '.')) {
    $fail('The vacation-started mail is wrong: ' . json_encode($rows));
}
$clear(EmailKind::VacationStarted);

// Vacation ending: within a day of the end, once per vacation, only for opted-in players.
$reminders = new Reminders();
$pdo->run('UPDATE users SET vacationActiveTil=? WHERE id=?', [time() + 3600, $uid]);
$reminders->vacationEnding();
if ($queue() !== []) {
    $fail('Vacation-ending mail needs an opt-in.');
}
EmailPreferences::set($uid, EmailKind::VacationEnding, true);
$pdo->run('UPDATE users SET vacationActiveTil=? WHERE id=?', [time() + 3 * 86400, $uid]);
$reminders->vacationEnding();
if ($queue() !== []) {
    $fail('A vacation that ends in three days is not reminded yet.');
}
$end = time() + 3600;
$pdo->run('UPDATE users SET vacationActiveTil=? WHERE id=?', [$end, $uid]);
$reminders->vacationEnding();
$rows = $queue();
if (count($rows) !== 1 || $rows[0]['subject'] !== $world . ' – Vacation ending soon'
    || !str_contains($text($rows[0]['html']), 'Your vacation mode ends at ' . gmdate('d.m.y H:i', $end) . '.')) {
    $fail('The vacation-ending mail is wrong: ' . json_encode($rows));
}
$clear(EmailKind::VacationEnding);
if ($reminders->vacationEnding() !== 0 || $queue() !== []) {
    $fail('A vacation is reminded once.');
}
$pdo->run('UPDATE users SET vacationActiveTil=0 WHERE id=?', [$uid]);

// A sitter signed in: the sitter's name and the time, only for an opted-in owner.
$pdo->run('UPDATE users SET sit1Uid=?, sit1Permissions=55 WHERE id=?', [$other, $uid]);
$sitterHash = (string)$pdo->fetchScalar('SELECT password FROM users WHERE id=?', [$other]);
$announce = new ReflectionMethod(Session::class, 'announceSitter');
$announce->invoke(Session::getInstance(), $uid, $sitterHash);
if ($queue() !== []) {
    $fail('Sitter mail needs an opt-in.');
}
EmailPreferences::set($uid, EmailKind::SitterSignIn, true);
$announce->invoke(Session::getInstance(), $uid, 'not-a-sitter-password');
if ($queue() !== []) {
    $fail('A password that is nobody\'s sitter password announces nothing.');
}
$announce->invoke(Session::getInstance(), $uid, $sitterHash);
$sitterName = (string)$pdo->fetchScalar('SELECT name FROM users WHERE id=?', [$other]);
$rows = $queue();
if (count($rows) !== 1 || $rows[0]['subject'] !== $world . ' – A sitter signed in'
    || !preg_match('/^.*Hello .*' . preg_quote($sitterName, '/') . ' signed in to your account at \d\d\.\d\d\.\d\d \d\d:\d\d\./', $text($rows[0]['html']))) {
    $fail('The sitter mail is wrong: ' . json_encode($rows));
}
$announce->invoke(Session::getInstance(), $uid, $sitterHash);
if (count($queue()) !== 1) {
    $fail('A second sitter mail within a day must be dropped.');
}
echo "Reminder mail runtime passed.\n";
