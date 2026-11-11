<?php

declare(strict_types=1);

/**
 * Contract: the map outline panel, its mark dialogs and the minimap follow the reference: layer data sits
 * in a `scroll-dataContainer` inside the simplebar container, every mark layer keeps its own dialog
 * options (the merge helper deep-copies), the dialogs use the reference coordinate inputs and titles, and
 * the minimap is a JPEG served at /map/minimap.jpg with a one-day cache.
 */
function failMapOutlineMinimapContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $source = file_get_contents($root . '/' . $path);
    if ($source === false) {
        failMapOutlineMinimapContract("Cannot read $path.");
    }

    return $source;
};
$client = $read('game/deployment/public/game-client.js');
$page = $read('game/include/resources/Templates/map/normal.php');
$minimap = $read('game/include/Controller/MinimapCtrl.php');
$cache = $read('game/include/Game/Map/ImageMapCache.php');
$nginx = $read('docker/nginx/default.conf.template');

foreach (['scroll-dataContainer', 'Travian.Helpers.deepmergeObject({}, i)'] as $needle) {
    if (!str_contains($client, $needle)) {
        failMapOutlineMinimapContract("The map client must contain $needle.");
    }
}
if (str_contains($client, '<div class="{expandContainer}"><div class="{data}"></div></div>') || str_contains($client, 'e[t] = Object.assign({}, i)')) {
    failMapOutlineMinimapContract('The map client must not keep the shared-option merge or the extra data container.');
}
foreach (['<div class="entry player selected">', 'inputmode=\"numeric\"', 'cih.insertCoordinates(event)', "T(\"map\", \"ownFlags\");?>',"] as $needle) {
    if (!str_contains($page, $needle)) {
        failMapOutlineMinimapContract("The map page must contain $needle.");
    }
}
if (!str_contains($page, 'src="/map/minimap.jpg"')) {
    failMapOutlineMinimapContract('The minimap image must be requested from /map/minimap.jpg.');
}
if (!str_contains($minimap, "imagejpeg(") || str_contains($minimap, 'imagegif(') || !str_contains($minimap, "'minimap.jpg', 'jpeg'")) {
    failMapOutlineMinimapContract('The minimap must be rendered and cached as a JPEG.');
}
if (!str_contains($cache, 'header("Cache-Control: max-age=86400");') || !str_contains($nginx, 'location = /map/minimap.jpg')) {
    failMapOutlineMinimapContract('The minimap needs its nginx route and the one-day cache header.');
}

foreach (['imagecreatetruecolor($size, $size)', '$size = 2 * MAP_SIZE + 1;', 'MAP_SIZE - (int)$row[\'y\']'] as $needle) {
    if (!str_contains($minimap, $needle)) {
        failMapOutlineMinimapContract("The minimap must draw one pixel per tile ($needle).");
    }
}
if (str_contains($minimap, 'basic.png') || is_dir(dirname(__DIR__, 2) . '/game/deployment/public/img/map/tile/1x1')) {
    failMapOutlineMinimapContract('The 185x109 drawing base and its tile images must stay removed.');
}

echo "Map outline and minimap contract passed.\n";
