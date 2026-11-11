<?php

declare(strict_types=1);

/**
 * Contract: every hero picture is composed by Game\Hero\HeroImage from the
 * Hero V2 atlases and served as /heroV2/{head,body}/<hash>.<uid>.1.png.
 * The fixtures are the reference renders of one appearance
 * (captured 2026-10-30); the compositor must stay within resampling noise.
 */

function failHeroImageContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
define('STATE_DIR', sys_get_temp_dir() . '/');
require $root . '/game/include/Game/Hero/HeroImage.php';
require $root . '/game/include/Game/Hero/HeroFace.php';

foreach ([
    '/game/include/Controller/Hero_imageCtrl.php',
    '/game/include/Controller/HeroBodyCtrl.php',
    '/game/deployment/public/img/hero',
    '/game/deployment/public/js/Game/Logout',
] as $retired) {
    if (file_exists($root . $retired)) {
        failHeroImageContract("Retired hero image path still exists: $retired");
    }
}

$routes = file_get_contents($root . '/game/include/mainInclude.php');
$nginx = file_get_contents($root . '/docker/nginx/default.conf.template');
if (str_contains($routes, 'hero_image.php') || str_contains($routes, 'hero_body.php')
    || str_contains($routes, 'hero_head.php') || !str_contains($routes, "'hero_v2_image.php' => 'HeroV2ImageCtrl'")) {
    failHeroImageContract('mainInclude must route only hero_v2_image.php.');
}
if (!str_contains($nginx, '/heroV2/(head|body)/([0-9a-f]{40})')) {
    failHeroImageContract('nginx must map /heroV2/{head,body}/<hash>.<uid>.1.png.');
}

$callers = shell_exec('grep -rIlE "hero_image\\.php|hero_body\\.php|hero_head\\.php" '
    . escapeshellarg($root . '/game') . ' ' . escapeshellarg($root . '/assets/gpack/travian/mainPage/lang') . ' 2>/dev/null');
if (trim((string)$callers) !== '') {
    failHeroImageContract("Legacy hero image URLs remain in:\n" . $callers);
}

$class = new ReflectionClass(Game\Hero\HeroImage::class);
$compose = $class->getMethod('composeBody');
$crop = $class->getMethod('cropHead');

$reference = [
    'gender' => 'male', 'skinColor' => 'skin1', 'eyeColor' => 'blue', 'hairColor' => 'brightbrown',
    'tribeColor' => 'gaul', 'jaw' => 3, 'eyes' => 3, 'brows' => 5, 'nose' => 2, 'mouth' => 12, 'ears' => 1,
    'hair' => 1, 'beard' => 1, 'scar' => null, 'tattoo' => 9,
];

/** Mean and max per-pixel difference (alpha and alpha-weighted colour, 0..255). */
function heroImageDiff(GdImage $a, GdImage $b): array
{
    $sum = 0.0;
    $max = 0.0;
    $width = imagesx($a);
    $height = imagesy($a);
    for ($y = 0; $y < $height; ++$y) {
        for ($x = 0; $x < $width; ++$x) {
            $c = imagecolorat($a, $x, $y);
            $d = imagecolorat($b, $x, $y);
            $alphaC = (127 - (($c >> 24) & 127)) * 2;
            $alphaD = (127 - (($d >> 24) & 127)) * 2;
            $diff = abs($alphaC - $alphaD);
            if ($alphaC || $alphaD) {
                foreach ([16, 8, 0] as $shift) {
                    $diff = max($diff, abs((($c >> $shift) & 255) - (($d >> $shift) & 255)) * min($alphaC, $alphaD) / 255);
                }
            }
            $sum += $diff;
            $max = max($max, $diff);
        }
    }

    return [$sum / ($width * $height), $max];
}

$body = $compose->invoke(null, $reference);
$head = $crop->invoke(null, $body);
foreach ([['body', $body, 278, 500], ['head', $head, 120, 120]] as [$kind, $image, $width, $height]) {
    if (imagesx($image) !== $width || imagesy($image) !== $height) {
        failHeroImageContract("The $kind image must be {$width}x{$height}.");
    }
    $expected = imagecreatefrompng(__DIR__ . "/../fixtures/hero-v2/$kind.png");
    [$mean, $max] = heroImageDiff($image, $expected);
    if ($mean > 1.0 || $max > 40.0) {
        failHeroImageContract(sprintf('The %s drifted from the reference render (mean %.3f, max %.1f).', $kind, $mean, $max));
    }
}

// Every atlas combination must compose without a missing frame.
foreach (['male', 'female'] as $gender) {
    foreach (['skin1', 'skin4'] as $skin) {
        foreach (['roman', 'teuton', 'gaul', 'egyptian', 'hun', 'spartan', 'viking'] as $tribe) {
            foreach (['black', 'blondepink', 'redblack'] as $hair) {
                $appearance = array_merge($reference, [
                    'gender' => $gender, 'skinColor' => $skin, 'tribeColor' => $tribe, 'hairColor' => $hair,
                    'beard' => $gender === 'male' ? 14 : null, 'scar' => 3, 'jaw' => 15, 'mouth' => 15,
                ]);
                $compose->invoke(null, $appearance);
            }
        }
    }
}

