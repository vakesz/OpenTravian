<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/game/include/Core/Time.php';
require_once dirname(__DIR__, 2) . '/game/include/Game/UserId.php';
require_once dirname(__DIR__, 2) . '/game/include/Game/AccessLevel.php';
require_once dirname(__DIR__, 2) . '/game/include/Game/Slot.php';
require_once dirname(__DIR__, 2) . '/game/include/Game/ResearchMode.php';
require_once dirname(__DIR__, 2) . '/game/include/Game/Conquest/CaptureResult.php';
require_once dirname(__DIR__, 2) . '/game/include/Game/Conquest/OasisCaptureResult.php';
require_once dirname(__DIR__, 2) . '/game/include/Game/Gid.php';
require_once dirname(__DIR__, 2) . '/game/include/Game/Unit.php';
require_once dirname(__DIR__, 2) . '/game/include/Game/Tribes.php';
require_once dirname(__DIR__, 2) . '/game/include/Game/Hero/ItemBtype.php';
require_once dirname(__DIR__, 2) . '/game/include/Game/Hero/ItemType.php';
require_once dirname(__DIR__, 2) . '/game/include/functions.general.php';

$expected = [
    57 => "\u{202D}57\u{202C}",
    0 => "\u{202D}0\u{202C}",
    -35 => "\u{202D}\u{2212}\u{202D}35\u{202C}\u{202C}",
];
foreach ($expected as $value => $html) {
    if (coordinateNumberHTML($value) !== $html || coordinateNumberHTML((string)$value) !== $html) {
        fwrite(STDERR, "Coordinate {$value} must render as the reference does (true minus, LRO/PDF wrappers).\n");
        exit(1);
    }
}

echo "Coordinate number contract passed.\n";
