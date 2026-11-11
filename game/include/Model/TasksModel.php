<?php

declare(strict_types=1);

namespace Model;

use Core\Database\DB;
use Game\Formulas;
use Game\Hero\HeroExperience;

use function T;
use function getGameSpeed;
use function logError;

/**
 * Reads village/account task progress and grants each task milestone once.
 *
 * @phpstan-type Task array{key: string, label: string, progress: int, target: int, milestone: int, stage: int, type: string, image: string, baseResources: int, baseExperience: int, alwaysOnTop: bool, eligible: bool, claimed: bool}
 * @phpstan-type RewardState array{level: int, heroMultiplier: float, resourceMultiplier: int, experienceMultiplier: int}
 */
class TasksModel
{
    public const FREE_GOLD_KEY = 'free-gold';
    public const FREE_GOLD_REWARD_GOLD = 100;
    private const WALL_GID_BY_RACE = [
        1 => 31,
        2 => 32,
        3 => 33,
        6 => 42,
        7 => 43,
        8 => 47,
        9 => 50,
    ];
    /** @var array<int, RewardState> */
    private array $rewardState = [];

    /**
     * @return list<Task>
     */
    public function getTasks(int $uid, string $scope, int $kid): array
    {
        if ($scope === 'general') {
            return $this->getGeneralTasks($uid, $kid);
        }

        return $this->getVillageTasks($uid, $kid);
    }

    public function hasAvailableReward(int $uid, int $activeKid): bool
    {
        foreach ([['village', $activeKid], ['general', 0]] as [$scope, $kid]) {
            foreach ($this->getTasks($uid, $scope, $kid) as $task) {
                if ($task['eligible'] && !$task['claimed']) {
                    return true;
                }
            }
        }

        return false;
    }

    public function collect(int $uid, int $kid, string $taskKey, int $milestone): bool
    {
        if ($uid <= 0 || $kid < 0 || $taskKey === '' || $milestone <= 0) {
            return false;
        }

        $db = DB::getInstance();
        if (!$db->begin_transaction()) {
            return false;
        }

        try {
            $scope = $kid === 0 ? 'general' : 'village';
            if ($scope === 'village' && !$this->villageBelongsTo($uid, $kid)) {
                throw new \RuntimeException('The selected village is no longer available.');
            }

            $task = $this->findTask($uid, $scope, $kid, $taskKey, $milestone);
            if ($task === null || !$task['eligible']) {
                throw new \RuntimeException('That task is not ready to collect.');
            }

            $isFreeGold = $taskKey === self::FREE_GOLD_KEY;
            if ($isFreeGold) {
                $claimUpdate = $db->run(
                    'UPDATE task_claims SET claimed_at=? WHERE uid=? AND kid=0 AND task_key=? AND milestone=1 AND claimed_at IS NULL',
                    [time(), $uid, self::FREE_GOLD_KEY]
                );
                if (!$claimUpdate || $claimUpdate->affected_rows !== 1) {
                    throw new \RuntimeException('That task reward was claimed by another request.');
                }
            } else {
                $insert = $db->run(
                    'INSERT INTO task_claims (uid, kid, task_key, milestone, completed_at) VALUES (?, ?, ?, ?, ?)',
                    [$uid, $kid, $taskKey, $milestone, time()]
                );
                if (!$insert) {
                    throw new \RuntimeException('That task reward has already been collected.');
                }
            }

            $reward = $this->rewardFor($task, $uid);
            $grant = (new HeroResourceInventory())->add($uid, [
                'wood' => $reward['resources'],
                'clay' => $reward['resources'],
                'iron' => $reward['resources'],
                'crop' => $reward['resources'],
            ]);
            if (!$grant) {
                throw new \RuntimeException('Could not grant task resources.');
            }

            if (!HeroExperience::add($uid, (int)$reward['experience'])) {
                throw new \RuntimeException('Could not grant hero experience.');
            }

            if ($isFreeGold) {
                $gold = $db->run('UPDATE users SET bought_gold=bought_gold+? WHERE id=?', [self::FREE_GOLD_REWARD_GOLD, $uid]);
                if (!$gold || $gold->affected_rows !== 1) {
                    throw new \RuntimeException('Could not grant Free Gold task gold.');
                }
            } else {
                $claimUpdate = $db->run('UPDATE task_claims SET claimed_at=? WHERE uid=? AND kid=? AND task_key=? AND milestone=? AND claimed_at IS NULL', [time(), $uid, $kid, $taskKey, $milestone]);
                if (!$claimUpdate || $claimUpdate->affected_rows !== 1) {
                    throw new \RuntimeException('The task reward was claimed by another request.');
                }
            }
            if (!$db->commit()) {
                throw new \RuntimeException('Could not commit the task reward transaction.');
            }
            // Season Pass task-reward mission progress is best-effort and must
            // not roll back an already-granted task board reward.
            (new SeasonPassModel())->onTaskRewardCollected($uid);
            return true;
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            logError(
                'Task reward collection failed for uid %d, kid %d, task %s, milestone %d: %s',
                [$uid, $kid, $taskKey, $milestone, $exception->getMessage()]
            );
            return false;
        }
    }

