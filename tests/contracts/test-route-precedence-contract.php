<?php

declare(strict_types=1);

/**
 * Clean routes are canonical and case-sensitive, like the reference: a path
 * derived parameter always overrides the query string, /Statistics-style
 * casing does not match, and the retired /dorf3.php redirects to the village
 * statistics route.
 */

$router = (string) file_get_contents(dirname(__DIR__, 2) . '/game/include/mainInclude.php');
$failures = [];

if (preg_match('/\$_(GET|REQUEST)\[[^\]]+\]\s*\?\?=/', $router) === 1) {
    $failures[] = 'mainInclude.php lets a query string override a path-derived parameter (??=); use setRouteParam().';
}
if (str_contains($router, 'strtolower(') || preg_match('#\#[a-z]*i\b#', $router) === 1) {
    $failures[] = 'mainInclude.php must match clean routes case-sensitively.';
}
if (!str_contains($router, "WebService::redirect('/village/statistics');")) {
    $failures[] = '/dorf3.php must redirect to /village/statistics.';
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "Route precedence contract passed.\n";
