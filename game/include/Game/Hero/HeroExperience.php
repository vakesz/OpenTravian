<?php

declare(strict_types=1);

namespace Game\Hero;

use Core\Database\DB;
use Game\Formulas;

/**
 * Grants hero experience and applies the official level-up heal.
 *
 * Travian support ("Hero Health and Revival", "Hero Experience", read 2026-11-10): when a hero
 * levels up they are fully healed; a hero that dies in the same action is not revived by the level up.
 * Callers must apply lethal or non-lethal damage before granting experience so a dead hero keeps health 0.
 */
final class HeroExperience
{
    private function __construct()
    {
    }

    /** Adds experience; if the hero gains one or more levels and is still alive, sets health to 100%. */
    public static function add(int $uid, int $amount): bool
    {
        if ($uid < 1 || $amount < 1) {
            return false;
        }
        $db = DB::getInstance();
        $row = $db->query("SELECT exp, health FROM hero WHERE uid={$uid} FOR UPDATE")->fetch_assoc();
        if (!$row) {
            return false;
        }
        $before = Formulas::heroLevel((int)$row['exp']);
        if ($db->query("UPDATE hero SET exp=exp+{$amount} WHERE uid={$uid}") === false) {
            return false;
        }
        $after = Formulas::heroLevel((int)$row['exp'] + $amount);
        if ($after > $before && (float)$row['health'] > 0) {
            $db->query('UPDATE hero SET health=100, lastupdate=' . time() . " WHERE uid={$uid} AND health>0");
        }

        return true;
    }
}
