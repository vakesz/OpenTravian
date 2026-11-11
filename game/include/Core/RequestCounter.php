<?php

declare(strict_types=1);

namespace Core;

use Core\Caching\Caching;
use Game\Metrics\Metrics;

/**
 * Counts game requests by status class for the metrics endpoint. Registered once from the shared
 * bootstrap that every web entry loads; one Redis increment at the end of the request, never for
 * the command line or for the metrics request itself, and never allowed to fail the request.
 */
final class RequestCounter
{
    private function __construct()
    {
    }

    public static function register(): void
    {
        if (PHP_SAPI === 'cli' || defined('TRAVIAN_METRICS_REQUEST')) {
            return;
        }
        register_shutdown_function([self::class, 'count']);
    }

    public static function count(): void
    {
        try {
            $code = http_response_code();
            Caching::singleton()->hIncrBy(Metrics::REQUEST_COUNTER_KEY, intdiv(is_int($code) ? $code : 200, 100) . 'xx', 1);
        } catch (\Throwable $exception) {
            // Counting is best effort.
        }
    }
}
