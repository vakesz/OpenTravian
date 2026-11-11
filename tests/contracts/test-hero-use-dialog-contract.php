<?php

declare(strict_types=1);

/*
 * The use dialog of a consumable carries the reference strings captured for the Cage, the Bucket and the Book of
 * Wisdom (description, amount label, impact row, confirm label, the Bucket's error while the hero lives).
 */

use Game\Hero\ConsumableDialog;

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

function failUseDialog(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$context = ['health' => 40, 'experience' => 1000, 'perScroll' => 100, 'bonus' => 10, 'loyaltyFull' => false];
$cage = ConsumableDialog::describe(9, 114, true, 0, $context);
if ($cage['confirm'] !== 'Equip' || $cage['label'] !== 'Number of cages to equip' || !$cage['input']
    || $cage['impacts'] !== [['kind' => 'count', 'label' => 'Number of equipped cages:', 'from' => 0]] || $cage['error'] !== null
    || !str_starts_with($cage['description'], 'Equip and raid an oasis with animals')) {
    failUseDialog('The Cage dialog must match the reference.');
}

$bucket = ConsumableDialog::describe(12, 108, true, 0, $context);
if ($bucket['confirm'] !== 'Consume' || $bucket['input'] || $bucket['description'] !== 'Use to revive your hero immediately.'
    || $bucket['error'] !== 'Your hero is alive. Therefore you cannot use this item.') {
    failUseDialog('The Bucket dialog must match the reference.');
}
if (ConsumableDialog::describe(12, 108, false, 0, $context)['error'] !== null) {
    failUseDialog('The Bucket dialog must have no error once the hero is dead.');
}

$book = ConsumableDialog::describe(13, 110, true, 0, $context);
if ($book['confirm'] !== 'Use' || $book['input'] || $book['error'] !== null
    || $book['description'] !== 'Use to reclaim all spent attribute points and be able to spend them again.') {
    failUseDialog('The Book of Wisdom dialog must match the reference.');
}

$ointment = ConsumableDialog::describe(11, 106, true, 0, $context);
if ($ointment['label'] !== 'Number of ointments used' || $ointment['confirm'] !== 'Use'
    || $ointment['impacts'] !== [['kind' => 'health', 'label' => 'Hero health after use:', 'from' => 40]]) {
    failUseDialog('The Ointment dialog must match the reference strings.');
}
$scroll = ConsumableDialog::describe(10, 107, true, 0, $context);
if ($scroll['label'] !== 'Number of scrolls used' || count($scroll['impacts']) !== 3
    || $scroll['impacts'][0]['label'] !== 'Experience gained by using scroll:' || $scroll['impacts'][0]['value'] !== 100
    || $scroll['impacts'][1]['value'] !== 10 || $scroll['impacts'][2]['perItem'] !== 110
    || $scroll['impacts'][2]['label'] !== 'Hero experience after usage:' || $scroll['impacts'][2]['from'] !== 1000) {
    failUseDialog('The Scroll dialog must match the reference strings.');
}
$bandage = ConsumableDialog::describe(7, 112, true, 3, $context);
if ($bandage['label'] !== 'Number of small bandages to equip' || $bandage['confirm'] !== 'Equip'
    || $bandage['impacts'] !== [['kind' => 'count', 'label' => 'Number of equipped small bandages:', 'from' => 3]]
    || ConsumableDialog::describe(8, 113, true, 0, $context)['label'] !== 'Number of bandages to equip') {
    failUseDialog('The Bandage dialogs must match the reference strings.');
}
$tablet = ConsumableDialog::describe(14, 109, true, 0, ['loyaltyFull' => true] + $context);
if ($tablet['label'] !== 'Number of tablets of law used'
    || $tablet['error'] !== 'You cannot use this item currently, as your village has reached maximum loyalty.') {
    failUseDialog('The Tablet of Law dialog must match the reference strings.');
}

$script = (string)file_get_contents($root . '/game/deployment/public/game-client.js');
if (!str_contains($script, 'item.isConsumable || item.isMaterial')) {
    failUseDialog('Bucket and Book clicks must open the use dialog.');
}

echo "Hero use dialog contract passed.\n";
