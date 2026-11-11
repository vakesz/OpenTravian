<?php

declare(strict_types=1);

namespace Game\Conquest;

use Core\Database\DB;

/**
 * Who may not lower a village's loyalty. Administrators of the owner's own alliance or of a confederate alliance
 * cannot, unless the village holds an artefact or its owner allowed it; administrators of an alliance that has a
 * NAP with the owner's alliance cannot at all.
 */
final class LoyaltyProtection
{
    public const NAP = 'nap';
    public const ALLIES = 'allies';

    private const DIPLOMACY_CONFEDERACY = 1;
    private const DIPLOMACY_NAP = 2;

    /**
     * @return self::NAP|self::ALLIES|null the reason the attacker cannot lower the loyalty, or null when it can
     */
    public static function blockedBy(int $attackerUid, int $defenderUid, int $villageKid): ?string
    {
        if ($attackerUid === $defenderUid) {
            return null;
        }
        $db = DB::getInstance();
        $attackerAid = (int) $db->fetchScalar('SELECT aid FROM users WHERE id=?', [$attackerUid], 0);
        $defenderAid = (int) $db->fetchScalar('SELECT aid FROM users WHERE id=?', [$defenderUid], 0);
        if ($attackerAid === 0 || $defenderAid === 0) {
            return null;
        }
        if (self::hasDiplomacy($attackerAid, $defenderAid, self::DIPLOMACY_NAP)) {
            return self::NAP;
        }
        $friendly = $attackerAid === $defenderAid || self::hasDiplomacy($attackerAid, $defenderAid, self::DIPLOMACY_CONFEDERACY);
        if (!$friendly) {
            return null;
        }
        $hasArtefact = (int) $db->fetchScalar('SELECT COUNT(id) FROM artefacts WHERE kid=?', [$villageKid], 0) > 0;
        $allowed = (int) $db->fetchScalar('SELECT allowLoyaltyDecrease FROM vdata WHERE kid=?', [$villageKid], 0) === 1;

        return $hasArtefact || $allowed ? null : self::ALLIES;
    }

    private static function hasDiplomacy(int $aid, int $otherAid, int $type): bool
    {
        return (int) DB::getInstance()->fetchScalar(
            'SELECT COUNT(id) FROM diplomacy WHERE accepted=1 AND type=? AND ((aid1=? AND aid2=?) OR (aid1=? AND aid2=?))',
            [$type, $aid, $otherAid, $otherAid, $aid],
            0
        ) > 0;
    }
}
