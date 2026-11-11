<?php

declare(strict_types=1);

// Contract: map block and mark images behave like the reference: an invalid block size is an empty
// 404 with Cache-Control: no-store, block ends wrap like the rest of the map, and the images carry the
// ETag their conditional requests (If-None-Match, 304) are compared with.

$root = dirname(__DIR__, 2);
$block = (string)file_get_contents($root . '/game/include/Controller/MapBlockCtrl.php');
$mark = (string)file_get_contents($root . '/game/include/Controller/MapMarkCtrl.php');
$cache = (string)file_get_contents($root . '/game/include/Game/Map/ImageMapCache.php');

foreach ([
    'MapBlockCtrl notFound' => [$block, "http_response_code(404);\n        header('Cache-Control: no-store');"],
    'MapBlockCtrl size check' => [$block, "self::notFound();"],
    'MapBlockCtrl wraps the block ends' => [$block, '$x1 = Formulas::coordinateFixer($x1);'],
    'MapMarkCtrl size check' => [$mark, 'MapBlockCtrl::notFound();'],
    'MapMarkCtrl wraps the block ends' => [$mark, '$x1 = Formulas::coordinateFixer($x1);'],
    'ETag header' => [$cache, 'header("ETag: $eTag");'],
] as $what => [$source, $needle]) {
    if (!str_contains($source, $needle)) {
        fwrite(STDERR, "$what is missing.\n");
        exit(1);
    }
}
if (str_contains($mark, 'exit(-1)') || str_contains($block, "=== -1) {\n            die;")) {
    fwrite(STDERR, "An invalid block size must be a 404, not an empty 200.\n");
    exit(1);
}

echo "Map image routes contract passed.\n";
