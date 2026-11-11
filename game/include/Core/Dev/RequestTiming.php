<?php

declare(strict_types=1);

namespace Core\Dev;

use Core\Database\DB;

/**
 * Request timing and query count for developers: one fixed-position badge appended to the page
 * and a Server-Timing header. It exists only where TRAVIAN_DEV_TOOLS=1 (compose.dev.yaml sets it
 * on the php service; no production file does), and even there it is off until a browser opts in
 * with ?devFooter=1 (a cookie; ?devFooter=0 clears it). Off, it does nothing at all, so pages are
 * byte-for-byte what the game renders. On, it is added after the page is built, outside every
 * game template, as a single out-of-flow element that cannot move, resize or intercept anything.
 */
final class RequestTiming
{
    public const ENVIRONMENT_VARIABLE = 'TRAVIAN_DEV_TOOLS';
    public const COOKIE = 'travianDevFooter';
    public const ELEMENT_ID = 'travianDevFooter';

    private function __construct()
    {
    }

    /** Whether developer tooling is switched on for this deployment. */
    public static function available(): bool
    {
        return getenv(self::ENVIRONMENT_VARIABLE) === '1';
    }

    public static function start(): void
    {
        if (PHP_SAPI === 'cli' || !self::available() || defined('TRAVIAN_METRICS_REQUEST')) {
            return;
        }
        $toggle = $_GET['devFooter'] ?? null;
        if ($toggle === '1' || $toggle === '0') {
            $on = $toggle === '1';
            setcookie(self::COOKIE, $on ? '1' : '', ['expires' => $on ? 0 : 1, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
            $_COOKIE[self::COOKIE] = $on ? '1' : '';
        }
        if (($_COOKIE[self::COOKIE] ?? '') !== '1') {
            return;
        }
        DB::$profile = true;
        ob_start([self::class, 'filter']);
    }

    /** Output-buffer callback: adds the Server-Timing header and, for an HTML page, the badge. */
    public static function filter(string $buffer): string
    {
        $stats = [
            'ms' => (microtime(true) - (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) * 1000,
            'queries' => DB::$queryCount,
            'queryMs' => DB::$queryTime * 1000,
            'memory' => memory_get_peak_usage(true),
        ];
        if (!headers_sent()) {
            header('Server-Timing: total;dur=' . round($stats['ms'], 1) . ', db;dur=' . round($stats['queryMs'], 1) . ';desc="' . $stats['queries'] . ' queries"');
        }

        return self::isHtml() ? self::inject($buffer, $stats) : $buffer;
    }

    /**
     * @param array{ms: float, queries: int, queryMs: float, memory: int} $stats
     */
    public static function inject(string $html, array $stats): string
    {
        $position = strripos($html, '</body>');
        if ($position === false || str_contains($html, 'id="' . self::ELEMENT_ID . '"')) {
            return $html;
        }

        return substr($html, 0, $position) . self::badge($stats) . substr($html, $position);
    }

    /**
     * @param array{ms: float, queries: int, queryMs: float, memory: int} $stats
     */
    public static function badge(array $stats): string
    {
        $text = sprintf('dev %d ms · %d queries %d ms · %.1f MB', $stats['ms'], $stats['queries'], $stats['queryMs'], $stats['memory'] / 1048576);

        return '<div id="' . self::ELEMENT_ID . '" style="position:fixed;left:0;bottom:0;z-index:2147483647;margin:0;padding:2px 8px;'
            . 'font:11px/1.4 monospace;color:#fff;background:rgba(0,0,0,.75);pointer-events:none;border-radius:0 4px 0 0">'
            . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</div>';
    }

    private static function isHtml(): bool
    {
        foreach (headers_list() as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                return stripos($header, 'text/html') !== false;
            }
        }

        return true;
    }
}
