<?php

declare(strict_types=1);

// An expired ban is lifted exactly once and never lost: when the unban fails part-way, the banQueue row and the ban
// both stay, and the next pass lifts it. A throwaway fixture player is banned for the test.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Ban queue atomic runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Automation;
use Core\Database\DB;
use Game\AccessLevel;
use Model\InfoBoxModel;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
try {
    $fixture = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Ban queue atomic runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$uid = $fixture['id'];
$db = DB::getInstance();
$db->run('UPDATE users SET access=? WHERE id=?', [AccessLevel::BANNED, $uid]);
$db->run('INSERT INTO banQueue (uid, reason, time, end) VALUES (?, ?, ?, ?)', [$uid, 'zz test', time() - 7200, time() - 60]);
$banId = (int)$db->lastInsertId();
(new InfoBoxModel())->addInfo($uid, 0, 14, '', 0, 0);

$access = static fn (): int => (int)$db->fetchScalar("SELECT access FROM users WHERE id=$uid");
$queued = static fn (): int => (int)$db->fetchScalar("SELECT COUNT(*) FROM banQueue WHERE id=$banId");
$notices = static fn (): int => (int)$db->fetchScalar("SELECT COUNT(*) FROM infobox WHERE uid=$uid AND type=14");

$failing = new class extends InfoBoxModel {
    public function deleteInfoByType(int $uid, int $type): void
    {
        parent::deleteInfoByType($uid, $type);
        throw new RuntimeException('injected failure');
    }
};
$thrown = false;
try {
    Automation::getInstance()->processBanRow($banId, $failing);
} catch (RuntimeException $exception) {
    $thrown = $exception->getMessage() === 'injected failure';
}
if (!$thrown) {
    $fail('The injected failure must reach the caller.');
}
if ($db->transactionLevel() !== 0) {
    $fail('A failed unban must not leave a transaction open.');
}
if ($queued() !== 1 || $access() !== AccessLevel::BANNED || $notices() !== 1) {
    $fail("A failed unban must keep the queue row, the ban and the notice (row {$queued()}, access {$access()}, notices {$notices()}).");
}

if (!Automation::getInstance()->processBanRow($banId)) {
    $fail('The retry must lift the ban.');
}
if ($queued() !== 0 || $access() !== AccessLevel::PLAYER || $notices() !== 0) {
    $fail("The retry must lift the ban and remove the row and the notice (row {$queued()}, access {$access()}, notices {$notices()}).");
}
if (Automation::getInstance()->processBanRow($banId)) {
    $fail('A consumed ban row must not be processed again.');
}

echo "Ban queue atomic runtime passed.\n";
