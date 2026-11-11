<?php

declare(strict_types=1);

$starvation = file_get_contents(dirname(__DIR__, 2) . '/game/include/Game/Starvation.php');

if ($starvation === false
    || !str_contains($starvation, "\$heroKid = \$isMovement && (int)\$row['mode'] === 1 ? \$row['to_kid'] : \$row['kid'];")
    || !str_contains($starvation, "SELECT exp FROM hero WHERE kid=' . (int)\$heroKid . ' LIMIT 1")
    || !str_contains($starvation, 'Formulas::heroRegenerateCost(Formulas::heroLevel($experience), $row[\'race\'])')) {
    fwrite(STDERR, "Starvation must use the affected hero's actual level when releasing crop.\n");
    exit(1);
}

echo "Starvation hero-cost contract passed.\n";
