<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/game/include/Game/Hero/HeroFace.php';

use Game\Hero\HeroFace;

function assertHeroFaceEncoding(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$face = new HeroFace();

$storedNone = [
    'gender' => 'male',
    'hairStyle' => 5,
    'beard' => 5,
    'scar' => 0,
    'tattoo' => 0,
];
$displayNone = $face->encodeAttribute($storedNone);
assertHeroFaceEncoding($displayNone['hairStyle'] === 1499, 'Legacy hair none must remain selectable as none.');
assertHeroFaceEncoding($displayNone['beard'] === 5999, 'Legacy beard none must remain selectable as none.');
assertHeroFaceEncoding($displayNone['scar'] === 8000, 'Default scar storage must render no scar.');
assertHeroFaceEncoding($displayNone['tattoo'] === 9000, 'Default tattoo storage must render no tattoo.');

$displayChoices = [
    'gender' => 'male',
    'hairStyle' => 1005,
    'beard' => 5005,
    'scar' => 8001,
    'tattoo' => 9015,
];
$storedChoices = $face->decodeAttribute($displayChoices);
assertHeroFaceEncoding($storedChoices['hairStyle'] === 6, 'Sixth hair style must not collide with legacy none storage.');
assertHeroFaceEncoding($storedChoices['beard'] === 6, 'Sixth beard style must not collide with legacy none storage.');
assertHeroFaceEncoding($storedChoices['scar'] === 1, 'First scar must not collide with the default no-scar storage.');
assertHeroFaceEncoding($storedChoices['tattoo'] === 15, 'Last tattoo must preserve its distinct storage value.');
assertHeroFaceEncoding($face->encodeAttribute($storedChoices) === $displayChoices, 'Appearance choices must round-trip through storage.');

echo "Hero face encoding contract passed.\n";
