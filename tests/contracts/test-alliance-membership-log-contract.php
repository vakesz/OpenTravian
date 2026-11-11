<?php

declare(strict_types=1);

// Founding, inviting, joining and leaving an alliance each leave a log entry that names the player, including
// "left" (the player is already out of the alliance when the entry is written). Runs on throwaway fixture players.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Alliance membership log contract skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Database\DBI;
use Model\AllianceModel;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $founder = fixtureVillageCreate();
    $pdo->run('UPDATE users SET name=? WHERE id=?', ['zzfxf' . getmypid(), $founder['id']]);
    $member = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Alliance membership log contract passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$aid = 0;
register_shutdown_function(static function () use ($pdo, &$aid): void {
    if ($aid > 0) {
        foreach (['alidata' => 'id', 'ali_log' => 'aid', 'ali_invite' => 'aid', 'alistats' => 'aid', 'alliance_notification' => 'aid'] as $table => $column) {
            $pdo->run("DELETE FROM `$table` WHERE `$column`=?", [$aid]);
        }
    }
});

$model = new AllianceModel();
$tag = 'zz' . substr((string) getmypid(), -3);
$aid = (int) $model->createAlliance($founder['id'], 'zzfx alliance ' . getmypid(), $tag);
if ($aid <= 0) {
    $fail('createAlliance must return the new alliance id.');
}
if ($model->createAlliance($member['id'], 'zzfx other ' . getmypid(), $tag) !== false) {
    $fail('A second alliance with the same tag must be refused.');
}
$pdo->run('UPDATE alidata SET max=10 WHERE id=?', [$aid]);

if ($model->sendInvite($founder['id'], $aid, $member['id']) !== 22222 || $model->sendInvite($founder['id'], $aid, $member['id']) !== 1) {
    $fail('An invitation is sent once; a repeat reports that it was already sent.');
}
$inviteId = (int) $pdo->fetchScalar('SELECT id FROM ali_invite WHERE aid=? AND uid=?', [$aid, $member['id']]);
if ($model->acceptInvite($member['id'], $inviteId) !== $aid) {
    $fail('Accepting an invitation must return the joined alliance id.');
}
if ((int) $pdo->fetchScalar('SELECT aid FROM users WHERE id=?', [$member['id']]) !== $aid) {
    $fail('The accepting player must belong to the alliance.');
}

$model->leaveAlliance($member['id'], $aid);
if ((int) $pdo->fetchScalar('SELECT aid FROM users WHERE id=?', [$member['id']]) !== 0) {
    $fail('The leaving player must no longer belong to the alliance.');
}
$memberName = (string) $pdo->fetchScalar('SELECT name FROM users WHERE id=?', [$member['id']]);
$left = (string) $pdo->fetchScalar('SELECT data FROM ali_log WHERE aid=? AND type=? ORDER BY id DESC LIMIT 1', [$aid, AllianceModel::LOG_LEFT]);
if ($left !== AllianceModel::LOG_LEFT . ':' . $member['id'] . ':' . $memberName) {
    $fail("The 'left' log entry must name the player, got '$left'.");
}
$joined = (string) $pdo->fetchScalar('SELECT COUNT(id) FROM ali_log WHERE aid=? AND type=?', [$aid, AllianceModel::LOG_JOINED]);
if ((int) $joined !== 2) {
    $fail('Founding and joining must each be logged (expected 2 joined entries).');
}

echo "Alliance membership log contract passed.\n";
