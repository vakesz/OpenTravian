<?php

declare(strict_types=1);

namespace Game;

use Model\ArtefactsModel;

/**
 * The buildings a catapult wave may aim at, by Rally Point level: the one definition the send form lists and the
 * send request accepts. 99 (random) is always allowed and is not part of the groups.
 */
final class CatapultTargets
{
    private const RESOURCES = [Gid::WOODCUTTER, Gid::CLAY_PIT, Gid::IRON_MINE, Gid::CROPLAND, Gid::SAWMILL, Gid::BRICKYARD, Gid::IRON_FOUNDRY, Gid::GRAIN_MILL, Gid::BAKERY];
    private const INFRASTRUCTURE = [Gid::WAREHOUSE, Gid::GRANARY, Gid::MAIN_BUILDING, Gid::MARKETPLACE, Gid::EMBASSY, Gid::TOWN_HALL, Gid::RESIDENCE, Gid::PALACE, Gid::COMMAND_CENTER, Gid::TREASURY, Gid::TRADE_OFFICE, Gid::GREAT_WAREHOUSE, Gid::GREAT_GRANARY, Gid::HORSE_DRINKING_TROUGH, Gid::WATERWORKS, Gid::HARBOR];
    private const MILITARY = [Gid::SMITHY, Gid::TOURNAMENT_SQUARE, Gid::RALLY_POINT, Gid::BARRACKS, Gid::STABLE, Gid::WORKSHOP, Gid::ACADEMY, Gid::GREAT_BARRACKS, Gid::GREAT_STABLE, Gid::BREWERY, Gid::HEROS_MANSION, Gid::HOSPITAL, Gid::ASCLEPEION];
    private const WORLD_WONDER = Gid::WONDER_OF_THE_WORLD;

    /**
     * Group names are the `Buildings.newBuilding.*` translation suffixes, in display order.
     *
     * @return array<string, list<int>>
     */
    public static function groups(int $rallyPointLevel, ?bool $wonderPlansReleased = null): array
    {
        $groups = ['Resources' => [], 'Infrastructure' => [], 'Military' => []];
        if ($rallyPointLevel >= 3) {
            $groups['Infrastructure'] = [Gid::WAREHOUSE, Gid::GRANARY];
        }
        if ($rallyPointLevel >= 5) {
            $groups['Resources'] = self::RESOURCES;
        }
        if ($rallyPointLevel >= 10) {
            $groups['Infrastructure'] = self::INFRASTRUCTURE;
            if ($wonderPlansReleased ?? ArtefactsModel::wwPlansReleased()) {
                $groups['Infrastructure'][] = self::WORLD_WONDER;
            }
            $groups['Military'] = self::MILITARY;
        }

        return $groups;
    }

    /**
     * @return list<int>
     */
    public static function allowed(int $rallyPointLevel, ?bool $wonderPlansReleased = null): array
    {
        return array_merge([99], ...array_values(self::groups($rallyPointLevel, $wonderPlansReleased)));
    }

    /**
     * The building a catapult actually aims at: random (99) during the attacker's Brewery celebration, and under a
     * random-aim Cranny artefact unless the requested building is exempt (World Wonder always; Treasury too for the
     * small and large artefact, size 1 and 2).
     *
     * @param int $crannyArtefactSize size of the defender's random-aim artefact, 0 when none
     */
    public static function aim(int $requestedGid, bool $breweryCelebration, int $crannyArtefactSize): int
    {
        if ($breweryCelebration) {
            return 99;
        }
        if ($crannyArtefactSize === 0) {
            return $requestedGid;
        }
        $exempt = $crannyArtefactSize < 3 ? [Gid::TREASURY, self::WORLD_WONDER] : [self::WORLD_WONDER];

        return in_array($requestedGid, $exempt, true) ? $requestedGid : 99;
    }
}
