<?php

declare(strict_types=1);

namespace Game;

/**
 * Village building slots (fdata fN/fNt, building_upgrade.building_field, the build.php `id`).
 * Source: Village::getQueueRange ([1, 18] fields, [19, 99] buildings), Village::canBuild
 * (40 wall, 99 WW, 39 rally point), BuildCtrl, VillageCenterCtrl, RegisterModel (f39t rally point).
 */
final class Slot
{
    /** Resource fields (dorf1) are slots FIRST_FIELD..LAST_FIELD. */
    public const FIRST_FIELD = 1;
    public const LAST_FIELD = 18;
    /** Free building slots in the village centre (dorf2). */
    public const FIRST_BUILDING = 19;
    public const LAST_BUILDING = 38;
    public const RALLY_POINT = 39;
    public const WALL = 40;
    /** Last regular slot: `$i <= Slot::LAST` walks 1..40. */
    public const LAST = self::WALL;
    /** The Wonder of the World slot (fdata f99) in WW villages. */
    public const WONDER = 99;
}
