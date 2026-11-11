<?php

declare(strict_types=1);

// Vacation and sitter mail: wired where vacation starts and a sitter signs in, the ending reminder is an engine job
// that marks each vacation once, the approved texts are in place, and the rate limits are a day.

$root = dirname(__DIR__, 2);
$read = static fn (string $path): string => (string)file_get_contents($root . '/' . $path);
$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$check(str_contains($read('game/include/Model/OptionModel.php'), 'EventMail::vacationStarted($uid, $till)'), 'Entering vacation mode sends the started mail.');
$session = $read('game/include/Core/Session.php');
$check(str_contains($session, "if (\$isSitter) {\n            \$this->announceSitter((int)\$uid, (string)\$password);") && str_contains($session, 'EventMail::sitterSignedIn($ownerUid, $sitter)'), 'A sitter signing in tells the owner.');
$check(str_contains($session, '2 => 1,') && str_contains($session, '3 => 2,') && str_contains($session, 'default => 0,'), 'Only a real sitter password names a sitter.');
$launcher = $read('game/include/Core/Jobs/Launcher.php');
$check(str_contains($launcher, "[new Reminders(), 'vacationEnding']") && str_contains($launcher, 'routineJobs:vacationEndingReminders'), 'The ending reminder is an engine job.');
$reminders = $read('game/include/Game/Mail/Reminders.php');
$check(str_contains($reminders, 'INSERT IGNORE INTO email_reminder_sent') && str_contains($reminders, 'NOT EXISTS (SELECT 1 FROM email_reminder_sent') && str_contains($reminders, 'p.enabled = 1') && str_contains($reminders, 'ENDING_WITHIN = 86400'), 'Each vacation is reminded once, within a day of its end, for opted-in players.');
$check(str_contains($read('database/world.sql'), 'CREATE TABLE `email_reminder_sent`'), 'world.sql has the reminder marker table.');
$kind = $read('game/include/Game/Mail/EmailKind.php');
foreach (['self::VacationEnding, self::VacationStarted => [1, 86400]', 'self::SitterSignIn => [1, 86400]'] as $limit) {
    $check(str_contains($kind, $limit), "Rate limit: {$limit}");
}
$event = $read('game/include/Game/Mail/EventMail.php');
$check(substr_count($event, 'self::guard(') === 6, 'All six event mails are guarded.');
$en = $read('game/include/resources/Translation/en-US.php');
foreach (['Your vacation mode ends at %s.', 'Your vacation mode has started. It ends at %s.', '%s signed in to your account at %s.'] as $text) {
    $check(str_contains($en, "\$Definition['Mail']['{$text}'] = '{$text}';"), "Approved body: {$text}");
}
echo "Reminder mail contract passed.\n";
