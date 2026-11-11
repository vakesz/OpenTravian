<?php

declare(strict_types=1);

namespace Core\Helper;

use Core\Config;
use function session_cache_expire;
use function session_write_close;

class WebService
{
    private static string|false|null $ipAddress = null;

    public static function isPost(): bool
    {
        return strtolower((string) ($_SERVER['REQUEST_METHOD'] ?? '')) == 'post';
    }

    public static function isPut(): bool
    {
        return strtolower((string) ($_SERVER['REQUEST_METHOD'] ?? '')) == 'put';
    }

    public static function isGet(): bool
    {
        return strtolower((string) ($_SERVER['REQUEST_METHOD'] ?? '')) == 'get';
    }

    public static function isDelete(): bool
    {
        return strtolower((string) ($_SERVER['REQUEST_METHOD'] ?? '')) == 'delete';
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

    /**
     * The configured public world URL with a trailing slash. Links in mail and redirects
     * must use this, never the request's Host header.
     */
    public static function get_base_url(): string
    {
        return rtrim((string) Config::getProperty('settings', 'gameWorldUrl'), '/') . '/';
    }

    public static function fixSessionPrefix(string $key): string
    {
        return Config::getProperty('settings', 'worldUniqueId') . ':' . $key;
    }

    public static function getRealDomain(): string
    {
        return (string) preg_replace("/^(.*\.)?([^.]*\..*)$/", '$2', self::getIndexUrl());
    }

    public static function getIndexUrl(): string
    {
        global $globalConfig;

        return (string) $globalConfig['staticParameters']['indexUrl'];
    }

    public static function redirect(string $url, int $code = 302): void
    {
        if ($url === '') {
            return;
        }
        @ob_end_clean();
        if (!in_array($code, [301, 302], true)) {
            $code = 302;
        }
        header("Location: " . \normalizeRedirectTarget($url), true, $code);
        exit();
    }

    public static function getJustDomain(): string
    {
        return str_replace(['http://', '/'], '', self::getRealDomain());
    }

    public static function getJustSubDomain(): string
    {
        return str_replace([
            "http://",
            '/',
        ],
            '',
            self::get_base_url());
    }

    public static function getProtocol(): string
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) ? 'https://' : 'http://';
    }
} 