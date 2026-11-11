<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/game/include/Game/Hero/Artwork.php';

use Game\Hero\Artwork;

foreach ([
    [1, 2000, 86400, 1200],
    [2, 1300, 86400, 800],
    [3, 1000, 43200, 600],
    [5, 700, 43200, 400],
    [10, 400, 21600, 200],
] as [$speed, $limit, $cooldown, $reward]) {
    if (Artwork::reward($speed, 1200) !== $reward
        || Artwork::reward($speed, 100000) !== $limit
        || Artwork::reward($speed, 0) !== 0
        || Artwork::reward($speed, -1) !== 0
        || Artwork::availableAt($speed, 100000) !== 100000 + $cooldown
        || Artwork::availableAt($speed, 0) !== 0) {
        throw new RuntimeException("Artwork reward, cap, or cooldown is incorrect at x$speed.");
    }
}
if (Artwork::reward(2, 1001) !== 667 || Artwork::reward(10, 1001) !== 166) {
    throw new RuntimeException('Fractional artwork rewards must round down after scaling.');
}
for ($previous = 0; $previous <= 40; ++$previous) {
    if (Artwork::canDrop($previous) !== ($previous >= 31)) {
        throw new RuntimeException('Artwork must not drop from the first 31 adventures.');
    }
}
foreach ([4 => 3, 6 => 5, 9 => 5, 20 => 10, 1000 => 10] as $custom => $published) {
    if (Artwork::rules($custom) !== Artwork::rules($published)) {
        throw new RuntimeException('Custom artwork speeds must hold the preceding published point.');
    }
}
$root = dirname(__DIR__, 2);
$schema = file_get_contents("$root/database/world.sql");
$update = file_get_contents("$root/game/include/update.php");
if (!str_contains($schema, "`lastArtworkUse` INT(10) UNSIGNED NOT NULL DEFAULT '0'")
    || !str_contains($update, 'if ($currentVersion < 101)')
    || !str_contains($update, 'ADD COLUMN IF NOT EXISTS `lastArtworkUse`')) {
    throw new RuntimeException('New and existing worlds must persist artwork cooldowns without rewriting old uses.');
}
foreach (['game-client.js'] as $file) {
    $script = file_get_contents("$root/game/deployment/public/$file");
    // The use dialog shows an amount field only when the server's descriptor asks for one (an artwork
    // has none), and without a field the request carries exactly one item.
    foreach ([
        'item.artwork.availableAt > Date.now() / 1000',
        'item.artwork.cp',
        'var field = info.input',
        'return input.length ? Math.min(Math.max(isNaN(value) ? 1 : value, 1), item.amount) : 1;',
    ] as $required) {
        if (!str_contains($script, $required)) {
            throw new RuntimeException("Artwork single-use, cooldown, and reward preview must be wired in $file.");
        }
    }
}
$dialog = file_get_contents("$root/game/include/Game/Hero/ConsumableDialog.php");
if (!str_contains($dialog, '\'input\' => !in_array($type, [self::BUCKET, self::BOOK_OF_WISDOM, self::ARTWORK], true),')) {
    throw new RuntimeException('The artwork use dialog must have no amount field.');
}
echo "Artwork speed tables, caps, rounding, cooldowns, drop boundaries and client wiring passed.\n";
