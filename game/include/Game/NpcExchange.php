<?php

declare(strict_types=1);

namespace Game;

/**
 * The distribution arithmetic of the NPC merchant: whatever the player asks for is scaled to the stock that is in the
 * village when the exchange runs, so the total never changes. Shared by the dialog, the normalising API, the purchase
 * and the one-click balance.
 */
final class NpcExchange
{
    private function __construct()
    {
    }

    /**
     * Scales `$seed` (four wanted amounts, any size) to `$total`, rounding the leftovers to the largest fractions.
     *
     * @param array<int, int|float> $seed
     * @return list<int>
     */
    public static function scale(array $seed, int $total): array
    {
        $seedTotal = array_sum($seed);
        $scaled = [];
        $fractions = [];
        $assigned = 0;
        foreach ($seed as $index => $value) {
            $exact = $value * $total / $seedTotal;
            $scaled[$index] = (int)floor($exact);
            $fractions[$index] = $exact - $scaled[$index];
            $assigned += $scaled[$index];
        }
        arsort($fractions);
        foreach (array_keys($fractions) as $index) {
            if ($assigned >= $total) {
                break;
            }
            ++$scaled[$index];
            ++$assigned;
        }

        return array_values($scaled);
    }
}
