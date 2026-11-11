#!/usr/bin/env php
<?php

declare(strict_types=1);

// Dev-only: compares the live account/world databases with database/account.sql and database/world.sql
// and prints (or applies) the DDL that reconciles live -> expected. NOT a migration system.
//
//   docker compose exec -T -e TRAVIAN_TEST_ADMIN_PASSWORD="$MARIADB_ROOT_PASSWORD" php php dev/schema-diff.php
//   ... php dev/schema-diff.php --apply                      # non-destructive changes only
//   ... php dev/schema-diff.php --apply --allow-destructive  # also DROP column/table, narrowing, UNIQUE on clean data
//
// The expected schemas are loaded into scratch databases (zz_schemadiff_*) with the admin account, which
// is dropped again afterwards. Admin user defaults to root; host and database names come from the usual
// TRAVIAN_DB_HOST / TRAVIAN_ACCOUNT_DATABASE / TRAVIAN_WORLD_DATABASE variables. Column order and seed
// rows are not compared (order differences are only reported).

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This tool may only run from the command line.\n");
    exit(1);
}

$apply = in_array('--apply', $argv, true);
$allowDestructive = in_array('--allow-destructive', $argv, true);
if ($allowDestructive && !$apply) {
    fwrite(STDERR, "--allow-destructive only makes sense together with --apply.\n");
    exit(2);
}

