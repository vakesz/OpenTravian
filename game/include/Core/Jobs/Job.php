<?php

declare(strict_types=1);

namespace Core\Jobs;

use Core\Config;
use Core\Database\DB;
use Core\ErrorHandler;
use function gc_enable;
use function logError;

class Job
{
    private int $lastReload = 0;
    private int|float $interval = 0;
    /** @var callable|list<object> */
    private $callback;
    /** @var (callable(): (int|float|null|false))|null */
    private $nextDue;

    private const CONFIG_REFRESH_SECONDS = 1.0;
    private const MIN_IDLE_SLEEP_US = 100000;
    private const MAX_IDLE_SLEEP_US = 500000;

    /**
     * @param string               $name
     * @param int|float            $interval
     * @param callable|list<object> $callBack runs directly, or each object's runAction() in turn
     * @param bool                 $daemon
     * @param callable|null $nextDue Daemons with interval 0 only: returns the next due time in milliseconds (or null
     *                               when nothing is queued) so the loop sleeps until then instead of polling.
     * #return Job
     */
    public function __construct(string $name, int|float $interval, callable|array $callBack, bool $daemon = false, ?callable $nextDue = null)
    {
        global $prgName;
        $this->lastReload = time();
        $prgName = $name;
        $this->callback = $callBack;
        $this->nextDue = $nextDue;
        if ($daemon) {
            global $workerRegistry, $loop;
            if (!($workerRegistry instanceof WorkerRegistry)) {
                throw new \RuntimeException('Automation worker registry is not initialized.');
            }
            $identity = $workerRegistry->nextIdentity($name);
            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new \RuntimeException("Unable to fork automation worker $identity.");
            }
            $loop = TRUE;
            pcntl_signal(SIGTERM,
                function ($signal) {
                    global $prgName, $loop;
                    $loop = FALSE;
                });
            pcntl_signal(SIGINT,
                function ($signal) {
                    global $prgName, $loop;
                    $loop = FALSE;
                });
            if ($pid === 0) {
                $prgName = $identity;
                $this->setInterval($interval);
                $db = DB::getInstance()->forceNewDatabase();
                $config = Config::getInstance();
                $db->query("UPDATE config SET needsRestart=0");
                gc_enable();

                $lastConfigRefresh = 0.0;

                while ($loop) {
                    mt_srand(make_seed());
                    if (microtime(true) - $lastConfigRefresh >= self::CONFIG_REFRESH_SECONDS) {
                        $config->dynamic = (object)$db->query("SELECT * FROM config LIMIT 1")->fetch_assoc();
                        $lastConfigRefresh = microtime(true);
                    }
                    if ($config->dynamic->needsRestart == 1) {
                        $loop = false;
                        pcntl_signal_dispatch();
                        continue;
                    }
                    if ($config->dynamic->finishStatusSet) {
                        sleep(5);
                        pcntl_signal_dispatch();
                        continue;
                    }
                    try {
                        if ($config->dynamic->automationState) {
                            $startedAt = microtime(true);
                            $this->runJob($callBack, TRUE);
                            EngineHeartbeat::recordRound($name, $startedAt);
                        }
                    } catch (\Exception $e) {
                        ErrorHandler::getInstance()->handleExceptions($e);
                        sleep(2);
                    } catch (\Error $e) {
                        ErrorHandler::getInstance()->handleExceptions($e);
                        sleep(2);
                    }
                    if ($config->game->start_time > time()) sleep(5);
                    usleep($this->sleepMicroseconds());
                    pcntl_signal_dispatch();
                    if (rand(5, 100) % 5 == 0) {
                        gc_collect_cycles(); //Forces collection of any existing garbage cycles
                    }
                }
                exit();
            }
            $workerRegistry->register($identity, $pid);
        } else {
            $this->setInterval($interval);
        }
    }

    private function sleepMicroseconds(): int
    {
        $fixed = max($this->interval * 1000 * 1000, self::MIN_IDLE_SLEEP_US);
        if ($this->nextDue === null || $this->interval > 0) {
            return (int)$fixed;
        }
        try {
            $next = ($this->nextDue)();
        } catch (\Throwable $e) {
            return self::MIN_IDLE_SLEEP_US;
        }
        if ($next === null || $next === false) {
            return self::MAX_IDLE_SLEEP_US;
        }
        $wait = ((float)$next - miliseconds()) * 1000;

        return (int)min(max($wait, self::MIN_IDLE_SLEEP_US), self::MAX_IDLE_SLEEP_US);
    }

    private function setInterval(int|float $sec): void
    {
        $this->interval = $sec;
    }

    /**
     * @param callable|list<object> $callBack
     */
    public function runJob(callable|array $callBack, bool $daemon = false): void
    {
        if (!$this->checkInterval($daemon)) {
            return;
        }
        $db = DB::getInstance();
        if (!$db->checkConnection()) {
            return;
        }
        $this->updateLastReload();
        if (is_callable($callBack)) {
            $callBack();
        } else {
            foreach ($callBack as $cb) {
                try {
                    if (method_exists($cb, "runAction")) {
                        $cb->runAction();
                    } else {
                        logError(print_r($this, TRUE));
                    }
                } catch (\Exception $e) {
                    ErrorHandler::getInstance()->handleExceptions($e);
                    sleep(2);
                }
            }
        }
    }

    private function checkInterval(bool $daemon): bool
    {
        return $daemon || time() - $this->lastReload >= $this->interval;
    }

    private function updateLastReload(): void
    {
        $this->lastReload = time();
    }

    public function runAction(): void
    {
        try {
            $this->runJob($this->callback);
        } catch (\Exception $e) {
            ErrorHandler::getInstance()->handleExceptions($e);
        }
    }
}
