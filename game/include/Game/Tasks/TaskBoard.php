<?php

declare(strict_types=1);

namespace Game\Tasks;

use Model\TasksModel;

use function T;

/**
 * Builds the grouped, ordered task-board rows shared by the /tasks page and the
 * progressive-tasks reload endpoint, and maps reference quest types onto the
 * local (key, milestone) task identity.
 */
final class TaskBoard
{
    public const TASKS_PER_PAGE = 7;

    /** Task image tribe classes (Gpack img_ltr/themes/default/buildings/<tribe>/). */
    private const TRIBES = [1 => 'roman', 2 => 'teuton', 3 => 'gaul', 6 => 'egyptian', 7 => 'hun', 8 => 'spartan', 9 => 'viking'];

    /** @var array<int, array{allLabel: string, fieldLabel: string, description: string}> */
    private const FIELD_TASKS = [
        1 => ['allLabel' => 'allWoodcutters', 'fieldLabel' => 'fieldWoodcutters', 'description' => 'woodcuttersInfo'],
        2 => ['allLabel' => 'allClayPits', 'fieldLabel' => 'fieldClayPits', 'description' => 'clayPitsInfo'],
        3 => ['allLabel' => 'allIronMines', 'fieldLabel' => 'fieldIronMines', 'description' => 'ironMinesInfo'],
        4 => ['allLabel' => 'allCroplands', 'fieldLabel' => 'fieldCroplands', 'description' => 'cropFieldsInfo'],
    ];

    /** @var array<int, string> */
    private const BUILDING_DESCRIPTION_KEYS = [
        8 => 'millInfo',
        10 => 'warehouseInfo',
        11 => 'granaryInfo',
        15 => 'mainBuildingInfo',
        16 => 'rallyPointInfo',
        17 => 'marketInfo',
        18 => 'embassyInfo',
        19 => 'barracksInfo',
        23 => 'crannyInfo',
        25 => 'residenceInfo',
        31 => 'cityWallInfo',
        32 => 'cityWallInfo',
        33 => 'cityWallInfo',
        42 => 'cityWallInfo',
        43 => 'cityWallInfo',
        47 => 'cityWallInfo',
        50 => 'cityWallInfo',
    ];

    public static function tribeClass(int $race): string
    {
        return self::TRIBES[$race] ?? 'roman';
    }

    /**
     * Reference task type token (`task ... group <type>`).
     *
     * @param array<mixed>|array<string, bool|int|string> $task
     */
    public static function referenceType(array $task): string
    {
        $key = (string)$task['key'];
        return match ((string)$task['type']) {
            'building' => 'buildingProgress',
            'resourceBuildingsAllLevels' => 'allResourceBuildingsHaveLevel',
            'resourceBuildingsLevelOfOneOfTheBuildings' => str_starts_with($key, 'field-')
                ? 'resourceBuildingsFirstLevel'
                : 'resourceBuildingsLevelOfOneOfTheBuildings',
            default => (string)$task['type'],
        };
    }

    /**
     * @param array<mixed>|array<string, bool|int|string> $task
     */
    public static function buildingId(array $task): ?int
    {
        return preg_match('/^g([0-9]{1,2})$/', (string)$task['image'], $m) === 1 ? (int)$m[1] : null;
    }

    /**
     * `summer ...` classes of the task artwork, exactly as the reference emits them.
     *
     * @param array<mixed, mixed>|array<mixed> $task
     */
    public static function imageClass(array $task, string $tribe, string $context = 'group'): string
    {
        $buildingId = self::buildingId($task);
        if ($buildingId !== null) {
            $class = ' building g' . $buildingId . ' ' . $tribe;
            if (in_array($context, ['level', 'description'], true)
                && in_array(self::referenceType($task), [
                    'buildingProgress',
                    'resourceBuildingsFirstLevel',
                    'allResourceBuildingsHaveLevel',
                ], true)) {
                $class .= ' big';
            }
            return $class;
        }
        // The task keeps the reference type token, but the Gpack only draws the all-resources artwork under
        // `resourceBuildingsAllLevels`; the reference no longer lists this task, so its own class is unreadable.
        $type = self::referenceType($task);
        return $type === 'allResourceBuildingsHaveLevel' ? 'resourceBuildingsAllLevels' : $type;
    }

