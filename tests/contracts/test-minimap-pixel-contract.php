<?php

declare(strict_types=1);

$source = file_get_contents(dirname(__DIR__, 2) . '/game/include/Controller/MinimapCtrl.php');

if ($source === false
    || !str_contains($source, '$x = MAP_SIZE + (int)$row[\'x\'];')
    || !str_contains($source, '$y = MAP_SIZE - (int)$row[\'y\'];')
    || !str_contains($source, 'imagesetpixel($img, $x, $y,')) {
    fwrite(STDERR, "Minimap rendering must use explicit integer pixel coordinates.\n");
    exit(1);
}

echo "Minimap pixel contract passed.\n";
