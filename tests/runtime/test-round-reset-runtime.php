<?php

declare(strict_types=1);

// A round reset on throwaway copies of the world and account databases: the finished round is archived
// (rounds, round_standings), the round tables are emptied, the map is generated again, and the accounts are
// kept (identity and settings, a fresh start village each) or removed. The live databases are never written:
// the world and account connections of this process are pointed at the copies. The reset pauses the engine
// through Redis for its duration and takes a real backup of the copies, which this test removes again.
// Needs a database user that may CREATE DATABASE (TRAVIAN_TEST_ADMIN_PASSWORD, TRAVIAN_TEST_ADMIN_USER, default
// root); without one it prints a SKIPPED banner on stderr and exits 0.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Round reset runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use Core\Caching\Caching;
use Core\Config;
use Core\Database\DB;
use Core\Database\GlobalDB;
use Core\Jobs\EngineHeartbeat;
use Game\Round\RoundReset;
use Game\Round\RoundResetRefused;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$skip = static function (string $reason): never {
    fwrite(STDERR, "\n*** SKIPPED: round reset runtime: $reason\n"
        . "*** Set TRAVIAN_TEST_ADMIN_PASSWORD (and TRAVIAN_TEST_ADMIN_USER, default root) to a database user that may CREATE DATABASE.\n\n");
    echo "Round reset runtime passed (SKIPPED).\n";
    exit(0);
};
$check = static function (bool $condition, string $message) use ($fail): void {
    if (!$condition) {
        $fail($message);
    }
};

$connectionFile = (getenv('TRAVIAN_STATE_DIR') ?: '') . '/connection.local.php';
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
$adminUser = $adminPassword !== false ? (getenv('TRAVIAN_TEST_ADMIN_USER') ?: 'root') : (string) ($cfg['username'] ?? '');
$adminPassword = $adminPassword !== false ? $adminPassword : (string) ($cfg['password'] ?? '');
$admin = @new mysqli($cfg['hostname'], $adminUser, $adminPassword);
if ($admin->connect_errno) {
    $skip('cannot connect as ' . $adminUser . '.');
}
$suffix = getmypid();
$scratch = ['world' => "zz_round_world_$suffix", 'account' => "zz_round_account_$suffix"];
foreach ($scratch as $name) {
    if (!$admin->query("CREATE DATABASE `$name` CHARACTER SET utf8mb4")) {
        foreach ($scratch as $created) {
            $admin->query("DROP DATABASE IF EXISTS `$created`");
        }
        $skip('the user ' . $adminUser . ' may not CREATE DATABASE.');
    }
}

// The retention pass of the reset's backup must not touch the real backups.
putenv('TRAVIAN_BACKUP_KEEP_LAST=100000');
putenv('TRAVIAN_BACKUP_KEEP_DAILY=100000');
putenv('TRAVIAN_BACKUP_EXPORT_DIR=');
$backupGlob = rtrim(BACKUP_PATH, '/') . '/backup-*';
$backupsBefore = glob($backupGlob) ?: [];
$files = ['world' => sys_get_temp_dir() . "/zz_round_world_$suffix.sql.gz", 'account' => sys_get_temp_dir() . "/zz_round_account_$suffix.sql.gz"];

// Runs on every way out, including a failed check (which exits).
$cleanup = static function () use ($backupGlob, $backupsBefore, $files, $scratch, $admin): void {
    foreach (array_diff(glob($backupGlob) ?: [], $backupsBefore) as $file) {
        @unlink($file);
    }
    foreach ($files as $file) {
        @unlink($file);
    }
    EngineHeartbeat::clearPause();
    try {
        $cache = Caching::getInstance();
        foreach (RoundReset::CACHE_PATTERNS as $pattern) {
            $cache->deleteByPattern($pattern);
        }
    } catch (Throwable) {
        // The cache is only a convenience here.
    }
    foreach ($scratch as $name) {
        $admin->query("DROP DATABASE IF EXISTS `$name`");
    }
};
register_shutdown_function($cleanup);

