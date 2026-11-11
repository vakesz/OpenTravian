<?php

declare(strict_types=1);

// api_legacy.php routes by HTTP verb: a "VERB path" key or a pattern 'method' limits a route,
// a verbless one answers every verb. Executes the lookup half of the router for each case.

$source = (string)file_get_contents(dirname(__DIR__, 2) . '/game/deployment/public/api_legacy.php');
$start = strpos($source, '$path = ');
$end = strpos($source, "if (\$route === null) {\n    // nginx");
if ($start === false || $end === false) {
    fwrite(STDERR, "api_legacy.php routing block not found.\n");
    exit(1);
}
$lookup = substr($source, $start, $end - $start);
// __DIR__ inside the eval must point at the public directory like in the real script.
// The lookup runs against this table, so the contract does not depend on the real routes.
$table = <<<'PHP'
['routes' => [
    'POST item' => [null, 'item-create'],
    'DELETE item' => [null, 'item-delete'],
    'POST item/all' => ['item', 'all'],
    'plain' => [null, 'plain'],
], 'patterns' => [
    ['method' => 'PUT', 'pattern' => '#^item/(?P<id>[1-9][0-9]*)$#D', 'target' => [null, 'item-update'], 'parameters' => ['id']],
    ['method' => 'DELETE', 'pattern' => '#^item/(?P<id>[1-9][0-9]*)$#D', 'target' => [null, 'item-remove'], 'parameters' => ['id']],
    ['pattern' => '#^thing/(?P<id>[1-9][0-9]*)$#D', 'target' => ['thing', 'show'], 'parameters' => ['id']],
]]
PHP;
$lookup = str_replace("require dirname(__DIR__) . '/include/api_legacy_routes.php'", $table, $lookup);

$resolve = static function (string $method, string $endpoint) use ($lookup): array {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = '/api/v1/' . $endpoint;
    $_GET = [];
    eval($lookup);

    return [$route ?? null, $_GET];
};

$cases = [
    ['POST', 'item', [null, 'item-create'], []],
    ['DELETE', 'item', [null, 'item-delete'], []],
    ['GET', 'item', null, []],
    ['PUT', 'item', null, []],
    ['POST', 'item/all', ['item', 'all'], []],
    ['PUT', 'item/7', [null, 'item-update'], ['id' => '7']],
    ['DELETE', 'item/7', [null, 'item-remove'], ['id' => '7']],
    ['POST', 'item/7', null, []],
    ['GET', 'item/7', null, []],
    ['GET', 'plain', [null, 'plain'], []],
    ['PUT', 'plain', [null, 'plain'], []],
    ['GET', 'thing/3', ['thing', 'show'], ['id' => '3']],
    ['DELETE', 'thing/3', ['thing', 'show'], ['id' => '3']],
];
foreach ($cases as [$method, $endpoint, $expectedRoute, $expectedGet]) {
    [$route, $get] = $resolve($method, $endpoint);
    if ($route !== $expectedRoute || $get !== $expectedGet) {
        fwrite(STDERR, "Unexpected legacy route for {$method} {$endpoint}.\n");
        exit(1);
    }
}

echo "api_legacy method contract passed.\n";
