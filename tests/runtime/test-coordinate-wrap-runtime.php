<?php

declare(strict_types=1);

// Map coordinates wrap around the world edge, however far out they are (the reference answers a block
// request at 9999..10008 with a wrapped image, not an error).

require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use Game\Formulas;

$edge = (int)MAP_SIZE;
$size = 2 * $edge + 1;
$cases = [
    0 => 0,
    $edge => $edge,
    -$edge => -$edge,
    $edge + 1 => -$edge,
    -$edge - 1 => $edge,
    $edge + $size => $edge,
    -$edge - 3 * $size - 1 => $edge,
];
foreach ($cases as $input => $expected) {
    $actual = Formulas::coordinateFixer($input);
    if ($actual !== $expected) {
        fwrite(STDERR, "coordinateFixer($input) = $actual, expected $expected.\n");
        exit(1);
    }
}
for ($x = -3 * $size; $x <= 3 * $size; $x += 7) {
    $fixed = Formulas::coordinateFixer($x);
    if ($fixed < -$edge || $fixed > $edge || (($fixed - $x) % $size) !== 0) {
        fwrite(STDERR, "coordinateFixer($x) = $fixed is not a wrapped equivalent.\n");
        exit(1);
    }
}

echo "Coordinate wrap runtime test passed.\n";
