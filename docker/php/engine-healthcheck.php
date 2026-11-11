<?php

declare(strict_types=1);

$stateDirectory = rtrim(getenv('TRAVIAN_STATE_DIR') ?: '/var/lib/travian', '/');

// Before installation the engine correctly waits for setup.lock. Treat that
// waiting state as ready so a fresh stack is not reported as broken.
if (!is_file($stateDirectory . '/setup.lock')) {
    exit(0);
}

require '/travian/game/include/Core/Jobs/EngineHeartbeat.php';

try {
    $redis = new Redis();
    $host = getenv('TRAVIAN_REDIS_HOST') ?: '127.0.0.1';
    $port = (int)(getenv('TRAVIAN_REDIS_PORT') ?: 6379);
    if (!$redis->connect($host, $port, 1.0)) {
        throw new RuntimeException('Redis connection refused.');
    }
    $password = (string)getenv('TRAVIAN_REDIS_PASSWORD');
    if ($password !== '' && !$redis->auth($password)) {
        throw new RuntimeException('Redis authentication failed.');
    }
    $raw = $redis->rawCommand('GET', \Core\Jobs\EngineHeartbeat::LIVENESS_KEY);
    $beat = is_string($raw) ? json_decode($raw, true) : null;
    $status = \Core\Jobs\EngineHeartbeat::describe(is_array($beat) ? $beat : null, time());
    exit($status['state'] === 'running' ? 0 : 1);
} catch (Throwable $exception) {
    fwrite(STDERR, "Engine health check failed: {$exception->getMessage()}\n");
    exit(1);
}
