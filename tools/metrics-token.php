#!/usr/bin/env php
<?php

declare(strict_types=1);

// Creates (or with --rotate replaces) the bearer token that guards /metrics. The token lives in
// the state volume as metrics-token.local (mode 0600); this tool prints where, never the value.
//
//   docker compose exec -u www-data php php tools/metrics-token.php [--rotate]
//
// See "Metrics" in docs/operate.md for the Prometheus scrape configuration.

use Game\Metrics\MetricsToken;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/game/deployment/include/env.php';
require dirname(__DIR__) . '/game/include/Game/Metrics/MetricsToken.php';

try {
    $created = MetricsToken::create(in_array('--rotate', $argv, true));
} catch (\RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
fwrite(STDOUT, ($created ? 'Wrote' : 'The metrics token already exists in') . ' ' . MetricsToken::file() . ($created ? '' : " (use --rotate to replace it)") . "\n");
exit(0);
