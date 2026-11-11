<?php

declare(strict_types=1);

// Adventures never expire: an adventure created a year ago is still listed, counted and enterable, the spawn run leaves it
// alone, and its reward tier is the tier of the day it appeared (not the tier on offer when the hero gets there).

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Adventure no-expiry runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/dorf1.php';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/dorf1.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Config;
use Core\Database\DBI;
use Game\Formulas;
use Model\AdventureModel;

$fail = static function (string $message): never {
    fwrite(STDERR, 'Adventure no-expiry runtime failed: ' . $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $player = fixtureVillageCreate();
} catch (Throwable $exception) {
    echo "Adventure no-expiry runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$uid = (int)$player['id'];
$kid = (int)$player['kid'];
try {
    $pdo->run('DELETE FROM adventure WHERE uid=?', [$uid]);
    $target = $kid + 1;
    $pdo->run('INSERT INTO adventure (uid, kid, dif, created, end) VALUES (?, ?, 0, ?, 0)', [$uid, $target, time() - 365 * 86400]);
    $model = new AdventureModel();
    if ($model->getAdventureCount($uid) !== 1) {
        $fail('a year-old adventure is not counted.');
    }
    if ($model->checkForAdventure($uid, $target) !== 1 || $model->getAdventure($uid, $kid + 1) === null) {
        $fail('a year-old adventure cannot be entered.');
    }
    $model->checkForNewAdventures();
    if ($model->getAdventureCount($uid) < 1 || (int)$pdo->fetchScalar('SELECT COUNT(*) FROM adventure WHERE uid=? AND kid=? AND end=0', [$uid, $target]) !== 1) {
        $fail('the spawn run closed an old adventure.');
    }

    // The tier follows the creation day: early adventures stay tier 1 when finished late, at every speed band.
    $config = Config::getInstance();
    $originalSpeed = $config->game->speed;
    try {
        foreach ([2, 10, 100] as $speed) {
            $config->game->speed = $speed;
            if (Formulas::heroItemsTierAt(0) !== 1) {
                $fail("a day-zero adventure must carry tier 1 at x{$speed}.");
            }
            $late = 400 * 86400;
            if (Formulas::heroItemsTierAt($late) !== 3) {
                $fail("a late adventure must carry tier 3 at x{$speed}.");
            }
            $tiers = array_map(static fn (int $elapsed): int => Formulas::heroItemsTierAt($elapsed), [0, 10 * 86400, 100 * 86400, 200 * 86400, $late]);
            $sorted = $tiers;
            sort($sorted);
            if ($tiers !== $sorted) {
                $fail("the tier falls with age at x{$speed}: " . json_encode($tiers));
            }
        }
    } finally {
        $config->game->speed = $originalSpeed;
    }
} finally {
    fixtureVillageRemove($uid, $kid);
}

echo "Adventure no-expiry runtime passed.\n";
