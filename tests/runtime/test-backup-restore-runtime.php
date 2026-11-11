<?php

declare(strict_types=1);

// The retention policy and backup verification on temporary directories, the tools/backup.php
// command line, and a round trip of the real world and account databases into throwaway
// databases (dump, restore, compare). The round trip needs a database user that may CREATE
// DATABASE: set TRAVIAN_TEST_ADMIN_PASSWORD (and TRAVIAN_TEST_ADMIN_USER, default root). Without
// one it prints a SKIPPED banner on stderr and exits 0, so the gap is visible in the run.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Backup restore runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use Core\Database\BackupSets;
use Core\Database\DB;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};

// Retention: 5 backups on 3 days, keep last 2 + 2 daily.
$dir = sys_get_temp_dir() . '/backup-retention-' . getmypid();
mkdir($dir);
$day = 86400000;
$base = (int)(strtotime('2026-01-10 12:00:00 UTC') * 1000);
$stamps = [];
foreach ([0, 1, 2, 3, 4] as $i) {
    $stamps[$i] = ($base - ($i < 2 ? 0 : ($i - 1) * $day) - $i * 3600000) . '-aaaa' . $i;
}
foreach ($stamps as $stamp) {
    touch("$dir/backup-$stamp.sql.gz");
    touch("$dir/backup-$stamp-account.sql.gz");
}
putenv('TRAVIAN_BACKUP_KEEP_LAST=2');
putenv('TRAVIAN_BACKUP_KEEP_DAILY=2');
DB::pruneBackups($dir);
$left = array_map('basename', glob("$dir/*"));
foreach ([0, 1] as $i) {
    if (!in_array("backup-{$stamps[$i]}.sql.gz", $left, true) || !in_array("backup-{$stamps[$i]}-account.sql.gz", $left, true)) {
        $fail("The newest backups must survive with both files (missing set $i).");
    }
}
foreach ([3, 4] as $i) {
    if (in_array("backup-{$stamps[$i]}.sql.gz", $left, true)) {
        $fail("Backup $i is older than the policy keeps and must be pruned.");
    }
}
// The backup a restore is about to read must survive the safety backup's retention pass,
// even when it is old enough for the policy to drop (it shares a day with newer ones).
foreach ($stamps as $stamp) {
    touch("$dir/backup-$stamp.sql.gz");
    touch("$dir/backup-$stamp-account.sql.gz");
}
DB::pruneBackups($dir, [$stamps[4]]);
foreach (['', '-account'] as $suffix) {
    if (!is_file("$dir/backup-{$stamps[4]}$suffix.sql.gz")) {
        $fail('A protected backup must survive pruning with both files.');
    }
}
array_map('unlink', glob("$dir/*"));
rmdir($dir);

// Export: a backup is copied whole or not at all, and never leaves a partial file behind.
$src = sys_get_temp_dir() . '/backup-export-src-' . getmypid();
$out = sys_get_temp_dir() . '/backup-export-out-' . getmypid();
mkdir($src);
mkdir($out);
file_put_contents("$src/backup-1-a.sql.gz", 'world');
file_put_contents("$src/backup-1-a-account.sql.gz", 'account');
if (!DB::exportBackup(["$src/backup-1-a-account.sql.gz", "$src/backup-1-a.sql.gz"], $out)
    || file_get_contents("$out/backup-1-a.sql.gz") !== 'world' || file_get_contents("$out/backup-1-a-account.sql.gz") !== 'account'
    || glob("$out/.*.partial") !== []) {
    $fail('An export must copy every file of the backup and leave no temporary file.');
}
array_map('unlink', glob("$out/*"));
if (DB::exportBackup(["$src/backup-1-a-account.sql.gz", "$src/missing.sql.gz"], $out) || glob("$out/*") !== [] || glob("$out/.*.partial") !== []) {
    $fail('A failed export must remove the files it already copied and any temporary file.');
}
if (DB::exportBackup(["$src/backup-1-a.sql.gz"], "$out/missing-directory")) {
    $fail('An export to a missing directory must fail.');
}
array_map('unlink', glob("$src/*"));
rmdir($src);
rmdir($out);

// Verification: a complete dump passes; a cut-short dump, a damaged archive and a missing file do not.
$vdir = sys_get_temp_dir() . '/backup-verify-' . getmypid();
mkdir($vdir);
file_put_contents("$vdir/good.sql.gz", gzencode("CREATE TABLE t (id INT);\n\n-- Dump completed on 2026-11-07  8:11:21\n"));
file_put_contents("$vdir/cut.sql.gz", gzencode("CREATE TABLE t (id INT);\nINSERT INTO t VALUES (1),(2"));
file_put_contents("$vdir/bad.sql.gz", substr((string)gzencode(random_bytes(5000)), 0, 400));
if (BackupSets::problem("$vdir/good.sql.gz") !== null) {
    $fail('A complete dump must verify.');
}
if (BackupSets::problem("$vdir/cut.sql.gz") !== 'the dump is incomplete') {
    $fail('A dump without the completion line must be reported incomplete.');
}
if (BackupSets::problem("$vdir/bad.sql.gz") !== 'the archive is damaged') {
    $fail('A truncated archive must be reported damaged.');
}
if (BackupSets::problem("$vdir/none.sql.gz") !== 'the file is missing') {
    $fail('A missing file must be reported.');
}
$set = ['name' => 'backup-1-a', 'time' => 0, 'world' => "$vdir/good.sql.gz", 'account' => "$vdir/cut.sql.gz", 'state' => null, 'size' => 0];
if (BackupSets::problems($set) !== ['cut.sql.gz: the dump is incomplete']) {
    $fail('A set reports each damaged file by name.');
}
array_map('unlink', glob("$vdir/*"));
rmdir($vdir);

