<?php

namespace Game\Helpers;

use Core\Database\DB;

class LoyaltyHelper
{
    const UPDATE_DELAY = 600;

    /**
     * @param mixed $kid
     */
    public static function updateVillageLoyalty($kid): void
    {
        $db = DB::getInstance();
        $result = $db->query("SELECT kid, loyalty, last_loyalty_update FROM vdata WHERE kid=$kid");
        while ($row = $result->fetch_assoc()) {
            if ($row['loyalty'] < 100) {
                //points per hour
                $points = self::getVillageExpansionBuildings($row['kid']);
                //points per second
                $points /= 3600;
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
        for ($i = 19; $i <= 38; ++$i) {
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
                //points per hour
                $points = self::getVillageExpansionBuildings($row['kid']);
                //points per second
                $points /= 3600;
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
                //points per hour
                $points = self::getVillageExpansionBuildings($row['did']);
                //points per second
                $points /= 3600;
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
            //points per hour
            $points = self::getVillageExpansionBuildings($row['did']);
            //points per second
            $points /= 3600;
            $elapsedSeconds = time() - $row['last_loyalty_update'];
            $points *= $elapsedSeconds;
            $loyalty = min(100, $row['loyalty'] + $points);
            $db->query("UPDATE odata SET loyalty=$loyalty, last_loyalty_update=" . time() . " WHERE kid={$row['kid']}");
        }
    }
}