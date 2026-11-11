<?php

declare(strict_types=1);

// The Discord world-event webhook is off by default, keeps its URL only in protected state (never in SQL, forms or
// logs), is configured on its own admin page, queues events through the world database, posts from an isolated
// engine job with a 3 second limit, and is part of the encrypted state archive's file set.

function failWebhookContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $contents = file_get_contents($root . '/' . $path);
    if ($contents === false) {
        failWebhookContract("Cannot read $path.");
    }

    return $contents;
};
require_once $root . '/game/include/Game/Webhook/WebhookConfig.php';

use Game\Webhook\WebhookConfig;

// Config: off by default, URL validation, file mode, no URL in the admin values.
$directory = sys_get_temp_dir() . '/webhook-contract-' . getmypid();
mkdir($directory, 0700);
register_shutdown_function(static function () use ($directory): void {
    array_map('unlink', glob($directory . '/*') ?: []);
    @rmdir($directory);
});
$config = new WebhookConfig($directory . '/webhook.local.php');
if ($config->enabled() || $config->url() !== null || $config->events() !== []) {
    failWebhookContract('The webhook must be off, without URL or events, until an operator configures it.');
}
$url = 'https://discord.com/api/webhooks/123456789012345678/abcdefghijklmnopqrstuvwxyz0123456789';
foreach (['http://discord.com/api/webhooks/123456789012345678/abcdefghijklmnopqrstuvwxyz', 'https://example.com/api/webhooks/123456789012345678/abcdefghijklmnopqrstuvwxyz', 'https://discord.com/api/webhooks/x/y', $url . "\n"] as $bad) {
    if (WebhookConfig::validUrl($bad)) {
        failWebhookContract("Only a discord.com webhook address is accepted: $bad");
    }
}
$parsed = $config->fromSubmission(['webhook_enabled' => '1', 'webhook_url' => $url, 'webhook_events' => ['worldStart', 'nonsense'], 'webhook_milestones' => '5, 100']);
if ($parsed['errors'] !== [] || $parsed['config']['events'] !== ['worldStart'] || $parsed['config']['milestones'] !== [5, 100]) {
    failWebhookContract('The form must keep only known events and valid milestones.');
}
if ($config->fromSubmission(['webhook_enabled' => '1'])['errors'] === []) {
    failWebhookContract('Switching the webhook on without a URL must be refused.');
}
if (!$config->save($parsed['config']) || (fileperms($config->path()) & 0777) !== 0600 || !$config->enabled()) {
    failWebhookContract('The state file must be saved with mode 0600.');
}
if (str_contains(json_encode($config->formValues()), 'abcdefghijklmnop') || $config->fromSubmission(['webhook_url' => ''])['config']['url'] !== $url) {
    failWebhookContract('A blank URL keeps the stored one and the URL never reaches the form values.');
}
if (!$config->save($config->fromSubmission(['webhook_remove' => '1'])['config']) || $config->enabled() || $config->url() !== null) {
    failWebhookContract('Removing the URL must switch the webhook off.');
}
if ($config->fromSubmission(['webhook_enabled' => '1', 'webhook_url' => $url, 'webhook_milestones' => '0, 101'])['errors'] === []) {
    failWebhookContract('Milestones outside 1 to 100 must be refused.');
}

// Wiring.
$world = $read('database/world.sql');
if (!str_contains($world, 'CREATE TABLE `webhook_queue`') || !str_contains($world, 'UNIQUE KEY `uniq_webhook_dedupe`')) {
    failWebhookContract('world.sql must create the webhook queue with its dedupe key.');
}
$launcher = $read('game/include/Core/Jobs/Launcher.php');
if (!str_contains($launcher, "new Job('webhookDelivery', 5,") || !str_contains($launcher, 'self::getInstance()->webhookDelivery();')) {
    failWebhookContract('The engine must run the webhook delivery as its own job.');
}
if (!str_contains($read('game/include/Game/Webhook/DiscordSender.php'), 'TIMEOUT_SECONDS = 3')
    || !str_contains($read('game/include/Game/Webhook/DiscordSender.php'), 'CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS')) {
    failWebhookContract('The webhook post must be bounded to three seconds.');
}
foreach ([
    'game/include/Model/AllianceModel.php' => "WorldEvents::record('allianceFounded'",
    'game/include/Model/ArtefactsModel.php' => "WorldEvents::record('artifactsReleased')",
    'game/include/Core/Automation.php' => "WorldEvents::record('worldStart')",
] as $path => $needle) {
    if (!str_contains($read($path), $needle)) {
        failWebhookContract("$path must raise its world event: $needle");
    }
}
if (!str_contains($read('game/include/Model/ArtefactsModel.php'), "WorldEvents::record('wwPlansReleased')")
    || !str_contains($read('game/include/Core/Automation.php'), 'WorldEvents::finished((int) $resultCode)')) {
    failWebhookContract('The plan release and the end of the round must raise their events.');
}
if (!str_contains($read('game/include/Game/Admin/AdminMenu.php'), "['admin.php?action=webhook', 'Discord webhook'")) {
    failWebhookContract('The admin menu must link the webhook page.');
}
$controller = $read('game/include/admin/include/Controllers/WebhookCtrl.php');
if (!str_contains($controller, 'Session::validateChecker()') || !str_contains($controller, 'AdminUi::secret(\'webhook_url\'')) {
    failWebhookContract('The admin page must check the session checker and take the URL as a write-only field.');
}
// The URL is never written to SQL or a log by the webhook code.
foreach (glob($root . '/game/include/Game/Webhook/*.php') ?: [] as $file) {
    $source = (string) file_get_contents($file);
    if (preg_match('/(error_log|Log::addLog|->run\([^;]*\$url)/', $source) === 1) {
        failWebhookContract(basename($file) . ' must not log or store the webhook URL.');
    }
}
// The state archive takes every *.local.* file, so webhook.local.php is in its file set.
require_once $root . '/game/include/Core/Database/StateArchive.php';
file_put_contents($directory . '/webhook.local.php', "<?php\n");
$names = array_map('basename', Core\Database\StateArchive::files($directory));
if (!in_array('webhook.local.php', $names, true)) {
    failWebhookContract('webhook.local.php must be part of the encrypted state archive.');
}

echo "Webhook contract passed.\n";
