<?php

declare(strict_types=1);

namespace Game;

/**
 * wdata.oasistype values (0 = not an oasis). Source: the 'oases' effect table in
 * Formulas::load() (type => [resource => 25% steps]) and OasesModel::ANIMALS_BY_TYPE.
 * Types 1, 5, 9 and 13 are unused.
 */
final class OasisType
{
    public const WOOD_25 = 2;
    public const WOOD_25_CROP_25 = 3;
    public const WOOD_50 = 4;
    public const CLAY_25 = 6;
    public const CLAY_25_CROP_25 = 7;
    public const CLAY_50 = 8;
    public const IRON_25 = 10;
    public const IRON_25_CROP_25 = 11;
    public const IRON_50 = 12;
    public const CROP_25 = 14;
    public const CROP_50 = 15;

    /** Wood oases, which the map draws on forest tiles. */
    public const WOOD = [self::WOOD_25, self::WOOD_25_CROP_25, self::WOOD_50];
}
