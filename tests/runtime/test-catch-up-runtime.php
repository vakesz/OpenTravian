<?php

declare(strict_types=1);

// The catch-up pass after a long engine stop, on a throwaway fixture player only: overdue rows of different
// tables complete in one global chronological order with their original due times, at the configured rate,
// stop on a stop request or the time cap, are left alone when nothing is overdue, and are never completed twice
// when a worker (a second process) claims rows at the same moment.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Catch-up runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Database\DBI;
use Core\Jobs\CatchUp;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$check = static function (bool $condition, string $message) use ($fail): void {
    if (!$condition) {
        $fail($message);
    }
};

$pdo = DBI::getInstance();
try {
    $fixture = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Catch-up runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$kid = $fixture['kid'];
$now = time();
$never = static fn (): bool => false;
$level = static fn (int $field): int => (int) $pdo->fetchScalar("SELECT f$field FROM fdata WHERE kid=?", [$kid]);

// The decision to run.
$after = CatchUp::settings()['after'];
$check(!CatchUp::needed(0, $now), 'A world that never ran has nothing to catch up.');
$check(!CatchUp::needed($now - $after, $now) && CatchUp::needed($now - $after - 1, $now), 'The pass runs only past the configured outage length.');

// Every row below is dated in the future and the pass is limited to the fixture village (kid:): rows of the live world
// due before the cutoff are left alone, and the running engine's own workers only
// take rows that are due now, so they cannot take these and the checks are exact.
$base = $now + 100000;
$cutoff = $base + 10000;

// Global chronological order across tables, original due times, nothing left, a second run does nothing.
$pdo->run('INSERT IGNORE INTO smithy (kid) VALUES (?)', [$kid]);
$pdo->run('DELETE FROM research WHERE kid=?', [$kid]);
$pdo->run('DELETE FROM building_upgrade WHERE kid=?', [$kid]);
$pdo->run('DELETE FROM demolition WHERE kid=?', [$kid]);
$pdo->run('UPDATE smithy SET u1=0, u2=0, u3=0, u4=0, u5=0, u6=0, u7=0, u8=0 WHERE kid=?', [$kid]);
$pdo->run('UPDATE fdata SET f1=3, f2=3 WHERE kid=?', [$kid]);
$due = [
    ['research', $base - 5000, 'INSERT INTO research (kid, nr, mode, end_time) VALUES (?, 1, 0, ?)'],
    ['building', $base - 4000, 'INSERT INTO building_upgrade (kid, building_field, isMaster, start_time, commence) VALUES (?, 1, 0, ?, ?)'],
    ['research', $base - 3000, 'INSERT INTO research (kid, nr, mode, end_time) VALUES (?, 2, 0, ?)'],
    ['demolition', $base - 2000, 'INSERT INTO demolition (kid, building_field, end_time, complete) VALUES (?, 2, ?, 0)'],
    ['research', $base - 1000, 'INSERT INTO research (kid, nr, mode, end_time) VALUES (?, 3, 0, ?)'],
    ['building', $base - 500, 'INSERT INTO building_upgrade (kid, building_field, isMaster, start_time, commence) VALUES (?, 1, 0, ?, ?)'],
];
// Inserted out of order so only the due times can explain the result.
foreach (array_reverse($due) as [$source, $time, $sql]) {
    $pdo->run($sql, $source === 'building' ? [$kid, $time - 10, $time] : [$kid, $time]);
}
$order = [];
$started = microtime(true);
$result = CatchUp::run($cutoff, $never, null, static function (string $source, int $id, float $at) use (&$order, $kid, $pdo): void {
    $order[] = [$source, $at];
}, 20, 60, kid: $kid);
$elapsed = microtime(true) - $started;
$check($result['stoppedBy'] === 'done' && $result['remaining'] === 0 && $result['processed'] === count($due), 'The pass must complete every overdue row: ' . json_encode($result));
$check(array_column($order, 1) === array_map(static fn (array $row): float => (float) $row[1], $due), 'Rows must complete in due-time order across tables: ' . json_encode($order));
$check(array_column($order, 0) === ['research', 'building', 'research', 'demolition', 'research', 'building'], 'The tables must interleave in due-time order, not one table after the other.');
$check($elapsed >= 0.25, 'The rate cap of 20 rows a second must pace six rows over at least 0.25 s, took ' . round($elapsed, 3));
$check($level(1) === 5, 'Both overdue constructions must have completed once (field level 3 to 5), got ' . $level(1));
$check($level(2) === 2, 'The overdue demolition must have completed once, got ' . $level(2));
$check((int) $pdo->fetchScalar('SELECT u1+u2+u3 FROM smithy WHERE kid=?', [$kid]) === 3, 'Each overdue research must have completed once.');
$again = CatchUp::run($cutoff, $never, null, null, 20, 60, kid: $kid);
$check($again['processed'] === 0 && $again['stoppedBy'] === 'done', 'A second pass must find nothing to do.');
$check($level(1) === 5 && $level(2) === 2, 'A second pass must not complete anything twice.');

// A row due after the cutoff is not touched.
$pdo->run('INSERT INTO research (kid, nr, mode, end_time) VALUES (?, 4, 0, ?)', [$kid, $cutoff + 3600]);
$check(CatchUp::run($cutoff, $never, null, null, 1000, 60, kid: $kid)['processed'] === 0 && (int) $pdo->fetchScalar('SELECT COUNT(*) FROM research WHERE kid=?', [$kid]) === 1, 'A row not yet due must be left to the workers.');
$pdo->run('DELETE FROM research WHERE kid=?', [$kid]);

// A stop request and the time cap end the pass early and leave the rest for the workers.
foreach (range(1, 6) as $i) {
    $pdo->run('INSERT INTO research (kid, nr, mode, end_time) VALUES (?, 5, 0, ?)', [$kid, $base - 100 + $i]);
}
$calls = 0;
$stopped = CatchUp::run($cutoff, static function () use (&$calls): bool {
    return ++$calls > 2;
}, null, null, 1000, 60, kid: $kid);
$check($stopped['stoppedBy'] === 'stopped' && $stopped['processed'] === 2 && $stopped['remaining'] === 4, 'A stop request must end the pass after the current row: ' . json_encode($stopped));
$capped = CatchUp::run($cutoff, $never, null, null, 2, 1, kid: $kid);
$check($capped['stoppedBy'] === 'cap' && $capped['processed'] >= 1 && $capped['processed'] <= 3 && $capped['remaining'] >= 1, 'The time cap must end the pass: ' . json_encode($capped));
CatchUp::run($cutoff, $never, null, null, 1000, 60, kid: $kid);
$check((int) $pdo->fetchScalar('SELECT COUNT(*) FROM research WHERE kid=?', [$kid]) === 0, 'The remaining rows must complete when the pass runs on.');

// Progress is reported while it runs.
foreach (range(1, 3) as $i) {
    $pdo->run('INSERT INTO research (kid, nr, mode, end_time) VALUES (?, 6, 0, ?)', [$kid, $base - 100 + $i]);
}
$reports = [];
CatchUp::run($cutoff, $never, static function (array $progress) use (&$reports): void {
    $reports[] = $progress;
}, null, 4, 60, 0.5, kid: $kid);
$check($reports !== [] && $reports[0]['remaining'] < 3 && $reports[0]['processed'] >= 1, 'A long pass must report its progress: ' . json_encode($reports));

// No double claim: a worker (a separate process calling the workers' own research processor on the same rows in a
// loop) and the catch-up pass race for them; each row must take effect exactly once. The rows are dated in the future
// like the others, so the running engine cannot take part and the count is exact; the worker process picks rows by id
// instead of by "due now", which is the only difference from the engine's own research pass.
$stopFile = sys_get_temp_dir() . '/catchup-stop-' . getmypid();
@unlink($stopFile);
$script = 'require "/travian/game/deployment/include/env.php"; require "/travian/game/include/bootstrap.php";'
    . 'echo "ready\n"; $automation = Core\Automation::getInstance(); $db = Core\Database\DB::getInstance(); $end = microtime(true) + 30;'
    . 'while (!is_file(' . var_export($stopFile, true) . ') && microtime(true) < $end) {'
    . ' $rows = $db->query("SELECT id FROM research WHERE kid=' . (int) $kid . ' ORDER BY end_time, id LIMIT 50");'
    . ' while ($rows && ($row = $rows->fetch_assoc())) { $automation->processResearchTask((int) $row["id"]); }'
    . ' usleep(1000); }';
$worker = proc_open([PHP_BINARY, '-r', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
$check(is_resource($worker) && trim((string) fgets($pipes[1])) === 'ready', 'The worker process must start.');
$pdo->run('UPDATE smithy SET u1=0, u2=0, u3=0, u4=0, u5=0, u6=0, u7=0, u8=0 WHERE kid=?', [$kid]);
$values = [];
$parameters = [];
foreach (range(1, 8) as $nr) {
    foreach (range(1, 5) as $n) {
        $values[] = '(?, ?, 0, ?)';
        array_push($parameters, $kid, $nr, $base - 10000 + $nr * 10 + $n);
    }
}
$pdo->run('INSERT INTO research (kid, nr, mode, end_time) VALUES ' . implode(', ', $values), $parameters);
CatchUp::run($cutoff, $never, null, null, 1000, 60, kid: $kid);
touch($stopFile);
$workerOutput = stream_get_contents($pipes[2]);
proc_close($worker);
@unlink($stopFile);
$levels = $pdo->fetchSingleRow('SELECT u1, u2, u3, u4, u5, u6, u7, u8 FROM smithy WHERE kid=?', [$kid]);
$check(array_map('intval', array_values($levels)) === array_fill(0, 8, 5), 'Each of the 40 racing rows must take effect exactly once: ' . json_encode($levels) . ' ' . $workerOutput);
$check((int) $pdo->fetchScalar('SELECT COUNT(*) FROM research WHERE kid=?', [$kid]) === 0, 'No racing row may be left behind.');

echo "Catch-up runtime passed.\n";
