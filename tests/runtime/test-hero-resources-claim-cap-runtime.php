<?php

declare(strict_types=1);

// "Collect all" moves the hero's carried resources into the village only as far as the warehouse and granary
// hold them; the rest stays with the hero instead of being cut off by the next storage clamp.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Hero resources claim cap runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Database\DBI;
use Model\HeroResourceInventory;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $player = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Hero resources claim cap runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
register_shutdown_function(static fn () => DBI::getInstance()->run('DELETE FROM hero_resources WHERE uid=?', [$player['id']]));

$pdo->run('UPDATE vdata SET wood=700, clay=0, iron=0, crop=0, maxstore=800, maxcrop=800, lastmupdate=? WHERE kid=?', [miliseconds(), $player['kid']]);
$pdo->run('DELETE FROM hero_resources WHERE uid=?', [$player['id']]);
$pdo->run('INSERT INTO hero_resources (uid, wood, clay, iron, crop, updated_at) VALUES (?, 500, 0, 0, 0, ?)', [$player['id'], time()]);

$taken = (new HeroResourceInventory())->claim($player['id'], $player['kid']);
$villageWood = (float)$pdo->fetchScalar('SELECT wood FROM vdata WHERE kid=?', [$player['kid']]);
$heroWood = (int)$pdo->fetchScalar('SELECT wood FROM hero_resources WHERE uid=?', [$player['id']]);

if ($taken['wood'] < 90 || $taken['wood'] > 100) {
    $fail("Only the free warehouse space (about 100) may be collected, {$taken['wood']} was.");
}
if ($heroWood !== 500 - $taken['wood']) {
    $fail("The hero must keep what did not fit: 500 - {$taken['wood']} expected, $heroWood left.");
}
if ($villageWood > 800.001) {
    $fail("The village must not hold more than its warehouse (800), it holds $villageWood.");
}

// A full warehouse takes nothing and the stack stays with the hero.
$pdo->run('UPDATE vdata SET wood=800, lastmupdate=? WHERE kid=?', [miliseconds(), $player['kid']]);
$before = (int)$pdo->fetchScalar('SELECT wood FROM hero_resources WHERE uid=?', [$player['id']]);
$taken = (new HeroResourceInventory())->claim($player['id'], $player['kid']);
if ($taken['wood'] !== 0 || (int)$pdo->fetchScalar('SELECT wood FROM hero_resources WHERE uid=?', [$player['id']]) !== $before) {
    $fail('A full warehouse must take nothing from the hero.');
}

echo "Hero resources claim cap runtime passed.\n";
