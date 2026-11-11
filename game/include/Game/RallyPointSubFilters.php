<?php

declare(strict_types=1);

namespace Game;

/**
 * The Rally Point overview's sub-filter selection, stored as a comma separated
 * list of the active sub-filter ids (a cookie and the `subfilters` query value).
 */
final class RallyPointSubFilters
{
    /**
     * @param array<int, int|bool> $subFilters sub-filter id => active flag
     */
    public static function implode(array $subFilters): string
    {
        $active = [];
        foreach ($subFilters as $subFilterId => $subFilterActive) {
            if ($subFilterActive) {
                $active[] = $subFilterId;
            }
        }

        return implode(',', $active);
    }

    /**
     * The list that results from flipping one sub-filter.
     *
     * @param array<int, int|bool> $subFilters
     */
    public static function toggle(int $id, array $subFilters): string
    {
        $subFilters[$id] = empty($subFilters[$id]) ? 1 : 0;

        return self::implode($subFilters);
    }
}