    /**
     * @return list<Task>
     */
    private function getVillageTasks(int $uid, int $kid): array
    {
        $db = DB::getInstance();
        $village = $this->fetchOne(
            'SELECT v.kid, v.owner, v.name, v.pop, v.cp, v.created, u.race AS player_race, f.* FROM vdata v JOIN fdata f ON f.kid=v.kid JOIN users u ON u.id=v.owner WHERE v.kid=? AND v.owner=?',
            [$kid, $uid]
        );
        if ($village === null) {
            return [];
        }

        $fields = $this->readResourceFields($village);
        $buildings = $this->readBuildings($village);
        $tasks = [];

        $this->appendMilestoneTasks($tasks, 'population', 'Population', (int)$village['pop'], [50, 100, 150, 250, 350, 500, 750, 1000], 'populationProgressInVillage', 100, 5);
        $this->appendMilestoneTasks($tasks, 'culture', 'Culture point production', (int)$village['cp'], [50, 100, 150, 250, 350, 500], 'culturePointsProductionProgressInVillage', 100, 5);

        /** @var list<array{list<int>, string, list<int>}> $buildingCatalog */
        $buildingCatalog = [
            [[10], 'Warehouse', [1, 3, 7, 12, 20]], [[11], 'Granary', [1, 3, 7, 12, 20]],
            [[15], 'Main Building', [1, 3, 7, 12, 20]], [[16], 'Rally Point', [1, 10, 20]],
            [[17], 'Marketplace', [1]], [[18], 'Embassy', [1]], [[19], 'Barracks', [1]],
            [[23], 'Cranny', [1, 3, 6, 10]], [[25], 'Residence', [1]], [[8], 'Grain Mill', [1, 5]],
            [[31, 32, 33, 42, 43, 47, 50], 'City Wall', [1, 3, 7, 12, 20]],
        ];
        foreach ($buildingCatalog as [$gids, $fallbackLabel, $levels]) {
            $level = 0;
            foreach ($buildings as $building) {
                if (array_intersect($gids, $building['types'])) {
                    $level = max($level, $building['level']);
                }
            }
            // The current tribe determines a wall's title before it has been
            // built. Once present, use the actual structure in the field.
            $imageGid = count($gids) === 1
                ? $gids[0]
                : (self::WALL_GID_BY_RACE[(int)$village['player_race']] ?? $gids[0]);
            foreach ($buildings as $building) {
                $presentGids = array_intersect($gids, $building['types']);
                if ($presentGids) {
                    $imageGid = reset($presentGids);
                    break;
                }
            }
            // Keep the pre-existing key for wall claims. It is persistence,
            // whereas the label is player-facing and tribe-specific.
            $key = count($gids) === 1 ? 'building-' . $gids[0] : 'building-city-wall';
            $label = T('Buildings', $imageGid . '.title', $fallbackLabel);
            $this->appendMilestoneTasks($tasks, $key, $label, $level, $levels, 'building', 150, 10, 'g' . $imageGid);
        }

        $fieldCatalog = [1 => ['Woodcutters', [2, 4, 7, 10]], 2 => ['Clay Pits', [2, 4, 7, 10]], 3 => ['Iron Mines', [2, 4, 7, 10]], 4 => ['Croplands', [2, 4, 7, 10]]];
        foreach ($fieldCatalog as $type => [$label, $levels]) {
            $maxLevel = 0;
            foreach ($fields as $field) {
                if ($field['type'] === $type) {
                    $maxLevel = max($maxLevel, $field['level']);
                }
            }
            $this->appendMilestoneTasks($tasks, 'field-' . $type, $label, $maxLevel, $levels, 'resourceBuildingsLevelOfOneOfTheBuildings', 150, 10, 'g' . $type);
        }

        $allFieldLevels = [1 => [2, 3, 4, 5, 8, 10], 2 => [2, 3, 4, 5, 8, 10], 3 => [2, 3, 4, 5, 8, 10], 4 => [2, 3, 4, 5, 8, 10]];
        foreach ($allFieldLevels as $type => $levels) {
            $typeFields = array_values(array_filter($fields, static fn (array $field): bool => $field['type'] === $type));
            $count = count($typeFields);
            foreach ($levels as $index => $target) {
                $progress = count(array_filter($typeFields, static fn (array $field): bool => $field['level'] >= $target));
                $this->appendOneTask($tasks, 'all-fields-' . $type, 'All ' . strtolower($fieldCatalog[$type][0]), $progress, max(1, $count), $target, 'resourceBuildingsAllLevels', 500, 20, $index + 1, 'g' . $type);
            }
        }

        foreach ([2, 4, 7, 10] as $index => $target) {
            $progress = 0;
            foreach ($fieldCatalog as $type => $_) {
                foreach ($fields as $field) {
                    if ($field['type'] === $type && $field['level'] >= $target) {
                        ++$progress;
                        break;
                    }
                }
            }
            $this->appendOneTask($tasks, 'even-growth', 'Even growth', $progress, 4, $target, 'resourceBuildingsLevelOfOneOfTheBuildings', 350, 15, $index + 1, 'oneEachResource');
        }

        foreach ([2, 4, 7, 8, 9, 10] as $index => $target) {
            $progress = count(array_filter($fields, static fn (array $field): bool => $field['level'] >= $target));
            $this->appendOneTask($tasks, 'all-resources', 'Complete economy', $progress, max(1, count($fields)), $target, 'resourceBuildingsAllLevels', 1000, 40, $index + 1, 'allResources');
        }

        $this->attachClaimState($tasks, $uid, $kid);
        return $tasks;
    }

