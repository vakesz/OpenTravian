<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

function failSmtpDeliveryContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function assertSmtpDelivery(bool $condition, string $message): void
{
    if (!$condition) {
        failSmtpDeliveryContract($message);
    }
}

$read = static function (string $path) use ($root): string {
    $contents = file_get_contents($root . '/' . $path);
    if ($contents === false) {
        failSmtpDeliveryContract('Could not read ' . $path . '.');
    }

    return $contents;
};

$state = sys_get_temp_dir() . '/travian-smtp-contract-' . bin2hex(random_bytes(8));
if (!mkdir($state, 0700, true) && !is_dir($state)) {
    failSmtpDeliveryContract('Could not create the SMTP contract state directory.');
}
define('SMTP_CONFIG_FILE', $state . '/smtp.local.php');
require_once $root . '/game/include/Game/SupportContact.php';
require_once $root . '/game/include/Game/SmtpConfig.php';

try {
    $submitted = [
        'smtp_host' => 'smtp.example.test',
        'smtp_port' => '587',
        'smtp_encryption' => 'starttls',
        'smtp_from' => 'operator@example.test',
        'smtp_auth' => '1',
        'smtp_username' => 'operator@example.test',
        'smtp_password' => str_repeat('x', 32),
    ];
    $parsed = \Game\SmtpConfig::fromSubmission($submitted);
    assertSmtpDelivery($parsed['config'] !== null && $parsed['errors'] === [], 'A complete TLS SMTP setup must validate.');
    assertSmtpDelivery(\Game\SmtpConfig::save($parsed['config']), 'SMTP configuration must save atomically.');
    assertSmtpDelivery((fileperms(SMTP_CONFIG_FILE) & 0777) === 0600, 'SMTP configuration must be mode 0600.');
    $loaded = \Game\SmtpConfig::current();
    assertSmtpDelivery($loaded !== null && $loaded['password'] === str_repeat('x', 32), 'Saved SMTP configuration must load from protected state.');
    $form = \Game\SmtpConfig::formValues($loaded);
    assertSmtpDelivery(!array_key_exists('password', $form) && $form['passwordConfigured'], 'SMTP forms must not expose the saved password.');

    $update = $submitted;
    $update['smtp_password'] = '';
    $preserved = \Game\SmtpConfig::fromSubmission($update, $loaded, true);
    assertSmtpDelivery($preserved['config'] !== null && $preserved['config']['password'] === str_repeat('x', 32), 'A blank admin password must preserve the existing secret.');
    $invalid = $submitted;
    $invalid['smtp_encryption'] = 'none';
    $invalid['smtp_password'] = '';
    assertSmtpDelivery(\Game\SmtpConfig::fromSubmission($invalid)['config'] === null, 'Unencrypted or incomplete SMTP must be rejected.');
} finally {
    if (is_file(SMTP_CONFIG_FILE)) {
        unlink(SMTP_CONFIG_FILE);
    }
    if (is_file(SMTP_CONFIG_FILE . '.lock')) {
        unlink(SMTP_CONFIG_FILE . '.lock');
    }
    rmdir($state);
}

$smtpConfig = $read('game/include/Game/SmtpConfig.php');
$setup = $read('game/deployment/public/setup.php')
    . $read('game/include/Game/Installer/InstallInput.php')
    . $read('game/include/Game/Installer/WorldInstaller.php');
$setupView = $read('game/deployment/include/setup-view.php');
$admin = $read('game/include/admin/include/Controllers/ConfigurationDetailsCtrl.php');
$env = $read('game/deployment/include/env.php');
$ignore = $read('.gitignore');
$schema = $read('database/account.sql');
$queueSchema = $read('game/include/Core/Mail/MailQueueSchema.php');
$mailer = $read('game/include/Core/Helper/Mailer.php');
$worker = $read('game/include/Core/Mail/QueueWorker.php');
$transport = $read('game/include/Core/Mail/SmtpTransport.php');
$launcher = $read('game/include/Core/Jobs/Launcher.php');

foreach ([
    'SMTP_CONFIG_FILE',
    "'/smtp.local.php'",
] as $needle) {
    assertSmtpDelivery(str_contains($env, $needle), 'The runtime must define protected SMTP state storage.');
}
assertSmtpDelivery(str_contains($ignore, '/smtp.local.php'), 'The protected SMTP state file must be ignored by Git.');
foreach (['SmtpConfig::fromSubmission($values)', 'SmtpConfig::save($values[\'smtp\'])', "'smtp_host'", "'smtp_password'"] as $needle) {
    assertSmtpDelivery(str_contains($setup, $needle), 'Setup must collect and persist required SMTP configuration: ' . $needle);
}
foreach (['SMTP host', 'SMTP port', 'SMTP encryption', 'SMTP sender email', 'SMTP authentication'] as $needle) {
    assertSmtpDelivery(str_contains($setupView, $needle), 'Setup must render the required SMTP field: ' . $needle);
}
foreach (['saveSmtp', 'SmtpConfig::save', 'smtp_password', 'needsRestart=1'] as $needle) {
    assertSmtpDelivery(str_contains($admin, $needle), 'Multihunter must be able to update protected SMTP delivery settings: ' . $needle);
}

$globalConfigStart = strpos($setup, 'function buildGlobalConfig');
$globalConfigEnd = strpos($setup, 'public static function buildConnection');
assertSmtpDelivery($globalConfigStart !== false && $globalConfigEnd !== false, 'Could not locate generated global-config builder.');
$globalConfigBuilder = substr($setup, $globalConfigStart, $globalConfigEnd - $globalConfigStart);
assertSmtpDelivery(!str_contains($globalConfigBuilder, 'smtp_password') && !str_contains($globalConfigBuilder, "'smtp'"), 'SMTP secrets must not enter globalConfig.php.');

foreach (['`replyTo`', '`status`', '`attempts`', '`availableAt`', '`lockedAt`', '`leaseToken`', '`lastError`', '`mailQueueClaim`'] as $needle) {
    assertSmtpDelivery(str_contains($schema, $needle), 'Fresh account schema is missing mail queue field/index ' . $needle . '.');
}
foreach (['FOR UPDATE SKIP LOCKED', 'leaseToken', 'MAX_ATTEMPTS = 8', "? 'failed' : 'queued'", 'DELETE FROM mailServer'] as $needle) {
    assertSmtpDelivery(str_contains($worker, $needle), 'Queue worker is missing reliable delivery behavior: ' . $needle);
}
foreach (['replyTo', 'MailQueueSchema::isReady', 'SupportContact::isValid', '$statement->affected_rows'] as $needle) {
    assertSmtpDelivery(str_contains($mailer, $needle), 'Mailer must validate and queue reply metadata: ' . $needle);
}
foreach (['vendor/autoload.php', 'setFrom($smtp[\'from\'])', 'Sender = $smtp[\'from\']', 'addReplyTo', 'ENCRYPTION_STARTTLS', 'ENCRYPTION_SMTPS'] as $needle) {
    assertSmtpDelivery(str_contains($transport, $needle), 'SMTP transport is missing secure delivery configuration: ' . $needle);
}
assertSmtpDelivery(!str_contains($transport, 'SMTPOptions'), 'SMTP transport must not disable TLS peer verification.');
assertSmtpDelivery(str_contains($launcher, "new Job('mailDelivery', 5") && str_contains($launcher, 'QueueWorker'), 'The engine must run the bounded mail-delivery worker.');
assertSmtpDelivery(is_file($root . '/services/api/include/vendor/autoload.php'), 'The intentional PHPMailer runtime dependency is missing.');

echo "SMTP delivery contract passed.\n";
