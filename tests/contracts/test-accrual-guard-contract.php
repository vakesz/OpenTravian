<?php

declare(strict_types=1);

/**
 * Source contract: ResourcesHelper::updateVillageResources credits accrual relatively (so a concurrent debit is
 * kept), clamps it to the stores, and guards both of its writes with the lastmupdate it read (so one interval is
 * credited once). When the full-mode guard misses, the recomputed production is still stored.
 * The behaviour is exercised in tests/runtime/test-accrual-debit-race-runtime.php; the guard-miss branch needs a
 * concurrent writer, so it is pinned here.
 */

function failAccrualGuardContract(string $message): never
{
    fwrite(STDERR, 'Accrual guard contract failed: ' . $message . PHP_EOL);
    exit(1);
}

$source = file_get_contents(dirname(__DIR__, 2) . '/game/include/Game/ResourcesHelper.php');
if ($source === false) {
    failAccrualGuardContract('could not read ResourcesHelper.php.');
}
if (!preg_match('/public static function updateVillageResources\(.*?\n    \}\n/s', $source, $match)) {
    failAccrualGuardContract('updateVillageResources could not be located.');
}
$body = $match[0];

if (substr_count($body, 'AND lastmupdate=" . (int)$row[\'lastmupdate\']') !== 2) {
    failAccrualGuardContract('both accrual writes (simple and full mode) must be guarded by the lastmupdate they read.');
}
if (substr_count($body, 'wood=LEAST(maxstore, wood+') !== 2 || substr_count($body, 'crop=LEAST(maxcrop, crop+') !== 2) {
    failAccrualGuardContract('both accrual writes must be relative and clamped to the warehouse and granary.');
}
if (preg_match('/crop=\{\$crop\}/', $body)) {
    failAccrualGuardContract('crop must not be written as the absolute value that was read.');
}
if (!preg_match('/affectedRows\(\) === 0\) \{\s*\$db->query\("UPDATE vdata SET woodp=\{\$production\[0\]\}, clayp=\{\$production\[1\]\}, ironp=\{\$production\[2\]\}, cropp=\{\$production\[3\]\} WHERE kid=\$kid"\);/', $body)) {
    failAccrualGuardContract('when the full-mode guard misses, the recomputed production must still be stored.');
}

echo "Accrual guard contract passed.\n";
