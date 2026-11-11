<?php

declare(strict_types=1);

// The Discord world-event webhook against the world database with a fake sender: events are queued only while the
// webhook is on and the event is ticked, once per subject, bounded in number; the worker posts one message per tick,
// waits out 429 answers, backs off failures and drops a row after eight; and no message holds anything but the
// whitelisted public facts.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Webhook runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/ajax.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use Core\Database\DB;
use Game\Webhook\QueueWorker;
use Game\Webhook\WebhookConfig;
use Game\Webhook\WebhookSender;
use Game\Webhook\WorldEvents;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$db = DB::getInstance();
$directory = sys_get_temp_dir() . '/webhook-test-' . getmypid();
mkdir($directory, 0700);
$path = $directory . '/webhook.local.php';
register_shutdown_function(static function () use ($db, $directory, $path): void {
    $db->query("DELETE FROM webhook_queue WHERE dedupe_key LIKE 'zztest%' OR dedupe_key LIKE 'allianceFounded:zz%' OR dedupe_key LIKE 'wwMilestone:%' OR dedupe_key IN ('worldStart','worldEnd','roundWinner','artifactsReleased','wwPlansReleased') OR dedupe_key LIKE 'test:%'");
    @unlink($path);
    @rmdir($directory);
});
$url = 'https://discord.com/api/webhooks/123456789012345678/abcdefghijklmnopqrstuvwxyz0123456789';
$config = new WebhookConfig($path);
$save = static function (array $override = []) use ($config, $url): void {
    $config->save($override + ['enabled' => true, 'url' => $url, 'events' => array_keys(WebhookConfig::EVENTS), 'milestones' => [10, 50]]);
};
$count = static fn (string $where = '1'): int => (int)$db->fetchScalar("SELECT COUNT(*) FROM webhook_queue WHERE $where");
$db->query("DELETE FROM webhook_queue WHERE dedupe_key IN ('worldStart','worldEnd','roundWinner','artifactsReleased','wwPlansReleased') OR dedupe_key LIKE 'wwMilestone:%' OR dedupe_key LIKE 'allianceFounded:zz%' OR dedupe_key LIKE 'test:%'");
$events = new WorldEvents($config, $db);

// Off by default and per event.
if ($events->enqueue('worldStart') || $count("dedupe_key='worldStart'") !== 0) {
    $fail('Nothing may be queued while the webhook is not configured.');
}
$save(['enabled' => false]);
if ($events->enqueue('worldStart')) {
    $fail('Nothing may be queued while the webhook is switched off.');
}
$save(['events' => ['worldEnd']]);
if ($events->enqueue('worldStart') || !$events->enqueue('worldEnd')) {
    $fail('Only the ticked events are queued.');
}
if ($events->enqueue('worldEnd') || $count("dedupe_key='worldEnd'") !== 1) {
    $fail('An event subject is queued once.');
}

// The state file: mode, and the URL stays out of the admin values.
if ((fileperms($path) & 0777) !== 0600) {
    $fail('webhook.local.php must be mode 0600.');
}
if (str_contains(json_encode($config->formValues()), 'abcdefghijklmnop')) {
    $fail('The webhook URL must never reach the admin form values.');
}

// Privacy: the message of an alliance event has the whitelisted facts only.
$save();
$events->enqueue('allianceFounded', 'zz1', ['alliance' => ['name' => '@everyone *Raiders*', 'tag' => 'RD']]);
$payload = (string)$db->fetchScalar("SELECT payload FROM webhook_queue WHERE dedupe_key='allianceFounded:zz1'");
$message = json_decode($payload, true);
if (($message['allowed_mentions']['parse'] ?? null) !== [] || str_contains($payload, '@everyone')) {
    $fail('Mentions must be switched off and neutralised in a message.');
}
if (!str_contains($payload, 'Alliance founded') || !str_contains($payload, 'RD')) {
    $fail('The alliance message must name the alliance by its tag and name.');
}

