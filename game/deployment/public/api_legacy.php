<?php

declare(strict_types=1);

$path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$endpoint = preg_replace('#^/api/v1/#', '', $path);

// The ajax section is chosen by the route table only, never by the client.
unset($_GET['section'], $_REQUEST['section']);

$legacyRoutes = require dirname(__DIR__) . '/include/api_legacy_routes.php';
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
// A route key or pattern may be limited to one HTTP verb ("PUT village-group/<id>").
// Method-specific entries win; verbless entries answer every verb.
$route = $legacyRoutes['routes'][$method . ' ' . $endpoint] ?? $legacyRoutes['routes'][$endpoint] ?? null;

if ($route === null) {
    foreach ($legacyRoutes['patterns'] as $patternRoute) {
        if (isset($patternRoute['method']) && $patternRoute['method'] !== $method) {
            continue;
        }
        if (!preg_match($patternRoute['pattern'], $endpoint, $matches)) {
            continue;
        }

        $route = $patternRoute['target'];
        foreach ($patternRoute['parameters'] as $parameter) {
            $_GET[$parameter] = $matches[$parameter];
        }
        break;
    }
}

if ($route === null) {
    // nginx sends every /api/v1/ request here, so this table is the only list of
    // legacy endpoints. Anything else goes to the API front controller exactly as
    // nginx's /api/ location would have sent it (path without the /api prefix).
    $query = (string)($_SERVER['QUERY_STRING'] ?? '');
    $_SERVER['REQUEST_URI'] = rawurldecode((string)preg_replace('#^/api#', '', $path)) . ($query !== '' ? '?' . $query : '');
    $_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__, 3) . '/services/api/index.php';
    require $_SERVER['SCRIPT_FILENAME'];
    exit;
}

[$section, $command] = $route;
$_GET['cmd'] = $command;
if ($section !== null) {
    $_GET['section'] = $section;
}
$_SERVER['REQUEST_URI'] = '/ajax.php?' . http_build_query($_GET);
$_SERVER['PHP_SELF'] = '/ajax.php';
$_SERVER['SCRIPT_NAME'] = '/ajax.php';

require __DIR__ . '/index.php';
