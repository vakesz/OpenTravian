<?php

declare(strict_types=1);

// The map and hero images are fetched in parallel while a player browses. They must not hold
// (or, where they need no session, even wait for) the player's session lock.

$root = dirname(__DIR__, 2);
$read = static fn (string $path): string => (string)file_get_contents($root . '/' . $path);
$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

foreach (['game/deployment/public/map_block.php', 'game/deployment/public/hero_v2_image.php'] as $entry) {
    $source = $read($entry);
    $define = strpos($source, "define('TRAVIAN_WITHOUT_SESSION', true);");
    $boot = strpos($source, "require __DIR__ . '/index.php';");
    if ($define === false || $boot === false || $define > $boot) {
        $fail("$entry must define TRAVIAN_WITHOUT_SESSION before it boots the game.");
    }
}
foreach (['MapBlockCtrl', 'HeroV2ImageCtrl'] as $controller) {
    if (preg_match('/Session::|\$_SESSION/', $read("game/include/Controller/$controller.php")) === 1) {
        $fail("$controller runs without a session and must not read one.");
    }
}

$mark = $read('game/include/Controller/MapMarkCtrl.php');
$close = strpos($mark, 'session_write_close();');
if ($close === false || $close > strpos($mark, '$this->renderEmptyOverlay();') || $close > strpos($mark, '$this->renderImage(')) {
    $fail('MapMarkCtrl must release the session lock before it renders.');
}
$minimap = $read('game/include/Controller/MinimapCtrl.php');
$close = strpos($minimap, 'session_write_close();');
if ($close === false || $close > strpos($minimap, '$this->cache();')) {
    $fail('MinimapCtrl must release the session lock before it renders.');
}

echo "Image endpoint session contract passed.\n";
