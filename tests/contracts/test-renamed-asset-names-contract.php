<?php

declare(strict_types=1);

use Core\Helper\CombatIcon;

// File names that code assembles at request time (so no reference scan can see them) must exist:
// the combat icons, the map border tiles and the header coins.

$root = dirname(__DIR__, 2);
require_once $root . '/game/include/Core/Helper/CombatIcon.php';

function expectRenamedAsset(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

foreach (['attack', 'attackLost', 'defend', 'defendLost', 'lootEmpty', 'lootHalf', 'lootFull'] as $name) {
    expectRenamedAsset(is_file($root . '/game/deployment/public/img/svg/combat/' . CombatIcon::fileName($name)), "No combat icon file for $name: " . CombatIcon::fileName($name));
}
expectRenamedAsset(CombatIcon::fileName('attackLost') === 'svg-attack-lost.svg' && CombatIcon::fileName('attack') === 'svg-attack.svg', 'Combat icon names are kebab-case.');

// Map border tiles: the tile with no neighbours is the bare name, the others carry the mask.
$source = (string) file_get_contents($root . '/game/include/Controller/MapBlockCtrl.php');
expectRenamedAsset(str_contains($source, 'function maskedTile('), 'The map block controller names border tiles through maskedTile().');
$masks = ['', 'n', 'w', 's', 'e', 'nw', 'ne', 'ns', 'we', 'ws', 'se', 'nws', 'nwe', 'nse', 'wse', 'nwse'];
foreach (['sea', 'forest', 'dark'] as $kind) {
    foreach (['60x60', '30x30', '6x6'] as $size) {
        foreach ($masks as $mask) {
            if ($kind === 'dark' && $mask === 'nwse') {
                continue; // fully surrounded: the controller draws nothing
            }
            $file = $mask === '' ? $kind : $kind . '-' . $mask;
            expectRenamedAsset(is_file($root . "/game/deployment/public/img/map/$kind/$size/$file.png"), "Missing map tile $kind/$size/$file.png");
        }
    }
}

foreach (['gold', 'silver'] as $coin) {
    $file = '/assets/gpack/travian/mainPage/%s/hud/top-bar/currency/' . $coin . '-coin.svg';
    expectRenamedAsset(is_file($root . sprintf($file, 'img_common')) || is_file($root . sprintf($file, 'img_ltr')), "Missing $coin coin.");
}

echo "Renamed asset names contract passed.\n";
