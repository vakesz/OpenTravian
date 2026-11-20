<?php

declare(strict_types=1);

namespace Game;

/**
 * The fixed users.id values the installer creates and the "owner" ids the engine uses for
 * them. InstallerModel::finalize() creates Support, Natars and Multihunter (in that order)
 * and then renumbers them to 0, 1 and 2; every real player has an id from 3 up.
 */
final class UserId
{
    /** "TG Support" (users.id 0): built-in admin and the sender of system messages; mail to it goes to Multihunter. */
    public const SUPPORT = 0;
    /** Owner/uid of free oases and of Nature's animals (odata.owner = 0, units of race 4); shares 0 with SUPPORT. */
    public const NATURE = 0;
    /** The Natars account (users.id 1, race 5): Natar villages, WW villages, artefact holders. */
    public const NATARS = 1;
    /** The Multihunter account (users.id 2): built-in admin; users.access 0 keeps it off leaderboards. */
    public const MULTIHUNTER = 2;
    /** Highest system account: `$uid > UserId::LAST_SYSTEM` means a real player. */
    public const LAST_SYSTEM = self::MULTIHUNTER;
    /** Lowest player id: `$uid < UserId::FIRST_PLAYER` means a system account. */
    public const FIRST_PLAYER = 3;
    /**
     * Legacy (initial-commit) rule in ReinforcementProcessor: a hidden account with an id up to 10
     * does not accept reinforcements. Nothing reserves ids 3-10 today, so the rule only affects
     * whichever accounts got those ids.
     */
    public const LEGACY_HIDDEN_MAX = 10;
}