$host = getenv('TRAVIAN_DB_HOST') ?: 'db';
$user = getenv('TRAVIAN_TEST_ADMIN_USER') ?: 'root';
$password = (string)getenv('TRAVIAN_TEST_ADMIN_PASSWORD');
if ($password === '') {
    fwrite(STDERR, "Set TRAVIAN_TEST_ADMIN_PASSWORD (the database administrator password).\n");
    exit(2);
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli($host, $user, $password);
$db->set_charset('utf8mb4');

$targets = [
    ['live' => getenv('TRAVIAN_ACCOUNT_DATABASE') ?: 'travian_main', 'file' => dirname(__DIR__) . '/database/account.sql'],
    ['live' => getenv('TRAVIAN_WORLD_DATABASE') ?: 'travian_world', 'file' => dirname(__DIR__) . '/database/world.sql'],
];

function q(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

/** @return array<string, array<string, mixed>> tables keyed by lower-case name */
function snapshot(mysqli $db, string $schema): array
{
    $s = $db->real_escape_string($schema);
    $tables = [];
    foreach ($db->query("SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA='$s' AND TABLE_TYPE='BASE TABLE'") as $r) {
        $tables[strtolower($r['TABLE_NAME'])] = ['name' => $r['TABLE_NAME'], 'engine' => $r['ENGINE'], 'collation' => $r['TABLE_COLLATION'], 'columns' => [], 'indexes' => []];
    }
    foreach ($db->query("SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLLATION_NAME, COLUMN_COMMENT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$s' ORDER BY TABLE_NAME, ORDINAL_POSITION") as $r) {
        $tables[strtolower($r['TABLE_NAME'])]['columns'][$r['COLUMN_NAME']] = [$r['COLUMN_TYPE'], $r['IS_NULLABLE'], $r['COLUMN_DEFAULT'], strtolower((string)$r['EXTRA']), $r['COLLATION_NAME'], $r['COLUMN_COMMENT']];
    }
    $idx = [];
    foreach ($db->query("SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, INDEX_TYPE, COLUMN_NAME, SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='$s' ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX") as $r) {
        $i = &$idx[strtolower($r['TABLE_NAME'])][$r['INDEX_NAME']];
        $i ??= ['unique' => !(int)$r['NON_UNIQUE'], 'type' => $r['INDEX_TYPE'], 'cols' => []];
        $i['cols'][] = $r['COLUMN_NAME'] . ($r['SUB_PART'] !== null ? '(' . $r['SUB_PART'] . ')' : '');
        unset($i);
    }
    foreach ($idx as $t => $list) {
        $tables[$t]['indexes'] = $list;
    }
    return $tables;
}

/** Column and index definition lines of SHOW CREATE TABLE, keyed by column name / index name. */
function definitions(mysqli $db, string $schema, string $table): array
{
    $row = $db->query('SHOW CREATE TABLE ' . q($schema) . '.' . q($table))->fetch_row();
    $cols = $indexes = [];
    foreach (explode("\n", (string)$row[1]) as $line) {
        $line = rtrim(trim($line), ',');
        if (preg_match('/^`((?:[^`]|``)+)`\s/', $line, $m)) {
            $cols[str_replace('``', '`', $m[1])] = $line;
        } elseif (preg_match('/^PRIMARY KEY/', $line)) {
            $indexes['PRIMARY'] = $line;
        } elseif (preg_match('/^(?:UNIQUE |FULLTEXT |SPATIAL )?KEY `((?:[^`]|``)+)`/', $line, $m)) {
            $indexes[str_replace('``', '`', $m[1])] = $line;
        }
    }
    return [$row[1], $cols, $indexes];
}

/** True when the new column type can lose data held in the old one. */
function narrows(string $old, string $new): bool
{
    $parse = static function (string $t): array {
        preg_match('/^(\w+)(?:\(([^)]*)\))?\s*(unsigned)?/i', $t, $m);
        return [strtolower($m[1] ?? $t), $m[2] ?? '', !empty($m[3])];
    };
    [$ot, $oa, $ou] = $parse($old);
    [$nt, $na, $nu] = $parse($new);
    $ints = ['tinyint' => 1, 'smallint' => 2, 'mediumint' => 3, 'int' => 4, 'bigint' => 8];
    $text = ['tinytext' => 255, 'text' => 65535, 'mediumtext' => 16777215, 'longtext' => 4294967295];
    if (isset($ints[$ot], $ints[$nt])) {
        return $ints[$nt] < $ints[$ot] || ($ints[$nt] === $ints[$ot] && $ou !== $nu);
    }
    if (in_array($ot, ['varchar', 'char', 'varbinary', 'binary'], true) && $nt === $ot) {
        return (int)$na < (int)$oa;
    }
    if (isset($text[$ot], $text[$nt])) {
        return $text[$nt] < $text[$ot];
    }
    if (in_array($ot, ['enum', 'set'], true) && $nt === $ot) {
        $o = array_map('trim', explode(',', $oa));
        $n = array_map('trim', explode(',', $na));
        return array_diff($o, $n) !== [] || ($ot === 'enum' && array_slice($n, 0, count($o)) !== $o);
    }
    return $ot !== $nt; // different type family: treat as potentially lossy
}

function importSql(mysqli $db, string $schema, string $file): void
{
    $db->query('DROP DATABASE IF EXISTS ' . q($schema));
    $db->query('CREATE DATABASE ' . q($schema) . ' CHARACTER SET utf8mb4');
    $db->select_db($schema);
    $db->multi_query((string)file_get_contents($file));
    do {
        if ($r = $db->store_result()) {
            $r->free();
        }
    } while ($db->more_results() && $db->next_result());
    $db->query('COMMIT');
}

/**
 * @return array{safe: list<string>, destructive: list<string>, blocked: list<string>, notes: list<string>}
 */
function diff(mysqli $db, string $live, string $expected): array
{
    $out = ['safe' => [], 'destructive' => [], 'blocked' => [], 'notes' => []];
    $want = snapshot($db, $expected);
    $have = snapshot($db, $live);
    $phase = ['create' => [], 'cols' => [], 'idx' => [], 'drops' => []];

    foreach ($want as $key => $t) {
        $name = q($live) . '.' . q($t['name']);
        if (!isset($have[$key])) {
            $phase['create'][] = [false, preg_replace('/^CREATE TABLE /', 'CREATE TABLE ' . q($live) . '.', definitions($db, $expected, $t['name'])[0], 1)];
            continue;
        }
        $h = $have[$key];
        $name = q($live) . '.' . q($h['name']);
        [, $colDefs, $idxDefs] = definitions($db, $expected, $t['name']);

        if ($t['engine'] !== $h['engine']) {
            $phase['cols'][] = [false, "ALTER TABLE $name ENGINE={$t['engine']}"];
        }
        if ($t['collation'] !== $h['collation']) {
            $charset = explode('_', (string)$t['collation'])[0];
            $phase['cols'][] = [false, "ALTER TABLE $name DEFAULT CHARACTER SET $charset COLLATE {$t['collation']}"];
        }

        $prev = null;
        foreach ($t['columns'] as $c => $sig) {
            $after = $prev === null ? 'FIRST' : 'AFTER ' . q($prev);
            if (!isset($h['columns'][$c])) {
                $phase['cols'][] = [false, "ALTER TABLE $name ADD COLUMN {$colDefs[$c]} $after"];
            } elseif ($h['columns'][$c] !== $sig) {
                $phase['cols'][] = [narrows($h['columns'][$c][0], $sig[0]), "ALTER TABLE $name MODIFY COLUMN {$colDefs[$c]}"];
            }
            $prev = $c;
        }
        foreach ($h['columns'] as $c => $_) {
            if (!isset($t['columns'][$c])) {
                $phase['drops'][] = [true, "ALTER TABLE $name DROP COLUMN " . q($c)];
            }
        }
        $order = static fn(array $cols, array $other): array => array_values(array_filter(array_keys($cols), static fn($c) => isset($other[$c])));
        if ($order($t['columns'], $h['columns']) !== $order($h['columns'], $t['columns'])) {
            $out['notes'][] = "{$h['name']}: column order differs from the schema (not applied).";
        }

        foreach ($h['indexes'] as $i => $sig) {
            if (!isset($t['indexes'][$i]) || $t['indexes'][$i] !== $sig) {
                $drop = $i === 'PRIMARY' ? 'DROP PRIMARY KEY' : 'DROP INDEX ' . q($i);
                $phase['idx'][] = [false, "ALTER TABLE $name $drop"];
            }
        }
        foreach ($t['indexes'] as $i => $sig) {
            if (isset($h['indexes'][$i]) && $h['indexes'][$i] === $sig) {
                continue;
            }
            $stmt = "ALTER TABLE $name ADD {$idxDefs[$i]}";
            if ($sig['unique']) {
                $plain = array_map(static fn($c) => preg_replace('/\(\d+\)$/', '', $c), $sig['cols']);
                if (array_diff($plain, array_keys($h['columns'])) === []) {
                    $list = implode(', ', array_map('q', $plain));
                    $notNull = implode(' AND ', array_map(static fn($c) => q($c) . ' IS NOT NULL', $plain));
                    $dups = $db->query("SELECT $list, COUNT(*) n FROM $name WHERE $notNull GROUP BY $list HAVING n > 1 LIMIT 5")->fetch_all(MYSQLI_ASSOC);
                    if ($dups !== []) {
                        $out['blocked'][] = "$stmt  -- BLOCKED, duplicates: " . json_encode($dups);
                        continue;
                    }
                }
            }
            $phase['idx'][] = [false, $stmt];
        }
    }
    foreach ($have as $key => $h) {
        if (!isset($want[$key])) {
            $phase['drops'][] = [true, 'DROP TABLE ' . q($live) . '.' . q($h['name'])];
        }
    }
    foreach ($phase as $items) {
        foreach ($items as [$destructive, $sql]) {
            $out[$destructive ? 'destructive' : 'safe'][] = $sql;
        }
    }
    return $out;
}

foreach ($db->query("SHOW DATABASES LIKE 'zz\\_schemadiff\\_%'") as $row) {
    $db->query('DROP DATABASE ' . q((string)current($row)));
}

$exit = 0;
$pending = 0;
foreach ($targets as $i => $target) {
    $scratch = 'zz_schemadiff_' . $i . '_' . getmypid();
    try {
        importSql($db, $scratch, $target['file']);
        $result = diff($db, $target['live'], $scratch);
    } finally {
        $db->query('DROP DATABASE IF EXISTS ' . q($scratch));
    }
    echo "== {$target['live']} (" . basename($target['file']) . ") ==\n";
    foreach (['safe' => 'Non-destructive', 'destructive' => 'Destructive (needs --apply --allow-destructive)', 'blocked' => 'Blocked'] as $k => $title) {
        if ($result[$k] === []) {
            continue;
        }
        echo "-- $title\n";
        foreach ($result[$k] as $sql) {
            echo $sql . ";\n";
        }
    }
    foreach ($result['notes'] as $note) {
        echo "-- note: $note\n";
    }
    $todo = array_merge($result['safe'], $apply && $allowDestructive ? $result['destructive'] : []);
    if ($apply) {
        foreach ($todo as $sql) {
            try {
                $db->query($sql);
                echo "applied: $sql\n";
            } catch (mysqli_sql_exception $e) {
                echo "FAILED: $sql\n  " . $e->getMessage() . "\n";
                $exit = 1;
                break;
            }
        }
    }
    $pending += count($result['safe']) + count($result['destructive']) + count($result['blocked']);
    if ($result['safe'] === [] && $result['destructive'] === [] && $result['blocked'] === []) {
        echo "-- in sync" . ($result['notes'] ? ' (apart from the notes above)' : '') . "\n";
    }
}
if (!$apply && $pending > 0) {
    echo "\nDry run: $pending statement(s) pending. Re-run with --apply to execute the non-destructive set.\n";
}
exit($exit);
