<?php

declare(strict_types=1);

// The Spartan weapon sets (items 149-163) must act in battle like the other tribes' weapons: a hero carrying one
// adds its attack and defence bonus to its own unit type (Spartan units only) and its fighting strength to the hero.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Spartan weapon bonus runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use Game\Hero\HeroHelper;
use Game\Hero\HeroItems;
use Model\BattleModel;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};

$battle = (new ReflectionClass(BattleModel::class))->newInstanceWithoutConstructor();
$property = new ReflectionProperty(BattleModel::class, 'heroItemsModel');
$property->setValue($battle, new HeroItems());
$bonus = new ReflectionMethod(BattleModel::class, 'item_unit_bonus');

$wave = static function (int $race, int $type, int $heroes = 1): array {
    $num = array_fill(1, 11, 0);
    $num[11] = $heroes;

    return [
        'race' => $race,
        'units' => ['num' => $num, 'trapped' => array_fill(1, 11, 0), 'dead' => array_fill(1, 11, 0)],
        'heroItems' => ['rightHand' => ['btype' => 4, 'type' => $type], 'leftHand' => null, 'body' => null],
    ];
};

// Weapon set => [unit slot, bonus at each tier, unit id].
$sets = [149 => [1, [3, 4, 5], 71], 152 => [3, [3, 4, 5], 73], 155 => [4, [3, 4, 5], 74], 158 => [5, [6, 8, 10], 75], 161 => [6, [9, 12, 15], 76]];
foreach ($sets as $first => [$slot, $perTier, $unitId]) {
    foreach ($perTier as $tier => $expected) {
        $type = $first + $tier;
        $properties = (new HeroItems())->getHeroItemProperties(4, $type);
        if (($properties['unitId'] ?? null) !== $unitId || ($properties['unit_power'] ?? null) !== $expected
            || ($properties['hero_power'] ?? null) !== [250, 1000, 4000][$tier]) {
            $fail("Item {$type} must carry unit {$unitId}, bonus {$expected} and fighting strength " . [250, 1000, 4000][$tier] . '.');
        }
        $spartan = $bonus->invoke($battle, $wave(8, $type));
        if ($spartan[$slot] !== $expected || array_sum($spartan) !== $expected) {
            $fail("Item {$type} must give a Spartan hero +{$expected} on unit slot {$slot} only.");
        }
        // Another tribe's hero gains nothing from it, and neither does a hero that is not with the army.
        if (array_sum($bonus->invoke($battle, $wave(1, $type))) !== 0 || array_sum($bonus->invoke($battle, $wave(8, $type, 0))) !== 0) {
            $fail("Item {$type} must not add a unit bonus for another tribe or a missing hero.");
        }
        if ((new HeroHelper())->calcItemPower(['btype' => 4, 'type' => $type], null, null) !== [250, 1000, 4000][$tier]) {
            $fail("Item {$type} must add its fighting strength to the hero.");
        }
    }
}

echo "Spartan weapon bonus runtime passed.\n";