// The command line: argument handling and the refusals that protect the live databases.
$cli = static function (string $arguments) use ($fail): array {
    $output = [];
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('/travian/tools/backup.php') . ' ' . $arguments . ' 2>&1', $output, $code);

    return [$code, implode("\n", $output)];
};
$cdir = sys_get_temp_dir() . '/backup-cli-' . getmypid();
mkdir($cdir);
$older = '1793995696400-6be0a5d5e8610f8c';
$newer = '1794039080121-7cf0b63e9c7b9cc3';
foreach ([$older, $newer] as $stamp) {
    file_put_contents("$cdir/backup-$stamp.sql.gz", gzencode("-- Dump completed on 2026-11-07  8:11:21\n"));
}
file_put_contents("$cdir/backup-$newer-account.sql.gz", gzencode("-- Dump completed on 2026-11-07  8:11:21\n"));
[$code, $out] = $cli('list --dir=' . escapeshellarg($cdir));
if ($code !== 0 || strpos($out, $newer) === false || strpos($out, $older) === false || strpos($out, $newer) > strpos($out, $older) || !str_contains($out, 'world+account') || !str_contains($out, 'world only')) {
    $fail("list --dir must show both backups, newest first, with their parts:\n$out");
}
[$code, $out] = $cli('verify --dir=' . escapeshellarg($cdir));
if ($code !== 0 || substr_count($out, ' ok') !== 2) {
    $fail("verify must pass sound backups:\n$out");
}
file_put_contents("$cdir/backup-$older.sql.gz", 'not gzip');
[$code, $out] = $cli('verify backup-' . $older . ' --dir=' . escapeshellarg($cdir));
if ($code !== 1 || !str_contains($out, 'DAMAGED')) {
    $fail("verify must exit 1 on a damaged backup:\n$out");
}
[$code] = $cli('restore backup-' . $newer . ' --dir=' . escapeshellarg($cdir));
if ($code !== 2) {
    $fail('restore without --yes must refuse (exit 2).');
}
[$code] = $cli('restore ../../etc/passwd --yes');
if ($code !== 2) {
    $fail('restore must reject a name that is not a backup name (exit 2).');
}
array_map('unlink', glob("$cdir/*"));
rmdir($cdir);