// Equipped gear: part of the picture and of its hash (approximate layers, see
// docs/research/travian-ui-ux-source-comparison.md).
if (Game\Hero\HeroImage::GEAR_SLOTS !== ['shoes', 'body', 'helmet', 'leftHand', 'rightHand']) {
    failHeroImageContract('Gear layers must stack in the reference layerConfigs order: shoes, body, helmet, leftHand, rightHand.');
}
$hash = $class->getMethod('computeHash');
$plainHash = $hash->invoke(null, $reference);
$geared = array_merge($reference, ['gear' => ['leftHand' => 76, 'shoes' => 97]]);
if ($hash->invoke(null, $geared) === $plainHash
    || $hash->invoke(null, array_merge($reference, ['gear' => ['leftHand' => 76]])) === $hash->invoke(null, $geared)) {
    failHeroImageContract('The hero image hash must change with every equipped item.');
}
$gearedBody = $compose->invoke(null, $geared);
[$gearMean] = heroImageDiff($gearedBody, $body);
if ($gearMean < 0.05) {
    failHeroImageContract('Equipped gear must change the composed hero picture.');
}
[$headMean] = heroImageDiff($crop->invoke(null, $compose->invoke(null, array_merge($reference, ['gear' => ['helmet' => 4]]))), $head);
if ($headMean < 0.1) {
    failHeroImageContract('An equipped helmet must change the head picture.');
}

// Body armour replaces the tribe tunic and is not tribe-tinted (measured on 237 
// composites, 2026-11-01): the armoured picture must not depend on the tribe, while the
// hash still carries the tribe and the bare tunic still does.
$armoured = fn (string $tribe) => $compose->invoke(null, array_merge($reference, ['tribeColor' => $tribe, 'gear' => ['body' => 85]]));
[$armourTribeMean] = heroImageDiff($armoured('roman'), $armoured('teuton'));
[$tunicTribeMean] = heroImageDiff(
    $compose->invoke(null, array_merge($reference, ['tribeColor' => 'roman'])),
    $compose->invoke(null, array_merge($reference, ['tribeColor' => 'teuton']))
);
if ($armourTribeMean > 0.05 || $tunicTribeMean < 0.5) {
    failHeroImageContract(sprintf('Armour must not be tribe-tinted while the tunic is (armour %.3f, tunic %.3f).', $armourTribeMean, $tunicTribeMean));
}
if ($hash->invoke(null, array_merge($reference, ['tribeColor' => 'roman', 'gear' => ['body' => 85]]))
    === $hash->invoke(null, array_merge($reference, ['tribeColor' => 'teuton', 'gear' => ['body' => 85]]))) {
    failHeroImageContract('The hero image hash must include the tribe even when armour is worn.');
}

// Every item type has a layer, or is listed here as missing.
$missingGear = [];
$itemTypes = [
    'helmet' => range(1, 15),
    'body' => range(82, 93),
    'leftHand' => array_merge(range(61, 69), range(73, 81)),
    'rightHand' => array_merge(range(16, 60), range(115, 144), range(149, 163)),
    'shoes' => range(94, 102),
];
foreach ($itemTypes as $slot => $types) {
    foreach ($types as $type) {
        foreach (['male', 'female'] as $gender) {
            $art = Game\Hero\HeroImage::gearArt($gender, $slot, $type);
            if ($art === null) {
                if (!in_array("$gender-$slot-$type", $missingGear, true)) {
                    failHeroImageContract("Hero gear $gender $slot type $type has no layer and is not listed as missing.");
                }
            } elseif (!is_file(Game\Hero\HeroImage::gearLayerPath($gender, $slot, $art))) {
                failHeroImageContract("Hero gear layer is missing for $gender $slot art $art.");
            }
        }
    }
}
if (Game\Hero\HeroImage::gearArt('male', 'helmet', 9999) !== null || Game\Hero\HeroImage::gearArt('male', 'horse', 103) !== null) {
    failHeroImageContract('Unknown item types must fall back to no layer.');
}
foreach (glob(dirname(__DIR__, 2) . '/assets/gpack/travian/mainPage/textures/hero/items/*.png') ?: [] as $layerFile) {
    $size = getimagesize($layerFile);
    if (!preg_match('/\/(male|female)-(shoes|body|helmet|leftHand|rightHand)-\d+\.png$/', $layerFile) || $size[0] !== 278 || $size[1] !== 500) {
        failHeroImageContract('Unexpected hero gear layer file: ' . basename($layerFile));
    }
}

echo "Hero V2 image contract passed.\n";
