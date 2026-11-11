<?php

declare(strict_types=1);

// The shared mail design: the queue row carries an unsubscribe URL that becomes List-Unsubscribe headers, every mail
// has the opt-in footer, the newsletter goes only to opted-in players through compose, preview and queue, the legacy
// newsletter table and its admin pages are gone, and the unsubscribe route is token-only.

$root = dirname(__DIR__, 2);
$read = static fn (string $path): string => (string)file_get_contents($root . '/' . $path);
$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$account = $read('database/account.sql');
$check(str_contains($account, '`unsubscribeUrl` varchar(512)') && !str_contains($account, 'newsletter'), 'account.sql: the queue has unsubscribeUrl and the legacy newsletter table is gone.');
$check(str_contains($read('game/include/Core/Mail/MailQueueSchema.php'), "'unsubscribeUrl',"), 'The schema check requires the column.');
$transport = $read('game/include/Core/Mail/SmtpTransport.php');
$check(str_contains($transport, "'List-Unsubscribe'") && str_contains($transport, "'List-Unsubscribe-Post', 'List-Unsubscribe=One-Click'"), 'The transport sends the one-click unsubscribe headers.');
$check(str_contains($read('game/include/Core/Mail/QueueWorker.php'), 'unsubscribeUrl'), 'The queue worker hands the URL to the transport.');

foreach (['ImportEmailCtrl', 'DeleteEmailNewsletterCtrl'] as $retired) {
    $check(!is_file($root . "/game/include/admin/include/Controllers/{$retired}.php"), "{$retired} is retired.");
}
$check(!is_file($root . '/game/include/admin/include/templates/importEmail.php') && !is_file($root . '/game/include/admin/include/templates/deleteEmailNewsletter.php'), 'The legacy newsletter templates are retired.');
$dispatcher = $read('game/include/admin/include/Core/Dispatcher.php');
$check(!str_contains($dispatcher, 'importEmail') && !str_contains($dispatcher, 'deleteEmailNewsletter'), 'The menu no longer lists the retired pages.');
foreach (['game/include/Model/OptionModel.php', 'game/include/Model/EmailVerification.php', 'game/include/Model/InstallerModel.php', 'game/include/admin/include/Controllers/SendEmailCtrl.php'] as $file) {
    $check(!preg_match('/FROM newsletter|INTO newsletter|subscribeNewsletter/', $read($file)), "{$file} no longer uses the legacy newsletter table.");
}

$admin = $read('game/include/admin/include/Controllers/SendEmailCtrl.php');
$check(str_contains($admin, 'Newsletter::recipients()') && str_contains($admin, 'Newsletter::campaignAllowed()') && str_contains($admin, "AuditLog::record('mail.newsletter-queued'")
    && strpos($admin, "'preview'") !== false && str_contains($admin, "value=\"queue\""), 'The admin page previews, then queues, limited and audited.');
$check(str_contains($admin, "T('Mail', 'Email delivery is not configured for this world. Messages stay queued until it is.')") && str_contains($admin, "' They are waiting in the queue, not sent.'"), 'Without SMTP the page says nothing was sent.');
$check(!str_contains($admin, 'Mailer::sendBatch') && !str_contains($admin, 'sentTo'), 'The page does not pretend to have sent mail.');

$newsletter = $read('game/include/Game/Mail/Newsletter.php');
$check(str_contains($newsletter, 'u.newsletter = 1') && str_contains($newsletter, 'u.email_verified = 1') && str_contains($newsletter, 'u.access > 0') && str_contains($newsletter, 'NOT EXISTS (SELECT 1 FROM deleting'), 'Recipients are opted-in, verified, unbanned and not in deletion.');
$service = $read('game/include/Game/Mail/MailService.php');
$check(str_contains($service, 'incrementWithinLimit') && str_contains($service, 'UnsubscribeToken::url') && str_contains($service, "'mail/message'") && str_contains($service, 'deliveryConfigured'), 'Every mail is rate limited and carries an unsubscribe link.');
$template = $read('game/include/resources/Templates/mail/message.php');
$check(str_contains($template, 'You are receiving this email because you opted in on this game world.') && str_contains($template, "'Unsubscribe'") && str_contains($template, 'unsubscribeUrl'), 'The mail footer is the approved text with the link.');
$check(!is_dir($root . '/assets/gpack/mail'), 'The mails use no images, so assets/gpack/mail stays absent.');

$routes = $read('game/include/mainInclude.php');
$check(str_contains($routes, "'unsubscribe' => 'UnsubscribeCtrl'") && str_contains($routes, '/unsubscribe/'), 'The unsubscribe route exists.');
$controller = $read('game/include/Controller/UnsubscribeCtrl.php');
$check(str_contains($controller, 'UnsubscribeToken::verify') && str_contains($controller, "AuditLog::record('mail.unsubscribe'") && str_contains($controller, "throttle_public_form('unsubscribe'"), 'The unsubscribe link is token-checked, throttled and audited.');

$en = $read('game/include/resources/Translation/en-US.php');
foreach (['You are receiving this email because you opted in on this game world.', 'Unsubscribe', 'You have been unsubscribed.', 'Confirm', 'All email', 'Email notifications', 'Newsletter', 'Incoming attacks', 'New messages', 'Construction finished', 'Vacation ending soon', 'Vacation started', 'A sitter signed in', 'Email delivery is not configured for this world. Messages stay queued until it is.'] as $text) {
    $check(str_contains($en, "\$Definition['Mail']['{$text}'] = '{$text}';"), "Approved mail copy: {$text}");
}
$options = $read('game/include/resources/Templates/options/Account.php');
$check(str_contains($options, 'name="newsletter"') && str_contains($options, "T('Mail', 'Email delivery is not configured for this world."), 'Options offers the newsletter opt-in and the delivery note.');
echo "Newsletter mail contract passed.\n";
