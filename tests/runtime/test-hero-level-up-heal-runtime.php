<?php

declare(strict_types=1);

// Level-up restores a living hero to 100% health; a dead hero that gains the level stays dead.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Hero level-up heal runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/hero/inventory';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/hero/inventory';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Database\DBI;
use Game\Formulas;
use Game\Hero\HeroExperience;

$fail = static function (string $message): never {
    fwrite(STDERR, 'Hero level-up heal runtime failed: ' . $message . "\n");
    exit(1);
};

try {
    $player = fixtureVillageCreate();
} catch (Throwable $exception) {
    echo "Hero level-up heal runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}

$pdo = DBI::getInstance();
$uid = (int)$player['id'];
$kid = (int)$player['kid'];

// Last experience still at level 1 (heroExperience(n) is the nominal threshold; ceil in heroLevel can cross earlier).
$justBelow = 0;
while ((int)Formulas::heroLevel($justBelow + 1) < 2) {
    $justBelow++;
}
if ((int)Formulas::heroLevel($justBelow) !== 1 || (int)Formulas::heroLevel($justBelow + 1) !== 2) {
    fixtureVillageRemove($uid, $kid);
    $fail("could not find the level-1→2 boundary (justBelow={$justBelow}).");
}

$pdo->run('UPDATE hero SET exp=?, health=40, lastupdate=? WHERE uid=?', [$justBelow, time(), $uid]);
if (!HeroExperience::add($uid, 1)) {
    fixtureVillageRemove($uid, $kid);
    $fail('HeroExperience::add refused a living level-up.');
}
$row = $pdo->fetchSingleRow('SELECT exp, health FROM hero WHERE uid=?', [$uid]);
if ((int)Formulas::heroLevel((int)$row['exp']) !== 2 || (int)(float)$row['health'] !== 100) {
    fixtureVillageRemove($uid, $kid);
    $fail('living hero did not heal to 100 on level-up: ' . json_encode($row));
}

$pdo->run('UPDATE hero SET exp=?, health=0, lastupdate=? WHERE uid=?', [$justBelow, time(), $uid]);
if (!HeroExperience::add($uid, 1)) {
    fixtureVillageRemove($uid, $kid);
    $fail('HeroExperience::add refused a dead level-up.');
}
$row = $pdo->fetchSingleRow('SELECT exp, health FROM hero WHERE uid=?', [$uid]);
if ((int)Formulas::heroLevel((int)$row['exp']) !== 2 || (float)$row['health'] !== 0.0) {
    fixtureVillageRemove($uid, $kid);
    $fail('dead hero was revived by level-up: ' . json_encode($row));
}

fixtureVillageRemove($uid, $kid);
echo "Hero level-up heal runtime passed.\n";
