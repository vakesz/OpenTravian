<?php

declare(strict_types=1);

$controller = file_get_contents(
    dirname(__DIR__, 2) . '/game/include/Controller/RallyPoint/RallyPoint.php',
);

if (!is_string($controller)) {
    fwrite(STDERR, "Unable to read the rally-point controller.\n");
    exit(1);
}

foreach (["document.addEvent(", '/js/Game/Logout/Ee.js', 'new Ee()'] as $legacyClientCode) {
    if (str_contains($controller, $legacyClientCode)) {
        fwrite(STDERR, "Rally-point output must not inject the retired Prototype logout animation.\n");
        exit(1);
    }
}

echo "Rally-point legacy client contract passed.\n";
