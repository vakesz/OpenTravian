<?php

declare(strict_types=1);

// Event mails: wired where the events happen, opted into in Options, with the approved texts, never able to break the
// game, and never carrying message text or attacker troops.

$root = dirname(__DIR__, 2);
$read = static fn (string $path): string => (string)file_get_contents($root . '/' . $path);
$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$check(str_contains($read('game/include/Model/MovementsModel.php'), 'EventMail::attackIncoming($kid, $to_kid)') && str_contains($read('game/include/Model/MovementsModel.php'), 'self::ATTACKTYPE_NORMAL, self::ATTACKTYPE_RAID'), 'Attacks and raids notify the target village owner.');
$check(str_contains($read('game/include/Model/MessageModel.php'), 'EventMail::newMessage($to_uid, $uid)') && str_contains($read('game/include/Model/MessageModel.php'), '$autoType === 0 && !$isAlliance'), 'Player messages (not system or alliance mails) notify the recipient.');
$check(str_contains($read('game/include/Game/Buildings/BuildingAction.php'), 'EventMail::constructionFinished((int)$kid, $item_id, $level + $levels)'), 'A building reaching a level notifies its owner.');

$event = $read('game/include/Game/Mail/EventMail.php');
$check(str_contains($event, 'private static function guard') && substr_count($event, 'self::guard(') === 6 && str_contains($event, 'catch (\\Throwable'), 'Every event mail is guarded so it cannot break the game.');
$check(str_contains($event, 'MailService::queue(') && !str_contains($event, 'Mailer::') && !str_contains($event, 'mailServer'), 'Event mails only go through the mail service.');
$check(!str_contains($event, '$message') && !preg_match('/SELECT[^;]*(topic|message)\b/', $event) && !str_contains($event, 'u1'), 'No message text and no attacker troops are read for a mail.');
$check(str_contains($event, "'Incoming attacks'") === false && str_contains($event, 'MailService::subject(EmailKind::'), 'Subjects are the approved "<world> – <label>".');

$kind = $read('game/include/Game/Mail/EmailKind.php');
$check(str_contains($kind, 'return [self::Attack, self::Construction, self::Message, self::VacationEnding, self::VacationStarted, self::SitterSignIn];'), 'Options offers the six event mails, in the approved order.');
foreach (['self::Attack => [1, 1800]', 'self::Construction => [1, 3600]', 'self::Message => [1, 900]'] as $limit) {
    $check(str_contains($kind, $limit), "Rate limit: {$limit}");
}

$options = $read('game/include/resources/Templates/options/Account.php');
$check(str_contains($options, 'name="emailNotifications"') && str_contains($options, 'name="emailNotification[') && str_contains($options, "T('Mail', 'Email notifications')"), 'Options lists the notifications under the approved heading.');
$controller = $read('game/include/Controller/OptionCtrl.php');
$check(str_contains($controller, "isset(\$_POST['emailNotifications'])") && str_contains($controller, 'EmailPreferences::set('), 'Options saves the opt-ins behind the CSRF check.');
$check(strpos($controller, 'matchesChecker') < strpos($controller, "isset(\$_POST['emailNotifications'])"), 'The opt-in save follows the CSRF check.');

$en = $read('game/include/resources/Translation/en-US.php');
foreach (['%d incoming attacks on %s. The first arrives at %s.', 'An incoming attack on %s arrives at %s.', '%s in %s has been completed (level %d).', 'You have a new message from %s.'] as $text) {
    $check(str_contains($en, "\$Definition['Mail']['{$text}'] = '{$text}';"), "Approved body: {$text}");
}
echo "Event mail contract passed.\n";