    /**
     * The reference detail state keeps every claimed or claimable milestone,
     * plus the first future milestone. It presents collectable/open milestones
     * in ascending level order and the completed history newest first.
     *
     * @return list<array>
     * @param array<mixed> $group
     */
    public static function detailMilestones(array $group): array
    {
        $details = [];
        $includedFutureMilestone = false;
        foreach (($group['milestones'] ?? []) as $milestone) {
            if (!is_array($milestone)) {
                continue;
            }
            if (!empty($milestone['claimed']) || !empty($milestone['eligible'])) {
                $details[] = $milestone;
                continue;
            }
            if (!$includedFutureMilestone) {
                $details[] = $milestone;
                $includedFutureMilestone = true;
            }
        }

        usort($details, static function (array $left, array $right): int {
            $state = static function (array $milestone): int {
                if (!empty($milestone['eligible']) && empty($milestone['claimed'])) {
                    return 1;
                }
                return !empty($milestone['claimed']) ? 3 : 2;
            };
            $leftState = $state($left);
            $rightState = $state($right);
            if ($leftState !== $rightState) {
                return $leftState <=> $rightState;
            }

            $leftLevel = (int)($left['milestone'] ?? 0);
            $rightLevel = (int)($right['milestone'] ?? 0);
            return $leftState === 3
                ? $rightLevel <=> $leftLevel
                : $leftLevel <=> $rightLevel;
        });

        return $details;
    }

    /**
     * Resolves the exact title for one task detail milestone.
     *
     * @param array<mixed> $group
     * @param array<mixed> $milestone
     */
    public static function detailTitle(array $group, array $milestone): string
    {
        $key = (string)($group['key'] ?? '');
        $target = max(1, (int)($milestone['milestone'] ?? 1));
        if ($key === TasksModel::FREE_GOLD_KEY) {
            return T('Tasks', 'freeGold');
        }

        $buildingId = self::buildingId($milestone);
        if ($buildingId !== null) {
            $building = T('Buildings', $buildingId . '.title');
            return $target === 1
                ? sprintf(T('Tasks', 'buildLevel'), $building, $target)
                : sprintf(T('Tasks', 'upgradeToLevel'), $building, $target);
        }

        if (preg_match('/^all-fields-([1-4])$/', $key, $match) === 1) {
            $field = self::FIELD_TASKS[(int)$match[1]];
            return sprintf(T('Tasks', 'allBuildingsOfLevel'), T('Tasks', $field['allLabel']), $target);
        }
        if (preg_match('/^field-([1-4])$/', $key, $match) === 1) {
            $building = T('Buildings', $match[1] . '.title');
            return sprintf(T('Tasks', 'oneBuildingOfLevel'), $building, $target);
        }

        return match ($key) {
            'population', 'general-population' => sprintf(T('Tasks', 'reachPopulation'), $target),
            'culture', 'general-culture' => sprintf(T('Tasks', 'reachCpProduction'), $target),
            'even-growth' => sprintf(T('Tasks', 'evenGrowthTitle'), $target),
            'all-resources' => sprintf(T('Tasks', 'completeEconomyTitle'), $target),
            default => (string)($group['label'] ?? ''),
        };
    }

    /**
     * Resolves the exact explanatory copy for the active task group.
     *
     * @param array<mixed> $group
     */
    public static function detailDescription(array $group): string
    {
        $key = (string)($group['key'] ?? '');
        if ($key === TasksModel::FREE_GOLD_KEY) {
            return sprintf(T('Tasks', 'freeGoldInfo'), TasksModel::FREE_GOLD_REWARD_GOLD);
        }

        $buildingId = self::buildingId($group['current'] ?? $group);
        if ($buildingId !== null && isset(self::BUILDING_DESCRIPTION_KEYS[$buildingId])) {
            return T('Tasks', self::BUILDING_DESCRIPTION_KEYS[$buildingId]);
        }

        if (preg_match('/^(?:all-fields|field)-([1-4])$/', $key, $match) === 1) {
            return T('Tasks', self::FIELD_TASKS[(int)$match[1]]['description']);
        }

        return match ($key) {
            'population', 'general-population' => T('Tasks', 'populationInfo'),
            'culture', 'general-culture' => T('Tasks', 'culturePointsProductionInfo'),
            'even-growth' => T('Tasks', 'evenGrowthInfo'),
            'all-resources' => T('Tasks', 'completeEconomyInfo'),
            default => '',
        };
    }