    /**
     * @return list<Task>
     */
    private function getGeneralTasks(int $uid, int $activeKid): array
    {
        $row = $this->fetchOne('SELECT COALESCE(SUM(pop),0) AS population, COALESCE(SUM(cp),0) AS culture FROM vdata WHERE owner=? AND isFarm=0', [$uid]);
        if ($row === null) {
            return [];
        }
        $tasks = [];
        $population = (int)$row['population'];
        $culture = (int)$row['culture'];
        $populationMilestones = [500, 1000, 1500, 2500, 5000, 10000, 20000];
        $cultureMilestones = [500, 1000, 1500, 2500, 5000, 10000, 20000];
        while (end($populationMilestones) <= $population) {
            $populationMilestones[] = end($populationMilestones) * 2;
        }
        while (end($cultureMilestones) <= $culture) {
            $cultureMilestones[] = end($cultureMilestones) * 2;
        }
        $this->appendMilestoneTasks($tasks, 'general-population', 'Population', $population, $populationMilestones, 'populationProgress', 500, 20, 'population', true);
        $this->appendMilestoneTasks($tasks, 'general-culture', 'Culture point production', $culture, $cultureMilestones, 'culturePointsProductionProgress', 500, 20, 'culture', true);
        if ($this->hasOpenedFreeGoldTask($uid)) {
            $tasks[] = [
                'key' => self::FREE_GOLD_KEY,
                'label' => T('Tasks', 'freeGold', 'Free Gold'),
                'progress' => 1,
                'target' => 1,
                'milestone' => 1,
                'stage' => 1,
                'type' => 'freeGold',
                'image' => 'freeGold',
                'baseResources' => 300,
                'baseExperience' => 20,
                'alwaysOnTop' => true,
                'eligible' => true,
                'claimed' => false,
            ];
        }
        $this->attachClaimState($tasks, $uid, 0);
        return $tasks;
    }

    /** The payment wizard writes the account-scoped eligibility record once. */
    public function markFreeGoldShopOpened(int $uid): bool
    {
        if ($uid <= 0) {
            return false;
        }

        return (bool)DB::getInstance()->run(
            'INSERT IGNORE INTO task_claims (uid, kid, task_key, milestone, completed_at) VALUES (?, 0, ?, 1, ?)',
            [$uid, self::FREE_GOLD_KEY, time()]
        );
    }

    private function hasOpenedFreeGoldTask(int $uid): bool
    {
        return (int)DB::getInstance()->fetchScalar(
            'SELECT COUNT(*) FROM task_claims WHERE uid=? AND kid=0 AND task_key=? AND milestone=1',
            [$uid, self::FREE_GOLD_KEY]
        ) === 1;
    }

    /**
     * @param list<Task> $tasks
     * @param list<int> $milestones
     */
    private function appendMilestoneTasks(array &$tasks, string $key, string $label, int $value, array $milestones, string $type, int $baseResources, int $baseExperience, ?string $image = null, bool $alwaysOnTop = false): void
    {
        foreach ($milestones as $index => $target) {
            $this->appendOneTask($tasks, $key, $label, $value, $target, $target, $type, $baseResources, $baseExperience, $index + 1, $image ?? $type, $alwaysOnTop);
        }
    }

