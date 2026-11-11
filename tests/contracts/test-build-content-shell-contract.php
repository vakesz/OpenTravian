<?php

declare(strict_types=1);

$controller = file_get_contents(__DIR__ . '/../../game/include/Controller/BuildCtrl.php');
$gpack = file_get_contents(__DIR__ . '/../../assets/gpack/travian/mainPage/css_ltr/imports.css');

if ($controller === false || $gpack === false) {
    fwrite(STDERR, "Unable to read the building-shell contract sources.\n");
    exit(1);
}

if (!str_contains($controller, "contentCssClass'] = 'build'")) {
    fwrite(STDERR, "Building views must apply the build class to the #content shell.\n");
    exit(1);
}

if (!str_contains($gpack, '#content.build{') && !str_contains($gpack, '#content.build {')) {
    fwrite(STDERR, "The native GPack no longer exposes the build content-shell selector.\n");
    exit(1);
}

echo "Building content-shell contract passed.\n";
