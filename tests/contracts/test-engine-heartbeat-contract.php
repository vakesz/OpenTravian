<?php

declare(strict_types=1);

// Contract: engine heartbeat status mapping, stale-code fingerprint, and removal of the systemd-era engine control.
// Loads only the pure parts of EngineHeartbeat (no Redis or database needed).

$root = dirname(__DIR__, 2);
$source = file_get_contents($root . '/game/include/Core/Jobs/EngineHeartbeat.php');
if ($source === false) {
    fwrite(STDERR, "Cannot read EngineHeartbeat.\n");
    exit(1);
}
require_once $root . '/game/include/Core/Jobs/EngineHeartbeat.php';

use Core\Jobs\EngineHeartbeat;

function check(bool $ok, string $message): void
{
    if (!$ok) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

$now = 1_000_000;
check(EngineHeartbeat::describe(null, $now) === ['state' => 'not_running', 'text' => 'Not running'], 'No beat must be Not running.');
check(EngineHeartbeat::describe(['ts' => $now - 7], $now)['text'] === 'Running (last beat 7s ago)', 'Fresh beat text.');
check(EngineHeartbeat::describe(['ts' => $now - 90], $now)['state'] === 'running', '90s is still running.');
check(EngineHeartbeat::describe(['ts' => $now - 91], $now)['state'] === 'stalled', '91s is stalled.');
check(EngineHeartbeat::describe(['ts' => $now - 600], $now)['text'] === 'Stalled (last beat 10m ago)', 'Stalled text.');
check(str_contains(EngineHeartbeat::describe(['ts' => $now - 3, 'state' => 'paused'], $now)['text'], 'paused'), 'Paused is shown.');
$catching = EngineHeartbeat::describe(['ts' => $now - 3, 'state' => 'catching-up', 'remaining' => 1234], $now);
check($catching['state'] === 'running' && $catching['text'] === 'Running (last beat 3s ago, Catching up (1234))', 'Catching up is shown with the count and counts as running: ' . $catching['text']);

// Liveness for the backup restore: only a fresh beat means an engine is running; the Redis key outlives a stopped engine by an hour.
check(!EngineHeartbeat::isAlive(null, $now), 'No beat is not alive.');
check(EngineHeartbeat::isAlive(['ts' => $now - 5], $now), 'A fresh beat is alive.');
check(EngineHeartbeat::isAlive(['ts' => $now - 90], $now), '90s is alive.');
check(!EngineHeartbeat::isAlive(['ts' => $now - 275], $now), 'A beat four minutes old, the key of a stopped engine, is not alive.');
check(EngineHeartbeat::isAlive(['ts' => $now - 3, 'state' => 'paused'], $now), 'A paused engine is alive.');
$backupTool = file_get_contents($root . '/tools/backup.php');
check($backupTool !== false && str_contains($backupTool, 'EngineHeartbeat::isAlive(EngineHeartbeat::read(), time())') && !str_contains($backupTool, 'if (EngineHeartbeat::read() !== null)'), 'The restore must refuse only while the heartbeat is fresh.');

$dir = sys_get_temp_dir() . '/engine-fp-' . bin2hex(random_bytes(4));
mkdir($dir . '/Core', 0777, true);
mkdir($dir . '/resources', 0777, true);
file_put_contents($dir . '/Core/A.php', "<?php\n");
$base = EngineHeartbeat::fingerprint($dir);
check($base === EngineHeartbeat::fingerprint($dir), 'Fingerprint must be stable.');
file_put_contents($dir . '/resources/T.php', "<?php // template\n");
check($base === EngineHeartbeat::fingerprint($dir), 'Templates must not affect the fingerprint.');
file_put_contents($dir . '/Core/B.php', "<?php\n");
$added = EngineHeartbeat::fingerprint($dir);
check($added !== $base, 'A new file must change the fingerprint.');
file_put_contents($dir . '/Core/A.php', "<?php // changed\n");
touch($dir . '/Core/A.php', time() + 50);
check(EngineHeartbeat::fingerprint($dir) !== $added, 'A modified file must change the fingerprint.');
foreach (['/Core/A.php', '/Core/B.php', '/resources/T.php'] as $f) {
    unlink($dir . $f);
}
rmdir($dir . '/Core');
rmdir($dir . '/resources');
rmdir($dir);

$ctrl = file_get_contents($root . '/game/include/admin/include/Controllers/ConfigurationDetailsCtrl.php');
$backups = file_get_contents($root . '/game/include/admin/include/Controllers/BackupsCtrl.php');
$engine = file_get_contents($root . '/game/include/AutomationEngine.php');
$compose = file_get_contents($root . '/compose.yaml');
check(!str_contains($ctrl, 'systemctl') && !str_contains($ctrl, 'shell_exec'), 'Admin must not shell out for engine status.');
check(str_contains($ctrl, 'EngineHeartbeat::status()'), 'Admin must read the heartbeat.');
check(!str_contains($backups, 'engine_filename') && str_contains($backups, 'EngineHeartbeat::requestPause()'), 'Restore must use the pause flag.');
check(str_contains($engine, 'EngineHeartbeat::fingerprint') && str_contains($engine, 'EngineHeartbeat::pauseRequested()'), 'Engine must guard stale code and honour pause.');
check(preg_match('/engine:.*?restart: unless-stopped/s', (string)$compose) === 1, 'Engine service needs a restart policy.');
check(str_contains($source, "public const LIVENESS_KEY = 'travian:engine:heartbeat'") && str_contains($source, "rawCommand('SETEX', self::LIVENESS_KEY") && str_contains($source, "rawCommand('DEL', self::LIVENESS_KEY"), 'The beat must also be written to the unprefixed liveness key and removed on shutdown.');
$health = (string)file_get_contents($root . '/docker/php/engine-healthcheck.php');
check(!str_contains($health, 'bootstrap.php') && str_contains($health, 'EngineHeartbeat::LIVENESS_KEY') && str_contains($health, 'rawCommand'), 'The health check must read the liveness key and not boot the game.');
foreach (glob($root . '/game/include/config.php') as $f) {
    check(!str_contains((string)file_get_contents($f), 'engine_filename'), 'engine_filename must be gone.');
}
echo "engine heartbeat contract ok\n";
