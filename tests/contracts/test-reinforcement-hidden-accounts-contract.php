<?php

declare(strict_types=1);

/**
 * Contract: reinforcements arriving at a hidden account return home only when that account is a
 * system account (Support, Natars, Multihunter: users.id 0-2). Natars refuse them even when visible.
 * A hidden real player keeps receiving reinforcements whatever its id; the old rule also refused
 * them for hidden players with ids 3-10.
 */

$processor = file_get_contents(dirname(__DIR__, 2) . '/game/include/Model/Movements/ReinforcementProcessor.php');

if ($processor === false) {
    fwrite(STDERR, "ReinforcementProcessor must be readable.\n");
    exit(1);
}

if (!str_contains($processor, "if (\$to_uid === UserId::NATARS || (\$to_uid <= UserId::LAST_SYSTEM && (int) \$m->getUser(\$to_uid, 'hidden')['hidden'] === 1)) {")) {
    fwrite(STDERR, "Only hidden system accounts (ids up to UserId::LAST_SYSTEM) and the Natars may refuse reinforcements.\n");
    exit(1);
}

if (preg_match('/\$to_uid\s*<=\s*(10|UserId::LEGACY_HIDDEN_MAX)\b/', $processor) === 1) {
    fwrite(STDERR, "Hidden real players with ids 3-10 must not refuse reinforcements.\n");
    exit(1);
}

echo "Reinforcement hidden-accounts contract passed.\n";
