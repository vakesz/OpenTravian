<?php

declare(strict_types=1);

// Contract: a fully upgraded building or field shows the reference tooltip text, not a per-building
// "is completely extended." sentence (the build page keeps that one in its own message).

$root = dirname(__DIR__, 2);
$village = (string)file_get_contents($root . '/game/include/Core/Village.php');
$catalog = (string)file_get_contents($root . '/game/include/resources/Translation/en-US.php');

if (!str_contains($catalog, "['upgradeNotices']['completelyUpgraded'] = 'Completely upgraded; therefore no further upgrade possible.';")
    || !str_contains($village, 'T("Buildings", "upgradeNotices.completelyUpgraded")')
    || str_contains($village, '<span class="notice">\' . T("Buildings", "{$building[\'item_id\']}.title") . \' \' . T("Buildings", "upgradeNotices.reachedMaxLvL")')) {
    fwrite(STDERR, "The village tooltip of a maxed building must read the reference text.\n");
    exit(1);
}

// The Treasury favourite button reads like the Marketplace and Palace ones ("Set tab"), not a legacy sentence.
$treasury = (string)file_get_contents($root . '/game/include/Controller/Build/TreasuryCtrl.php');
if (!str_contains($treasury, "T('inGame', 'Set tab')") || str_contains($catalog, "['Treasury']['select tab x as favor tab']")) {
    fwrite(STDERR, "The Treasury favourite title must be the shared 'Set tab' text.\n");
    exit(1);
}

echo "Village max-level tooltip contract passed.\n";
