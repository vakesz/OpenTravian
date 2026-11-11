<?php

declare(strict_types=1);

// Like the reference, a tile address opens the map on that tile: /position_details.php and /karte.php?d= redirect to
// /karte.php?x=&y=, and the map page opens the tile details dialog for coordinates in its URL (showPopup).

function failPositionDetailsRouteContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $contents = file_get_contents($root . '/' . $path);
    if ($contents === false) {
        failPositionDetailsRouteContract("Cannot read $path.");
    }

    return $contents;
};
$controller = $read('game/include/Controller/PositionDetailsCtrl.php');
if (!str_contains($controller, "'/karte.php?x=' . (int)\$_GET['x'] . '&y=' . (int)\$_GET['y']")
    || str_contains($controller, 'tileDetails')) {
    failPositionDetailsRouteContract('The position-details route must only redirect to the map on that tile.');
}
foreach (['game/include/Controller/MapCtrl.php'] as $path) {
    if (!str_contains($read($path), '$this->redirect("/karte.php?x={$xy[\'x\']}&y={$xy[\'y\']}");')) {
        failPositionDetailsRouteContract("$path must send karte.php?d= to karte.php?x=&y=.");
    }
}
if (!str_contains($read('game/include/Controller/MapCtrl.php'), '"showPopup" => isset($_GET[\'x\'], $_GET[\'y\'])')
    || !str_contains($read('game/include/resources/Templates/map/normal.php'), "showPopup: <?=\$vars['Map']['showPopup'] ? 'true' : 'false';?>")) {
    failPositionDetailsRouteContract('The map page must ask for the tile dialog exactly when coordinates are in the URL.');
}
$client = $read('game/deployment/public/game-client.js');
foreach (['showPopup: !1,', 'url: "/karte.php?x={x}&y={y}"', 'this.showPopup &&'] as $needle) {
    if (!str_contains($client, $needle)) {
        failPositionDetailsRouteContract("The map client must support the tile dialog on load: missing $needle");
    }
}

echo "Position details route contract passed.\n";
