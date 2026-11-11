<?php

declare(strict_types=1);

namespace Core;

use const ERROR_LOG_FILE;
use function json_encode;
use function restore_exception_handler;

class ErrorHandler
{
    private static ?self $instance = null;
    private const MAX_BACKTRACE_FRAMES = 20;
    private const MAX_MESSAGE_LENGTH = 8192;
    private const MAX_LOG_BYTES = 10485760;
    private bool $isCLI = false;

    public function __construct()
    {
        error_reporting(E_ALL & ~E_NOTICE);
        ini_set('display_errors', '0');
        set_error_handler([$this, 'handleErrors']);
        register_shutdown_function([$this, 'handleFatalErrors']);
        $this->isCLI = PHP_SAPI === 'cli';
        if ($this->isCLI) {
            set_exception_handler([$this, 'handleExceptions']);
        }
    }

    public function setAsCGI(): void
    {
        if ($this->isCLI) {
            restore_exception_handler();
            $this->isCLI = false;
        }
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * @param string|array<array-key, mixed> $message
     */
    public function log(string|array $message): void
    {
        if (is_array($message)) {
            $message = (string) json_encode($message);
        }
        if (strlen($message) > self::MAX_MESSAGE_LENGTH) {
            $message = substr($message, 0, self::MAX_MESSAGE_LENGTH) . '... [truncated]';
        }
        $line = '[' . date("Y.m.d H:i:s") . '] ' . $message . PHP_EOL;
        $file = $this->getErrorLogsLocation();
        $written = false;
        // One rotated generation keeps error_log.log bounded.
        clearstatcache(true, $file);
        if (is_file($file) && (int)@filesize($file) > self::MAX_LOG_BYTES) {
            @rename($file, $file . '.1');
        }
        if (!is_file($file) && is_dir(dirname($file)) && is_writable(dirname($file))) {
            @touch($file);
            @chmod($file, 0640);
        }
        if (is_writable($file)) {
            $written = file_put_contents($file, $line, FILE_APPEND | LOCK_EX) !== false;
        }
        // Fall back to the PHP error log (stderr in Docker); the CLI engine always
        // mirrors to stderr so `docker compose logs engine` shows errors.
        if (!$written || $this->isCLI) {
            error_log(rtrim($line));
        }
    }

    public function handleExceptions(\Throwable $e): void
    {
        $this->log($e->getMessage());
    }

    public function handleFatalErrors(): void
    {
        $error = error_get_last();
        if ($error !== null && $error['type'] === E_ERROR) {
            $this->handleErrors($error['type'], $error['message'], $error['file'], $error['line']);
        }
    }

    public function handleErrors(int $errNumber, string $errMessage, string $errFile, int $errLine): bool
    {
        if (!(error_reporting() & $errNumber)) {
            return false;
        }

        switch ($errNumber) {
            case E_WARNING      :
            case E_USER_WARNING :
            case E_NOTICE       :
            case E_USER_NOTICE  :
            case E_DEPRECATED   :
            case E_USER_DEPRECATED:
                $type = 'warning';
                break;
            default             :
                $type = 'fatal error';
                break;
        }
        $trace = array_reverse(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, self::MAX_BACKTRACE_FRAMES + 1));
        array_pop($trace);
        $items = [];
        foreach ($trace as $item) {
            $items[] = ($item['file'] ?? '<unknown file>') . ' ' . ($item['line'] ?? '<unknown line>') . ' calling ' . $item['function'] . '()';
        }
        $errString = 'Backtrace from ' . $type . ' \'' . $errMessage . '\' at ' . $errFile . ' ' . $errLine . ': ' . implode(' | ',
                $items);
        $this->log($errString);
        if ($this->isCLI && $errNumber !== E_DEPRECATED && $errNumber !== E_USER_DEPRECATED) {
            throw new \ErrorException($errString, 0, $errNumber, $errFile, $errLine);
        }
        return true;
    }

    private function getErrorLogsLocation(): string
    {
        return ERROR_LOG_FILE;
    }
}
