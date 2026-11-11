<?php

namespace Game\AllianceBonus;

use Core\Database\DB;
use Game\Formulas;

class AllianceBonus
{
    private static function availableLevel(int $level, int $joinTime): int
    {
        while ($level > 1 && time() - $joinTime < Formulas::getAllianceBonusCoolDownForNewPlayers($level)) {
            --$level;
        }
        return $level;
    }

    /**
     * @param mixed $aid
     * @param mixed $joinTime
     * @return (float|int)|int
     */
    public static function getTrainingBonus($aid, $joinTime)
    {
        if ($aid > 0) {
            $db = DB::getInstance();
            $level = $db->fetchScalar("SELECT training_bonus_level FROM alidata WHERE id=$aid");
            $level = self::availableLevel($level, $joinTime);
            return 1 - ($level * 2 / 100);
        }
        return 1;
    }

    /**
     * @param mixed $aid
     * @param mixed $joinTime
     * @return float|int
     */
    public static function getCulturePointProductionBonus($aid, $joinTime)
    {
        if ($aid > 0) {
            $db = DB::getInstance();
            $level = $db->fetchScalar("SELECT cp_bonus_level FROM alidata WHERE id=$aid");
            return self::culturePointProductionMultiplier((int)$level, (int)$joinTime);
        }

        return 1;
    }

    /**
     * The dashboard ranks every public player at once, so it receives the
     * already-loaded bonus level rather than issuing one alliance query per
     * player. Keep its cooldown calculation identical to the live CP ticker.
     */
    public static function culturePointProductionMultiplier(int $level, int $joinTime): float
    {
        return 1 + (self::availableLevel(max(0, $level), $joinTime) * 4 / 100);
    }

    /**
     * @param mixed $aid
     * @param mixed $joinTime
     * @return (float|int)|int
     */
    public static function getTradersBonus($aid, $joinTime)
    {
        if ($aid > 0) {
            $db = DB::getInstance();
            $level = $db->fetchScalar("SELECT trade_bonus_level FROM alidata WHERE id=$aid");
            $level = self::availableLevel($level, $joinTime);
            return 1 + ($level * 30 / 100);
        }

        return 1;
    }

    /**
     * @param int $aid
     * @param int $joinTime
     * @return (float|int)|int
     */
    public static function getArmorBonus($aid, $joinTime)
    {
        if ($aid > 0) {
            $db = DB::getInstance();
            $level = $db->fetchScalar("SELECT armor_bonus_level FROM alidata WHERE id=$aid");
            $level = self::availableLevel($level, $joinTime);
            return 1 + ($level * 2 / 100);
        }
        return 1;
    }
}
