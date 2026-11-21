<?php

declare(strict_types=1);

// Hero level stops at 100 however much experience the hero has, attribute
// points stop at 400, and bandages read from a database row reach the strict
// battle code as numbers (a string count crashed every battle where the hero
// carried bandages and the attacker lost troops).

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Hero level cap runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/hero/inventory';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/hero/inventory';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use Game\Formulas;
use Game\Hero\HeroHelper;
use Game\Hero\ItemBtype;
use Game\Hero\ItemType;
use Model\BattleSetter;

$fail = static function (string $message): never {
    fwrite(STDERR, 'Hero level cap runtime failed: ' . $message . "\n");
    exit(1);
};

foreach ([0 => 0, 1 => 1, 99 => 99, 100 => 100, 101 => 100, 250 => 100] as $nominal => $expected) {
    $level = Formulas::heroLevel(Formulas::heroExperience($nominal));
    if ($level !== (float)$expected) {
        $fail("experience for level $nominal gave level $level, expected $expected.");
    }
}
if (Formulas::heroLevel(PHP_INT_MAX >> 8) !== (float)Formulas::HERO_MAX_LEVEL) {
    $fail('huge experience must still be level ' . Formulas::HERO_MAX_LEVEL . '.');
}

foreach ([0 => 4, 1 => 8, 98 => 396, 99 => 400, 100 => 400, 150 => 400] as $level => $points) {
    if (Formulas::heroAttributePoints($level) !== $points) {
        $fail("level $level must give $points attribute points, got " . Formulas::heroAttributePoints($level) . '.');
    }
}

// A bag row exactly as mysqli returns it: every column a string.
$bandages = (new HeroHelper())->getBandages(['btype' => (string)ItemBtype::BANDAGE, 'type' => (string)ItemType::BANDAGE, 'num' => '12']);
if (!is_int($bandages['num']) || $bandages['num'] !== 12) {
    $fail('a bandage count from the database must be an int, got ' . var_export($bandages['num'], true) . '.');
}
$dead = [1 => 30, 2 => 10];
$traps = min($bandages['num'], ceil(40 * $bandages['eff'] / 100));
$revived = (new ReflectionClass(BattleSetter::class))->newInstanceWithoutConstructor()->move_to_traps($dead, $traps);
if (array_sum($revived) <= 0) {
    $fail('bandages must revive part of the dead troops.');
}

echo "Hero level cap runtime passed.\n";
