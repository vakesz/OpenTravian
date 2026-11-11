<?php

declare(strict_types=1);

namespace Game\Hero;

use Core\Database\DB;

/**
 * Ointments equipped in the hero's bag slot heal the hero right after a battle, at one health point each, and are
 * used up doing so. Ointments left in the inventory are never used automatically.
 *
 * Source: Travian's support article on hero consumable items (read 2026-11-08): "Equip: the ointments wait in the
 * bag slot and heal non-lethal damage right after the hero's next battle". The article does not say whether damage
 * from before the battle is healed as well; only the health this battle took off is, so the hero never ends above
 * the health it brought into the battle. A hero that died gets nothing (the damage is lethal).
 */
final class BagOintment
{
    private const OINTMENT_BASE_TYPE = 11;

    private function __construct()
    {
    }

    /**
     * @param int|float $lost the health points the battle took off the surviving hero
     * @return int the number of ointments used
     */
    public static function healAfterBattle(int $uid, int|float $lost): int
    {
        $wanted = (int)ceil($lost);
        if ($wanted <= 0) {
            return 0;
        }
        $db = DB::getInstance();
        $bag = $db->run(
            'SELECT i.id, i.num FROM inventory v JOIN items i ON i.id=v.bag AND i.uid=v.uid '
            . 'WHERE v.uid=? AND i.btype=? AND i.proc=1',
            [$uid, self::OINTMENT_BASE_TYPE]
        );
        $stack = $bag ? $bag->get_result()->fetch_assoc() : null;
        if (!$stack) {
            return 0;
        }
        $health = (float)$db->fetchScalar('SELECT health FROM hero WHERE uid=?', [$uid], 0);
        if ($health <= 0) {
            return 0;
        }
        $used = min((int)$stack['num'], $wanted, (int)ceil(100 - $health));
        if ($used <= 0) {
            return 0;
        }
        if (!$db->run('UPDATE hero SET health=LEAST(100, health+?) WHERE uid=? AND health>0', [$used, $uid])) {
            return 0;
        }
        if ($used >= (int)$stack['num']) {
            $db->run('DELETE FROM items WHERE id=? AND uid=?', [(int)$stack['id'], $uid]);
            $db->run('UPDATE inventory SET bag=0 WHERE uid=? AND bag=?', [$uid, (int)$stack['id']]);
        } else {
            $db->run('UPDATE items SET num=num-? WHERE id=? AND uid=?', [$used, (int)$stack['id'], $uid]);
        }
        HeroImage::forget($uid);

        return $used;
    }
}
