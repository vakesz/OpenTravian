<?php

declare(strict_types=1);

namespace Core\Jobs;

final class WorkerRegistry
{
    private int $sequence = 0;
    /** @var array<string, int> worker identity => pid */
    private array $children = [];

    public function nextIdentity(string $name): string
    {
        ++$this->sequence;

        return $name . ':' . $this->sequence;
    }

    public function register(string $identity, int $pid): void
    {
        if ($pid <= 0 || isset($this->children[$identity])) {
            throw new \RuntimeException('Invalid or duplicate worker registration.');
        }
        $this->children[$identity] = $pid;
    }

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    public function signalAll(int $signal): void
    {
        foreach ($this->children as $pid) {
            @posix_kill($pid, $signal);
        }
    }

    /**
     * @return array<string, array{pid: int, status: int|null}>
     */
    public function reapExited(): array
    {
        $exited = [];
        foreach ($this->children as $identity => $pid) {
            $status = 0;
            $result = pcntl_waitpid($pid, $status, WNOHANG);
            if ($result === 0) {
                continue;
            }
            $exited[$identity] = [
                'pid' => $pid,
                'status' => $result === $pid ? $status : null,
            ];
            unset($this->children[$identity]);
        }

        return $exited;
    }

    public function shutdown(int $graceSeconds = 15): void
    {
        $this->signalAll(SIGTERM);
        $deadline = microtime(true) + max(0, $graceSeconds);
        while (microtime(true) < $deadline) {
            $this->reapExited();
            if (!$this->hasChildren()) {
                return;
            }
            usleep(100000);
        }

        $this->signalAll(SIGKILL);
        foreach ($this->children as $identity => $pid) {
            pcntl_waitpid($pid, $status);
            unset($this->children[$identity]);
        }
    }
}
