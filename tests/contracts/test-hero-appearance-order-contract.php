<?php

declare(strict_types=1);

function failHeroAppearanceOrderContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$face = new \Game\Hero\HeroFace();
// Jaw option 8 (value 57) costs gold, but it is the option the hero wears.
$html = $face->getAllHeroFaceAttributesHtml('male', ['headProfile' => 57]);
$firstPage = explode('<div class="page page1', $html)[0];
if (!str_contains($firstPage, 'page page0 active')) {
    failHeroAppearanceOrderContract('The first appearance page must render.');
}
$locked = [];
foreach (preg_split('~(?=<div class="option option\d+)~', $firstPage, -1, PREG_SPLIT_NO_EMPTY) as $chunk) {
    if (preg_match('~option option(\d+)~', $chunk, $number) !== 1) {
        continue;
    }
    $locked[(int)$number[1]] = str_contains($chunk, 'lockedIndicator');
}
if ($locked[8] ?? true) {
    failHeroAppearanceOrderContract('The option the hero wears must not show a lock.');
}
$seenLocked = false;
foreach ($locked as $isLocked) {
    if ($isLocked) {
        $seenLocked = true;
    } elseif ($seenLocked) {
        failHeroAppearanceOrderContract('Options the hero can use must come before the locked ones.');
    }
}

// The reference's curated order: the first jaw options are 1 3 2 6 4 5 while nothing is owned.
$plain = $face->getAllHeroFaceAttributesHtml('male', []);
preg_match_all('~class="option option(\d+|Empty)[ "]~', explode('<div class="page page1', $plain)[0], $jaw);
if (array_slice($jaw[1], 0, 6) !== ['1', '3', '2', '6', '4', '5'] || array_slice($jaw[1], -3) !== ['13', '14', '15']) {
    failHeroAppearanceOrderContract('The jaw options must follow the reference order.');
}

// Beard, scar and tattoo offer an Empty tile in place of the slot without artwork (13, 6 and 8).
$pages = preg_split('~(?=<div class="page page\d+)~', $plain, -1, PREG_SPLIT_NO_EMPTY);
foreach (['7' => ['13', 'beard'], '8' => ['6', 'scar'], '9' => ['8', 'tattoo']] as $index => [$missing, $name]) {
    $page = $pages[(int)$index] ?? '';
    if (!str_contains($page, 'class="option optionEmpty') || !str_contains($page, 'data-option="empty"')
        || str_contains($page, 'class="option option' . $missing . '"')) {
        failHeroAppearanceOrderContract("The {$name} options must show Empty instead of slot {$missing}.");
    }
}

// Prices follow the reference tables, and the Empty tile and stale empty-slot values are free.
$prices = [['hairStyle', 1002, 120], ['hairStyle', 1003, 30], ['beard', 5007, 30], ['scar', 8008, 20], ['tattoo', 9006, 80], ['headProfile', 52, 0], ['beard', 5999, 0]];
foreach ($prices as [$attribute, $value, $expected]) {
    if ($face->getAppearanceOptionCost($attribute, $value, 'male') !== $expected) {
        failHeroAppearanceOrderContract("{$attribute} {$value} must cost {$expected} gold.");
    }
}
$normalized = $face->normalizeEmptySlots(['gender' => 'male', 'beard' => 5012, 'scar' => 8006, 'tattoo' => 9008]);
if ($normalized['beard'] !== 5999 || $normalized['scar'] !== 8000 || $normalized['tattoo'] !== 9000) {
    failHeroAppearanceOrderContract('Values on a slot without artwork must read as none.');
}

// Women have no beard page.
if (substr_count($face->getAllHeroFaceAttributesHtml('female', []), '<div class="page page') !== 9) {
    failHeroAppearanceOrderContract('Women have nine appearance pages (no beard).');
}

// A worn paid option is never charged again, a newly picked one that is not owned still is.
$base = ['gender' => 'male', 'headProfile' => 50, 'hairColor' => 1500, 'hairStyle' => 1000, 'ears' => 2000, 'eyebrow' => 7000,
    'eyes' => 4000, 'nose' => 3000, 'mouth' => 6000, 'beard' => 5007, 'scar' => 8000, 'tattoo' => 9000];
$request = array_merge($base, ['ears' => 2002]); // another category, the stored beard (30 gold) unchanged
if ($face->lockedSelections($request, $base, []) !== []) {
    failHeroAppearanceOrderContract('A worn paid option must not be charged when another category is saved.');
}
$request = array_merge($base, ['hairStyle' => 1002]); // hair option 3 costs 120 gold and is not owned
if ($face->lockedSelections($request, $base, []) !== [['hairStyle', 1002, 120]]) {
    failHeroAppearanceOrderContract('A newly picked paid option that is not owned must still be charged.');
}
if ($face->lockedSelections($request, $base, ['hairStyle:1002' => true]) !== []) {
    failHeroAppearanceOrderContract('An owned paid option must not be charged.');
}
// Another gender is not "worn": the same paid hair value is charged again for the new gender.
$stored = array_merge($base, ['hairStyle' => 1002]);
$request = array_merge($stored, ['gender' => 'female']);
if ($face->lockedSelections($request, $stored, []) !== [['hairStyle', 1002, 120]]) {
    failHeroAppearanceOrderContract('A gender change must be charged against the new gender.');
}

echo "Hero appearance order contract passed.\n";