    /**
     * Ordered groups: claimable first, then open, then collected. The current
     * reference comparator alphabetizes claimable/collected cards; within open
     * cards, always-on-top entries precede remaining-progress/title sorting.
     *
     * @return array<mixed>
     */
    public static function groups(TasksModel $model, int $uid, string $scope, int $kid): array
    {
        $groups = [];
        foreach ($model->getTasks($uid, $scope, $kid) as $task) {
            $key = (string)$task['key'];
            $groups[$key] ??= [
                'key' => $key,
                'label' => (string)$task['label'],
                'type' => (string)$task['type'],
                'image' => (string)$task['image'],
                'milestones' => [],
            ];
            $task['reward'] = $model->rewardFor($task, $uid);
            $groups[$key]['milestones'][] = $task;
        }

        $achieved = $open = $collected = [];
        foreach ($groups as $key => $group) {
            $group['current'] = null;
            foreach ($group['milestones'] as $milestone) {
                if (!$milestone['claimed']) {
                    $group['current'] = $milestone;
                    break;
                }
            }
            $group['completed'] = $group['current'] === null;
            $group['current'] ??= end($group['milestones']) ?: null;
            $group['label'] = self::label($group);

            if ($group['completed']) {
                $collected[$key] = $group;
            } elseif (!empty($group['current']['eligible'])) {
                $achieved[$key] = $group;
            } else {
                $open[$key] = $group;
            }
        }

        $sortByTitle = static function (array $left, array $right): int {
            return strnatcasecmp((string)$left['label'], (string)$right['label']);
        };
        uasort($achieved, $sortByTitle);
        uasort($collected, $sortByTitle);
        uasort($open, static function (array $left, array $right) use ($sortByTitle): int {
            $leftAlwaysOnTop = !empty($left['current']['alwaysOnTop']);
            $rightAlwaysOnTop = !empty($right['current']['alwaysOnTop']);
            if ($leftAlwaysOnTop !== $rightAlwaysOnTop) {
                return $leftAlwaysOnTop ? -1 : 1;
            }
            if ($leftAlwaysOnTop) {
                return $sortByTitle($left, $right);
            }

            $leftRemaining = max(0, (int)$left['current']['target'] - (int)$left['current']['progress']);
            $rightRemaining = max(0, (int)$right['current']['target'] - (int)$right['current']['progress']);
            return $leftRemaining === $rightRemaining
                ? $sortByTitle($left, $right)
                : $leftRemaining <=> $rightRemaining;
        });

        return $achieved + $open + $collected;
    }

    /**
     * Resolves a reference collect payload to an unclaimed, eligible local task.
     *
     * @return array{key: string, milestone: int}|null
     */
    public static function resolve(TasksModel $model, int $uid, string $scope, int $kid, string $questType, ?int $buildingId, int $targetLevel): ?array
    {
        foreach ($model->getTasks($uid, $scope, $kid) as $task) {
            if (!$task['eligible'] || $task['claimed']
                || (int)$task['milestone'] !== $targetLevel
                || self::referenceType($task) !== $questType
                || self::buildingId($task) !== $buildingId) {
                continue;
            }
            return ['key' => (string)$task['key'], 'milestone' => (int)$task['milestone']];
        }
        return null;
    }

    /**
     * @param array<string, array<int|string, array<string, array<string, int>|bool|int|string>|bool|int|string>|bool|string> $group
     */
    private static function label(array $group): string
    {
        $key = (string)$group['key'];
        $image = (string)$group['image'];
        $fields = [
            'all-fields-' => 'allLabel',
            'field-' => 'fieldLabel',
        ];
        foreach ($fields as $prefix => $labelKey) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '([1-4])$/', $key, $m) === 1) {
                return T('Tasks', self::FIELD_TASKS[(int)$m[1]][$labelKey]);
            }
        }
        if (preg_match('/^g([0-9]{1,2})$/', $image, $m) === 1) {
            $building = T('Buildings', $m[1] . '.title');
            if (str_starts_with($key, 'all-fields-')) {
                return sprintf(T('Tasks', 'allBuildings'), $building);
            }
            if (str_starts_with($key, 'building-') || str_starts_with($key, 'field-')) {
                return $building;
            }
        }

        return match ($key) {
            'population', 'general-population' => T('combat', 'Population'),
            'culture', 'general-culture' => T('Tasks', 'culturePointProduction'),
            'even-growth' => T('Tasks', 'evenGrowth'),
            'all-resources' => T('Tasks', 'completeEconomy'),
            default => (string)$group['label'],
        };
    }
}
