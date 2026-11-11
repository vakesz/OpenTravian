<?php

declare(strict_types=1);

namespace Core;

class WebService
{
    private static string|false|null $ipAddress = null;

    public static function isPost(): bool
    {
        return strtolower((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'post';
    }

    public static function ipAddress(): string|false
    {
        if (self::$ipAddress !== null) {
            return self::$ipAddress;
        }
        // Only REMOTE_ADDR is trusted. Client-supplied headers such as X-Forwarded-For
        // are spoofable; nginx rewrites REMOTE_ADDR from them only for TRAVIAN_TRUSTED_PROXY.
        $ip = $_SERVER['REMOTE_ADDR'] ?? false;
        if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $ip = false;
        }

        return self::$ipAddress = $ip;
    }

    public static function getProtocol(): string
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
            ? 'https'
            : 'http';
    }

    public static function redirect(string $url, int $code = 302): never
    {
        @ob_end_clean();
        if (!in_array($code, [301, 302], true)) {
            $code = 302;
        }
        header('Location: ' . $url, true, $code);
        exit();
    }

    public static function getRealDomain(): string
    {
        return (string) preg_replace('/^(.*\.)?([^.]*\..*)$/', '$2', self::getIndexUrl());
    }

    public static function getIndexUrl(): string
    {
        global $globalConfig;

        return (string) $globalConfig['staticParameters']['indexUrl'];
    }

    public static function getForumUrl(): string
    {
        global $globalConfig;

        return (string) $globalConfig['staticParameters']['forumUrl'];
    }

    public static function getJustDomain(): string
    {
        return str_replace(['http://', '/'], '', self::getRealDomain());
    }
}
