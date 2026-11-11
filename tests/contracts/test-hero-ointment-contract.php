#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: an ointment heals one health point, up to 100%. Used from the inventory it heals at once; equipped in the
 * bag slot it heals the surviving hero right after a battle (attacker and every defending or reinforcing hero, once
 * each) and is used up. Ointments left in the inventory are never used automatically, and adventures do not use them.
 */

function failOintmentContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function assertOintment(bool $condition, string $message): void
{
    if (!$condition) {
        failOintmentContract($message);
    }
}

$root = dirname(__DIR__, 2);
$inventorySource = (string)file_get_contents($root . '/game/include/Model/HeroInventoryModel.php');
assertOintment(
    strpos($inventorySource, 'case 11:') !== false
    && strpos($inventorySource, '$used = min($amount, (int)ceil(100 - $health));') !== false
    && strpos($inventorySource, 'Ointments cannot be used while the hero is dead.') !== false,
    'Manual ointment use must heal a living hero by at most one percentage point per item.'
);
assertOintment(
    strpos($inventorySource, "if ((int)\$item['proc'] !== 0)") !== false
    && strpos($inventorySource, 'Move this item into the inventory before using it.') !== false,
    'An equipped ointment must be moved back to inventory before manual use.'
);
assertOintment(
    strpos($inventorySource, '[...range(1, 9), 11]') !== false
    && strpos($inventorySource, '$useItem = $type >= 10 && $clicked;') !== false
    && strpos($inventorySource, 'Could not add to the equipped stack.') !== false,
    'Ointments must be equippable into the bag slot and stack with the equipped ones; a bag drop alone must not use them.'
);

$bag = (string)file_get_contents($root . '/game/include/Game/Hero/BagOintment.php');
assertOintment(
    strpos($bag, 'i.proc=1') !== false && strpos($bag, '$health <= 0') !== false && strpos($bag, '(int)ceil($lost)') !== false,
    'Only equipped ointments heal, never a dead hero, and at most the health this battle took.'
);

$battleSource = (string)file_get_contents($root . '/game/include/Model/BattleModel.php');
assertOintment(
    substr_count($battleSource, 'BagOintment::healAfterBattle(') === 2,
    'The attacking hero and each defending or reinforcing hero are healed once per battle.'
);
$helperSource = (string)file_get_contents($root . '/game/include/Game/Hero/HeroHelper.php');
$adventureSource = (string)file_get_contents($root . '/game/include/Model/Movements/AdventureProcessor.php');
assertOintment(
    strpos($helperSource, 'autoOintmentHeal') === false
    && strpos($battleSource, 'autoOintmentHeal') === false
    && strpos($adventureSource, 'BagOintment') === false,
    'Adventure completion and the old automatic heal must not use ointments.'
);

echo "Hero ointment contract passed.\n";
