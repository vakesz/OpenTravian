<?php

namespace Game\Hero;

/** Calculates the supply-weighted hero XP awarded after a battle. */
final class CombatExperience
{
    public static function offensive(int $killedEnemySupply, int $helmetBonus, int $worldMultiplier): int
    {
        return self::applyBonus($killedEnemySupply, $helmetBonus, $worldMultiplier);
    }

    /**
     * @param array<int, array{supply: int, helmetBonus: int}> $heroArmies
     * @param int $totalDefendingSupply Supply of every defending army, including those without a hero.
     * @return array<int, int>
     */
    public static function defensiveShares(
        array $heroArmies,
        int $totalDefendingSupply,
        int $killedEnemySupply,
        int $worldMultiplier
    ): array {
        $killedEnemySupply = max(0, $killedEnemySupply);
        $totalDefendingSupply = max(0, $totalDefendingSupply);
        $worldMultiplier = max(1, $worldMultiplier);
        if ($killedEnemySupply === 0 || $totalDefendingSupply === 0) {
            return [];
        }

        $shares = [];
        foreach ($heroArmies as $uid => $heroArmy) {
            $share = $killedEnemySupply * max(0, (int)$heroArmy['supply']) / $totalDefendingSupply;
            $shares[(int)$uid] = self::applyBonus($share, (int)$heroArmy['helmetBonus'], $worldMultiplier);
        }

        return $shares;
    }

    private static function applyBonus(float $experience, int $helmetBonus, int $worldMultiplier): int
    {
        return max(0, (int)round($experience * max(0, 100 + $helmetBonus) / 100 * max(1, $worldMultiplier)));
    }
}
