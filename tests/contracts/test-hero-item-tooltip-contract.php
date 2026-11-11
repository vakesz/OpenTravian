<?php

declare(strict_types=1);

function failHeroItemTooltipContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

use Game\Hero\HeroItems;
use Game\Hero\HeroItemTooltip;

$isolate = static fn (string $sign, string $number, string $suffix): string => "\u{202D}" . $sign . "\u{202D}" . $number . "\u{202C}" . $suffix . "\u{202C}";

// Light Breastplate (item 88): one fighting strength block under a tier 1 armor header.
$breastplate = HeroItemTooltip::html(2, 88);
foreach ([
    '<div class="heroV2"><div class="heroItemTooltip tier1 heroItemTooltipV1">',
    '<div class="subTitle">Tier 1 Armor</div><div class="separator"></div>',
    '<div class="stat"><div>' . $isolate('+', '250', '') . ' fighting strength</div></div>',
] as $needle) {
    if (!str_contains($breastplate, $needle)) {
        failHeroItemTooltipContract("Light Breastplate tooltip is missing: {$needle}");
    }
}

// Weapons list the unit bonus first, then the hero's fighting strength.
$weapon = HeroItemTooltip::html(4, 142);
if (strpos($weapon, 'attack and defense for every Marauder') === false
    || strpos($weapon, 'attack and defense') > strpos($weapon, 'fighting strength')) {
    failHeroItemTooltipContract('Weapon tooltips must list the unit bonus before the fighting strength.');
}

// The Spartan sets (149-163) show the same two blocks as the other weapons.
$spartan = HeroItemTooltip::html(4, 163);
foreach (['heroItemTooltip tier3 heroItemTooltipV1', 'Tier 3 Weapon', ' attack and defense for every Corinthian Crusher', ' fighting strength'] as $needle) {
    if (!str_contains($spartan, $needle)) {
        failHeroItemTooltipContract("The Spartan weapon tooltip is missing: {$needle}");
    }
}
if (!str_contains($spartan, $isolate('+', '15', '')) || !str_contains($spartan, $isolate('+', '4000', ''))) {
    failHeroItemTooltipContract('The Spartan weapon tooltip must list +15 and +4000.');
}

// Consumables use the consumable tier, keep the reference's trailing space and add the equip and stack notes.
$cage = HeroItemTooltip::html(9, 114);
foreach ([
    'heroItemTooltip consumable heroItemTooltipV1',
    '<div class="subTitle">Consumable </div>',
    '<div class="details">Equip and raid an oasis',
    'Takes effect when equipped.',
    'The item is stackable.',
    'Has to be equipped before battle to take effect.',
    'No fight will take place but animals will be captured.',
] as $needle) {
    if (!str_contains($cage, $needle)) {
        failHeroItemTooltipContract("Cage tooltip is missing: {$needle}");
    }
}

// The bucket only explains itself while the hero lives.
if (!str_contains(HeroItemTooltip::html(12, 108, true), '<div class="notClickable">Your hero is alive. Therefore you cannot use this item.</div>')
    || str_contains(HeroItemTooltip::html(12, 108, false), 'notClickable')) {
    failHeroItemTooltipContract('The bucket tooltip must carry the alive-hero note only while the hero is alive.');
}

// Every auction item produces a tier-labelled header, and the horse family adds "Mounts hero" to the benefits list.
foreach (HeroItems::getAvailableItemTypes() as $btype => $types) {
    foreach ($types as $type) {
        $html = HeroItemTooltip::html($btype, $type);
        if (!str_contains($html, '<div class="separator"></div>') || !preg_match('~<div class="subTitle">(Tier [123] [A-Za-z ]+|Consumable )</div>~', $html)) {
            failHeroItemTooltipContract("Item {$type} has no tier or consumable sub title.");
        }
    }
}
if (HeroItemTooltip::benefits(6, 103)[0] !== 'Mounts hero') {
    failHeroItemTooltipContract('Horses must list "Mounts hero" first in the equipment benefits.');
}

echo "Hero item tooltip contract passed.\n";
