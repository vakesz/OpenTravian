<?php

declare(strict_types=1);

$source = file_get_contents(dirname(__DIR__, 2) . '/game/include/Controller/MapBlockCtrl.php');

if (!is_string($source)) {
    fwrite(STDERR, "Map block controller must be readable.\n");
    exit(1);
}

if (!str_contains($source, '$spritePath = "img/map/village/tribe{$tribeId}/{$this->getTileSize()}x{$this->getTileSize()}/village{$index}.png";')
    || !str_contains($source, 'if (!is_file($spritePath))')
    || !str_contains($source, 'rendering the tile without its village overlay')
    || !str_contains($source, '$sprite = @imagecreatefrompng($spritePath);')
    || !str_contains($source, 'if ($sprite === false)')
    || !str_contains($source, 'imagecopy($this->getImgResource(), $sprite,')) {
    fwrite(STDERR, "A missing or unreadable tribe sprite must leave its map block renderable.\n");
    exit(1);
}

if (str_contains($source, 'imagecopy($this->getImgResource(), imagecreatefrompng("img/map/village/tribe{$tribeId}')) {
    fwrite(STDERR, "Village sprites must be validated before GD compositing.\n");
    exit(1);
}

// The revision rises whenever the map art changes (3: the 6x6 zoom-3 set), so the contract only asks for a counter.
if (preg_match('/private const SPRITE_CACHE_REVISION = (\d+);/', $source, $revision) !== 1 || (int)$revision[1] < 3
    || !str_contains($source, '_sprites" . self::SPRITE_CACHE_REVISION')) {
    fwrite(STDERR, "Map sprite updates must invalidate the terrain-block cache.\n");
    exit(1);
}

$root = dirname(__DIR__, 2);
foreach ([8, 9] as $tribe) {
    foreach ([6, 30, 60] as $size) {
        foreach ([0, 1, 2, 3] as $population) {
            $sprite = $root . "/game/deployment/public/img/map/village/tribe{$tribe}/{$size}x{$size}/village{$population}.png";
            if (!is_file($sprite) || filesize($sprite) < 50) {
                fwrite(STDERR, "Optional tribe {$tribe} needs a usable {$size}px population-{$population} map sprite.\n");
                exit(1);
            }
        }
    }
}

echo "Map missing-sprite resilience contract passed.\n";
