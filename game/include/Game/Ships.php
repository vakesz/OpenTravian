<?php

declare(strict_types=1);

namespace Game;

use Core\Config;
use Core\Database\DB;

/**
 * Single source for Harbor ships (gid 49).
 *
 * Sources (read 2026-10-31): Knowledge Base articles 24, 25 and 198 and the
 * building tool data of the Legends Knowledge Base (Harbor ship
 * cap per level). Costs and times are x1 values; construction time is scaled
 * by world speed and the Harbor level like troop training (0.9^(level-1)), the
 * level rule being the one unpublished detail (docs/research/travian-harbor-data.md).
 * Raid ship crop is 150 (article 198, the newer one; article 25 says 350 and
 * main.js carries no ship data).
 */
final class Ships
{
    public const TRADE = 1;
    public const WAR = 2;
    public const DECOY = 3;
    public const RAID = 4;

    /** Training rows use nr 100 + ship type; units.ship1..ship4 hold the home ships. */
    public const TRAINING_NR_OFFSET = 100;

    /** Harbor building gid. */
    public const HARBOR_GID = 49;

    /** Ships allowed by Harbor level 1..20 (Knowledge Base building data). */
    private const CAP_BY_LEVEL = [
        1 => 1, 3, 6, 10, 15, 21, 28, 36, 45, 55, 66, 78, 91, 105, 120, 136, 153, 171, 190, 210,
    ];

    private const TYPES = [
        self::TRADE => ['key' => 'tradeShip', 'cost' => [2835, 1235, 1985, 750], 'time' => 4500, 'units' => 0],
        self::WAR => ['key' => 'warShip', 'cost' => [18500, 8355, 12275, 7500], 'time' => 13500, 'units' => PHP_INT_MAX],
        self::DECOY => ['key' => 'decoyWarShip', 'cost' => [950, 350, 750, 350], 'time' => 2100, 'units' => 60],
        self::RAID => ['key' => 'raidShip', 'cost' => [750, 450, 450, 150], 'time' => 1200, 'units' => 200],
    ];

    /** Trade ship capacity at x1 by tribe id (articles 25 and 198; Viking 3000 per the lead). */
    private const TRADE_CAPACITY = [1 => 2500, 2 => 3000, 3 => 2750, 6 => 2500, 7 => 3000, 8 => 2750, 9 => 3000];

    private const DEEP_WATER_SPEED = 18;
    private const VIKING_RAID_SPEED = 24;

    /**
     * @return list<int>
     */
    public static function types(): array
    {
        return array_keys(self::TYPES);
    }

