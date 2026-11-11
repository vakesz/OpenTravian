<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/game/deployment/include/env.php';
require dirname(__DIR__, 2) . '/game/include/bootstrap.php';

use Core\Config;
use Core\Database\DB;
use Model\AutoExtendModel;

set_exception_handler(static function (Throwable $exception): never {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
});

function autoExtendDurationFail(string $message): never
{
    throw new RuntimeException('FAIL: ' . $message);
}

$db = DB::getInstance();
$config = Config::getInstance();
$uid = (int)$db->fetchScalar('SELECT MAX(id) FROM users WHERE id > 2');
if ($uid === 0) {
    autoExtendDurationFail('the runtime check needs an installed player account.');
}

$plusDuration = 72 * 3600;
$boostDuration = 3600;
$plusCost = (int)$config->gold->plusGold;
$boostCost = (int)$config->gold->productionBoostGold;
if ($plusCost <= 0 || $boostCost <= 0) {
    autoExtendDurationFail('Plus and production-boost gold costs must be positive.');
}

$config->gold->plusAccountDurationSeconds = $plusDuration;
$config->gold->productionBoostDurationSeconds = $boostDuration;

$db->begin_transaction();
try {
    $db->query("DELETE FROM autoExtend WHERE uid=$uid");
    $db->query("UPDATE users SET gift_gold=100, bought_gold=0, plus=0, b1=0 WHERE id=$uid");
    $model = new AutoExtendModel();
    $now = time();

    // This boost is inside the Plus renewal window (72h / 7) but outside its
    // own one-hour renewal window. It must not consume gold yet.
    $farBoostEnd = $now + 10 * 3600;
    $db->query("INSERT INTO autoExtend (uid,type,commence,enabled,finished,lastChecked) VALUES ($uid,2,$farBoostEnd,1,0,0)");
    $model->processAutoExtend();
    if ((int)$db->fetchScalar("SELECT gift_gold+bought_gold FROM users WHERE id=$uid") !== 100
        || (int)$db->fetchScalar("SELECT b1 FROM users WHERE id=$uid") !== 0) {
        autoExtendDurationFail('a production boost was renewed using the Plus renewal window.');
    }

    // A boost inside its own one-hour / seven renewal window must renew and
    // retain the feature's original expiry as the start of the next period.
    $nearBoostEnd = time() + 300;
    $db->query("UPDATE users SET b1=$nearBoostEnd WHERE id=$uid");
    $db->query("UPDATE autoExtend SET commence=$nearBoostEnd, lastChecked=0 WHERE uid=$uid AND type=2");
    $model->processAutoExtend();
    if ((int)$db->fetchScalar("SELECT gift_gold+bought_gold FROM users WHERE id=$uid") !== 100 - $boostCost
        || (int)$db->fetchScalar("SELECT b1 FROM users WHERE id=$uid") !== $nearBoostEnd + $boostDuration) {
        autoExtendDurationFail('a production boost did not renew from its own duration window.');
    }

    // Plus retains its independently configured 72-hour / seven renewal window.
    $plusEnd = time() + 10 * 3600;
    $db->query("UPDATE users SET plus=$plusEnd WHERE id=$uid");
    $db->query("INSERT INTO autoExtend (uid,type,commence,enabled,finished,lastChecked) VALUES ($uid,1,$plusEnd,1,0,0)");
    $model->processAutoExtend();
    if ((int)$db->fetchScalar("SELECT gift_gold+bought_gold FROM users WHERE id=$uid") !== 100 - $boostCost - $plusCost
        || (int)$db->fetchScalar("SELECT plus FROM users WHERE id=$uid") !== $plusEnd + $plusDuration) {
        autoExtendDurationFail('Plus did not retain its own duration window.');
    }

    echo "Auto-extend respects independent Plus and production-bonus durations (rolled back).\n";
} finally {
    $db->rollback();
}
