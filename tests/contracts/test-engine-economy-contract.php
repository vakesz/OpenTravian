<?php

declare(strict_types=1);

// Trade routes pay before they send inside one transaction, and starvation upkeep never
// credits a store above its capacity (behaviour: tests/runtime/test-trade-route-runtime.php).

$root = dirname(__DIR__, 2);
$fail = static function (string $message): void {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$automation = (string)file_get_contents($root . '/game/include/Core/Automation.php');
$start = strpos($automation, 'private function processTradeRoute(');
$end = strpos($automation, 'private function deleteBatched(');
$body = substr($automation, (int)$start, (int)$end - (int)$start);
foreach (['begin_transaction()', 'rollback()', 'commit()', 'FOR UPDATE', 'Village::debit('] as $needle) {
    if (!str_contains($body, $needle)) {
        $fail("The trade route run must use {$needle}.");
    }
}
if (strpos($body, 'Village::debit(') > strpos($body, '->sendResources(')) {
    $fail('A trade route must debit the village before it sends the goods.');
}
if (str_contains($body, 'UPDATE vdata SET wood=wood-')) {
    $fail('No unguarded absolute debit may remain in the trade route code.');
}
$starvation = (string)file_get_contents($root . '/game/include/Game/Starvation.php');
foreach (['LEAST(maxstore, wood+', 'LEAST(maxcrop, crop+'] as $needle) {
    if (!str_contains($starvation, $needle)) {
        $fail("Starvation must clamp production to capacity: {$needle}.");
    }
}

echo "Engine economy contract passed.\n";