    public static function isShip(int $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    public static function key(int $type): string
    {
        return self::TYPES[$type]['key'];
    }

    /** units column holding the home ships of a type. */
    public static function column(int $type): string
    {
        return 'ship' . $type;
    }

    /**
     * @return list<int>
     */
    public static function cost(int $type): array
    {
        return self::TYPES[$type]['cost'];
    }

    /** Most units one ship carries (0 for trade ships, which carry resources). */
    public static function unitCapacity(int $type): int
    {
        return self::TYPES[$type]['units'];
    }

    /** Ships a Harbor of this level allows over all ship types. */
    public static function maxShips(int $harborLevel): int
    {
        if ($harborLevel < 1) {
            return 0;
        }
        return self::CAP_BY_LEVEL[min($harborLevel, 20)];
    }

    /**
     * Time to build one ship (world speed and Harbor level applied) in the ticks the training queue stores: seconds up
     * to x20, milliseconds above, nanoseconds above x20000. One tick at the least, like troop training.
     */
    public static function buildTime(int $type, int $harborLevel): int
    {
        $rate = Config::getProperty('game', 'useNanoseconds') ? 1_000_000_000 : (Config::getProperty('game', 'useMilSeconds') ? 1_000 : 1);
        $time = self::TYPES[$type]['time'] / getGameSpeed() * (0.9 ** (max(1, $harborLevel) - 1)) * $rate;
        return max(1, (int)floor(round($time)));
    }

    /** Resource capacity of one trade ship. */
    public static function tradeCapacity(int $tribe, int $tradeOfficeLevel, float $allianceBonus = 1.0): int
    {
        $base = self::TRADE_CAPACITY[$tribe] ?? self::TRADE_CAPACITY[1];
        return (int)round($base * getGameSpeed() * (Formulas::TradeOfficeValue($tribe, $tradeOfficeLevel) / 100) * $allianceBonus);
    }

    /**
     * Fields per hour over deep water; ship speed cannot be boosted there. Trade ships follow the economic speed like
     * merchants on land, raiding and troop ships the movement band like troops.
     */
    public static function deepWaterSpeed(int $type, int $tribe): float
    {
        $speed = ($type === self::RAID && $tribe === 9) ? self::VIKING_RAID_SPEED : self::DEEP_WATER_SPEED;
        return $speed * ($type === self::TRADE ? getGameSpeed() : Config::getProperty('game', 'movement_speed_increase'));
    }

    /**
     * Ships waiting in a village, by type.
     *
     * @return array<int, int>
     */
    public static function homeShips(int $kid): array
    {
        $row = DB::getInstance()->query("SELECT ship1, ship2, ship3, ship4 FROM units WHERE kid=" . $kid)->fetch_assoc() ?: [];
        $ships = [];
        foreach (self::types() as $type) {
            $ships[$type] = (int)($row[self::column($type)] ?? 0);
        }

        return $ships;
    }

    /** Ships still being built, over all types. */
    public static function queuedShips(int $kid): int
    {
        return (int)DB::getInstance()->fetchScalar("SELECT SUM(num) FROM training WHERE kid=? AND item_id=? AND nr>?", [$kid, self::HARBOR_GID, self::TRAINING_NR_OFFSET]);
    }

    /** Ships of this home harbor that are out on a movement or a delivery. */
    public static function awayShips(int $kid): int
    {
        $db = DB::getInstance();

        return (int)$db->fetchScalar("SELECT COUNT(id) FROM movement WHERE ship>0 AND ((mode=0 AND kid=?) OR (mode=1 AND to_kid=?))", [$kid, $kid])
            + (int)$db->fetchScalar("SELECT SUM(ships) FROM send WHERE ships>0 AND ((mode=0 AND kid=?) OR (mode=1 AND to_kid=?))", [$kid, $kid]);
    }

    /** Ships the Harbor may still build: its cap minus every ship owned, away or queued. */
    public static function buildRoom(int $kid, int $harborLevel): int
    {
        $owned = array_sum(self::homeShips($kid)) + self::awayShips($kid) + self::queuedShips($kid);

        return max(0, self::maxShips($harborLevel) - $owned);
    }

    /** True while the village has a standing Harbor (level 1 or more). */
    public static function hasHarbor(int $kid): bool
    {
        $conditions = [];
        for ($field = 19; $field <= 40; ++$field) {
            $conditions[] = "(f{$field}t=" . self::HARBOR_GID . " AND f{$field}>0)";
        }

        return (bool)DB::getInstance()->fetchScalar("SELECT " . implode(' OR ', $conditions) . " FROM fdata WHERE kid=?", [$kid]);
    }

    /**
     * Brings a ship home when its movement row is processed.
     *
     * A ship comes home with its army when the army returns (mode 1), and at
     * once when the army stays at the target (reinforcements, settlers): ships
     * are never stationed elsewhere (article 25). A ship whose Harbor was
     * destroyed meanwhile is lost (article 24). Attacks and raids keep the ship
     * with the army, whose return row carries it; an army wiped out loses it.
     *
     *
     * @param array<mixed> $row
     */
    public static function dockOnArrival(array $row): void
    {
        $type = (int)($row['ship'] ?? 0);
        if (!self::isShip($type)) {
            return;
        }
        if ((int)$row['mode'] === 1) {
            $home = (int)$row['to_kid'];
        } elseif (in_array((int)$row['attack_type'], [2, 5], true)) {
            $home = (int)$row['kid'];
        } else {
            return;
        }
        if (self::hasHarbor($home)) {
            $column = self::column($type);
            DB::getInstance()->query("UPDATE units SET $column=$column+1 WHERE kid=" . $home);
        }
    }

    /**
     * The ship an army of $unitCount units would sail on, or 0 when none fits.
     *
     * Raids use a raid ship first (up to 200 units, article 198); everything
     * else, and raids no raid ship can take, use a decoy warship for up to 60
     * units before a warship (article 198: "If a decoy warship is available and
     * you send 60 units or fewer, it will be used first"). Raid ships never
     * carry reinforcements, normal attacks or settlers.
     */
    public static function pickForArmy(int $kid, int $attackType, int $unitCount): int
    {
        $home = self::homeShips($kid);
        $order = $attackType === 4 ? [self::RAID, self::DECOY, self::WAR] : [self::DECOY, self::WAR];
        foreach ($order as $type) {
            if ($home[$type] > 0 && $unitCount <= self::unitCapacity($type)) {
                return $type;
            }
        }

        return 0;
    }

    /** Takes one ship out of the village; false when none is left (nothing changes). */
    public static function take(int $kid, int $type): bool
    {
        $column = self::column($type);
        $db = DB::getInstance();
        $db->query("UPDATE units SET $column=$column-1 WHERE kid=$kid AND $column>=1");

        return $db->affectedRows() >= 1;
    }

    /** Puts a ship back, for a movement that failed after the ship was taken. */
    public static function restore(int $kid, int $type): void
    {
        $column = self::column($type);
        DB::getInstance()->query("UPDATE units SET $column=$column+1 WHERE kid=" . $kid);
    }

    /**
     * Seconds a delivery takes between two villages.
     *
     * Merchants walk at their tribe's speed. Trade ships sail the deep-water
     * part of the straight line at the fixed ship speed and walk the rest at the
     * tribe's merchant speed (article 25).
     */
    public static function tradeTravelSeconds(int $fromKid, int $toKid, int $tribe, bool $byShip): int
    {
        $landSpeed = Formulas::merchantSpeed($tribe);
        if (!$byShip) {
            return Formulas::travelTimeSeconds(Formulas::getDistance($fromKid, $toKid) / $landSpeed * 3600);
        }
        $distance = Formulas::getFlatDistance($fromKid, $toKid);
        $deep = $distance * \Game\Map\ShipRoute::deepFraction($fromKid, $toKid);

        return Formulas::travelTimeSeconds($deep / self::deepWaterSpeed(self::TRADE, $tribe) * 3600 + ($distance - $deep) / $landSpeed * 3600);
    }

    /** Docks trade ships that finished a delivery; they are lost when their Harbor is gone. */
    public static function dockTradeShips(int $kid, int $ships): void
    {
        if ($ships > 0 && self::hasHarbor($kid)) {
            DB::getInstance()->query("UPDATE units SET ship1=ship1+$ships WHERE kid=" . $kid);
        }
    }

    /**
     * Boards a raid on a ship when the farm-list entry asks for one and a ship fits.
     * Without a fitting ship the raid walks, as article 198 says. Resets the
     * calculator, which a list reuses for every target.
     *
     * @return int the ship type taken, or 0
     */
    public static function boardRaid(int $kid, bool $useShip, int $unitCount, SpeedCalculator $calculator, int $tribe): int
    {
        $calculator->setShip(0, 0);
        if (!$useShip) {
            return 0;
        }
        $type = self::pickForArmy($kid, 4, $unitCount);
        if ($type === 0 || !self::take($kid, $type)) {
            return 0;
        }
        $calculator->setShip($type, $tribe);

        return $type;
    }
}
