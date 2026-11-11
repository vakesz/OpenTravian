<?php

declare(strict_types=1);

// A speed change rescales the timers that are still running (tools/reconcile-world-speed-profile.php). A movement that
// started in the past, a training batch that has already begun and every other column below "now" are unsigned BIGINT or
// INT in the schema, so the subtraction must be signed: the rescale used to abort with "BIGINT UNSIGNED value is out of
// range" and roll every timer back. Runs the real statements on fixture rows inside a transaction that is rolled back,
// for speeds up (10 -> 100, 1 -> 1000, across the millisecond unit) and down (100 -> 10).

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "World speed timers runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/dorf1.php';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/dorf1.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use Core\Database\DB;
use Game\WorldSpeedTimers;

$fail = static function (string $message): never {
    fwrite(STDERR, 'World speed timers runtime failed: ' . $message . "\n");
    exit(1);
};
$db = DB::getInstance();
$kid = 4000000;   // no such village: the rows belong to nobody and are rolled back anyway
$now = time();

foreach ([[10, 100], [1, 1000], [100, 10], [10, 25000]] as [$from, $to]) {
    $oldUnit = \Game\WorldSpeedProfile::trainingTimeUnit($from);
    $nowMs = $now * 1000;
    $at = "({$from}x to {$to}x)";
    $db->begin_transaction();
    try {
        $rows = [
            // [table, insert columns => values, [column => [old value, scale unit of that column]]]
            'movement past start' => ['movement', ['kid' => $kid, 'to_kid' => $kid + 1, 'race' => 1, 'mode' => 0, 'attack_type' => 3, 'start_time' => $nowMs - 600000, 'end_time' => $nowMs + 1200000], ['start_time', 'end_time'], 1000],
            'movement very old start' => ['movement', ['kid' => $kid + 1, 'to_kid' => $kid, 'race' => 1, 'mode' => 0, 'attack_type' => 3, 'start_time' => 1000, 'end_time' => $nowMs + 90000], ['start_time', 'end_time'], 1000],
            'training begun' => ['training', ['kid' => $kid, 'nr' => 1, 'num' => 5, 'item_id' => 19, 'training_time' => 10 * $oldUnit, 'commence' => $now * $oldUnit - 40 * $oldUnit, 'end_time' => $now * $oldUnit + 3600 * $oldUnit], ['commence', 'end_time'], $oldUnit],
            'building queue' => ['building_upgrade', ['kid' => $kid, 'building_field' => 1, 'isMaster' => 0, 'start_time' => $now - 100, 'commence' => $now + 500], ['commence'], 1],
            'demolition' => ['demolition', ['kid' => $kid, 'building_field' => 20, 'end_time' => $now + 900], ['end_time'], 1],
            'research' => ['research', ['kid' => $kid, 'nr' => 2, 'mode' => 0, 'end_time' => $now + 700], ['end_time'], 1],
            'oasis abandonment' => ['odelete', ['kid' => $kid, 'oid' => 1, 'end_time' => $now + 1800], ['end_time'], 1],
            'alliance bonus' => ['alliance_bonus_upgrade_queue', ['aid' => $kid, 'type' => 1, 'time' => $now + 2400], ['time'], 1],
            'auction' => ['auction', ['uid' => 1, 'btype' => 1, 'type' => 1, 'num' => 1, 'time' => $now + 7200, 'finish' => 0, 'cancel' => 0], ['time'], 1],
        ];
        $ids = [];
        foreach ($rows as $name => [$table, $values]) {
            $columns = implode(', ', array_map(static fn (string $c): string => "`$c`", array_keys($values)));
            $marks = implode(', ', array_fill(0, count($values), '?'));
            if ($db->run("INSERT INTO `$table` ($columns) VALUES ($marks)", array_values($values)) === false) {
                throw new RuntimeException("could not insert the {$name} row");
            }
            $ids[$name] = (int)$db->fetchScalar('SELECT LAST_INSERT_ID()');
        }
        $plans = WorldSpeedTimers::plans($from, $to, $now);
        foreach ($plans as $plan) {
            if ($db->query(WorldSpeedTimers::statement($plan)) === false) {
                throw new RuntimeException('the rescale of ' . $plan['label'] . ' failed: ' . $db->error);
            }
        }
        $byTable = [];
        foreach ($plans as $plan) {
            $byTable[$plan['table']] = $plan;
        }
        foreach ($rows as $name => [$table, $values, $columns, $oldScale]) {
            $plan = $byTable[$table];
            $fromUnit = (int)($plan['from_unit'] ?? $plan['unit']);
            $factor = $plan['unit'] / $fromUnit;
            $nowOld = (int)(($plan['now'] / $plan['unit']) * $fromUnit);
            $row = $db->query("SELECT * FROM `$table` WHERE id=" . $ids[$name])->fetch_assoc();
            foreach ($columns as $column) {
                $old = (int)$values[$column];
                $expected = max(0, (int)round($plan['now'] + ($old - $nowOld) * $plan['ratio'] * $factor));
                // The scaled arithmetic runs in doubles: allow a rounding step of the column's unit.
                if (abs((float)$row[$column] - $expected) > max(1, $plan['unit'] >= 1000000000 ? 512 : 1)) {
                    throw new RuntimeException("{$name}.{$column} is {$row[$column]} after the rescale, expected {$expected}");
                }
            }
            if ($name === 'movement very old start' && (int)$row['start_time'] < 0) {
                throw new RuntimeException('a start time before the epoch must clamp at zero');
            }
            if ($table === 'training') {
                $expectedDuration = max(1, (int)round($values['training_time'] * $plan['ratio'] * $factor));
                if ((int)$row['training_time'] !== $expectedDuration) {
                    throw new RuntimeException("the training time is {$row['training_time']}, expected {$expectedDuration}");
                }
            }
        }
    } catch (Throwable $exception) {
        $db->rollback();
        $fail($exception->getMessage() . " {$at}.");
    }
    $db->rollback();
    if ((int)$db->fetchScalar('SELECT COUNT(*) FROM movement WHERE kid=?', [$kid]) !== 0) {
        $fail("the fixture rows were not rolled back {$at}.");
    }
}

echo "World speed timers runtime passed (past-start movements, begun training, queues and auctions, four speed changes).\n";