// Round trip of the real databases into throwaway ones.
$connectionFile = (getenv('TRAVIAN_STATE_DIR') ?: '') . '/connection.local.php';
$skip = static function (string $reason): never {
    fwrite(STDERR, "\n*** SKIPPED: backup restore round trip: $reason\n"
        . "*** Set TRAVIAN_TEST_ADMIN_PASSWORD (and TRAVIAN_TEST_ADMIN_USER, default root) to a database user that may CREATE DATABASE.\n\n");
    echo "Backup restore runtime passed (round trip SKIPPED).\n";
    exit(0);
};
if (!is_file($connectionFile)) {
    $skip('there is no connection file in the state directory.');
}
$cfg = (static function (string $file): ?array {
    include $file;

    return $connection['database'] ?? null;
})($connectionFile);
if (!is_array($cfg)) {
    $skip('the connection file has no database settings.');
}
mysqli_report(MYSQLI_REPORT_OFF);
$adminPassword = getenv('TRAVIAN_TEST_ADMIN_PASSWORD');
$adminUser = $adminPassword !== false ? (getenv('TRAVIAN_TEST_ADMIN_USER') ?: 'root') : (string)($cfg['username'] ?? '');
$adminPassword = $adminPassword !== false ? $adminPassword : (string)($cfg['password'] ?? '');
$admin = @new mysqli($cfg['hostname'], $adminUser, $adminPassword);
if ($admin->connect_errno) {
    $skip('cannot connect as ' . $adminUser . '.');
}
$suffix = getmypid();
$scratch = ['world' => "zz_backup_world_$suffix", 'account' => "zz_backup_account_$suffix"];
foreach ($scratch as $name) {
    if (!$admin->query("CREATE DATABASE `$name` CHARACTER SET utf8mb4")) {
        foreach ($scratch as $created) {
            $admin->query("DROP DATABASE IF EXISTS `$created`");
        }
        $skip('the user ' . $adminUser . ' may not CREATE DATABASE.');
    }
}
$files = ['world' => sys_get_temp_dir() . "/zz_world_$suffix.sql.gz", 'account' => sys_get_temp_dir() . "/zz_account_$suffix.sql.gz"];
$counts = static function (mysqli $link, string $database): array {
    $rows = [];
    $tables = $link->query("SELECT table_name FROM information_schema.tables WHERE table_schema = '" . $link->real_escape_string($database) . "' AND table_type = 'BASE TABLE'");
    foreach ($tables->fetch_all(MYSQLI_NUM) as [$table]) {
        $rows[$table] = (int)$link->query("SELECT COUNT(*) FROM `$database`.`$table`")->fetch_row()[0];
    }
    ksort($rows);

    return $rows;
};
try {
    // The engine keeps writing while the dump runs, so a table is only compared when its live row count
    // was the same before and after the dump; a mismatch is retried from a fresh dump a few times.
    $roundTrip = static function (string $kind, DB $database, string $name) use ($admin, $counts, $files, $scratch, $cfg, $adminUser, $adminPassword): ?string {
        $before = $counts($admin, $name);
        if (!$database->dumpTo($files[$kind])) {
            return "dumpTo() must write a complete $kind dump.";
        }
        $after = $counts($admin, $name);
        if (BackupSets::problem($files[$kind]) !== null) {
            return "The $kind dump must verify.";
        }
        $target = new DB(false);
        $target->setDatabaseDetails([$cfg['hostname'], $adminUser, $adminPassword, $scratch[$kind], 'utf8mb4']);
        if (!$target->restoreFrom($files[$kind])) {
            return "restoreFrom() must import the $kind dump into a throwaway database.";
        }
        $restored = $counts($admin, $scratch[$kind]);
        if (array_keys($restored) !== array_keys($before)) {
            return "The restored $kind database must have the same tables as the live one.";
        }
        $stable = 0;
        foreach ($before as $table => $rows) {
            if ($after[$table] === $rows) {
                ++$stable;
                if ($restored[$table] !== $rows) {
                    return "Table $table of the $kind database restored with {$restored[$table]} rows instead of $rows.";
                }
            }
        }

        return $stable * 2 < count($before) ? "Too many $kind tables changed while the dump ran to compare the restore." : null;
    };
    $live = ['world' => DB::getInstance(), 'account' => Core\Database\GlobalDB::getInstance()];
    foreach ($live as $kind => $database) {
        $problem = 'not run';
        for ($attempt = 1; $attempt <= 3 && $problem !== null; ++$attempt) {
            $problem = $roundTrip($kind, $database, (string)$database->fetchScalar('SELECT DATABASE()'));
        }
        if ($problem !== null) {
            $fail($problem);
        }
    }
} finally {
    foreach ($files as $file) {
        @unlink($file);
    }
    foreach ($scratch as $name) {
        $admin->query("DROP DATABASE IF EXISTS `$name`");
    }
}

// One dump implementation: a consistent snapshot with rows in primary-key order and binary columns as hex.
$blobDatabase = "zz_backup_blob_$suffix";
$blobFile = sys_get_temp_dir() . "/$blobDatabase.sql.gz";
if (!$admin->query("CREATE DATABASE `$blobDatabase` CHARACTER SET utf8mb4")) {
    $fail('Cannot create the dump format database.');
}
try {
    $admin->query("CREATE TABLE `$blobDatabase`.t (id INT PRIMARY KEY, b BLOB, s VARCHAR(20))");
    $admin->query("INSERT INTO `$blobDatabase`.t VALUES (3, 'c', NULL), (1, 0x00FF27, ''), (2, NULL, 'two')");
    $dumper = new DB(false);
    $dumper->setDatabaseDetails([$cfg['hostname'], $adminUser, $adminPassword, $blobDatabase, 'utf8mb4']);
    if (!$dumper->dumpTo($blobFile)) {
        $fail('dumpTo() must dump the format database.');
    }
    $sql = (string)gzdecode((string)file_get_contents($blobFile));
    if (!str_contains($sql, '0x00FF27')) {
        $fail('Binary columns must be dumped as hex.');
    }
    if (!preg_match('/\(1,0x00FF27.*\(2,NULL.*\(3,/s', $sql)) {
        $fail('Rows must be dumped in primary-key order, with NULL only for real NULLs.');
    }
    $admin->query("DELETE FROM `$blobDatabase`.t");
    if (!$dumper->restoreFrom($blobFile)) {
        $fail('The format database dump must restore.');
    }
    $row = $admin->query("SELECT HEX(b), s FROM `$blobDatabase`.t WHERE id = 1")->fetch_row();
    if ($row !== ['00FF27', '']) {
        $fail('A binary value and an empty string must survive the round trip: ' . json_encode($row));
    }
} finally {
    @unlink($blobFile);
    $admin->query("DROP DATABASE IF EXISTS `$blobDatabase`");
}

echo "Backup restore runtime passed.\n";
