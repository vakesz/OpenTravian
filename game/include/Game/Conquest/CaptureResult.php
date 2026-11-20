<?php

declare(strict_types=1);

namespace Game\Conquest;

/**
 * BattleModel::$info['captureResult'] for a chief attack (stored in the report, rendered by
 * ReportsCtrl). Any other value is a [before, after] loyalty pair.
 */
final class CaptureResult
{
    public const CAPTURED = 0;
    /** The target is the defender's capital. */
    public const CAPITAL = -1;
    public const NOT_ENOUGH_CULTURE_POINTS = -2;
    /** A residence, palace or command center still stands. */
    public const RESIDENCE_OR_PALACE_STANDS = -3;
    /** allowOnlyOneWWVillagePerAccount: the attacker already holds a WW village. */
    public const ONE_WONDER_LIMIT = -4;
    /** The attacker's alliance has a NAP with the defender's (LoyaltyProtection::NAP). */
    public const NAP = -5;
    /** The attacking village has no free expansion slot. */
    public const NO_EXPANSION_SLOT = -6;
}
