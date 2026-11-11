<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/game/deployment/include/env.php';
require dirname(__DIR__, 2) . '/game/include/bootstrap.php';

use Core\Automation;
use Core\Database\DB;

set_exception_handler(static function (Throwable $exception): never {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
});

$db = DB::getInstance();
$uid = (int)$db->fetchScalar('SELECT MAX(id) FROM users');
$otherUid = (int)$db->fetchScalar("SELECT MAX(id) FROM users WHERE id<$uid");
if (!$uid || !$otherUid) {
    throw new RuntimeException('Report retention runtime check requires two installed accounts.');
}

$db->begin_transaction();
try {
    $db->query("UPDATE ndata SET archive=1 WHERE uid IN($uid,$otherUid)");
    $add = static function (int $owner, int $time, int $archive = 0, int $protected = 0) use ($db): int {
        $db->query("INSERT INTO ndata
            (aid,uid,isEnforcement,kid,to_kid,type,bounty,data,time,private_key,viewed,archive,non_deletable)
            VALUES(0,$owner,0,0,0,1,'','',$time,'retention',0,$archive,$protected)");
        return (int)$db->lastInsertId();
    };
    $expired = time() - 7 * 86400 - 60;
    $fresh = time() - 7 * 86400 + 60;
    $expiredReports = [$add($uid, $expired), $add($otherUid, $expired - 1)];
    for ($i = 0; $i < 30; ++$i) {
        $expiredReports[] = $add($uid, $expired - $i - 2);
    }
    $archive = $add($uid, $expired, 1);
    $protected = $add($uid, $expired - 1, 0, 1);
    $freshReport = $add($uid, $fresh);
    $automation = new Automation();
    $automation->cleanupReports();
    foreach ($expiredReports as $id) {
        if ((int)$db->fetchScalar("SELECT COUNT(*) FROM ndata WHERE id=$id")) {
            throw new RuntimeException('FAIL: an unarchived report older than seven days was retained.');
        }
    }
    foreach ([$archive, $protected, $freshReport] as $id) {
        if (!(int)$db->fetchScalar("SELECT COUNT(*) FROM ndata WHERE id=$id")) {
            throw new RuntimeException("FAIL: a fresh, archived, or protected report $id was deleted.");
        }
    }
    echo "Seven-day report retention plus archive/protection exemptions passed (rolled back).\n";
} finally {
    $db->rollback();
}
