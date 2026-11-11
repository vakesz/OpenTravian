<?php

declare(strict_types=1);

// POST /api/v1/daily-quest/award on a throwaway fixture player: a milestone that was not reached, a bad request and a
// repeated claim are refused and change nothing; each reached milestone is credited once and the answer says whether
// another can still be collected; two processes claiming the same milestone at the same moment credit it once.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Daily quest award runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/dorf1.php';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/dorf1.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Controller\Ajax\dailyQuest\award;
use Core\Database\DBI;
use Core\Helper\WebService;
use Model\DailyQuestModel;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();

if (($argv[1] ?? '') !== 'child') {
    try {
        $user = fixtureVillageCreate();
    } catch (RuntimeException $exception) {
        echo "Daily quest award runtime passed (no fixture village: {$exception->getMessage()}).\n";
        exit(0);
    }
    $pdo->run('UPDATE users SET last_login_time=?, last_owner_login_time=? WHERE id=?', [time(), time(), $user['id']]);
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' child sequence ' . (int)$user['id'], $status);
    if ($status !== 0) {
        $fail('The sequential award phase failed.');
    }

    // The race: two processes, one barrier, the same milestone.
    $model = new DailyQuestModel();
    $pdo->run('DELETE FROM daily_quest WHERE uid=?', [$user['id']]);
    $model->addDailyQuestRow((int)$user['id']);
    for ($i = 1; $i <= 11; ++$i) {
        $pdo->run("UPDATE daily_quest SET qst$i=? WHERE uid=?", [$model->getStepCount($i), $user['id']]);
    }
    $before = (int)$pdo->fetchScalar('SELECT cp FROM users WHERE id=?', [$user['id']]);
    $barrier = sys_get_temp_dir() . '/daily-award-barrier-' . getmypid();
    @unlink($barrier);
    $racers = [];
    foreach ([1, 2] as $n) {
        $racers[$n] = proc_open(
            [PHP_BINARY, __FILE__, 'child', 'racer', (string)$user['id'], $barrier, (string)$n],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes[$n]
        );
    }
    // A racer is ready once it has booted (its session and models exist); only then is the barrier released.
    for ($waited = 0; count(glob($barrier . '.ready.*') ?: []) < 2; ++$waited) {
        if ($waited > 500) {
            $fail('The racers did not start.');
        }
        usleep(20000);
    }
    touch($barrier);
    $results = [];
    $errors = [];
    foreach ($racers as $n => $process) {
        $results[$n] = trim((string)stream_get_contents($pipes[$n][1]));
        $errors[$n] = trim((string)stream_get_contents($pipes[$n][2]));
        proc_close($process);
    }
    array_map('unlink', glob($barrier . '.ready.*') ?: []);
    @unlink($barrier);
    sort($results);
    if ($results !== ['denied', 'granted']) {
        $fail('Two simultaneous claims of one milestone must grant it exactly once, got ' . json_encode($results) . ' ' . json_encode($errors));
    }
    if ((int)$pdo->fetchScalar('SELECT reward1Done FROM daily_quest WHERE uid=?', [$user['id']]) !== 1) {
        $fail('The raced milestone must end up collected.');
    }
    echo "Daily quest award runtime passed.\n";
    exit(0);
}

// ---- child: one process per phase, because Session is a singleton ----
$phase = (string)$argv[2];
$uid = (int)$argv[3];
$_SESSION[WebService::fixSessionPrefix('uid')] = $uid;
$_SESSION[WebService::fixSessionPrefix('pw')] = (string)$pdo->fetchScalar('SELECT password FROM users WHERE id=?', [$uid]);
\Core\Session::getInstance();
$model = new DailyQuestModel();
$call = static function (array $body): array {
    $response = ['reload' => false, 'error' => false, 'success' => true, 'message' => '', 'reloadOnClose' => false];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = $body;
    (new award($response))->dispatch();

    return $response;
};
$claim = static fn (int $tier, string $action = 'dailyQuest'): array => $call(['action' => $action, 'questId' => sprintf('DailyQuestsReward_%02d', $tier)]);

if ($phase === 'racer') {
    $barrier = (string)$argv[4];
    touch($barrier . '.ready.' . (int)$argv[5]);
    while (!is_file($barrier)) {
        usleep(1000);
    }
    echo $claim(1)['error'] ? 'denied' : 'granted';
    exit(0);
}

// What a grant changes, summed up so a double credit shows (hero experience, culture points, hero resources,
// bag items, adventures, Plus and the four production boosts).
$snapshot = static fn (): string => json_encode([
    $pdo->fetchSingleRow('SELECT cp, plus, b1, b2, b3, b4 FROM users WHERE id=?', [$uid]),
    $pdo->fetchSingleRow('SELECT exp FROM hero WHERE uid=?', [$uid]),
    $pdo->fetchSingleRow('SELECT wood, clay, iron, crop FROM hero_resources WHERE uid=?', [$uid]),
    (int)$pdo->fetchScalar('SELECT COALESCE(SUM(num), 0) FROM items WHERE uid=?', [$uid]),
    (int)$pdo->fetchScalar('SELECT COUNT(*) FROM adventure WHERE uid=?', [$uid]),
]);
$setPoints = static function (int $steps) use ($pdo, $model, $uid): void {
    $pdo->run('DELETE FROM daily_quest WHERE uid=?', [$uid]);
    $model->addDailyQuestRow($uid);
    // Quest 11 (alliance bonus) is worth 6 points a step, quest 1 five, quest 2 five and so on; fill them in
    // order until the requested number of points is reached.
    $points = 0;
    for ($i = 1; $i <= 11 && $points < $steps; ++$i) {
        for ($step = 1; $step <= $model->getStepCount($i) && $points < $steps; ++$step) {
            $pdo->run("UPDATE daily_quest SET qst$i=? WHERE uid=?", [$step, $uid]);
            $points = $model->calcTotalPoints((array)$model->getQuest($uid));
        }
    }
};

