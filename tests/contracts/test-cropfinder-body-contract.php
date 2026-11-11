<?php

declare(strict_types=1);

/**
 * Contract: Crop finder uses meta-page body token `cropfinder`
 * (not village perspectiveResources).
 */

function failCropfinderBodyContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$ctrl = file_get_contents($root . '/game/include/Controller/CropfinderCtrl.php');
if ($ctrl === false) {
    failCropfinderBodyContract('Unable to read CropfinderCtrl.php');
}

if (!str_contains($ctrl, "bodyCssClass'] = 'cropfinder'")) {
    failCropfinderBodyContract('CropfinderCtrl must set bodyCssClass to cropfinder.');
}

if (str_contains($ctrl, "bodyCssClass'] = 'perspectiveResources'")) {
    failCropfinderBodyContract('CropfinderCtrl must not keep perspectiveResources body class.');
}

fwrite(STDOUT, "Cropfinder body contract passed.\n");
