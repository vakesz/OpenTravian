<?php

declare(strict_types=1);

/**
 * Contract: the adventure list's place icons use the root-absolute spacer image.
 */

function failHeroAdventurePlaceIconsContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$ctrl = file_get_contents($root . '/game/include/Controller/HeroAdventureCtrl.php');

if ($ctrl === false) {
    failHeroAdventurePlaceIconsContract('Unable to read the adventure controller.');
}

if (!str_contains($ctrl, 'src="/img/spacer.gif"')) {
    failHeroAdventurePlaceIconsContract('Adventure list place icons must use absolute /img/spacer.gif.');
}

if (str_contains($ctrl, 'src="img/spacer.gif"')) {
    failHeroAdventurePlaceIconsContract('Adventure list place icons must not keep relative img/spacer.gif.');
}

echo "Hero adventure place icons contract passed.\n";
