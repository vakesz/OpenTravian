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
    private const RESOURCES = [1, 2, 3, 4, 5, 6, 7, 8, 9];
    private const INFRASTRUCTURE = [10, 11, 15, 17, 18, 24, 25, 26, 44, 27, 28, 38, 39, 41, 45];
    private const MILITARY = [13, 14, 16, 19, 20, 21, 22, 29, 30, 35, 37];
    private const WORLD_WONDER = 40;

    /**
     * Group names are the `Buildings.newBuilding.*` translation suffixes, in display order.
     *
     * @return array<string, list<int>>
     */
    public static function groups(int $rallyPointLevel, ?bool $wonderPlansReleased = null): array
    {
        $groups = ['Resources' => [], 'Infrastructure' => [], 'Military' => []];
        if ($rallyPointLevel >= 3) {
            $groups['Infrastructure'] = [10, 11];
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
        $exempt = $crannyArtefactSize < 3 ? [27, self::WORLD_WONDER] : [self::WORLD_WONDER];

        return in_array($requestedGid, $exempt, true) ? $requestedGid : 99;
    }
}
