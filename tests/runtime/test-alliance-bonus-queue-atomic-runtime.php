<?php

declare(strict_types=1);

// A due alliance bonus upgrade is applied exactly once and never lost: when the level-up fails part-way, the queue
// row stays and nothing of the level-up is kept; the next pass applies it. The throwaway fixture player is put in an
// alliance id that no real alliance uses, so no real alliance is touched.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Alliance bonus queue atomic runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Automation;
use Core\Database\DB;
use Model\AllianceBonusModel;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
try {
    $fixture = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Alliance bonus queue atomic runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$uid = $fixture['id'];
$db = DB::getInstance();
$aid = (int)$db->fetchScalar('SELECT COALESCE(MAX(id), 0) FROM alidata') + 100000 + getmypid() % 1000;
$type = AllianceBonusModel::TYPE_CP;
$pending = AllianceBonusModel::USER_UNLOCK_PENDING_ANIMATION[$type];
register_shutdown_function(static function () use ($aid): void {
    DB::getInstance()->run('DELETE FROM alliance_bonus_upgrade_queue WHERE aid=?', [$aid]);
});
$db->run("UPDATE users SET aid=?, $pending=0 WHERE id=?", [$aid, $uid]);
$db->run('INSERT INTO alliance_bonus_upgrade_queue (aid, type, time) VALUES (?, ?, ?)', [$aid, $type, time() - 60]);
$rowId = (int)$db->lastInsertId();

$queued = static fn (): int => (int)$db->fetchScalar("SELECT COUNT(*) FROM alliance_bonus_upgrade_queue WHERE id=$rowId");
$flag = static fn (): int => (int)$db->fetchScalar("SELECT $pending FROM users WHERE id=$uid");

$failing = new class extends AllianceBonusModel {
    public function levelUpBonus(int $aid, int $type): void
    {
        parent::levelUpBonus($aid, $type);
        throw new RuntimeException('injected failure');
    }
};
$thrown = false;
try {
    Automation::getInstance()->processAllianceBonusRow($rowId, $failing);
} catch (RuntimeException $exception) {
    $thrown = $exception->getMessage() === 'injected failure';
}
if (!$thrown) {
    $fail('The injected failure must reach the caller.');
}
if ($db->transactionLevel() !== 0) {
    $fail('A failed level-up must not leave a transaction open.');
}
if ($queued() !== 1 || $flag() !== 0) {
    $fail("A failed level-up must keep the queue row and roll the level-up back (row {$queued()}, flag {$flag()}).");
}

if (!Automation::getInstance()->processAllianceBonusRow($rowId)) {
    $fail('The retry must apply the level-up.');
}
if ($queued() !== 0 || $flag() !== 1) {
    $fail("The retry must apply the level-up and remove the row (row {$queued()}, flag {$flag()}).");
}
if (Automation::getInstance()->processAllianceBonusRow($rowId)) {
    $fail('A consumed upgrade row must not be processed again.');
}

echo "Alliance bonus queue atomic runtime passed.\n";
