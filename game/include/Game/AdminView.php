<?php

declare(strict_types=1);

namespace Game;

/**
 * The remembered Simple / Advanced choice of the control panel and the setup wizard: a cookie set
 * by a plain link (?view=advanced). It only decides what is shown, never what is allowed, so it
 * is not a world change and not audited; every page stays reachable by its URL in both views.
 */
final class AdminView
{
    public const PANEL_COOKIE = 'adminView';
    public const SETUP_COOKIE = 'setupMode';
    public const MODES = ['simple', 'advanced'];

    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $cookies
     * @param array<string, mixed> $query
     * @return array{mode: string, remember: string|null} the mode to show and, when the request chose one, the mode to store
     */
    public static function resolve(array $cookies, array $query, string $cookie = self::PANEL_COOKIE, string $parameter = 'view'): array
    {
        $chosen = $query[$parameter] ?? null;
        if (is_string($chosen) && in_array($chosen, self::MODES, true)) {
            return ['mode' => $chosen, 'remember' => $chosen];
        }
        $stored = $cookies[$cookie] ?? null;

        return ['mode' => is_string($stored) && in_array($stored, self::MODES, true) ? $stored : 'simple', 'remember' => null];
    }

    /**
     * Stores the choice for a year; a display preference, so a session-less, script-less cookie.
     */
    public static function remember(string $mode, string $cookie = self::PANEL_COOKIE): void
    {
        setcookie($cookie, $mode, ['expires' => time() + 31536000, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
    }

    /** The request URI without the view parameter, to redirect back to after it was stored. */
    public static function withoutParameter(string $requestUri, string $parameter = 'view'): string
    {
        $path = (string) parse_url($requestUri, PHP_URL_PATH);
        parse_str((string) parse_url($requestUri, PHP_URL_QUERY), $query);
        unset($query[$parameter]);

        return ($path !== '' ? $path : '/') . ($query !== [] ? '?' . http_build_query($query) : '');
    }
}
