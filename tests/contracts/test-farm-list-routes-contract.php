<?php

declare(strict_types=1);

// The farm-list API's verbs and paths resolve to their controllers in the real route table.

$source = (string)file_get_contents(dirname(__DIR__, 2) . '/game/deployment/public/api_legacy.php');
$start = strpos($source, '$path = ');
$end = strpos($source, "if (\$route === null) {\n    // nginx");
if ($start === false || $end === false) {
    fwrite(STDERR, "api_legacy.php routing block not found.\n");
    exit(1);
}
$lookup = str_replace(
    "require dirname(__DIR__) . '/include/api_legacy_routes.php'",
    "require '" . dirname(__DIR__, 2) . "/game/deployment/include/api_legacy_routes.php'",
    substr($source, $start, $end - $start)
);
$resolve = static function (string $method, string $endpoint) use ($lookup): array {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = '/api/v1/' . $endpoint;
    $_GET = [];
    eval($lookup);

    return [$route ?? null, $_GET];
};

$cases = [
    ['POST', 'farm-list', ['farm-list', 'create'], []],
    ['GET', 'village/target/-12/34', ['village', 'target'], ['x' => '-12', 'y' => '34']],
    ['PUT', 'farm-list/12', ['farm-list', 'update'], ['id' => '12']],
    ['DELETE', 'farm-list/12', ['farm-list', 'remove'], ['id' => '12']],
    ['PUT', 'farm-list/change-expanded-state', ['farm-list', 'change-expanded-state'], []],
    ['PUT', 'farm-list/12/change-sorting', ['farm-list', 'change-sorting'], ['id' => '12']],
    ['PUT', 'farm-list/12/change-sort-index', ['farm-list', 'change-sort-index'], ['id' => '12']],
    ['POST', 'farm-list/slot', ['farm-list', 'slot'], []],
    ['PUT', 'farm-list/slot', ['farm-list', 'slot'], []],
    ['DELETE', 'farm-list/slot', ['farm-list', 'slot'], []],
    ['GET', 'farm-list', ['farm-list', 'view'], []],
    ['GET', 'farm-list/12', ['farm-list', 'view'], ['id' => '12']],
    ['POST', 'farm-list/12', null, []],
    ['GET', 'farm-list/slot', null, []],
];
foreach ($cases as [$method, $endpoint, $expectedRoute, $expectedGet]) {
    [$route, $get] = $resolve($method, $endpoint);
    if ($route !== $expectedRoute || $get !== $expectedGet) {
        fwrite(STDERR, "Unexpected route for {$method} {$endpoint}: " . json_encode([$route, $get]) . "\n");
        exit(1);
    }
    if ($route !== null) {
        $class = ucfirst(preg_replace_callback('/-(.)/', static fn (array $m): string => strtoupper($m[1]), $route[1]));
        $file = dirname(__DIR__, 2) . '/game/include/Controller/Ajax/' . lcfirst(preg_replace_callback('/-(.)/', static fn (array $m): string => strtoupper($m[1]), $route[0] ?? '')) . '/' . lcfirst($class) . '.php';
        if (!is_file($file)) {
            fwrite(STDERR, "Missing controller for {$method} {$endpoint}: {$file}\n");
            exit(1);
        }
    }
}

echo "Farm list routes contract passed.\n";
