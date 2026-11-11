<?php

declare(strict_types=1);

use Game\Formulas;

$heroExperienceSpeed = 1;

function getGameSpeed(): int
{
    global $heroExperienceSpeed;

    return $heroExperienceSpeed;
}

function assertHeroExperienceSpeed(int $speed, int $multiplier): void
{
    global $heroExperienceSpeed;

    $heroExperienceSpeed = $speed;
    if (Formulas::getHeroExpLevelMultiplier() !== $multiplier) {
        fwrite(STDERR, sprintf("Expected a %dx hero experience multiplier at %dx speed.\n", $multiplier, $speed));
        exit(1);
    }

    $experienceAtLevelTwelve = Formulas::heroExperience(12);
    if ((int)Formulas::heroLevel($experienceAtLevelTwelve) !== 12) {
        fwrite(STDERR, sprintf("Hero levels must remain invertible at %dx speed.\n", $speed));
        exit(1);
    }
}

function assertHeroExperienceReconciliation(int $fromSpeed, int $toSpeed, int $experience): void
{
    global $heroExperienceSpeed;

    $fromMultiplier = Formulas::heroExpLevelMultiplierForSpeed($fromSpeed);
    $toMultiplier = Formulas::heroExpLevelMultiplierForSpeed($toSpeed);
    $reconciledExperience = (int)round($experience * $toMultiplier / $fromMultiplier);

    $heroExperienceSpeed = $fromSpeed;
    $levelBefore = Formulas::heroLevel($experience);
    $heroExperienceSpeed = $toSpeed;
    $levelAfter = Formulas::heroLevel($reconciledExperience);

    if ($levelBefore !== $levelAfter) {
        fwrite(
            STDERR,
            sprintf(
                "Reconciled hero experience must preserve the level when changing from %dx to %dx speed.\n",
                $fromSpeed,
                $toSpeed
            )
        );
        exit(1);
    }
}

require dirname(__DIR__, 2) . '/game/include/Game/Formulas.php';

assertHeroExperienceSpeed(1, 1);
assertHeroExperienceSpeed(10, 1);
assertHeroExperienceSpeed(11, 20);
assertHeroExperienceSpeed(1000, 20);
assertHeroExperienceSpeed(1001, 40);
assertHeroExperienceSpeed(20000, 600);
assertHeroExperienceSpeed(20001, 601);
assertHeroExperienceSpeed(100000, 3000);
assertHeroExperienceReconciliation(1000, 1000000, 23850028);

echo "Hero experience speed contract passed.\n";
