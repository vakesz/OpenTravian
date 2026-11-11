<?php

declare(strict_types=1);

function failHeroItemValuesContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

use Game\Hero\HeroItems;

$items = new HeroItems();

// Helmet of the Gladiator, Tribune and Consul: 100, 400 and 1600 culture points per day.
foreach ([7 => 100, 8 => 400, 9 => 1600] as $type => $expected) {
    $cp = $items->getHeroItemProperties(1, $type)['cp'] ?? null;
    if ($cp !== $expected) {
        failHeroItemValuesContract("Helmet item {$type} must give {$expected} culture points per day, got " . var_export($cp, true) . '.');
    }
}

// Gelding, Thoroughbred and Warhorse: 14, 20 and 26 speed, a step of 6 per tier.
foreach ([103 => 14, 104 => 20, 105 => 26] as $type => $expected) {
    $speed = $items->getHeroItemProperties(6, $type)['speed_horse'] ?? null;
    if ($speed !== $expected) {
        failHeroItemValuesContract("Horse item {$type} must give {$expected} speed, got " . var_export($speed, true) . '.');
    }
}

// The artwork stays speed-banded: 24 h cooldown up to 2x, 6 h from 10x.
if (\Game\Hero\Artwork::rules(2)['cooldown'] !== 86400 || \Game\Hero\Artwork::rules(10)['cooldown'] !== 21600) {
    failHeroItemValuesContract('The artwork cooldown must stay speed-banded.');
}

$doc = (string)file_get_contents($root . '/docs/world-speed.md');
if (!str_contains($doc, 'Helmet of the Consul') || !str_contains($doc, 'Artwork::rules()')) {
    failHeroItemValuesContract('docs/world-speed.md must record the hero item values and the artwork bands.');
}

echo "Hero item values contract passed.\n";
