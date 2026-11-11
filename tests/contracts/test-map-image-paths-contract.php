<?php

declare(strict_types=1);

/**
 * Contract: every `img/map/...` path the map renderers build resolves to a file. The Gpack rename once changed
 * the oasis file names (oasis-type3-free.png) while MapBlockCtrl kept building the old ones, which made every map
 * block with an oasis answer HTTP 500. The templates below are the complete list: a new `img/map/` path in the
 * renderers must be added here with the values it can take, so a rename cannot break the map unnoticed again.
 */

function failMapImagePathsContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$public = $root . '/game/deployment/public/';
$sizes = ['60x60', '30x30', '6x6'];
$masks = ['', 'n', 'w', 's', 'e', 'nw', 'ns', 'ne', 'ws', 'we', 'se', 'nws', 'nwe', 'nse', 'wse', 'nwse'];
/** @var array<string, list<string>> the oasis types the world generator places */
$oasisTypes = [2, 3, 4, 6, 7, 8, 10, 11, 12, 14, 15];

// Every concrete path each template can produce, keyed by a pattern that must appear in the source.
$paths = [];
foreach ($sizes as $size) {
    foreach (['forest', 'sea', 'dark'] as $family) {
        foreach ($masks as $mask) {
            if ($family === 'dark' && $mask === 'nwse') {
                continue; // setDark() returns before drawing a fully surrounded tile
            }
            $name = $mask === '' ? $family : "$family-$mask";
            $paths['img/map/' . $family . '/{size}/'][] = "img/map/$family/$size/$name.png";
        }
    }
    foreach (range(0, 11) as $field) {
        $paths['img/map/grassland/{size}/grassland'][] = "img/map/grassland/$size/grassland$field.png";
    }
    $paths['img/map/empty/'][] = "img/map/empty/$size/empty.png";
    foreach ([1, 2, 3, 5, 6, 7, 8, 9] as $tribe) {
        foreach (range(0, 3) as $index) {
            $paths['img/map/village/tribe'][] = "img/map/village/tribe$tribe/$size/village$index.png";
        }
    }
    foreach (['own', 'member', 'conf', 'nap', 'war'] as $mark) {
        $paths['img/map/grid/{$this->image_size}x{$this->image_size}/marks/'][] = "img/map/grid/$size/marks/$mark.png";
    }
    foreach (range(0, 9) as $color) {
        $paths['img/map/grid/{$this->image_size}x{$this->image_size}/border/'][] = "img/map/grid/$size/border/border$color.png";
    }
}
foreach (['60x60', '30x30'] as $size) {
    foreach ($oasisTypes as $type) {
        foreach (['free', 'occupied'] as $state) {
            $paths['img/map/oasis/'][] = "img/map/oasis/$size/oasis-type$type-$state.png";
        }
    }
}
foreach (['30x30'] as $size) {
    foreach (['east', 'west', 'north', 'south'] as $ruler) {
        $paths['img/map/grid/{$this->image_size}x{$this->image_size}/rulers/'][] = "img/map/grid/$size/rulers/$ruler.png";
    }
}
foreach (['clay', 'hill', 'lake'] as $landscape) {
    $paths['img/map/' . $landscape . '/60x60/'][] = "img/map/$landscape/60x60/{$landscape}0.png";
}

$missing = [];
foreach ($paths as $paths_) {
    foreach ($paths_ as $path) {
        if (!is_file($public . $path)) {
            $missing[$path] = true;
        }
    }
}
if ($missing !== []) {
    failMapImagePathsContract('Map image files are missing: ' . implode(', ', array_slice(array_keys($missing), 0, 12)));
}

// The oasis path of MapBlockCtrl must use the renamed (kebab-case) files, and no renderer may build a map path
// outside the known templates.
$blocks = (string)file_get_contents($root . '/game/include/Controller/MapBlockCtrl.php');
if (!str_contains($blocks, 'oasis-type{$oasisType}-{$occupied}.png') || !str_contains($blocks, '"occupied" : "free"')) {
    failMapImagePathsContract('MapBlockCtrl must build the oasis tile name as oasis-type<N>-free|occupied.png.');
}
$known = '#img/map/(forest|sea|dark|grassland|empty|village|grid|oasis|clay|hill|lake|\{\$img\}|)#';
foreach (['MapBlockCtrl', 'MapMarkCtrl'] as $controller) {
    $source = (string)file_get_contents($root . "/game/include/Controller/$controller.php");
    preg_match_all('#img/map/([^"{/]*|\{\$img\})#', $source, $matches);
    foreach (array_unique($matches[1]) as $directory) {
        if (!preg_match($known, 'img/map/' . $directory)) {
            failMapImagePathsContract("$controller builds an img/map path (img/map/$directory) this contract does not cover.");
        }
    }
}

// The third zoom level matches the reference: blocks of 100x100 tiles drawn at 6 pixels (600x600 images), nothing of
// the old 120x120 / 5 pixel set left.
$marks = (string)file_get_contents($root . '/game/include/Controller/MapMarkCtrl.php');
$page = (string)file_get_contents($root . '/game/include/resources/Templates/map/normal.php');
$model = (string)file_get_contents($root . '/game/include/Model/MapModel.php');
foreach ([
    'MapBlockCtrl' => [$blocks, ['case 10000:', '$this->tileSize = 6;', ': 10000)']],
    'MapMarkCtrl' => [$marks, ['case 10000:', '$this->image_size = 6;']],
    'map/normal.php' => [$page, ['{"x": 100, "y": 100}']],
    'MapModel' => [$model, ['default => 99,']],
] as $name => [$source, $needles]) {
    foreach ($needles as $needle) {
        if (!str_contains($source, $needle)) {
            failMapImagePathsContract("$name must use the 100x100 block at 6px for the third zoom level ($needle).");
        }
    }
}
if (str_contains($blocks . $marks . $page, '14400') || str_contains($page, '"x": 120') || glob($public . 'img/map/*/5x5') !== []) {
    failMapImagePathsContract('The old 120x120 block / 5px zoom-3 set must be gone.');
}

fwrite(STDOUT, "Map image paths contract passed.\n");
