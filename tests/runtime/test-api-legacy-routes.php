<?php

declare(strict_types=1);

$legacyRoutes = require dirname(__DIR__, 2) . '/game/deployment/include/api_legacy_routes.php';
$routes = $legacyRoutes['routes'] ?? null;
$patterns = $legacyRoutes['patterns'] ?? null;

if (!is_array($routes) || !is_array($patterns)) {
    fwrite(STDERR, "Legacy API routes must provide routes and patterns arrays.\n");
    exit(1);
}

$expected = [
    'daily-quest' => [null, 'daily-quest'],
    'POST daily-quest/award' => ['daily-quest', 'award'],
    'goldclub' => [null, 'goldclub'],
    'POST alliance/bonus/contribute' => ['alliance', 'bonus-contribute'],
    'POST alliance/bonus/form/gold' => ['alliance', 'bonus-form-gold'],
    'POST alliance/bonus/form/scale-down' => ['alliance', 'bonus-form-scale-down'],
    'POST alliance/bonus/overview' => ['alliance', 'bonus-overview'],
    'PUT premium/contribute-resources-x3' => ['premium', 'contribute-resources-x3'],
    'POST premium/contribute-resources-x3' => ['premium', 'contribute-resources-x3'],
    'hero/attributes' => ['hero', 'attributes'],
    'DELETE infobox' => [null, 'infobox'],
    'POST infobox/read' => ['infobox', 'read'],
    'marketplace/exchange-resources' => ['marketplace', 'exchange-resources'],
    'marketplace/prepare' => ['marketplace', 'prepare'],
    'marketplace/reload' => ['marketplace', 'reload'],
    'message/check-recipient' => ['message', 'check-recipient'],
    'message/spam-report' => ['message', 'spam-report'],
    'payment-wizard' => [null, 'payment-wizard'],
    'paynet/redeem-voucher' => ['paynet', 'redeem-voucher'],
    'player/gold-amount' => ['player', 'gold-amount'],
    'plus' => [null, 'plus'],
    'premium' => [null, 'premium'],
    'premium/production-boost' => ['premium', 'production-boost'],
    'silver/exchange' => ['silver', 'exchange'],
    'POST troop/distanceSpeedAndDuration' => ['troop', 'distance-speed-and-duration'],
    'PUT village/change-names' => ['village', 'change-names'],
    'POST village-group' => [null, 'village-group'],
    'POST village-list' => [null, 'village-list'],
    'vacation-mode/confirmation' => ['vacation-mode', 'confirmation'],
];

foreach ($expected as $endpoint => $target) {
    if (($routes[$endpoint] ?? null) !== $target) {
        fwrite(STDERR, "Missing or incorrect legacy API route: {$endpoint}\n");
        exit(1);
    }
}

foreach ($routes as $endpoint => $target) {
    if (!is_string($endpoint) || !preg_match('#^(?:(?:GET|POST|PUT|DELETE) )?[A-Za-z0-9-]+(?:/[A-Za-z0-9-]+)*$#', $endpoint)
        || !is_array($target) || count($target) !== 2
        || !($target[0] === null || is_string($target[0])) || !is_string($target[1])) {
        fwrite(STDERR, "Invalid legacy API route declaration: {$endpoint}\n");
        exit(1);
    }
}

$controllerSegment = static fn (string $value): string => lcfirst(
    str_replace(' ', '', ucwords(str_replace('-', ' ', $value)))
);
$controllerFile = static function (array $target) use ($controllerSegment): string {
    [$section, $command] = $target;

    return dirname(__DIR__, 2) . '/game/include/Controller/Ajax/'
        . ($section === null ? '' : $controllerSegment($section) . '/')
        . $controllerSegment($command)
        . '.php';
};
$controllerClass = static function (array $target) use ($controllerSegment): string {
    [$section, $command] = $target;

    return 'Controller\\Ajax'
        . ($section === null ? '' : '\\' . $controllerSegment($section))
        . '\\' . $controllerSegment($command);
};
$assertController = static function (string $endpoint, array $target) use ($controllerClass, $controllerFile): void {
    $file = $controllerFile($target);
    if (!is_file($file)) {
        fwrite(STDERR, "Legacy API route has no Ajax controller: {$endpoint}\n");
        exit(1);
    }

    $source = file_get_contents($file);
    $expected = $controllerClass($target);
    if ($source === false
        || preg_match('/namespace\\s+([^;]+);/', $source, $namespace) !== 1
        || preg_match('/(?:abstract\\s+|final\\s+)?class\\s+([A-Za-z_][A-Za-z0-9_]*)\\b/', $source, $class) !== 1
        || trim($namespace[1]) . '\\' . $class[1] !== $expected) {
        fwrite(STDERR, "Legacy API route resolves to the wrong controller class: {$endpoint}\n");
        exit(1);
    }
};
foreach ($routes as $endpoint => $target) {
    $assertController($endpoint, $target);
}

$expectedPatterns = [
    '#^manual/building/(?P<id>[1-9][0-9]*)$#D' => ['manual', 'building'],
    '#^troop/(?P<id>[1-9][0-9]*)/mark$#D' => ['troop', 'mark'],
    '#^village/(?P<did>[1-9][0-9]*)/update-sort-index$#D' => ['village', 'update-sort-index'],
    '#^village-group/(?P<id>[1-9][0-9]*)$#D' => [null, 'village-group'],
];
foreach ($patterns as $pattern) {
    if (!is_array($pattern)
        || !isset($pattern['pattern'], $pattern['target'], $pattern['parameters'])
        || !is_string($pattern['pattern'])
        || !is_array($pattern['target'])
        || !is_array($pattern['parameters'])) {
        fwrite(STDERR, "Invalid legacy API pattern declaration.\n");
        exit(1);
    }
    if (isset($pattern['method']) && !in_array($pattern['method'], ['GET', 'POST', 'PUT', 'DELETE'], true)) {
        fwrite(STDERR, "Invalid legacy API pattern method.\n");
        exit(1);
    }
    $assertController($pattern['pattern'], $pattern['target']);
    unset($expectedPatterns[$pattern['pattern']]);
}
if ($expectedPatterns !== []) {
    fwrite(STDERR, "Missing legacy API route pattern.\n");
    exit(1);
}

foreach ([
    'payment-wizard/open-offers',
    'paynet/cart',
    'paynet/create-payment',
    'paynet/init',
    'paynet/transaction-history-dialog',
    'paynet/transactionHistoryDialog',
] as $retiredEndpoint) {
    if (array_key_exists($retiredEndpoint, $routes)) {
        fwrite(STDERR, "Retired V4 payment endpoint remains registered: {$retiredEndpoint}\n");
        exit(1);
    }
}

echo 'Legacy API route contract passed (' . count($routes) . ' routes, ' . count($patterns) . " patterns).\n";