try {
    foreach (['world' => DB::getInstance(), 'account' => GlobalDB::getInstance()] as $kind => $database) {
        $check($database->dumpTo($files[$kind]), "The live $kind database must dump.");
        $target = new DB(false);
        $target->setDatabaseDetails([$cfg['hostname'], $adminUser, $adminPassword, $scratch[$kind], 'utf8mb4']);
        $check($target->restoreFrom($files[$kind]), "The $kind copy must restore.");
    }

    // From here on this process only sees the copies.
    $world = DB::getInstance();
    $world->setDatabaseDetails([$cfg['hostname'], $adminUser, $adminPassword, $scratch['world'], 'utf8mb4']);
    $world->reconnect();
    $account = new DB(false);
    $account->setDatabaseDetails([$cfg['hostname'], $adminUser, $adminPassword, $scratch['account'], 'utf8mb4']);
    $account->reconnect();
    (new ReflectionProperty(GlobalDB::class, '_self'))->setValue(null, $account);
    $check($world->fetchScalar('SELECT DATABASE()') === $scratch['world'], 'The world connection must point at the copy.');

    $config = Config::getInstance();
    $refreshConfig = static function () use ($world, $config): void {
        $config->dynamic = (object) $world->query('SELECT * FROM config LIMIT 1')->fetch_assoc();
        $config->game->start_time = (int) $config->dynamic->startTime;
    };
    $finish = static function (int $result) use ($world, $refreshConfig): void {
        $world->run('UPDATE config SET serverFinished=?, finishStatusSet=1, serverFinishTime=?, isRestore=0, needsRestart=0', [$result, time() - 60]);
        $refreshConfig();
    };
    $worldId = (int) $config->settings->worldUniqueId;
    $account->run('UPDATE gameServers SET finished=1, registerClosed=1 WHERE id=?', [$worldId]);

    // A finished round with a winner, an alliance, medals and settings worth keeping.
    $players = [];
    $found = $world->query('SELECT id, name, password, email, race FROM users WHERE id > 2 AND access <> 3 ORDER BY id');
    while ($row = $found->fetch_assoc()) {
        $players[(int) $row['id']] = $row;
    }
    $check(count($players) >= 2, 'The copy needs at least two players.');
    $ids = array_keys($players);
    [$winner, $other] = [$ids[0], $ids[1]];
    $world->run("UPDATE users SET timezone='Pacific/Marquesas,0', gift_gold=12345, total_pop=9000, desc1='a profile text', sit1Uid=? WHERE id=?", [$other, $winner]);
    $world->run('INSERT INTO alidata (name, tag) VALUES (?, ?)', ['Round Test Alliance', 'RTA' . ($suffix % 1000)]);
    $aid = (int) $world->lastInsertId();
    $world->run('UPDATE users SET aid=? WHERE id=?', [$aid, $winner]);
    $world->run("INSERT INTO medal (uid, category, week, rank, points, img) VALUES (?, 1, 3, 1, '777', 'm1')", [$winner]);
    $world->run("INSERT INTO allimedal (aid, category, week, rank, points, img) VALUES (?, 2, 3, 1, '888', 'a1')", [$aid]);
    $world->run("INSERT INTO account_oauth_identity (provider, subject, uid, email, email_verified, created, last_login) VALUES ('google', ?, ?, 'rr@example.invalid', 1, 1, 1)", ['round-reset-' . $suffix, $winner]);
    $world->run('UPDATE fdata SET f99=100 WHERE kid=(SELECT kid FROM vdata WHERE owner=? ORDER BY capital DESC LIMIT 1)', [$winner]);
    $multihunterHash = (string) $world->fetchScalar('SELECT password FROM users WHERE id=2');
    $startGold = (int) $config->gold->startGold;

    // Refusals change nothing.
    $world->query('UPDATE config SET serverFinished=0, finishStatusSet=0');
    $refreshConfig();
    $usersBefore = (int) $world->fetchScalar('SELECT COUNT(*) FROM users');
    try {
        RoundReset::run(RoundReset::MODE_KEEP, 'tester');
        $fail('A round that has not finished must not be reset.');
    } catch (RoundResetRefused $refused) {
        $check(str_contains($refused->getMessage(), 'has not finished'), 'The unfinished refusal must say so.');
    }
    $finish(1);
    try {
        RoundReset::run('nonsense', 'tester');
        $fail('An unknown mode must be refused.');
    } catch (RoundResetRefused) {
    }
    $lock = Caching::getInstance()->lock('round_reset', 60);
    try {
        RoundReset::run(RoundReset::MODE_KEEP, 'tester');
        $fail('A second reset must be refused while one runs.');
    } catch (RoundResetRefused $refused) {
        $check(str_contains($refused->getMessage(), 'already running'), 'The lock refusal must say so.');
    } finally {
        Caching::getInstance()->unlock('round_reset', $lock);
    }
    $check((int) $world->fetchScalar('SELECT COUNT(*) FROM users') === $usersBefore && (int) $world->fetchScalar('SELECT COUNT(*) FROM rounds') === 0, 'A refused reset must not change the world.');

    // Keep the accounts.
    $startedAt = time();
    $roundId = RoundReset::run(RoundReset::MODE_KEEP, 'tester');
    $check($roundId === 1, 'The first archived round is round 1.');
    $round = $world->query('SELECT * FROM rounds WHERE id=1')->fetch_assoc();
    $check((int) $round['resultCode'] === 1 && (int) $round['winnerUid'] === $winner && $round['winnerName'] === $players[$winner]['name'], 'The archive must name the winner.');
    $check($round['winnerAllianceName'] === 'Round Test Alliance' && (int) $round['winnerAid'] === $aid, 'The archive must name the winner alliance.');
    $check($round['accountMode'] === 'keep' && $round['archivedBy'] === 'tester' && (int) $round['playerCount'] === count($players), 'The archive must record the mode, the admin and the player count.');
    $check(is_array(json_decode((string) $round['summary'], true)), 'The archive must hold the summary as JSON.');
    $top = $world->query("SELECT refId, value, detail FROM round_standings WHERE roundId=1 AND kind='player' AND position=1")->fetch_assoc();
    $check((int) $top['refId'] === $winner && (int) $top['value'] === 9000 && json_decode((string) $top['detail'], true)['alliance'] === 'Round Test Alliance', 'Players must be ranked by population with their details.');
    $check((int) $world->fetchScalar("SELECT COUNT(*) FROM round_standings WHERE roundId=1 AND kind='player'") === count($players), 'Every player must be archived.');
    $check((int) $world->fetchScalar("SELECT COUNT(*) FROM round_standings WHERE roundId=1 AND kind='alliance' AND refId=$aid AND value=9000") === 1, 'The alliance must be archived with its population.');
    $check((int) $world->fetchScalar("SELECT COUNT(*) FROM round_standings WHERE roundId=1 AND kind='medal' AND refId=$winner AND value=777") === 1, 'The player medal must be archived.');
    $check((int) $world->fetchScalar("SELECT COUNT(*) FROM round_standings WHERE roundId=1 AND kind='allimedal' AND refId=$aid AND value=888") === 1, 'The alliance medal must be archived.');

    $cfgRow = $world->query('SELECT * FROM config')->fetch_assoc();
    $check((int) $cfgRow['serverFinished'] === 0 && (int) $cfgRow['finishStatusSet'] === 0 && (int) $cfgRow['isRestore'] === 0 && (int) $cfgRow['needsRestart'] === 1 && (int) $cfgRow['startTime'] >= $startedAt && (int) $cfgRow['installed'] === 1, 'The new round must start now, with the engine told to restart.');
    $check((int) $account->fetchScalar("SELECT finished FROM gameServers WHERE id=$worldId") === 0 && (int) $account->fetchScalar("SELECT registerClosed FROM gameServers WHERE id=$worldId") === 0, 'The world must be open again in the account database.');
    $check((int) $world->fetchScalar('SELECT COUNT(*) FROM wdata') === (2 * (int) MAP_SIZE + 1) ** 2, 'The map must be generated again.');
    $check((int) $world->fetchScalar('SELECT COUNT(*) FROM medal') === 0 && (int) $world->fetchScalar('SELECT COUNT(*) FROM alidata') === 0 && (int) $world->fetchScalar('SELECT COUNT(*) FROM ndata') === 0, 'The round tables must be empty.');
    $check((int) $world->fetchScalar('SELECT COUNT(*) FROM rounds') === 1, 'The archive must survive the reset.');
    $check(array_map('intval', array_column($world->query('SELECT id FROM users WHERE id < 3 ORDER BY id')->fetch_all(MYSQLI_NUM), 0)) === [0, 1, 2], 'The system accounts must be created again.');
    $check((string) $world->fetchScalar('SELECT password FROM users WHERE id=2') === $multihunterHash, 'Multihunter must keep its password.');
    foreach ($players as $uid => $row) {
        $user = $world->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
        $check(is_array($user) && $user['name'] === $row['name'] && $user['password'] === $row['password'] && $user['email'] === $row['email'] && (int) $user['race'] === (int) $row['race'], "Player $uid must keep name, password, email and tribe.");
        $check((int) $user['gift_gold'] === $startGold && $user['desc1'] === '' && (int) $user['aid'] === 0 && (int) $user['total_pop'] > 0 && (int) $user['total_pop'] < 9000, "Player $uid must start over with gold, profile, alliance and statistics.");
        $check((int) $world->fetchScalar("SELECT COUNT(*) FROM vdata WHERE owner=$uid") === 1 && (int) $world->fetchScalar("SELECT COUNT(*) FROM hero WHERE uid=$uid") === 1, "Player $uid must get one fresh village and a hero.");
        $check((int) $world->fetchScalar("SELECT COUNT(*) FROM medal WHERE uid=$uid") === 0, "Player $uid must lose the medals.");
    }
    $check($world->fetchScalar("SELECT timezone FROM users WHERE id=$winner") === 'Pacific/Marquesas,0' && (int) $world->fetchScalar("SELECT sit1Uid FROM users WHERE id=$winner") === $other, 'Settings and sitters must be kept.');
    $check((int) $world->fetchScalar("SELECT COUNT(*) FROM account_oauth_identity WHERE uid=$winner") === 1, 'A kept account keeps its linked social account.');
    $check((int) $world->fetchScalar('SELECT COUNT(*) FROM available_villages WHERE occupied=1') >= count($players) + 3, 'The start villages must be taken on the new map.');
    $check((int) $world->fetchScalar('SELECT COUNT(*) FROM summary') === 1, 'The summary must start again with one row.');
    $check(!EngineHeartbeat::pauseRequested(), 'The engine pause must be released after a successful reset.');

    // The restore point is a real backup (world and account dump).
    $check(count(array_diff(glob($backupGlob) ?: [], $backupsBefore)) >= 2, 'The reset must leave a world and an account backup.');

    // Remove the accounts: a second round, the identities go.
    $finish(2);
    $second = RoundReset::run(RoundReset::MODE_REMOVE, 'tester');
    $check($second === 2, 'The second archived round is round 2.');
    $round = $world->query('SELECT resultCode, winnerUid, accountMode, playerCount FROM rounds WHERE id=2')->fetch_assoc();
    $check((int) $round['resultCode'] === 2 && $round['winnerUid'] === null && $round['accountMode'] === 'remove' && (int) $round['playerCount'] === count($players), 'A Natars win has no winner and the remove mode is recorded.');
    $check((int) $world->fetchScalar('SELECT COUNT(*) FROM users') === 3 && (int) $world->fetchScalar('SELECT COUNT(*) FROM vdata WHERE owner > 2') === 0, 'Removing the accounts leaves the system accounts only.');
    $check((int) $world->fetchScalar('SELECT COUNT(*) FROM account_oauth_identity') === 0, 'Removed accounts lose their linked social accounts.');
    $check((int) $world->fetchScalar('SELECT COUNT(*) FROM rounds') === 2, 'Both rounds stay archived.');
    $check(count(array_diff(glob($backupGlob) ?: [], $backupsBefore)) >= 4, 'Each reset must take its own backup.');
} finally {
    $cleanup();
}

echo "Round reset runtime passed.\n";
