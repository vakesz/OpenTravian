<?php

declare(strict_types=1);

// The Wonder of the World has its own art in the village centre and on the map: a stage per level band, a click
// outline per tribe and stage, and map tiles for every tribe that can own a Wonder (Natars included).

$root = dirname(__DIR__, 2);
$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};

require_once $root . '/game/include/Model/WonderOfTheWorldModel.php';
foreach ([0 => 0, 19 => 0, 20 => 1, 39 => 1, 40 => 2, 59 => 2, 60 => 3, 79 => 3, 80 => 4, 89 => 4, 90 => 5, 100 => 5] as $level => $stage) {
    if (\Model\WonderOfTheWorldModel::stage($level) !== $stage) {
        $fail("A level-{$level} Wonder must use art stage {$stage}.");
    }
}

$template = (string) file_get_contents($root . '/game/include/resources/Templates/village-center/main.php');
if (str_contains($template, "\$map['item_id'][")) {
    $fail('The village centre must not index the Wonder building id as an array.');
}
if (!str_contains($template, "\$buildingField['g40'][\$vars['raceName']][\$wwStage] ?? null")
    || !str_contains($template, 'viewBox="0 0 299 440"')) {
    $fail('The Wonder click outline must come from the viewer tribe and stage, in the 299x440 sprite box.');
}

$buildingField = [];
require $root . '/game/include/resources/Svg/buildFieldSvg.php';
foreach (['roman', 'teuton', 'gaul', 'egyptian', 'hun', 'spartan', 'viking'] as $tribe) {
    for ($stage = 0; $stage <= 5; ++$stage) {
        $path = $buildingField['g40'][$tribe][$stage] ?? '';
        if (!is_string($path) || !str_starts_with($path, 'M') || !str_ends_with($path, 'Z')) {
            $fail("The {$tribe} Wonder needs a closed click outline for stage {$stage}.");
        }
    }
}

// 14 building slots stay free besides the Main Building, Rally Point and wall: the Wonder stands on the other five
// and is drawn from slot 35, which the Gpack styles as the 299x440 Wonder box in both directions.
if (\Model\WonderOfTheWorldModel::RESERVED_SLOTS !== [21, 29, 31, 32, 35] || \Model\WonderOfTheWorldModel::DISPLAY_SLOT !== 35) {
    $fail('A Wonder village must reserve slots 21, 29, 31, 32 and 35 and draw the Wonder from slot 35.');
}
if (in_array(26, \Model\WonderOfTheWorldModel::RESERVED_SLOTS, true)) {
    $fail('A Wonder village keeps its Main Building slot.');
}
foreach (['css_ltr' => 'left', 'css_rtl' => 'right'] as $dir => $side) {
    $css = (string) file_get_contents($root . "/assets/gpack/travian/mainPage/{$dir}/imports.css");
    if (preg_match('/\.buildingSlot\.a35\.g40 \{\s*width: 299px;\s*height: 440px;\s*top: 147px;\s*' . $side . ': 501px;/', $css) !== 1) {
        $fail("The {$dir} Gpack must style the Wonder box on slot 35.");
    }
}
$centre = (string) file_get_contents($root . '/game/include/Controller/VillageCenterCtrl.php');
if (!str_contains($centre, '$i === WonderOfTheWorldModel::DISPLAY_SLOT')) {
    $fail('The village centre must draw the Wonder from its display slot.');
}
foreach (['Core/Village.php', 'Controller/BuildCtrl.php', 'Game/Buildings/AutoUpgradeAI.php'] as $file) {
    $source = (string) file_get_contents($root . '/game/include/' . $file);
    if (!str_contains($source, 'WonderOfTheWorldModel::RESERVED_SLOTS') || preg_match('/\[21, ?26, ?3[01]/', $source) === 1) {
        $fail("{$file} must use WonderOfTheWorldModel::RESERVED_SLOTS.");
    }
}

$map = (string) file_get_contents($root . '/game/include/Controller/MapBlockCtrl.php');
if (!str_contains($map, '"img/map/wonder/tribe{$tribeId}/{$this->getTileSize()}x{$this->getTileSize()}/wonder{$stage}.png"')
    || !str_contains($map, 'WonderOfTheWorldModel::stage(')) {
    $fail('Map blocks must draw a Wonder with its stage tile.');
}
foreach ([1, 2, 3, 5, 6, 7, 8, 9] as $tribe) {
    foreach ([6, 30, 60] as $size) {
        for ($stage = 0; $stage <= 5; ++$stage) {
            $tile = $root . "/game/deployment/public/img/map/wonder/tribe{$tribe}/{$size}x{$size}/wonder{$stage}.png";
            $info = is_file($tile) ? getimagesize($tile) : false;
            if ($info === false || $info[0] !== $size || $info[1] !== $size) {
                $fail("Tribe {$tribe} needs a {$size}px stage-{$stage} Wonder map tile.");
            }
        }
    }
}

echo "Wonder art contract passed.\n";
