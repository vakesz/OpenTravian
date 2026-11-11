<?php

declare(strict_types=1);

// Contract: a world installed without SMTP says so instead of offering email
// features that cannot work (verification, password reset), and queues nothing.

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string)file_get_contents($root . '/' . $path);

function expectNoSmtp(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$en = $read('game/include/resources/Translation/en-US.php');
expectNoSmtp(
    str_contains($en, "'deliveryNotConfigured' => 'Email delivery is not configured for this world, so verification emails cannot be sent.'"),
    'EVerify.errors.deliveryNotConfigured must carry the approved wording.'
);
expectNoSmtp(
    str_contains($en, "\$Definition['Login']['pwResetUnavailable'] = 'Password reset by email is not available because email delivery is not configured for this world.';"),
    'Login.pwResetUnavailable must carry the approved wording.'
);

$ajax = $read('game/include/Controller/Ajax/verify.php');
$newProgress = strpos($ajax, "fail('deliveryNotConfigured')");
expectNoSmtp(
    $newProgress !== false && strpos($ajax, '!SmtpConfig::isConfigured()') < $newProgress
        && $newProgress < strpos($ajax, 'EmailVerification::sendVerificationEmail'),
    'Starting a verification must be refused before any mail is queued when SMTP is off.'
);
expectNoSmtp(
    str_contains($read('game/include/Controller/EmailVerificationCtrl.php'), "isset(\$_GET['resendEmail']) && \\Game\\SmtpConfig::isConfigured()"),
    'Resending a verification email must require configured delivery.'
);
$segment = $read('game/include/resources/Templates/verify/segment.php');
expectNoSmtp(
    str_contains($segment, 'errors.deliveryNotConfigured') && str_contains($segment, 'if ($emailDelivery)'),
    'The verification page must show the notice and hide the email form without delivery.'
);

$login = $read('game/include/Controller/LoginCtrl.php');
$forgotten = (string)substr($login, (int)strpos($login, 'private function passwordForgotten'));
expectNoSmtp(
    strpos($forgotten, 'SmtpConfig::isConfigured()') < strpos($forgotten, 'addNewPassword')
        && str_contains($forgotten, "T('Login', 'pwResetUnavailable')"),
    'Password reset must refuse before queueing mail when SMTP is off.'
);
$template = $read('game/include/resources/Templates/system/login.php');
expectNoSmtp(
    str_contains($template, 'if (!$emailAvailable)') && str_contains($template, '<?php if ($emailAvailable): ?>'),
    'The login page must hide the forgot-password link and form without delivery.'
);

echo "No-SMTP email features contract passed.\n";
