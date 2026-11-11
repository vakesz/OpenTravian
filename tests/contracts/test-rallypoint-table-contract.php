<?php

declare(strict_types=1);

$renderer = file_get_contents(__DIR__ . '/../../game/include/Controller/RallyPoint/RallyPointHTML.php');

if ($renderer === false) {
    fwrite(STDERR, "Cannot read Rally Point table renderer.\n");
    exit(1);
}

$legacyTableStart = "'<table ' . (isset(\$row['id']) ? 'id=\"entity-' . \$row['id'] : '') . '\" cellspacing";
if (str_contains($renderer, $legacyTableStart)) {
    fwrite(STDERR, "Rally Point troop tables must not emit an unmatched quote when there is no movement ID.\n");
    exit(1);
}

if (!str_contains($renderer, '$tableIdAttribute') || !str_contains($renderer, "'<table' . \$tableIdAttribute")) {
    fwrite(STDERR, "Rally Point troop tables must compose the optional movement ID as a complete attribute.\n");
    exit(1);
}

echo "Rally Point table contract passed.\n";