// Milestones come from the highest World Wonder level.
$top = (int)$db->fetchScalar('SELECT MAX(f99) FROM fdata');
$db->query("DELETE FROM webhook_queue WHERE dedupe_key LIKE 'wwMilestone:%'");
$events->pollMilestones();
$expected = count(array_filter([10, 50], static fn (int $level): bool => $top >= $level));
if ($count("dedupe_key LIKE 'wwMilestone:%'") !== $expected) {
    $fail("Reached World Wonder levels (top {$top}) must be queued once each; expected {$expected}.");
}

// The worker: one post per tick, 429 waited out, failures backed off then dropped, success recorded.
$db->query("DELETE FROM webhook_queue WHERE dedupe_key LIKE 'zztest%'");
foreach (['zztest1', 'zztest2'] as $key) {
    $db->run('INSERT INTO webhook_queue (dedupe_key, payload, created_at, next_attempt_at) VALUES (?, ?, ?, 0)', [$key, '{"zz":1}', time()]);
}
$db->query("UPDATE webhook_queue SET sent_at=1 WHERE sent_at=0 AND dedupe_key NOT LIKE 'zztest%'");
$sender = new class implements WebhookSender {
    /** @var list<array{status:int,retryAfter:int}> */
    public array $answers = [];
    public int $posts = 0;
    public string $lastUrl = '';

    public function send(string $url, string $json): array
    {
        ++$this->posts;
        $this->lastUrl = $url;

        return array_shift($this->answers) ?? ['status' => 204, 'retryAfter' => 0];
    }
};
$worker = new QueueWorker($config, $sender, $db);
$sender->answers = [['status' => 204, 'retryAfter' => 0]];
if ($worker->drain() !== 1 || $sender->posts !== 1 || $count("dedupe_key LIKE 'zztest%' AND sent_at>1") !== 1) {
    $fail('A tick posts exactly one due message and records it as sent.');
}
$sender->answers = [['status' => 429, 'retryAfter' => 7]];
if ($worker->drain() !== 0 || (int)$db->fetchScalar("SELECT attempts FROM webhook_queue WHERE dedupe_key LIKE 'zztest%' AND sent_at=0") !== 0
    || (int)$db->fetchScalar("SELECT next_attempt_at FROM webhook_queue WHERE dedupe_key LIKE 'zztest%' AND sent_at=0") <= time() + 3) {
    $fail('A 429 answer must be waited out without counting as a failed attempt.');
}
if ($worker->drain() !== 0 || $sender->posts !== 2) {
    $fail('A message that is not due yet is not posted again.');
}
$db->query("UPDATE webhook_queue SET next_attempt_at=0 WHERE dedupe_key LIKE 'zztest%' AND sent_at=0");
for ($attempt = 1; $attempt <= 8; ++$attempt) {
    $sender->answers = [['status' => 500, 'retryAfter' => 0]];
    $worker->drain();
    $db->query("UPDATE webhook_queue SET next_attempt_at=0 WHERE dedupe_key LIKE 'zztest%' AND sent_at=0 AND dropped=0");
}
if ((int)$db->fetchScalar("SELECT dropped FROM webhook_queue WHERE dedupe_key LIKE 'zztest%' AND sent_at=0 ORDER BY id LIMIT 1") !== 1) {
    $fail('A message that failed eight times must be dropped.');
}
$posts = $sender->posts;
$save(['enabled' => false]);
$worker->drain();
if ($sender->posts !== $posts) {
    $fail('A switched-off webhook must not post.');
}

// The queue stays bounded.
$save();
$db->query("DELETE FROM webhook_queue WHERE dedupe_key LIKE 'zztest%'");
$pending = (int)$db->fetchScalar('SELECT COUNT(*) FROM webhook_queue WHERE sent_at=0 AND dropped=0');
for ($i = 0; $i < WorldEvents::MAX_PENDING + 5; ++$i) {
    $events->enqueue('allianceFounded', 'zz' . (100 + $i), ['alliance' => ['name' => 'A', 'tag' => 'T']]);
}
if ((int)$db->fetchScalar('SELECT COUNT(*) FROM webhook_queue WHERE sent_at=0 AND dropped=0') > WorldEvents::MAX_PENDING) {
    $fail('The pending queue must not grow beyond its cap.');
}

echo "Webhook runtime passed.\n";
