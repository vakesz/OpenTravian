<?php
declare(strict_types=1);

/**
 * Contract: the simulator keeps the .results mount before a user runs a
 * simulation and fills that same container afterward.  The result partial
 * supplies its contents only, avoiding a nested results box.
 */

$root = dirname(__DIR__, 2);
$main = file_get_contents($root . '/game/include/resources/Templates/combat/main.php');
$result = file_get_contents($root . '/game/include/resources/Templates/combat/simulatorResult.php');
if (!is_string($main) || !is_string($result)) {
    fwrite(STDERR, "Cannot read combat simulator result templates.\n");
    exit(1);
}

if (!str_contains($main, '<div class="results"><?= $vars[\'result\'] ?? \'\'; ?></div>')) {
    fwrite(STDERR, "Combat simulator must retain its empty result mount.\n");
    exit(1);
}
if (str_contains($result, '<div class="results">')) {
    fwrite(STDERR, "Combat result partial must not nest the shared result mount.\n");
    exit(1);
}
if (!str_contains($result, 'separator long') || !str_contains($result, 'result ')) {
    fwrite(STDERR, "Combat result partial must retain its native result-card contents.\n");
    exit(1);
}

echo "Combat simulator results-mount contract passed.\n";
