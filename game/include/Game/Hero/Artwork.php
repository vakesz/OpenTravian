<?php

declare(strict_types=1);

namespace Game\Hero;

final class Artwork
{
    /** @return array{numerator: int, denominator: int, limit: int, cooldown: int} */
    public static function rules(int $speed): array
    {
        return match (true) {
            $speed >= 10 => ['numerator' => 1, 'denominator' => 6, 'limit' => 400, 'cooldown' => 21600],
            $speed >= 5 => ['numerator' => 1, 'denominator' => 3, 'limit' => 700, 'cooldown' => 43200],
            $speed >= 3 => ['numerator' => 1, 'denominator' => 2, 'limit' => 1000, 'cooldown' => 43200],
            $speed >= 2 => ['numerator' => 2, 'denominator' => 3, 'limit' => 1300, 'cooldown' => 86400],
            default => ['numerator' => 1, 'denominator' => 1, 'limit' => 2000, 'cooldown' => 86400],
        };
    }

    public static function reward(int $speed, int $dailyProduction): int
    {
        $rules = self::rules($speed);
        return min($rules['limit'], intdiv(max(0, $dailyProduction) * $rules['numerator'], $rules['denominator']));
    }

    public static function availableAt(int $speed, int $lastUse): int
    {
        return $lastUse > 0 ? $lastUse + self::rules($speed)['cooldown'] : 0;
    }

    public static function canDrop(int $previousSuccessfulAdventures): bool
    {
        return $previousSuccessfulAdventures >= 31;
    }
}
