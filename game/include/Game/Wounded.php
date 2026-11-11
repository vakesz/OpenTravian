<?php

declare(strict_types=1);

namespace Game;

use Core\Database\DB;

/**
 * Wounded troops (Hospital gid 46, Asclepeion gid 48).
 *
 * Source: Knowledge Base article 77 "Hospital and Asclepeion" (OpenTravian).
 * - Only a village that has a Hospital (40% of its losses) or an Asclepeion
 *   (60%) when the battle ends produces wounded troops; this applies when
 *   attacking, defending and reinforcing (the wounded go to the home village
 *   of the troops that were hit).
 * - Siege engines, settlers, administrators and heroes never become wounded;
 *   Nature and Natars have no hospital.
 * - Wounded troops cannot fight and eat no crop. Unhealed wounded decay by
 *   10% per day, continuously and unscaled by world speed. Queued healing
 *   stops the decay. Conquest removes them with the village.
 * - Healing costs the unit's training cost and takes half its training time
 *   at the same building level (Hospital level multiplier 0.9^(level-1)), so it
 *   follows the world speed exactly like training.
 */
final class Wounded
{
    public const HOSPITAL = 46;
    public const ASCLEPEION = 48;
    public const HOSPITAL_SHARE = 0.4;
    public const ASCLEPEION_SHARE = 0.6;
    public const DECAY_PER_DAY = 0.9;
    public const HEAL_TIME_FACTOR = 0.5;

    public static function share(int $buildingId): float
    {
        return $buildingId === self::ASCLEPEION ? self::ASCLEPEION_SHARE : self::HOSPITAL_SHARE;
    }

    /** Which building a tribe heals in. */
    public static function buildingFor(int $tribe): int
    {
        return $tribe === 8 ? self::ASCLEPEION : self::HOSPITAL;
    }

    /** Unit numbers (1-10) that can be wounded: not siege (7, 8), administrators (9) or settlers (10). */
    public static function canBeWounded(int $tribe, int $nr): bool
    {
        return !in_array($tribe, [4, 5], true) && $nr >= 1 && $nr <= 6;
    }

    public static function decayed(float $amount, int $seconds): float
    {
        return $amount * pow(self::DECAY_PER_DAY, max(0, $seconds) / 86400);
    }

    /** Wounded produced for a village's losses of one unit type. */
    public static function woundedFor(int $dead, float $share): int
    {
        return (int)round($dead * $share);
    }

    /** @return int Hospital/Asclepeion level of a village at this moment (0 when it has neither). */
    public static function buildingLevel(int $kid, int $tribe): int
    {
        $db = DB::getInstance();
        $building = self::buildingFor($tribe);
        $level = 0;
        $row = $db->query("SELECT * FROM fdata WHERE kid=" . $kid)->fetch_assoc();
        if (!$row) {
            return 0;
        }
        for ($field = 19; $field <= 40; ++$field) {
            if ((int)$row['f' . $field . 't'] === $building) {
                $level = max($level, (int)$row['f' . $field]);
            }
        }

        return $level;
    }

    /**
     * Adds wounded troops for the units that died in a battle.
     *
     * @param array<int|string, int|float> $dead dead units keyed by unit number
     * @return int[] wounded added, keyed by unit number
     */
    public static function record(int $homeKid, int $tribe, array $dead): array
    {
        if ($homeKid <= 0 || in_array($tribe, [4, 5], true)) {
            return [];
        }
        if (self::buildingLevel($homeKid, $tribe) <= 0) {
            return [];
        }
        $share = self::share(self::buildingFor($tribe));
        $added = [];
        foreach ($dead as $nr => $count) {
            $nr = (int)$nr;
            if (!self::canBeWounded($tribe, $nr)) {
                continue;
            }
            $wounded = self::woundedFor((int)$count, $share);
            if ($wounded <= 0) {
                continue;
            }
            self::store($homeKid, $nr, self::amount($homeKid, $nr) + $wounded);
            $added[$nr] = $wounded;
        }

        return $added;
    }

    /** @return int[] wounded troops by unit number, decay applied */
    public static function all(int $kid): array
    {
        $db = DB::getInstance();
        $now = time();
        $result = $db->query("SELECT nr, num, updated_at FROM wounded WHERE kid=" . $kid);
        $wounded = [];
        while ($row = $result->fetch_assoc()) {
            $count = (int)floor(self::decayed((float)$row['num'], $now - (int)$row['updated_at']) + 1e-9);
            if ($count > 0) {
                $wounded[(int)$row['nr']] = $count;
            }
        }
        ksort($wounded);

        return $wounded;
    }

    /** Removes troops from the wounded pool (they enter the healing queue). */
    public static function take(int $kid, int $nr, int $count): bool
    {
        $have = self::amount($kid, $nr);
        if ($count <= 0 || floor($have + 1e-9) < $count) {
            return false;
        }
        self::store($kid, $nr, $have - $count);

        return true;
    }

    public static function purge(int $kid): void
    {
        DB::getInstance()->query("DELETE FROM wounded WHERE kid=" . $kid);
    }

    private static function amount(int $kid, int $nr): float
    {
        $db = DB::getInstance();
        $row = $db->query("SELECT num, updated_at FROM wounded WHERE kid=" . $kid . " AND nr=" . $nr)->fetch_assoc();

        return $row ? self::decayed((float)$row['num'], time() - (int)$row['updated_at']) : 0.0;
    }

    private static function store(int $kid, int $nr, float $amount): void
    {
        $db = DB::getInstance();
        if ($amount <= 1e-9) {
            $db->query("DELETE FROM wounded WHERE kid=" . $kid . " AND nr=" . $nr);

            return;
        }
        $db->query(
            "INSERT INTO wounded (kid, nr, num, updated_at) VALUES ({$kid}, {$nr}, " . sprintf('%.6F', $amount) . ", " . time()
            . ") ON DUPLICATE KEY UPDATE num=VALUES(num), updated_at=VALUES(updated_at)"
        );
    }
}
