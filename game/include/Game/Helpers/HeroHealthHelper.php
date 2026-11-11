<?php

namespace Game\Helpers;

use Core\Config;
use Core\Database\DB;
use Game\Hero\HeroHelper;

class HeroHealthHelper
{
    /**
     * @param int $uid
     */
    public static function updateUserHeroHealth($uid): void
    {
        $now = time();
        $db = DB::getInstance();
        $result = $db->query("SELECT uid, health, lastupdate, itemHealth FROM hero WHERE uid=$uid AND health>0");
        $helper = new HeroHelper();
        while ($row = $result->fetch_assoc()) {
            $healthRate = ($helper->calcHealth() + $row['itemHealth'])
                * max(1, (float)Config::getProperty('game', 'heroRegenerationMultiplier'));
            self::updateHeroHealth($row['uid'],
                min($row['health'] + $healthRate / 86400 * ($now - $row['lastupdate']), 100),
                time());
        }
    }

    /**
     * @param mixed $uid
     * @return mixed
     */
    public static function getHeroItemHealth($uid)
    {
        $helper = new HeroHelper();
        $db = DB::getInstance();
        $inventory = $db->query("SELECT helmet, body, shoes FROM inventory WHERE uid=$uid");
        if (!$inventory->num_rows) {
            return false;
        }
        $inventory = $inventory->fetch_assoc();
        return $helper->calcItemHealth($inventory['helmet'], $inventory['body'], $inventory['shoes']);
    }

    /**
     * @param mixed $uid
     * @param float|int $health
     * @param int $time
     */
    public static function updateHeroHealth($uid, $health, $time): void
    {
        $db = DB::getInstance();
        $db->query("UPDATE hero SET health=$health, lastupdate=$time WHERE uid=$uid AND health>0 AND health<100");
    }
}