// 30 points: only the first milestone is within reach.
$setPoints(30);
$before = $snapshot();
foreach ([
    'a milestone not reached' => $claim(2),
    'a milestone that does not exist' => $claim(5),
    'a wrong action' => $claim(1, 'reward'),
    'an unknown id' => $call(['action' => 'dailyQuest', 'questId' => 'AchievementQuestReward_01']),
    'no id' => $call(['action' => 'dailyQuest']),
] as $what => $response) {
    if (empty($response['error'])) {
        $fail("Claiming $what must be refused.");
    }
}
if ($snapshot() !== $before) {
    $fail('A refused claim must change nothing.');
}
$first = $claim(1);
if (!empty($first['error']) || ($first['claimable'] ?? null) !== false || !empty($first['reload'])) {
    $fail('The first milestone must be granted without a reload and with nothing left to collect: ' . json_encode($first));
}
$after = $snapshot();
if ($after === $before) {
    $fail('A granted milestone must credit its reward.');
}
if (empty($claim(1)['error']) || $snapshot() !== $after) {
    $fail('A second claim of the same milestone must be refused and credit nothing.');
}

// All four milestones, one at a time; the answer follows what is left.
$setPoints(100);
$remaining = [1, 2, 3, 4];
while ($remaining) {
    $tier = array_shift($remaining);
    $before = $snapshot();
    $response = $claim($tier);
    if (!empty($response['error'])) {
        $fail("Milestone $tier must be collectable: " . json_encode($response));
    }
    if (($response['claimable'] ?? null) !== ($remaining !== [])) {
        $fail("After milestone $tier the answer must say whether another is left: " . json_encode($response));
    }
    if ($snapshot() === $before) {
        $fail("Milestone $tier must credit its reward.");
    }
    if ((int)$pdo->fetchScalar("SELECT reward{$tier}Done FROM daily_quest WHERE uid=?", [$uid]) !== 1) {
        $fail("Milestone $tier must be marked collected.");
    }
}
foreach ([1, 2, 3, 4] as $tier) {
    $before = $snapshot();
    if (empty($claim($tier)['error']) || $snapshot() !== $before) {
        $fail("Milestone $tier must not be collectable twice.");
    }
}
// Every reward type of every milestone does what the dialog announces for it.
$heroResources = static fn (): array => array_map('intval', (array)$pdo->fetchSingleRow('SELECT wood, clay, iron, crop FROM hero_resources WHERE uid=?', [$uid]));
foreach (['1' => 4, '2' => 5, '3' => 5, '4' => 4] as $tier => $types) {
    $tier = (int)$tier;
    for ($type = 1; $type <= $types; ++$type) {
        $setPoints(100);
        $pdo->run("UPDATE daily_quest SET reward{$tier}Type=? WHERE uid=?", [$type, $uid]);
        $view = DailyQuestModel::rewardView($tier, $type);
        $cp = (int)$pdo->fetchScalar('SELECT cp FROM users WHERE id=?', [$uid]);
        $exp = (int)$pdo->fetchScalar('SELECT exp FROM hero WHERE uid=?', [$uid]);
        $resources = $heroResources();
        $items = (int)$pdo->fetchScalar('SELECT COALESCE(SUM(num), 0) FROM items WHERE uid=?', [$uid]);
        $adventures = (int)$pdo->fetchScalar('SELECT COUNT(*) FROM adventure WHERE uid=?', [$uid]);
        $boosts = (array)$pdo->fetchSingleRow('SELECT plus, b1, b2, b3, b4 FROM users WHERE id=?', [$uid]);
        $response = $claim($tier);
        if (!empty($response['error'])) {
            $fail("Milestone $tier reward type $type ({$view['id']}) must be collectable: " . json_encode($response));
        }
        $gained = array_map(static fn (int $now, int $was): int => $now - $was, $heroResources(), $resources);
        $amount = (int)$view['value'];
        $id = $view['id'];
        $ok = match (true) {
            $id === 'allResources' => $gained === array_fill(0, 4, $amount),
            $id === 'randomResource' => count(array_filter($gained)) === 1 && array_sum($gained) === $amount,
            $id === 'xp' => (int)$pdo->fetchScalar('SELECT exp FROM hero WHERE uid=?', [$uid]) - $exp === $amount,
            $id === 'amount' => (int)$pdo->fetchScalar('SELECT cp FROM users WHERE id=?', [$uid]) - $cp === $amount,
            $id === 'duration' => (int)$pdo->fetchScalar('SELECT plus FROM users WHERE id=?', [$uid]) > max((int)$boosts['plus'], time()),
            str_starts_with($id, 'resource_') => (int)$pdo->fetchScalar('SELECT b' . ($type - 1) . ' FROM users WHERE id=?', [$uid]) > max((int)$boosts['b' . ($type - 1)], time()),
            str_starts_with($id, 'heroItem_') => (int)$pdo->fetchScalar('SELECT COALESCE(SUM(num), 0) FROM items WHERE uid=?', [$uid]) - $items === $amount,
            $id === 'getExtraAdventures' => (int)$pdo->fetchScalar('SELECT COUNT(*) FROM adventure WHERE uid=?', [$uid]) - $adventures === $amount,
            default => false,
        };
        if (!$ok) {
            $fail("Milestone $tier reward type $type must credit what the dialog announces ($id x {$view['value']}).");
        }
    }
}
exit(0);
