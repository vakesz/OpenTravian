<?php

namespace Game\Helpers;

use Core\Database\DB;
use Core\Time;
use Game\Slot;

class LoyaltyHelper
{
    const UPDATE_DELAY = 600;

    /** Village loyalty: 2 points per building level every 3 hours. */
    public static function pointsPerHour(int $level): float
    {
        return $level * 2 / 3;
    }

    /**
     * @param mixed $kid
     */
    public static function updateVillageLoyalty($kid): void
    {
        $db = DB::getInstance();
        $result = $db->query("SELECT kid, loyalty, last_loyalty_update FROM vdata WHERE kid=$kid");
        while ($row = $result->fetch_assoc()) {
            if ($row['loyalty'] < 100) {
                $points = self::pointsPerHour(self::getVillageExpansionBuildings($row['kid']));
                $points /= Time::HOUR;
                $elapsedSeconds = time() - $row['last_loyalty_update'];
                $points *= $elapsedSeconds;
                $loyalty = min(100, $row['loyalty'] + $points);
                $db->query("UPDATE vdata SET loyalty=$loyalty, last_loyalty_update=" . time() . " WHERE kid={$row['kid']}");
            }
        }
    }

    /**
     * @param mixed $kid
     */
    public static function getVillageExpansionBuildings($kid): int
    {
        $db = DB::getInstance();
        $buildings = $db->query("SELECT * FROM fdata WHERE kid=$kid")->fetch_assoc();
        for ($i = Slot::FIRST_BUILDING; $i <= Slot::LAST_BUILDING; ++$i) {
            if ($buildings['f' . $i . 't'] == 25 || $buildings['f' . $i . 't'] == 26 || $buildings['f' . $i . 't'] == 44) {
                return (int)$buildings['f' . $i];
            }
        }
        return 0;
    }

    /**
     * @param mixed $uid
     */
    public static function updateUserVillagesLoyalty($uid): void
    {
        $db = DB::getInstance();
        $result = $db->query("SELECT kid, loyalty, last_loyalty_update FROM vdata WHERE owner=$uid");
        while ($row = $result->fetch_assoc()) {
            $loyalty = $row['loyalty'];
            if ($loyalty < 100) {
                $points = self::pointsPerHour(self::getVillageExpansionBuildings($row['kid']));
                $points /= Time::HOUR;
                $elapsedSeconds = time() - $row['last_loyalty_update'];
                $points *= $elapsedSeconds;
                $loyalty = min(100, $loyalty + $points);
            }
            $db->query("UPDATE vdata SET loyalty=$loyalty, last_loyalty_update=" . time() . " WHERE kid={$row['kid']}");
        }
    }

    /**
     * @param mixed $kid
     */
    public static function updateVillageOasesLoyalty($kid): void
    {
        $db = DB::getInstance();
        $result = $db->query("SELECT kid, did, loyalty, last_loyalty_update FROM odata WHERE owner>0 AND did=$kid");
        while ($row = $result->fetch_assoc()) {
            $loyalty = $row['loyalty'];
            if ($loyalty < 100) {
                $points = 2 / Time::HOUR;
                $elapsedSeconds = time() - $row['last_loyalty_update'];
                $points *= $elapsedSeconds;
                $loyalty = min(100, $row['loyalty'] + $points);
            }
            $db->query("UPDATE odata SET loyalty=$loyalty, last_loyalty_update=" . time() . " WHERE kid={$row['kid']}");
        }
    }

    /**
     * @param int $kid
     */
    public static function updateOasisLoyalty($kid): void
    {
        $db = DB::getInstance();
        $result = $db->query("SELECT kid, did, loyalty, last_loyalty_update FROM odata WHERE owner>0 AND kid=$kid");
        while ($row = $result->fetch_assoc()) {
            $points = 2 / Time::HOUR;
            $elapsedSeconds = time() - $row['last_loyalty_update'];
            $points *= $elapsedSeconds;
            $loyalty = min(100, $row['loyalty'] + $points);
            $db->query("UPDATE odata SET loyalty=$loyalty, last_loyalty_update=" . time() . " WHERE kid={$row['kid']}");
        }
    }
}