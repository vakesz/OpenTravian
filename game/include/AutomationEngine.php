<?php
set_time_limit(0);
ini_set('mysql.connect_timeout', '0');
ini_set('max_execution_time', '0');
declare(ticks=1);

use Core\Config;
use Core\ErrorHandler;
use Core\Jobs;
use Core\Jobs\CatchUp;
use Core\Jobs\EngineHeartbeat;
use Core\Jobs\WorkerRegistry;

require __DIR__ . '/bootstrap.php';

$automationLogFile = dirname(ERROR_LOG_FILE) . '/automation.log';
$foreground = getenv('TRAVIAN_FOREGROUND') === '1';

if (!$foreground) {
    $autoPID = pcntl_fork();
    if ($autoPID === -1) {
        exit(1);
    }
    if ($autoPID > 0) {
        exit(0);
    }
    fclose(STDIN);
    fclose(STDOUT);
    fclose(STDERR);
    fopen('/dev/null', 'r');
    fopen($automationLogFile, 'ab');
    fopen($automationLogFile, 'ab');
    if (posix_setsid() === -1) {
        exit(1);
    }
}

global $workerRegistry, $loop;
$workerRegistry = new WorkerRegistry();
$loop = true;

/**
 * @param mixed $signal
 */
function sig_handler($signal): void
{
    global $workerRegistry, $loop;
    $loop = false;
    try {
        $workerRegistry->signalAll(SIGTERM);
    } catch (\Throwable $exception) {
        ErrorHandler::getInstance()->handleExceptions($exception);
    }
}

$engineStartedAt = time();
$engineFingerprint = EngineHeartbeat::fingerprint(__DIR__);
$engineState = 'running';
$engineRemaining = 0;
// Read before the first beat below overwrites it.
$previousBeat = (int)(Config::getInstance()->dynamic->engineBeat ?? 0);
$engineBeat = static function () use (&$engineState, &$engineRemaining, $engineStartedAt, $engineFingerprint): void {
    EngineHeartbeat::beat([
        'pid' => getmypid(),
        'started_at' => $engineStartedAt,
        'fingerprint' => $engineFingerprint,
        'state' => $engineState,
    ] + ($engineState === 'catching-up' ? ['remaining' => $engineRemaining] : []));
};
$engineBeat();
$nextBeat = time() + EngineHeartbeat::BEAT_INTERVAL;
$nextFingerprint = time() + EngineHeartbeat::FINGERPRINT_INTERVAL;
// Exposed to Launcher so the quarantine retry job only releases rows quarantined by an older engine process.
putenv('TRAVIAN_ENGINE_STARTED_AT=' . $engineStartedAt);

pcntl_signal(SIGTERM, 'sig_handler');
pcntl_signal(SIGINT, 'sig_handler');
pcntl_signal(SIGHUP, 'sig_handler');
if (CatchUp::needed($previousBeat, $engineStartedAt)) {
    // The engine was away long enough that overdue work is completed in order, at a capped rate, before the
    // workers start (Core\Jobs\CatchUp). A stop signal or a pause request ends the pass early.
    $engineState = 'catching-up';
    $engineRemaining = CatchUp::remaining($engineStartedAt);
    $engineBeat();
    logError("Engine was away for " . ($engineStartedAt - $previousBeat) . "s; catching up $engineRemaining overdue tasks before starting the workers.");
    $caughtUp = CatchUp::run(
        $engineStartedAt,
        static fn (): bool => !$loop || EngineHeartbeat::pauseRequested(),
        static function (array $progress) use (&$engineRemaining, $engineBeat): void {
            $engineRemaining = $progress['remaining'];
            $engineBeat();
        }
    );
    $engineState = 'running';
    logError("Catch-up finished ({$caughtUp['stoppedBy']}): {$caughtUp['processed']} tasks completed, {$caughtUp['remaining']} left to the workers.");
    $engineBeat();
}
if ($loop && !EngineHeartbeat::pauseRequested()) {
    Jobs\Launcher::lunchJobs();
}

while ($loop) {
    sleep(1);
    pcntl_signal_dispatch();
    if (!$loop) {
        break;
    }
    $now = time();
    $pauseRequested = EngineHeartbeat::pauseRequested();
    if ($pauseRequested && $engineState !== 'paused') {
        // Maintenance pause: finish current jobs (workers get SIGTERM and drain), then acknowledge.
        $workerRegistry->shutdown(15);
        $engineState = 'paused';
        $engineBeat();
    } elseif (!$pauseRequested && $engineState === 'paused') {
        logError('Engine pause released; exiting so the supervisor restarts it on current code and data.');
        break;
    }
    if ($now >= $nextBeat) {
        $engineBeat();
        $nextBeat = $now + EngineHeartbeat::BEAT_INTERVAL;
    }
    if ($now >= $nextFingerprint) {
        $nextFingerprint = $now + EngineHeartbeat::FINGERPRINT_INTERVAL;
        if (EngineHeartbeat::fingerprint(__DIR__) !== $engineFingerprint) {
            logError('Deployed code changed since the engine started; exiting so the supervisor restarts it.');
            break;
        }
    }
    $exitedWorkers = $workerRegistry->reapExited();
    if (!$exitedWorkers) {
        continue;
    }
    foreach ($exitedWorkers as $identity => $state) {
        logError("Automation worker exited unexpectedly: $identity (PID {$state['pid']}).");
    }
    $loop = false;
    $workerRegistry->signalAll(SIGTERM);
}

$workerRegistry->shutdown(15);
EngineHeartbeat::clear();
