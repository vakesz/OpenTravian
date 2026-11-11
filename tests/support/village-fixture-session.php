<?php

declare(strict_types=1);

// Browser-test companion to village-fixture.php: creates the throwaway player with a random
// password and one queued construction, prints {"name","password","kid"} as a single JSON
// line, then stays alive until stdin closes. The fixture's shutdown handler removes every row
// when this script exits, so a crashed or interrupted browser test cannot leak the player.
// Run it with: docker compose exec -T php php tests/support/village-fixture-session.php
// With the argument `dailyquests` the player has 61 daily-quest points: the 25 and 50 milestones can be collected, the
// 75 and 100 ones are still locked.
// With the argument `adventures` the hero is at home in a village with a Rally Point and three adventures are listed.
// With the argument `visual` the player is deterministic for screenshot baselines instead: a fixed name,
// full stores (what can be afforded then no longer depends on the clock), an alliance with a fixed name
// and tag, one message and one report, and no queued construction.
// With the argument `auctions` the player can take part in auctions (ten adventures, silver), has items to sell,
// five running and twelve finished auctions of their own and fourteen bids, and the world has at least 25 item
// types for sale, so every auction list has a second page. No queued construction. The rows added for the
// player and the extra system auctions are removed with the fixture.

if (!is_file('/travian/game/deployment/include/env.php')) {
    fwrite(STDERR, "Run this inside the php container.\n");
    exit(2);
}
$_SERVER['REQUEST_URI'] = '/dorf1.php';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/dorf1.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once __DIR__ . '/village-fixture.php';
require_once __DIR__ . '/auction-fixture.php';

use Core\Database\DBI;
use Core\Security\Password;

$pdo = DBI::getInstance();
$fixture = fixtureVillageCreate();
$password = bin2hex(random_bytes(12));
$now = time();
$pdo->run(
    'UPDATE users SET password=?, last_login_time=?, last_owner_login_time=? WHERE id=?',
    [Password::hash($password), $now, $now, $fixture['id']]
);
$pdo->run('DELETE FROM building_upgrade WHERE kid=?', [$fixture['kid']]);
if (($argv[1] ?? '') === 'dailyquests') {
    $pdo->run('DELETE FROM daily_quest WHERE uid=?', [$fixture['id']]);
    $pdo->run('INSERT INTO daily_quest SET uid=?, qst1=1, qst2=3, qst3=3, qst4=1, qst5=3, qst6=3, qst7=3', [$fixture['id']]);
} elseif (($argv[1] ?? '') === 'visual') {
    $pdo->run('UPDATE users SET name=? WHERE id=?', ['zzfxvisual', $fixture['id']]);
    $pdo->run('UPDATE vdata SET wood=maxstore, clay=maxstore, iron=maxstore, crop=maxcrop WHERE kid=?', [$fixture['kid']]);
    $aid = (int)(new \Model\AllianceModel())->createAlliance($fixture['id'], 'zzfx visual', 'zzvis');
    // One message from the system account and one trade report, both at a fixed time, so the inbox and report
    // pages show a row. The fixture removal deletes the report (uid) but not a message sent by another account.
    $pdo->run(
        "INSERT INTO mdata (uid, to_uid, topic, message, md5_checksum, time) VALUES (1, ?, 'Welcome', 'A message for the visual baselines.', '', 1767268800)",
        [$fixture['id']]
    );
    \Game\NoticeHelper::addNotice(
        0, $fixture['id'], $fixture['kid'], $fixture['kid'], \Game\NoticeHelper::TYPE_RESOURCES_MOST_WOOD, '',
        [
            'sender' => ['uid' => $fixture['id'], 'uname' => 'zzfxvisual', 'kid' => $fixture['kid']],
            'receiver' => ['uid' => $fixture['id'], 'uname' => 'zzfxvisual', 'kid' => $fixture['kid']],
            'resources' => [1 => 100, 2 => 50, 3 => 50, 4 => 50],
            'timeTaken' => 600,
        ],
        1767268800
    );
    register_shutdown_function(static function () use ($pdo, $aid, $fixture): void {
        $pdo->run('DELETE FROM mdata WHERE to_uid=? AND uid=1', [$fixture['id']]);
        if ($aid > 0) {
            foreach (['alidata' => 'id', 'ali_log' => 'aid', 'ali_invite' => 'aid', 'alistats' => 'aid', 'alliance_notification' => 'aid'] as $table => $column) {
                $pdo->run("DELETE FROM `$table` WHERE `$column`=?", [$aid]);
            }
        }
    });
} elseif (($argv[1] ?? '') === 'adventures') {
    // The hero is at home in a village with a Rally Point, and three open adventures are listed.
    $pdo->run('UPDATE fdata SET f39=1, f39t=16 WHERE kid=?', [$fixture['kid']]);
    $pdo->run('UPDATE users SET protection=0, vacationActiveTil=0 WHERE id=?', [$fixture['id']]);
    $pdo->run('UPDATE units SET u11=1 WHERE kid=?', [$fixture['kid']]);
    $pdo->run('DELETE FROM adventure WHERE uid=?', [$fixture['id']]);
    $tiles = $pdo->fetchPluck('SELECT id FROM wdata WHERE occupied=0 AND id<>? ORDER BY id LIMIT 3 OFFSET 40', [$fixture['kid']]);
    foreach ($tiles as $index => $tile) {
        $pdo->run('INSERT INTO adventure (uid, kid, dif, created) VALUES (?, ?, ?, ?)', [$fixture['id'], $tile, $index % 2, $now]);
    }
} elseif (($argv[1] ?? '') === 'auctions') {
    auctionFixtureSeed($fixture['id']);
} else {
    $pdo->run(
        'INSERT INTO building_upgrade (kid, building_field, isMaster, start_time, commence) VALUES (?, 1, 0, ?, ?)',
        [$fixture['kid'], $now, $now + 3600]
    );
}
echo json_encode([
    'name' => (string)$pdo->fetchScalar('SELECT name FROM users WHERE id=?', [$fixture['id']]),
    'password' => $password,
    'kid' => $fixture['kid'],
]), "\n";
flush();
stream_get_contents(STDIN);
