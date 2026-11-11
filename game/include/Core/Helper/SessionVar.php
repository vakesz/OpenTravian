<?php

declare(strict_types=1);

namespace Core\Helper;
class SessionVar
{
    public static function getVariable(string $name, mixed $default, int $timeout = 0): mixed
    {
        if (!isset($_SESSION[WebService::fixSessionPrefix($name)])) {
            $_SESSION[WebService::fixSessionPrefix($name)] = $default;
            $_SESSION[WebService::fixSessionPrefix($name . '_time')] = time();
        }
        if ($timeout) {
            if (time() - (int) $_SESSION[WebService::fixSessionPrefix($name . '_time')] > $timeout) {
                $_SESSION[WebService::fixSessionPrefix($name)] = $default;
                $_SESSION[WebService::fixSessionPrefix($name . '_time')] = time();
            }
        }
        return $_SESSION[WebService::fixSessionPrefix($name)];
    }

    public static function getElapsedTimes(string $name): int
    {
        if (isset($_SESSION[WebService::fixSessionPrefix($name . '_time')])) {
            return time() - (int) $_SESSION[WebService::fixSessionPrefix($name . '_time')];
        }
        return 0;
    }

    public static function timeoutNow(string $name): void
    {
        unset($_SESSION[WebService::fixSessionPrefix($name)]);
        unset($_SESSION[WebService::fixSessionPrefix($name . '_time')]);
    }

    public static function setVariable(string $name, mixed $value, bool $update = true): void
    {
        $_SESSION[WebService::fixSessionPrefix($name)] = $value;
        if ($update) {
            $_SESSION[WebService::fixSessionPrefix($name . '_time')] = time();
        }
    }
}