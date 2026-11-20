<?php

declare(strict_types=1);

namespace Game;

/**
 * Building type ids (gid), stored as fdata `fNt`, building_upgrade/demolition `item_id`
 * and the `gid` URL parameter. Source of truth: Formulas::$data['buildings'], where the
 * entry at key gid - 1 carries 'gid' => gid and the English 'name' these constants are named
 * after (tests/contracts/test-domain-constants-contract.php checks both).
 * Gid 0 means an empty slot and has no constant.
 */
final class Gid
{
    public const WOODCUTTER = 1;
    public const CLAY_PIT = 2;
    public const IRON_MINE = 3;
    public const CROPLAND = 4;
    public const SAWMILL = 5;
    public const BRICKYARD = 6;
    public const IRON_FOUNDRY = 7;
    public const GRAIN_MILL = 8;
    public const BAKERY = 9;
    public const WAREHOUSE = 10;
    public const GRANARY = 11;
    public const BLACKSMITH = 12;
    public const SMITHY = 13;
    public const TOURNAMENT_SQUARE = 14;
    public const MAIN_BUILDING = 15;
    public const RALLY_POINT = 16;
    public const MARKETPLACE = 17;
    public const EMBASSY = 18;
    public const BARRACKS = 19;
    public const STABLE = 20;
    public const WORKSHOP = 21;
    public const ACADEMY = 22;
    public const CRANNY = 23;
    public const TOWN_HALL = 24;
    public const RESIDENCE = 25;
    public const PALACE = 26;
    public const TREASURY = 27;
    public const TRADE_OFFICE = 28;
    public const GREAT_BARRACKS = 29;
    public const GREAT_STABLE = 30;
    public const CITY_WALL = 31;
    public const EARTH_WALL = 32;
    public const PALISADE = 33;
    public const STONEMASON = 34;
    public const BREWERY = 35;
    public const TRAPPER = 36;
    public const HEROS_MANSION = 37;
    public const GREAT_WAREHOUSE = 38;
    public const GREAT_GRANARY = 39;
    public const WONDER_OF_THE_WORLD = 40;
    public const HORSE_DRINKING_TROUGH = 41;
    public const STONE_WALL = 42;
    public const MAKESHIFT_WALL = 43;
    public const COMMAND_CENTER = 44;
    public const WATERWORKS = 45;
    public const HOSPITAL = 46;
    public const DEFENSIVE_WALL = 47;
    public const ASCLEPEION = 48;
    public const HARBOR = 49;
    public const BARRICADE = 50;

    /** Highest resource-field gid: `$gid <= Gid::LAST_RESOURCE_FIELD` means woodcutter..cropland. */
    public const LAST_RESOURCE_FIELD = self::CROPLAND;

    /** Every tribe's wall (Formulas::WALL_BUILDINGS is an alias). */
    public const WALLS = [self::CITY_WALL, self::EARTH_WALL, self::PALISADE, self::STONE_WALL, self::MAKESHIFT_WALL, self::DEFENSIVE_WALL, self::BARRICADE];

    /** Buildings that grant expansion slots and train settlers and chiefs. */
    public const EXPANSION = [self::RESIDENCE, self::PALACE, self::COMMAND_CENTER];
}
