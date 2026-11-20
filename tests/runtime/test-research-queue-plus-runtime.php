<?php

declare(strict_types=1);

// Smithy and Academy research queues: one research at a time, two while Travian Plus is active. The second one is
// queued behind the first (it finishes after it), and a third order is refused. Drives SmithyCtrl and AcademyCtrl
// the way a click on Improve / Research does, one process per click because Session and Village are singletons.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Research queue Plus runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = '/build.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Controller\Build\AcademyCtrl;
use Controller\Build\SmithyCtrl;
use Core\Config;
use Core\Database\DBI;
use Core\Helper\WebService;
use Core\Session;
use Core\Village;
use Game\Formulas;
use Game\Gid;
use Game\ResearchMode;
use Game\ResearchQueue;
use Game\WorldSpeedProfile;

$fail = static function (string $message): never {
    fwrite(STDERR, 'Research queue Plus runtime failed: ' . $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
// Field => [gid, level]: a level-20 Academy and Smithy, plus the troop buildings Academy prerequisites ask for.
const RESEARCH_FIELDS = [
    19 => [Gid::ACADEMY, 20],
    20 => [Gid::SMITHY, 20],
    21 => [Gid::MAIN_BUILDING, 20],
    22 => [Gid::BARRACKS, 20],
    23 => [Gid::STABLE, 20],
    24 => [Gid::WORKSHOP, 20],
];

if (($argv[1] ?? '') !== 'child') {
    try {
        $player = fixtureVillageCreate(1);
    } catch (Throwable $exception) {
        echo "Research queue Plus runtime passed (no fixture village: {$exception->getMessage()}).\n";
        exit(0);
    }
    $uid = (int)$player['id'];
    $kid = (int)$player['kid'];
    foreach (RESEARCH_FIELDS as $field => [$gid, $level]) {
        $pdo->run("UPDATE fdata SET f{$field}=?, f{$field}t=? WHERE kid=?", [$level, $gid, $kid]);
    }
    foreach (['smithy' => ResearchMode::SMITHY, 'academy' => ResearchMode::ACADEMY] as $building => $mode) {
        foreach ([false, true] as $plus) {
            $label = $building . ($plus ? ' with Plus' : ' without Plus');
            $pdo->run('DELETE FROM research WHERE kid=?', [$kid]);
            $pdo->run('UPDATE tdata SET u2=0, u3=0, u4=0, u5=0, u6=0, u7=0, u8=0, u9=0 WHERE kid=?', [$kid]);
            $pdo->run('UPDATE smithy SET u1=0, u2=0, u3=0, u4=0, u5=0, u6=0, u7=0, u8=0 WHERE kid=?', [$kid]);
            $pdo->run(
                'UPDATE users SET plus=?, last_login_time=?, last_owner_login_time=?, access=1, vacationActiveTil=0 WHERE id=?',
                [$plus ? time() + 86400 : 0, time(), time(), $uid]
            );
            for ($click = 1; $click <= 3; ++$click) {
                $output = [];
                exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' child ' . $building . ' ' . $uid . ' 2>&1', $output, $status);
                if ($status !== 0) {
                    $fail("$label, click $click exited $status:\n" . implode("\n", $output));
                }
            }
            $rows = $pdo->run('SELECT nr, end_time FROM research WHERE kid=? AND mode=? ORDER BY end_time ASC, id ASC', [$kid, $mode])
                ->fetchAll(PDO::FETCH_ASSOC);
            $expected = ResearchQueue::capacity($plus);
            if (count($rows) !== $expected) {
                $fail("$label: three clicks queued " . count($rows) . " researches, expected $expected: " . json_encode($rows));
            }
            if ($expected === 2 && (int)$rows[1]['end_time'] <= (int)$rows[0]['end_time']) {
                $fail("$label: the second research does not finish after the first: " . json_encode($rows));
            }
        }
    }
    fixtureVillageRemove($uid, $kid);
    echo "Research queue Plus runtime passed (Smithy and Academy, with and without Plus).\n";
    exit(0);
}

// ---- child: one click on Improve (Smithy) or Research (Academy) ----
set_exception_handler(static function (Throwable $exception): never {
    fwrite(STDERR, $exception::class . ': ' . $exception->getMessage() . "\n" . $exception->getTraceAsString() . "\n");
    exit(1);
});
$building = (string)$argv[2];
$uid = (int)$argv[3];
$kid = (int)$pdo->fetchScalar('SELECT kid FROM users WHERE id=?', [$uid]);
$config = Config::getInstance();
$config->dynamic->serverFinished = 0;
// x1, so a research outlasts the next click: on a fast local world the engine would finish it in between.
$config->game->speed = 1;
foreach (WorldSpeedProfile::profile(1) as $key => $value) {
    $config->game->$key = $value;
}
$config->game->useMilSeconds = false;
$config->game->useNanoseconds = false;
$pdo->run(
    'UPDATE vdata SET wood=1000000, clay=1000000, iron=1000000, crop=1000000, woodp=0, clayp=0, ironp=0, cropp=pop+10000,
     upkeep=0, maxstore=2000000, maxcrop=2000000, lastmupdate=? WHERE kid=?',
    [miliseconds(), $kid]
);
$_SESSION[WebService::fixSessionPrefix('uid')] = $uid;
$_SESSION[WebService::fixSessionPrefix('pw')] = $pdo->fetchScalar('SELECT password FROM users WHERE id=?', [$uid]);
$session = Session::getInstance();
$village = Village::getInstance();
$field = $building === 'smithy' ? 20 : 19;
if ($building === 'smithy') {
    // Unit 1 is always available; each click queues its next level.
    $unit = 1;
} else {
    $race = (int)$session->getRace();
    $queued = array_map('intval', $pdo->fetchPluck('SELECT nr FROM research WHERE kid=? AND mode=?', [$kid, ResearchMode::ACADEMY]));
    $unit = 0;
    for ($nr = 2; $nr <= 9 && $unit === 0; ++$nr) {
        if (in_array($nr, $queued, true)) {
            continue;
        }
        $ready = true;
        foreach (Formulas::uResearchPreRequests($race, $nr) as $gid => $level) {
            $ready = $ready && max($village->getTypeLevel($gid)) >= $level;
        }
        if ($ready) {
            $unit = nrToUnitId($nr, $race);
        }
    }
    if ($unit === 0) {
        throw new RuntimeException('No Academy research is available to the fixture village.');
    }
}
$_GET = ['id' => (string)$field, 'a' => (string)$unit, 'c' => $session->getChecker()];
$_SERVER['REQUEST_URI'] = '/build.php?' . http_build_query($_GET);
$_SERVER['QUERY_STRING'] = http_build_query($_GET);
// SmithyCtrl redirects (and exits) after the click; AcademyCtrl renders in place.
$building === 'smithy' ? new SmithyCtrl($field) : new AcademyCtrl($field);
