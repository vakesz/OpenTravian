<?php

namespace Game\Helpers;

use Core\Database\DB;
use Game\AllianceBonus\AllianceBonus;
use Game\Hero\HeroHelper;

class CulturePointsHelper
{
    /**
     * @param mixed $uid
     */
    public static function updateUserCP($uid): void
    {
        $db = DB::getInstance();
        $result = $db->query("SELECT id, aid, alliance_join_time, lastupdate, cp_prod FROM users WHERE id=$uid");
        while ($row = $result->fetch_assoc()) {
            $cp_prod = $row['cp_prod'] + self::getHeroCPProduction($row['id']);
            if ($row['aid'] > 0) {
                $cp_prod *= AllianceBonus::getCulturePointProductionBonus($row['aid'], $row['alliance_join_time']);
            }
            $cp = $cp_prod / 86400 * (time() - $row['lastupdate']);
            self::increaseUserCP(time(), $row['id'], $cp);
        }
    }

    /**
     * @param mixed $uid
     * @return (float|int)
     */
    public static function getHeroCPProduction($uid)
    {
        $cp_prod = 0;
        $helper = new HeroHelper();
        $db = DB::getInstance();
        $hero = $db->query("SELECT uid FROM hero WHERE health>0 AND uid=$uid");
        if ($hero->num_rows) {
            $inventory = $db->query("SELECT helmet FROM inventory WHERE uid=$uid");
            if ($inventory->num_rows) {
                $inventory = $inventory->fetch_assoc();
                $cp_prod += $helper->calcMoreCulturePoints($inventory['helmet']);
            }
        }
        return $cp_prod;
    }

    /**
     * @param int $time
     * @param mixed $uid
     * @param (float|int) $cp
     */
    public static function increaseUserCP($time, $uid, $cp): void
    {
        $db = DB::getInstance();
        $db->query("UPDATE users SET cp=cp+$cp, lastupdate=$time WHERE id=$uid");
    }
}