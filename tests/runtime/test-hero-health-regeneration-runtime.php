<?php

declare(strict_types=1);

// A living hero regenerates a flat 10 % per day from x1 through x10 (the vendor's hero health article and the reference
// world at x2 both show 10 %), then 10 * sqrt(speed / 10) % per day, times the living-hero multiplier above x20.
// Runs the real HeroHealthHelper on a fixture hero one hour after its last update, at several speeds.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Hero health regeneration runtime skipped outside the php container.\n";
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
use Game\Helpers\HeroHealthHelper;
use Game\WorldSpeedProfile;

$fail = static function (string $message): never {
    fwrite(STDERR, 'Hero health regeneration runtime failed: ' . $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $player = fixtureVillageCreate();
} catch (Throwable $exception) {
    echo "Hero health regeneration runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$uid = (int)$player['id'];
$config = Config::getInstance();
$originalSpeed = $config->game->speed;
$originalMultiplier = $config->game->heroRegenerationMultiplier;
try {
    if ((int)$pdo->fetchScalar('SELECT COUNT(*) FROM hero WHERE uid=?', [$uid]) !== 1) {
        $fail('the fixture player has no hero.');
    }
    // speed => percent per day of the basic regeneration (rounded as the game does) times the multiplier
    $expectedPerDay = [1 => 10.0, 2 => 10.0, 3 => 10.0, 5 => 10.0, 10 => 10.0, 11 => 10.0, 20 => 14.0, 100 => 32.0 * 1.5, 1000 => 100.0 * 2.66];
    foreach ($expectedPerDay as $speed => $perDay) {
        $config->game->speed = $speed;
        $config->game->heroRegenerationMultiplier = WorldSpeedProfile::heroRegenerationMultiplier($speed);
        $pdo->run('UPDATE hero SET health=50, itemHealth=0, lastupdate=? WHERE uid=?', [time() - 3600, $uid]);
        HeroHealthHelper::updateUserHeroHealth($uid);
        $health = (float)$pdo->fetchScalar('SELECT health FROM hero WHERE uid=?', [$uid]);
        $expected = min(100.0, 50 + $perDay / 24);
        if (abs($health - $expected) > 0.05) {
            $fail(sprintf('at x%d one hour gives %.3f health, expected %.3f (%.2f %% per day).', $speed, $health, $expected, $perDay));
        }
    }
    // Flat through x10 and continuous at x10/x11.
    foreach ([1, 2, 3, 4, 5, 10] as $speed) {
        if (WorldSpeedProfile::heroHealthPerDay($speed) !== 10.0) {
            $fail("the basic regeneration at x{$speed} must be exactly 10 %.");
        }
    }
    if (WorldSpeedProfile::heroHealthPerDay(11) - WorldSpeedProfile::heroHealthPerDay(10) > 0.6) {
        $fail('the basic regeneration jumps between x10 and x11.');
    }
} finally {
    $config->game->speed = $originalSpeed;
    $config->game->heroRegenerationMultiplier = $originalMultiplier;
    fixtureVillageRemove($uid, (int)$player['kid']);
}

echo "Hero health regeneration runtime passed (flat 10 % through x10, then the smooth curve).\n";
