<?php

declare(strict_types=1);

use Game\Metrics\Metrics;
use Game\Metrics\MetricsToken;

$root = dirname(__DIR__, 2);

function expectMetrics(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

define('STATE_DIR', sys_get_temp_dir() . '/metrics-contract-' . getmypid() . '/');
mkdir(STATE_DIR);
require_once $root . '/game/include/Game/Metrics/MetricsToken.php';
require_once $root . '/game/include/Game/Metrics/Metrics.php';

try {
    // Token: created private, never replaced without --rotate, verified in constant time.
    expectMetrics(MetricsToken::read() === null && !MetricsToken::accepts('Bearer anything', null), 'Without a token file nothing is accepted.');
    expectMetrics(MetricsToken::create(false) && (fileperms(MetricsToken::file()) & 0777) === 0600, 'The token must be created with mode 0600.');
    $token = MetricsToken::read();
    expectMetrics($token !== null && preg_match('/^[0-9a-f]{48}$/', $token) === 1, 'The token is 48 random hex characters.');
    expectMetrics(!MetricsToken::create(false) && MetricsToken::read() === $token, 'An existing token must not be replaced without --rotate.');
    expectMetrics(MetricsToken::accepts('Bearer ' . $token, $token) && MetricsToken::accepts('bearer ' . $token . ' ', $token), 'The right bearer token is accepted.');
    expectMetrics(!MetricsToken::accepts('Bearer wrong', $token) && !MetricsToken::accepts($token, $token) && !MetricsToken::accepts('Basic ' . $token, $token) && !MetricsToken::accepts(null, $token) && !MetricsToken::accepts('Bearer ', $token), 'Anything but the exact bearer token is refused.');
    expectMetrics(MetricsToken::create(true) && MetricsToken::read() !== $token && glob(STATE_DIR . '*.partial') === [], 'Rotation replaces the token without a leftover file.');

    // Exposition format.
    $text = Metrics::render([
        'engineUp' => true, 'heartbeatAge' => 3, 'engineStarted' => 1794000000, 'roundEnd' => 1794000100, 'roundDuration' => 0.25,
        'queues' => ['construction' => 0, 'research' => 2, 'movement' => null, 'trade' => 1, 'quarantined' => 1, 'mail' => 4],
        'playersOnline' => 5, 'playersTotal' => 90, 'backupAge' => 600, 'slowQueries' => 7,
        'requests' => ['2xx' => 100, '3xx' => 5, '4xx' => 2, '5xx' => 0, 'evil"class' => 9],
    ]);
    foreach (['travian_engine_up 1', 'travian_engine_heartbeat_age_seconds 3', 'travian_engine_last_round_duration_seconds 0.25', 'travian_queue_overdue{queue="research"} 2', 'travian_quarantined_tasks 1', 'travian_mail_queue{status="queued"} 4', 'travian_players_online 5', 'travian_http_requests_total{class="2xx"} 100'] as $line) {
        expectMetrics(str_contains($text, $line . "\n"), 'Missing sample: ' . $line);
    }
    expectMetrics(!str_contains($text, 'queue="movement"') && !str_contains($text, 'evil'), 'A null count and an unknown request class are left out.');
    expectMetrics(str_contains($text, "# TYPE travian_http_requests_total counter\n") && str_contains($text, "# TYPE travian_engine_up gauge\n"), 'Every family declares its type.');
    foreach (explode("\n", rtrim($text)) as $line) {
        expectMetrics(preg_match('/^(# (HELP|TYPE) [a-z_]+ .+|[a-z_]+(\{[a-z]+="[a-z0-9]+"\})? -?[0-9.]+)$/', $line) === 1, 'Not exposition format: ' . $line);
    }
    expectMetrics(Metrics::render(['queues' => []]) === '', 'No facts renders nothing.');

    // No player data can reach the output: every query is an aggregate, and no identifying column is read.
    $source = (string) file_get_contents($root . '/game/include/Game/Metrics/Metrics.php');
    preg_match_all('/(?:SELECT|SHOW)[^\'"]*/', $source, $queries);
    foreach ($queries[0] as $query) {
        expectMetrics(preg_match('/^(SELECT COUNT\(\*\) FROM users( WHERE last_login_time > )?|SELECT lastBackup FROM config|SHOW GLOBAL STATUS LIKE )/', $query) === 1, 'Only aggregates and fixed status values may be read: ' . $query);
    }
} finally {
    foreach (glob(STATE_DIR . '*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir(STATE_DIR);
}

// Reachability: the endpoint exists only on the internal listener and behind the token.
$entry = (string) file_get_contents($root . '/game/deployment/public/metrics.php');
expectMetrics(str_contains($entry, "TRAVIAN_METRICS_LISTENER") && str_contains($entry, 'MetricsToken::accepts(') && strpos($entry, 'MetricsToken::accepts(') < strpos($entry, 'bootstrap.php'), 'The entry must check the listener marker and the token before it loads the game.');
$nginx = (string) file_get_contents($root . '/docker/nginx/default.conf.template');
expectMetrics(substr_count($nginx, 'listen 9145;') === 1 && str_contains($nginx, 'fastcgi_param TRAVIAN_METRICS_LISTENER 1;'), 'nginx must serve /metrics on the internal listener and mark the request.');
expectMetrics(preg_match('/location = \/metrics\.php \{\s*return 404;/', $nginx) === 1 && preg_match('/location = \/metrics \{\s*return 404;/', $nginx) === 1, 'The port-80 server must answer 404 for /metrics and /metrics.php.');
$compose = (string) file_get_contents($root . '/compose.yaml');
expectMetrics(!preg_match('/"[^"]*:9145"/', $compose) && str_contains($compose, '- "9145"'), 'Port 9145 may be exposed to the Compose network but never published.');
expectMetrics(!str_contains($compose . (string) file_get_contents($root . '/compose.dev.yaml') . (string) file_get_contents($root . '/compose.tls.yaml'), ':9145:'), 'No compose file may publish 9145.');

// The request counter: registered once, in the shared bootstrap, never for the CLI or the metrics request.
$bootstrap = (string) file_get_contents($root . '/game/include/bootstrap.php');
expectMetrics(substr_count($bootstrap, 'RequestCounter::register()') === 1, 'The request counter is registered once, in the shared bootstrap.');
$counter = (string) file_get_contents($root . '/game/include/Core/RequestCounter.php');
expectMetrics(str_contains($counter, "PHP_SAPI === 'cli'") && str_contains($counter, 'TRAVIAN_METRICS_REQUEST'), 'The counter skips the CLI and the metrics request.');

echo "Metrics contract passed.\n";
