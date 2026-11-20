<?php

declare(strict_types=1);

namespace Game;

/**
 * Unit numbers within a tribe (the movement/units/enforcement column uN, 1-based) and the
 * special global unit ids. Global id of a tribe unit = (tribe - 1) * 10 + nr (nrToUnitId()).
 * Numbers 9 and 10 are the chief and settler for player tribes; for Nature (tribe 4) units
 * 1-10 are animals and there is no hero.
 * Source: nrToUnitId()/unitIdToNr() in functions.general.php, TrainingHelper, Formulas::uTrainingCost().
 */
final class Unit
{
    public const RAM = 7;
    public const CATAPULT = 8;
    /** Senator, chief, chieftain, nomarch, logades, ephor or jarl. */
    public const CHIEF = 9;
    public const SETTLER = 10;
    /** The hero's column, u11. */
    public const HERO = 11;
    /** The hero's global unit id (nrToUnitId(11, any tribe), training item_id). */
    public const HERO_ID = 98;
    /** Gaul trapper traps: number and global id 99, column u99. */
    public const TRAP = 99;
}
