<?php

declare(strict_types=1);

// A scouting attack stores the combat strength of both sides in its report (the report page shows it in the
// Statistics block). Two throwaway fixture players fight; every row they leave behind is removed with them.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Spy report strength runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Automation;
use Core\Database\DB;
use Core\Database\DBI;
use Game\Formulas;
use Game\NoticeHelper;
use Model\MovementsModel;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $attacker = fixtureVillageCreate();
    $pdo->run('UPDATE users SET name=? WHERE id=?', ['zzfxa' . getmypid(), $attacker['id']]);
    $defender = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Spy report strength runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$race = (int)$pdo->fetchScalar('SELECT race FROM users WHERE id=?', [$attacker['id']]);
$scout = Formulas::getSpyId($race);
if (!$scout) {
    echo "Spy report strength runtime passed (tribe $race has no scouts).\n";
    exit(0);
}

$db = DB::getInstance();
// The fixture village is not on the map yet: occupy its tile, and free it again however the test ends.
$pdo->run('UPDATE wdata SET occupied=1 WHERE id=?', [$defender['kid']]);
register_shutdown_function(static fn () => DBI::getInstance()->run('UPDATE wdata SET occupied=0 WHERE id=?', [$defender['kid']]));
{
    $units = array_fill(1, 11, 0);
    $units[$scout] = 5;
    $db->query('UPDATE units SET u1=0,u2=0,u3=0,u4=0,u5=0,u6=0,u7=0,u8=0,u9=0,u10=0,u11=0 WHERE kid=' . $defender['kid']);
    // Due a day from now: the live engine only takes movements that are due, so it never races this test for the
    // movement, and processMovementTask() consumes it by id.
    $now = (int)miliseconds() + 86400000;
    $id = (new MovementsModel())->addMovement(
        $attacker['kid'],
        $defender['kid'],
        $race,
        $units,
        0,
        0,
        1,
        0,
        0,
        MovementsModel::ATTACKTYPE_SPY,
        $now - 20000,
        $now - 10000
    );
    (new Automation())->processMovementTask($id);
    if ((int)$db->fetchScalar("SELECT COUNT(*) FROM movement WHERE id=$id AND mode=0")) {
        $fail('The scouting movement was not processed.');
    }
    $result = $db->query('SELECT type, data FROM ndata WHERE uid=' . $attacker['id'] . ' AND to_kid=' . $defender['kid'] . ' ORDER BY id DESC LIMIT 1');
    $notice = $result ? $result->fetch_assoc() : null;
    if (!is_array($notice)) {
        $fail('The scouting attack produced no report for the attacker.');
    }
    $report = NoticeHelper::parseReport((int)$notice['type'], $notice['data']);
    $strength = $report['strength'] ?? null;
    if (!is_array($strength) || !array_key_exists('attacker', $strength) || !array_key_exists('defender', $strength)) {
        $fail('A scouting report must store the attacker and defender combat strength; report type ' . $notice['type'] . ' holds ' . implode(',', array_keys($report)) . '.');
    }
    if ((int)$strength['attacker'] <= 0) {
        $fail('Five scouts must have a positive attacker strength, got ' . json_encode($strength) . '.');
    }
    if ((int)$strength['defender'] !== 0) {
        $fail('A village without scouts has no defending scout strength, got ' . json_encode($strength) . '.');
    }
    echo "Spy report strength runtime passed.\n";
}
