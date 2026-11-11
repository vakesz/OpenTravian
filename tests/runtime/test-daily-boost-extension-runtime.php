<?php

declare(strict_types=1);

// A daily-quest reward that grants Plus or a resource bonus while it is active EXTENDS it (its end moves by the reward),
// keeps the auto-extension in step (autoExtend.commence = the new end, so the renewal still matches) and leaves the
// "will be finished" notice only when auto-extend is OFF: the same rule as a purchase. Before, the reward always added
// the notice (even with auto-extend on) and left commence on the old end, so the renewal never ran.

require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once '/travian/tests/support/village-fixture.php';

use Core\Database\DB;
use Model\AutoExtendModel;
use Model\DailyQuestModel;

set_exception_handler(static function (Throwable $exception): never {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
});

function boostFail(string $message): never
{
    throw new RuntimeException('FAIL: ' . $message);
}

/** Exposes the reward grants the daily quest runs. */
final class DailyRewardProbe extends DailyQuestModel
{
    public function grantPlus(int $uid, int $seconds): bool
    {
        return $this->addPlus($uid, $seconds);
    }

    public function grantBoost(int $uid, int $resource, int $seconds): bool
    {
        return $this->addProductionBoost($uid, $resource, $seconds);
    }
}

$db = DB::getInstance();
$fixture = fixtureVillageCreate();
$uid = $fixture['id'];
$probe = new DailyRewardProbe();
$model = new AutoExtendModel();
$day = 86400;

$state = static function (int $type, string $column) use ($db, $uid): array {
    return [
        'end' => (int)$db->fetchScalar("SELECT $column FROM users WHERE id=$uid"),
        'commence' => (int)$db->fetchScalar("SELECT commence FROM autoExtend WHERE uid=$uid AND type=$type"),
        'enabled' => (int)$db->fetchScalar("SELECT enabled FROM autoExtend WHERE uid=$uid AND type=$type"),
        'notices' => (int)$db->fetchScalar("SELECT COUNT(*) FROM infobox WHERE uid=$uid AND type=$type"),
    ];
};

foreach ([
    ['Plus', 1, 'plus', static fn (int $seconds): bool => $probe->grantPlus($uid, $seconds)],
    ['lumber bonus', 2, 'b1', static fn (int $seconds): bool => $probe->grantBoost($uid, 1, $seconds)],
    ['crop bonus', 5, 'b4', static fn (int $seconds): bool => $probe->grantBoost($uid, 4, $seconds)],
] as [$label, $type, $column, $grant]) {
    // Active feature, auto-extend ON, an older notice left over from before.
    $oldEnd = time() + 3 * 3600;
    $db->query("DELETE FROM autoExtend WHERE uid=$uid AND type=$type");
    $db->query("DELETE FROM infobox WHERE uid=$uid AND type=$type");
    $db->query("UPDATE users SET $column=$oldEnd WHERE id=$uid");
    $db->query("INSERT INTO autoExtend (uid,type,commence,enabled,finished,lastChecked) VALUES ($uid,$type,$oldEnd,1,0,0)");
    $db->query("INSERT INTO infobox (uid,forAll,type,params,showFrom,showTo) VALUES ($uid,0,$type,'',$oldEnd-86400,$oldEnd+86400)");

    if (!$grant($day)) {
        boostFail("$label: the reward grant failed.");
    }
    $after = $state($type, $column);
    if ($after['end'] !== $oldEnd + $day) {
        boostFail("$label: the reward must add to the active end ({$after['end']} instead of " . ($oldEnd + $day) . ').');
    }
    if ($after['commence'] !== $after['end'] || $after['enabled'] !== 1) {
        boostFail("$label: auto-extend must follow the new end (commence {$after['commence']}, end {$after['end']}).");
    }
    if ($after['notices'] !== 0) {
        boostFail("$label: no expiry notice while auto-extend will renew it.");
    }

    // A second reward adds again.
    if (!$grant($day) || $state($type, $column)['end'] !== $oldEnd + 2 * $day) {
        boostFail("$label: a second reward must add again.");
    }

    // Auto-extend OFF: the notice shows for the new end, and the stored commence still follows it.
    $db->query("UPDATE autoExtend SET enabled=0 WHERE uid=$uid AND type=$type");
    $grant($day);
    $off = $state($type, $column);
    $notice = $db->query("SELECT showFrom, showTo FROM infobox WHERE uid=$uid AND type=$type")->fetch_assoc();
    if ($off['end'] !== $oldEnd + 3 * $day || $off['commence'] !== $off['end'] || $off['enabled'] !== 0 || $off['notices'] !== 1
        || (int)$notice['showFrom'] !== $off['end'] - $day || (int)$notice['showTo'] !== $off['end'] + $day) {
        boostFail("$label: with auto-extend off exactly one notice around the new end is expected.");
    }

    // An expired feature: the reward starts it from now.
    $db->query("UPDATE users SET $column=" . (time() - 100) . " WHERE id=$uid");
    $grant($day);
    $fresh = $state($type, $column)['end'];
    if ($fresh < time() + $day - 5 || $fresh > time() + $day + 5) {
        boostFail("$label: a reward for an expired feature must start from now.");
    }
}

// The renewal still matches after a reward: auto-extend ON, reward, then the stored commence equals the expiry.
$oldEnd = time() + 600;
$db->query("DELETE FROM autoExtend WHERE uid=$uid AND type=2");
$db->query("UPDATE users SET b1=$oldEnd WHERE id=$uid");
$db->query("INSERT INTO autoExtend (uid,type,commence,enabled,finished,lastChecked) VALUES ($uid,2,$oldEnd,1,0,0)");
$probe->grantBoost($uid, 1, $day);
if ((int)$db->fetchScalar("SELECT b1 FROM users WHERE id=$uid") !== (int)$db->fetchScalar("SELECT commence FROM autoExtend WHERE uid=$uid AND type=2")) {
    boostFail('the renewal compares the expiry with commence; they must be equal after a reward.');
}

echo "Daily boost extension runtime test passed.\n";
