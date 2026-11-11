<?php

declare(strict_types=1);

// The db service's health check script rotates the slow-query log by size and keeps a fixed number
// of generations, and compose wires it in. The script is run for real against a temporary file.

$root = dirname(__DIR__, 2);
$script = $root . '/docker/mariadb/health.sh';

function expectSlowLogRotation(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$directory = sys_get_temp_dir() . '/slow-log-rotation-' . getmypid();
mkdir($directory);
$log = $directory . '/slow.log';
$flushed = $directory . '/flushed';

$rotate = static function (int $maxMb, int $keep) use ($script, $log, $flushed): int {
    $command = sprintf(
        'TRAVIAN_SLOW_LOG_FILE=%s TRAVIAN_SLOW_LOG_FLUSH=%s TRAVIAN_MARIADB_SLOW_LOG_MAX_MB=%d TRAVIAN_MARIADB_SLOW_LOG_KEEP=%d sh %s rotate-only',
        escapeshellarg($log),
        escapeshellarg('touch ' . $flushed),
        $maxMb,
        $keep,
        escapeshellarg($script)
    );
    exec($command . ' 2>&1', $output, $code);

    return $code;
};

try {
    expectSlowLogRotation($rotate(1, 3) === 0 && !file_exists($log), 'A missing log is not an error and creates nothing.');

    file_put_contents($log, str_repeat('x', 1000));
    expectSlowLogRotation($rotate(1, 3) === 0 && file_exists($log) && !file_exists($log . '.1') && !file_exists($flushed), 'A log under the cap is left alone and the server is not asked to reopen it.');

    // Three rotations: each generation moves up one place and the oldest beyond the keep count is dropped.
    foreach (['one', 'two', 'three', 'four'] as $generation) {
        file_put_contents($log, $generation . str_repeat('x', 1048576));
        expectSlowLogRotation($rotate(1, 3) === 0, 'Rotation must succeed.');
        expectSlowLogRotation(!file_exists($log), 'The rotated log is renamed away so the server can reopen a fresh one.');
    }
    expectSlowLogRotation(file_exists($flushed), 'The server must be told to reopen the log after a rotation.');
    expectSlowLogRotation(str_starts_with((string) file_get_contents($log . '.1'), 'four'), 'The newest rotated log is .1.');
    expectSlowLogRotation(str_starts_with((string) file_get_contents($log . '.2'), 'three'), 'The previous one is .2.');
    expectSlowLogRotation(str_starts_with((string) file_get_contents($log . '.3'), 'two'), 'The oldest kept generation is .3.');
    expectSlowLogRotation(!file_exists($log . '.4'), 'Generations beyond the keep count are dropped.');
} finally {
    foreach (glob($directory . '/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($directory);
}

// Wiring: the db service runs the script as its health check and receives the limits.
$compose = (string) file_get_contents($root . '/compose.yaml');
foreach ([
    './docker/mariadb/health.sh:/usr/local/bin/travian-db-health.sh:ro',
    'test: ["CMD", "sh", "/usr/local/bin/travian-db-health.sh"]',
    'TRAVIAN_MARIADB_SLOW_LOG_MAX_MB: ${TRAVIAN_MARIADB_SLOW_LOG_MAX_MB:-50}',
    'TRAVIAN_MARIADB_SLOW_LOG_KEEP: ${TRAVIAN_MARIADB_SLOW_LOG_KEEP:-3}',
] as $needle) {
    expectSlowLogRotation(str_contains($compose, $needle), 'compose.yaml is missing: ' . $needle);
}
expectSlowLogRotation(!str_contains($compose, 'MYSQL_PWD') && !preg_match('/-p"?\$/', (string) file_get_contents($script)), 'The administrator password must not reach a command line.');

echo "Slow log rotation contract passed.\n";