    /**
     * @param list<Task> $tasks
     */
    private function appendOneTask(array &$tasks, string $key, string $label, int $progress, int $target, int $milestone, string $type, int $baseResources, int $baseExperience, int $stage, string $image, bool $alwaysOnTop = false): void
    {
        $tasks[] = [
            'key' => $key,
            'label' => $label,
            'progress' => min($progress, $target),
            'target' => $target,
            'milestone' => $milestone,
            'stage' => $stage,
            'type' => $type,
            'image' => $image,
            'baseResources' => $baseResources * $stage,
            'baseExperience' => $baseExperience * $stage,
            'alwaysOnTop' => $alwaysOnTop,
            'eligible' => $progress >= $target,
            'claimed' => false,
        ];
    }

    /**
     * @param list<Task> $tasks
     */
    private function attachClaimState(array &$tasks, int $uid, int $kid): void
    {
        $stmt = DB::getInstance()->run('SELECT task_key, milestone, claimed_at FROM task_claims WHERE uid=? AND kid=?', [$uid, $kid]);
        if (!$stmt) {
            throw new \RuntimeException('Could not read task claim status.');
        }
        $claims = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $claims[$row['task_key'] . ':' . (int)$row['milestone']] = $row['claimed_at'] !== null;
        }
        foreach ($tasks as &$task) {
            $task['claimed'] = $claims[$task['key'] . ':' . $task['milestone']] ?? false;
        }
        unset($task);
    }

    /**
     * @return Task|null
     */
    private function findTask(int $uid, string $scope, int $kid, string $taskKey, int $milestone): ?array
    {
        foreach ($this->getTasks($uid, $scope, $kid) as $task) {
            if ($task['key'] === $taskKey && $task['milestone'] === $milestone) {
                return $task;
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $task
     * @return array{resources: int, experience: int}
     */
    public function rewardFor(array $task, int $uid): array
    {
        if (($task['key'] ?? '') === self::FREE_GOLD_KEY) {
            // payment task: the shop reward is a fixed
            // 300-resource / 20-XP package, separate from milestone scaling.
            return ['resources' => 300, 'experience' => 20];
        }

        $state = $this->getRewardState($uid);

        return [
            'resources' => max(1, (int)round($task['baseResources'] * $state['resourceMultiplier'] * $state['heroMultiplier'])),
            'experience' => max(1, (int)round($task['baseExperience'] * $state['experienceMultiplier'] * $state['heroMultiplier'])),
        ];
    }

    /**
     * @return array{level: int, bonusPercent: int, nextBonusPercent: int}
     */
    public function getHeroRewardBonus(int $uid): array
    {
        $state = $this->getRewardState($uid);
        $bonus = max(0, $state['level'] - 1) * 2;

        return [
            'level' => $state['level'],
            'bonusPercent' => $bonus,
            'nextBonusPercent' => $state['level'] * 2,
        ];
    }

    /**
     * @return RewardState
     */
    private function getRewardState(int $uid): array
    {
        if (!isset($this->rewardState[$uid])) {
            $hero = $this->fetchOne('SELECT exp FROM hero WHERE uid=?', [$uid]);
            $level = $hero ? Formulas::heroLevel((int)$hero['exp']) : 0;
            $this->rewardState[$uid] = [
                'level' => $level,
                'heroMultiplier' => 1 + (max(0, $level - 1) * 0.02),
                'resourceMultiplier' => max(1, (int)floor(max(1, (int)getGameSpeed()) / 5)),
                'experienceMultiplier' => max(1, (int)Formulas::getHeroExpLevelMultiplier()),
            ];
        }
        return $this->rewardState[$uid];
    }

    private function villageBelongsTo(int $uid, int $kid): bool
    {
        return (int)DB::getInstance()->fetchScalar('SELECT COUNT(*) FROM vdata WHERE kid=? AND owner=?', [$kid, $uid], 0) === 1;
    }

    /**
     * @param array<string, mixed> $row
     * @return list<array{type: int, level: int}>
     */
    private function readResourceFields(array $row): array
    {
        $fields = [];
        for ($i = 1; $i <= 18; ++$i) {
            $fields[] = ['type' => (int)$row['f' . $i . 't'], 'level' => (int)$row['f' . $i]];
        }
        return $fields;
    }

    /**
     * @param array<string, mixed> $row
     * @return list<array{types: list<int>, level: int}>
     */
    private function readBuildings(array $row): array
    {
        $buildings = [];
        for ($i = 19; $i <= 40; ++$i) {
            $type = (int)$row['f' . $i . 't'];
            if ($type > 0) {
                $buildings[] = ['types' => [$type], 'level' => (int)$row['f' . $i]];
            }
        }
        return $buildings;
    }

    /**
     * @param list<int|string> $parameters
     * @return array<string, mixed>|null
     */
    private function fetchOne(string $query, array $parameters): ?array
    {
        $statement = DB::getInstance()->run($query, $parameters);
        if (!$statement) {
            return null;
        }
        $row = $statement->get_result()->fetch_assoc();
        return is_array($row) ? $row : null;
    }
}
