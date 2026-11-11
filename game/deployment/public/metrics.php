<?php

declare(strict_types=1);

// Prometheus metrics. nginx serves this only on its internal listener (port 9145, never
// published) and marks the request with TRAVIAN_METRICS_LISTENER; the port-80 server answers 404
// for it. A bearer token from protected state is required as well (tools/metrics-token.php).
// See "Metrics" in docs/operate.md.

define('TRAVIAN_WITHOUT_SESSION', true);
define('TRAVIAN_METRICS_REQUEST', true);

require_once dirname(__DIR__) . '/include/env.php';
require_once dirname(__DIR__, 2) . '/include/Game/Metrics/MetricsToken.php';

use Game\Metrics\MetricsToken;

$token = MetricsToken::read();
if (($_SERVER['TRAVIAN_METRICS_LISTENER'] ?? '') !== '1' || $token === null) {
    http_response_code(404);
    exit;
}
if (!MetricsToken::accepts($_SERVER['HTTP_AUTHORIZATION'] ?? null, $token)) {
    http_response_code(401);
    header('WWW-Authenticate: Bearer');
    exit;
}

require_once dirname(__DIR__, 2) . '/include/bootstrap.php';

header('Content-Type: text/plain; version=0.0.4; charset=utf-8');
header('Cache-Control: no-store');
echo Game\Metrics\Metrics::render(Game\Metrics\Metrics::collect(time()));
