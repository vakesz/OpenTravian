<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/game/include/Game/Hero/CombatExperience.php';

use Game\Hero\CombatExperience;

function assertHeroExperienceValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$equalHeroes = CombatExperience::defensiveShares([
    10 => ['supply' => 6, 'helmetBonus' => 0],
    11 => ['supply' => 6, 'helmetBonus' => 0],
    12 => ['supply' => 6, 'helmetBonus' => 0],
    13 => ['supply' => 6, 'helmetBonus' => 0],
    14 => ['supply' => 6, 'helmetBonus' => 0],
    15 => ['supply' => 6, 'helmetBonus' => 0],
    16 => ['supply' => 6, 'helmetBonus' => 0],
    17 => ['supply' => 6, 'helmetBonus' => 0],
    18 => ['supply' => 6, 'helmetBonus' => 0],
    19 => ['supply' => 6, 'helmetBonus' => 0],
], 60, 30, 1);
assertHeroExperienceValue(array_fill(10, 10, 3), $equalHeroes, 'Equal defender hero armies must split XP equally.');

$weightedHeroes = CombatExperience::defensiveShares([
    10 => ['supply' => 6, 'helmetBonus' => 0],
    11 => ['supply' => 100, 'helmetBonus' => 0],
], 106, 30, 1);
assertHeroExperienceValue(2, $weightedHeroes[10], 'A solo defender hero must receive its supply-weighted XP share.');
assertHeroExperienceValue(28, $weightedHeroes[11], 'A hero defending with troops must receive its larger supply-weighted XP share.');

$heroAlongsideTroops = CombatExperience::defensiveShares([
    10 => ['supply' => 6, 'helmetBonus' => 0],
], 100, 30, 1);
assertHeroExperienceValue(
    2,
    $heroAlongsideTroops[10],
    'Defending troops without a hero must still dilute their hero companion’s XP share.'
);

$helmetBonus = CombatExperience::defensiveShares([
    10 => ['supply' => 6, 'helmetBonus' => 15],
], 6, 30, 1);
assertHeroExperienceValue(35, $helmetBonus[10], 'A defender helmet must multiply the earned XP before rounding.');
assertHeroExperienceValue(600, CombatExperience::offensive(30, 0, 20), 'Fast-world combat XP must preserve the configured level multiplier.');
assertHeroExperienceValue(
    [],
    CombatExperience::defensiveShares([10 => ['supply' => 6, 'helmetBonus' => 0]], 6, 0, 1),
    'No enemy losses must grant no defensive XP.'
);

$battle = file_get_contents(dirname(__DIR__, 2) . '/game/include/Model/BattleModel.php');
if ($battle === false
    || !str_contains($battle, 'CombatExperience::defensiveShares')
    || !str_contains($battle, 'defendingSupply')
    || !str_contains($battle, 'HeroExperience::add')) {
    fwrite(STDERR, "Battle resolution must use supply-weighted defensive hero XP and HeroExperience::add.\n");
    exit(1);
}

echo "Hero combat-experience checks passed.\n";
