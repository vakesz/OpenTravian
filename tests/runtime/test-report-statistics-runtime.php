<?php

declare(strict_types=1);

// A fresh raid from a throwaway fixture village on an oasis must store the combat strength and
// the report's Statistics block must show it next to the supply and resource rows.

$_SERVER['REQUEST_URI'] = '/ajax.php';
require dirname(__DIR__, 2) . '/game/deployment/include/env.php';
require dirname(__DIR__, 2) . '/game/include/bootstrap.php';
require_once __DIR__ . '/../support/village-fixture.php';

use Controller\ReportsCtrl;
use Core\Automation;
use Core\Database\DB;
use Game\NoticeHelper;
use Model\MovementsModel;

set_exception_handler(static function (Throwable $exception): never {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
});

$fixture = fixtureVillageCreate();
$db = DB::getInstance();
$kid = $fixture['kid'];
$uid = $fixture['id'];
$race = (int)$db->fetchScalar("SELECT race FROM users WHERE id=$uid");
$target = (int)$db->fetchScalar('SELECT kid FROM odata WHERE owner=0 ORDER BY kid LIMIT 1');
if ($target === 0) {
    throw new RuntimeException('This runtime check needs a free oasis.');
}
$db->query("UPDATE units SET u1=0,u2=0,u3=0,u4=0,u5=0,u6=0,u7=0,u8=0,u9=0,u10=0,u11=0 WHERE kid=$target");
$units = array_fill(1, 11, 0);
$units[1] = 200;
$db->query("UPDATE units SET u1=200 WHERE kid=$kid");
$id = (int)(new MovementsModel())->addMovement($kid, $target, $race, $units, 0, 0, 0, 0, 0, MovementsModel::ATTACKTYPE_RAID, miliseconds() - 20000, miliseconds() - 10000);
(new Automation())->attackMovementComplete();
if ((int)$db->fetchScalar("SELECT COUNT(*) FROM movement WHERE id=$id")) {
    throw new RuntimeException('FAIL: the fixture raid was not processed.');
}
$report = $db->query("SELECT type, data FROM ndata WHERE uid=$uid AND to_kid=$target ORDER BY id DESC LIMIT 1")->fetch_assoc();
if (!$report) {
    throw new RuntimeException('FAIL: the fixture raid produced no report.');
}
$data = NoticeHelper::parseReport((int)$report['type'], $report['data']);
if (!is_array($data['strength'] ?? null) || (int)$data['strength']['attacker'] <= 0) {
    throw new RuntimeException('FAIL: the report did not store the combat strength.');
}

$render = new ReflectionMethod(ReportsCtrl::class, 'combatStatistics');
$controller = (new ReflectionClass(ReportsCtrl::class))->newInstanceWithoutConstructor();
$html = $render->invoke($controller, $data);
$strength = "\u{202D}" . number_format_x((int)$data['strength']['attacker']) . "\u{202C}";
foreach (['<table class="combatStatistic">', 'offence_medium', 'defence_medium', 'r5Big', 'resources_medium', $strength] as $needle) {
    if (!str_contains($html, $needle)) {
        throw new RuntimeException("FAIL: the Statistics block lacks $needle.");
    }
}
unset($data['strength']);
if (str_contains($render->invoke($controller, $data), 'offence_medium')) {
    throw new RuntimeException('FAIL: a report without stored strength must not show the combat strength row.');
}

echo "Report statistics runtime check passed.\n";
