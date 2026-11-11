<?php

declare(strict_types=1);

$translation = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Translation/en-US.php');

if ($translation === false) {
    fwrite(STDERR, "Unable to read the English building translations.\n");
    exit(1);
}

// Warehouse and Granary units are reference evidence (their pages were compared). The Great Warehouse and Great
// Granary follow their siblings, since the reference has no unit of their own and each stores the same goods.
// (The Great Granary entry used to pin 'resources', which was local behaviour, not reference evidence.)
foreach ([10 => 'resources', 11 => 'crops', 38 => 'resources', 39 => 'crops'] as $buildingId => $unit) {
    if (!str_contains($translation, "\$Definition['Buildings'][{$buildingId}]['unit'] = '{$unit}';")) {
        fwrite(STDERR, "Storage building {$buildingId} must use the reference '{$unit}' unit label.\n");
        exit(1);
    }
}

if (!str_contains($translation, "The resources lumber, clay and iron are stored in the warehouse.")) {
    fwrite(STDERR, "Warehouse description must use the reference player-facing wording.\n");
    exit(1);
}

if (!str_contains($translation, "\$Definition['Buildings']['level'] = 'Level';")) {
    fwrite(STDERR, "Building level label must use reference casing 'Level'.\n");
    exit(1);
}

echo "Building reference language contract passed.\n";
