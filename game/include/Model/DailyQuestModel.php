<?php

declare(strict_types=1);

namespace Model;

use Core\Config;
use Core\Database\DB;
use Core\Session;
use Game\Hero\HeroExperience;
use Game\PremiumDuration;
use Game\ResourcesHelper;

use function getGame;
use function logError;

class DailyQuestModel
{
    /** The milestones: 25, 50, 75 and 100 points. */
    public const MILESTONES = [1 => 25, 2 => 50, 3 => 75, 4 => 100];

    /**
     * What each milestone can grant, by the reward type stored in daily_quest.reward<N>Type: the id the dialog
     * names it by (icon and text), the base amount and the quest-effect kind that scales it. One table drives both
     * the grant and the dialog, so what the dialog announces is what is credited.
     *
     * @var array<int, array<int, array{0: string, 1: int, 2: string}>>
     */
    private const REWARDS = [
        1 => [
            1 => ['allResources', 200, 'res'],
            2 => ['xp', 50, 'exp'],
            3 => ['amount', 50, 'cp'],
            4 => ['randomResource', 1000, 'res'],
        ],
        2 => [
            1 => ['duration', 86400, 'plus'],
            2 => ['resource_lumber', 86400, 'productionBoost'],
            3 => ['resource_clay', 86400, 'productionBoost'],
            4 => ['resource_iron', 86400, 'productionBoost'],
            5 => ['resource_crop', 86400, 'productionBoost'],
        ],
        3 => [
            1 => ['heroItem_ointment', 5, 'item'],
            2 => ['heroItem_tabletOfLaw', 5, 'item'],
            3 => ['heroItem_bandage25', 5, 'item'],
            4 => ['heroItem_cage', 5, 'item'],
            5 => ['getExtraAdventures', 1, 'adv'],
        ],
        4 => [
            1 => ['amount', 400, 'cp'],
            2 => ['randomResource', 20000, 'res'],
            3 => ['xp', 400, 'exp'],
            4 => ['allResources', 4000, 'res'],
        ],
    ];

    /** Hero items: [building type, item type]. */
    private const ITEMS = [
        'heroItem_ointment' => [11, 106],
        'heroItem_tabletOfLaw' => [14, 109],
        'heroItem_bandage25' => [7, 112],
        'heroItem_cage' => [9, 114],
    ];

    /** @var array<int, array{reward: int, stepCount: int}> */
    private array $quest_data = [
        1  => ['reward' => 5, 'stepCount' => 1],
        2  => ['reward' => 3, 'stepCount' => 3],
        3  => ['reward' => 3, 'stepCount' => 3],
        4  => ['reward' => 5, 'stepCount' => 1],
        5  => ['reward' => 2, 'stepCount' => 3],
        6  => ['reward' => 4, 'stepCount' => 3],
        7  => ['reward' => 5, 'stepCount' => 3],
        8  => ['reward' => 3, 'stepCount' => 3], //changed
        9  => ['reward' => 3, 'stepCount' => 3], //changed
        10 => ['reward' => 5, 'stepCount' => 3],
        11 => ['reward' => 1, 'stepCount' => 6],
    ];
    /** @var array<string, array<string, mixed>> */
    private array $quest_json_data = [
        "AchievementQuest_01" => [
            "id"          => "AchievementQuest_01",
            "name"        => "achievementQuests.achQuest_01_name",
            "category"    => "achievementquests",
            "stepType"    => "achievementtask",
            "currentStep" => null,
            "stepCount"   => 1,
            "steps"       => [
                [
                    "stepId"          => 0,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
            ],
        ],
        "AchievementQuest_02" => [
            "id"          => "AchievementQuest_02",
            "name"        => "achievementQuests.achQuest_02_name",
            "category"    => "achievementquests",
            "stepType"    => "achievementtask",
            "currentStep" => null,
            "stepCount"   => 3,
            "steps"       => [
                [
                    "stepId"          => 0,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
                [
                    "stepId"          => 1,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
                [
                    "stepId"          => 2,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
            ],
        ],
        "AchievementQuest_03" => [
            "id"          => "AchievementQuest_03",
            "name"        => "achievementQuests.achQuest_03_name",
            "category"    => "achievementquests",
            "stepType"    => "achievementtask",
            "currentStep" => null,
            "stepCount"   => 3,
            "steps"       => [
                [
                    "stepId"          => 0,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
                [
                    "stepId"          => 1,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
                [
                    "stepId"          => 2,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
            ],
        ],
        "AchievementQuest_04" => [
            "id"          => "AchievementQuest_04",
            "name"        => "achievementQuests.achQuest_04_name",
            "category"    => "achievementquests",
            "stepType"    => "achievementtask",
            "currentStep" => null,
            "stepCount"   => 1,
            "steps"       => [
                [
                    "stepId"          => 0,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
            ],
        ],
        "AchievementQuest_05" => [
            "id"          => "AchievementQuest_05",
            "name"        => "achievementQuests.achQuest_05_name",
            "category"    => "achievementquests",
            "stepType"    => "achievementtask",
            "currentStep" => null,
            "stepCount"   => 3,
            "steps"       => [
                [
                    "stepId"          => 0,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
                [
                    "stepId"          => 1,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
                [
                    "stepId"          => 2,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
            ],
        ],
        "AchievementQuest_06" => [
            "id"          => "AchievementQuest_06",
            "name"        => "achievementQuests.achQuest_06_name",
            "category"    => "achievementquests",
            "stepType"    => "achievementtask",
            "currentStep" => null,
            "stepCount"   => 3,
            "steps"       => [
                [
                    "stepId"          => 0,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
                [
                    "stepId"          => 1,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
                [
                    "stepId"          => 2,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
            ],
        ],
        "AchievementQuest_07" => [
            "id"          => "AchievementQuest_07",
            "name"        => "achievementQuests.achQuest_07_name",
            "category"    => "achievementquests",
            "stepType"    => "achievementtask",
            "currentStep" => null,
            "stepCount"   => 3,
            "steps"       => [
                [
                    "stepId"          => 0,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
                [
                    "stepId"          => 1,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
                [
                    "stepId"          => 2,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
            ],
        ],
        "AchievementQuest_08" => [
            "id"          => "AchievementQuest_08",
            "name"        => "achievementQuests.achQuest_08_name",
            "category"    => "achievementquests",
            "stepType"    => "achievementtask",
            "currentStep" => null,
            "stepCount"   => 3,
            "steps"       => [
                [
                    "stepId"          => 0,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
                [
                    "stepId"          => 1,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
                [
                    "stepId"          => 2,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
            ],
        ],
        "AchievementQuest_09" => [
            "id"          => "AchievementQuest_09",
            "name"        => "achievementQuests.achQuest_09_name",
            "category"    => "achievementquests",
            "stepType"    => "achievementtask",
            "currentStep" => null,
            "stepCount"   => 3,
            "steps"       => [
                [
                    "stepId"          => 0,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
                [
                    "stepId"          => 1,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
                [
                    "stepId"          => 2,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
            ],
        ],
        "AchievementQuest_10" => [
            "id"          => "AchievementQuest_10",
            "name"        => "achievementQuests.achQuest_10_name",
            "category"    => "achievementquests",
            "stepType"    => "achievementtask",
            "currentStep" => null,
            "stepCount"   => 3,
            "steps"       => [
                [
                    "stepId"          => 0,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
                [
                    "stepId"          => 1,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
                [
                    "stepId"          => 2,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
            ],
        ],
        "AchievementQuest_11" => [
            "id"          => "AchievementQuest_11",
            "name"        => "achievementQuests.achQuest_11_name",
            "category"    => "achievementquests",
            "stepType"    => "achievementtask",
            "currentStep" => null,
            "stepCount"   => 6,
            "steps"       => [
                [
                    "stepId"          => 0,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
                [
                    "stepId"          => 1,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
                [
                    "stepId"          => 2,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
                [
                    "stepId"          => 3,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
                [
                    "stepId"          => 4,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
                [
                    "stepId"          => 5,
                    "type"            => "achievementtask",
                    "stepDescription" => null,
                ],
            ],
        ],
    ];

    public static function getAllianceContributionNeededForQuest(): int
    {
        return 1000;
    }

    public function setReward(int|string $uid): void
    {
        $uid = (int) $uid;
        $db = DB::getInstance();
        $row = $db->query("SELECT * FROM daily_quest WHERE uid=$uid")->fetch_assoc();
        if (!$row) {
            return;
        }

        $row = $this->normalizeQuestProgress($row);
        $points = $this->calcTotalPoints($row);
        $cycle = $this->cycle();

        foreach (self::MILESTONES as $rewardNumber => $pointsRequired) {
            $column = 'reward' . $rewardNumber . 'Type';
            if ($points >= $pointsRequired && !(int)$row[$column]) {
                $reward = self::rewardTypeForCycle($pointsRequired, $cycle);
                $db->query("UPDATE daily_quest SET $column=$reward WHERE uid=$uid");
            }
        }
    }

    /** The reset cycle the world is in; it moves the reward rotation on by one a day. */
    public function cycle(): int
    {
        $interval = max(1, (int)getGame('dailyQuestInterval'));
        $lastReset = (int)DB::getInstance()->fetchScalar('SELECT lastDailyQuestReset FROM config');

        return max(0, intdiv(max(0, $lastReset - (int)Config::getInstance()->game->start_time), $interval));
    }

    /** When the daily quests reset next. */
    public function nextResetAt(): int
    {
        return (int)DB::getInstance()->fetchScalar('SELECT lastDailyQuestReset FROM config') + max(1, (int)getGame('dailyQuestInterval'));
    }

    /**
     * What a milestone grants for a reward type, as the dialog names it. Durations are in days.
     *
     * @return array{id: string, value: int|float}
     */
    public static function rewardView(int $milestone, int $type): array
    {
        [$id, $base, $effect] = self::REWARDS[$milestone][$type];
        $value = self::calcEffect($base, $effect);
        if ($effect === 'plus' || $effect === 'productionBoost') {
            $value = round($value / 86400, 2);
        }

        return ['id' => $id, 'value' => is_float($value) && floor($value) === $value ? (int)$value : $value];
    }

    /**
     * The rotation of a milestone, today's reward first, with the time each next becomes the reward.
     *
     * @return list<array{id: string, value: int|float, resetAt: int}>
     */
    public function rotation(int $milestone): array
    {
        $cycle = $this->cycle();
        $reset = $this->nextResetAt();
        $interval = max(1, (int)getGame('dailyQuestInterval'));
        $rotation = [];
        for ($day = 0; $day < self::rotationLength($milestone); ++$day) {
            $rotation[] = self::rewardView($milestone, self::rewardTypeForCycle(self::MILESTONES[$milestone], $cycle + $day)) + ['resetAt' => $reset + $day * $interval];
        }

        return $rotation;
    }

    /** Days the rotation of a milestone takes to come round. */
    private static function rotationLength(int $milestone): int
    {
        return count(self::REWARDS[$milestone]);
    }

    /**
     * Returns the shared daily reward for a milestone and reset cycle.
     *
     * The result is deliberately independent of the player so every player in
     * the same world sees the same rotation, as required by the game rules.
     */
    public static function rewardTypeForCycle(int $pointsRequired, int $cycle): int
    {
        $rotations = [
            // Hero experience, culture points, one random resource, all resources.
            25 => [2, 3, 4, 1],
            // Lumber, clay, iron, crop, then OpenTravian Plus.
            50 => [2, 3, 4, 5, 1],
            // Ointments, tablets of law, small bandages, cages, then an additional adventure.
            75 => [1, 2, 3, 4, 5],
            // One random resource, hero experience, all resources, culture points.
            100 => [2, 3, 4, 1],
        ];

        if (!isset($rotations[$pointsRequired])) {
            throw new \InvalidArgumentException('Unsupported daily-quest reward milestone.');
        }

        $rotation = $rotations[$pointsRequired];
        return $rotation[$cycle % count($rotation)];
    }

    /**
     * Collects the reward of one milestone (1 to 4 for 25, 50, 75 and 100 points): assigns the day's reward to a
     * milestone that was just reached, then grants it once. False when the milestone is not reached, was already
     * collected or the grant failed.
     */
    public function claim(int $uid, int $kid, int $which): bool
    {
        $this->setReward($uid);

        return $this->collectReward($uid, $kid, $which);
    }

    public function collectReward(int|string $uid, int|string $kid, int|string $which): bool
    {
        $db = DB::getInstance();
        $uid = (int)$uid;
        $kid = (int)$kid;
        $which = (int)$which;
        $requiredPoints = [1 => 25, 2 => 50, 3 => 75, 4 => 100];
        if ($uid <= 0 || $kid <= 0 || !isset($requiredPoints[$which]) || !$db->begin_transaction()) {
            return false;
        }

        try {
            $statement = $db->run(
                'SELECT * FROM daily_quest WHERE uid=? FOR UPDATE',
                [$uid]
            );
            $row = $statement ? $statement->get_result()->fetch_assoc() : null;
            if (!$row) {
                throw new \RuntimeException('Daily quest progress is unavailable.');
            }
            $row = $this->normalizeQuestProgress($row);
            if ($this->calcTotalPoints($row) < $requiredPoints[$which]
                || (int)$row['reward' . $which . 'Done'] !== 0) {
                throw new \RuntimeException('Daily reward is no longer available.');
            }

            $reward = (int)$row['reward' . $which . 'Type'];
            if ($reward <= 0 || !$this->grantDailyReward($uid, $which, $reward)) {
                throw new \RuntimeException('The daily reward could not be granted.');
            }

            $done = $db->run('UPDATE daily_quest SET reward' . $which . 'Done=1 WHERE uid=? AND reward' . $which . 'Done=0', [$uid]);
            if (!$done || $done->affected_rows !== 1 || !$db->commit()) {
                throw new \RuntimeException('Could not complete daily reward collection.');
            }
            return true;
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            logError(
                'Daily reward collection failed for uid %d, kid %d, reward %d: %s',
                [$uid, $kid, $which, $exception->getMessage()]
            );
            return false;
        }
    }

    private function grantDailyReward(int $uid, int $milestone, int $reward): bool
    {
        if (!isset(self::REWARDS[$milestone][$reward])) {
            return false;
        }
        [$id, $base, $effect] = self::REWARDS[$milestone][$reward];
        $amount = (int) self::calcEffect($base, $effect);
        switch (true) {
            case $id === 'allResources':
                return $this->addHeroResources($uid, $amount, $amount, $amount, $amount);
            case $id === 'randomResource':
                return $this->addSingleHeroResource($uid, ['wood', 'clay', 'iron', 'crop'][mt_rand(0, 3)], $amount);
            case $id === 'xp':
                return HeroExperience::add($uid, $amount);
            case $id === 'amount':
                return $this->updateExactlyOneRow('UPDATE users SET cp=cp+? WHERE id=?', [$amount, $uid]);
            case $id === 'duration':
                return $this->addPlus($uid, PremiumDuration::atLeastOneDay($amount));
            case str_starts_with($id, 'resource_'):
                return $this->addProductionBoost($uid, $reward - 1, PremiumDuration::atLeastOneDay($amount));
            case isset(self::ITEMS[$id]):
                [$btype, $type] = self::ITEMS[$id];

                return (new AuctionModel())->addItemToUser($uid, $btype, $type, $amount);
            case $id === 'getExtraAdventures':
                $adventureModel = new AdventureModel();
                $totalAdventures = (int)Session::getInstance()->get('total_adventures');
                for ($i = 0, $count = max(1, $amount); $i < $count; ++$i) {
                    if (!$adventureModel->addAdventure($uid, $totalAdventures, time())) {
                        return false;
                    }
                }

                return true;
        }

        return false;
    }

    /**
     * @param list<int|string> $parameters
     */
    private function updateExactlyOneRow(string $query, array $parameters): bool
    {
        $db = DB::getInstance();
        $statement = $db->run($query, $parameters);
        return $statement !== false && $statement->affected_rows === 1;
    }

    private function addSingleHeroResource(int $uid, string $resource, int $amount): bool
    {
        return $this->addHeroResources(
            $uid,
            $resource === 'wood' ? $amount : 0,
            $resource === 'clay' ? $amount : 0,
            $resource === 'iron' ? $amount : 0,
            $resource === 'crop' ? $amount : 0
        );
    }

    private function addHeroResources(int $uid, int $wood, int $clay, int $iron, int $crop): bool
    {
        return (new HeroResourceInventory())->add($uid, compact('wood', 'clay', 'iron', 'crop'));
    }

    public static function calcEffect(int|float|string $eff, string $type, bool $asString = false): int|float|string
    {
        return Quest::calcEffect($eff, $type, $asString, true);
    }

    protected function addPlus(int $uid, int|float $till): bool
    {
        $db = DB::getInstance();
        $user = $db->query("SELECT plus FROM users WHERE id=$uid")->fetch_assoc();
        if (!$user) {
            return false;
        }
        $plusTill = (int) $user['plus'];
        $showTo = (int) ($plusTill > time() ? $plusTill + $till : time() + $till);
        $update = $db->run('UPDATE users SET plus=? WHERE id=?', [$showTo, $uid]);
        if (!$update || $update->affected_rows !== 1) {
            return false;
        }
        (new AutoExtendModel())->refreshAfterExtension($uid, 1, $showTo);
        return true;
    }

    protected function addProductionBoost(int $uid, int $resourceId, int|float $till): bool
    {
        $db = DB::getInstance();
        if ($resourceId < 1 || $resourceId > 4) {
            return false;
        }
        $boostTill = $db->fetchScalar("SELECT b{$resourceId} FROM users WHERE id=$uid");
        if ($boostTill === false) {
            return false;
        }
        $boostTill = (int) $boostTill;
        $refreshVillageResources = $boostTill < time();
        $showTo = (int) ($boostTill > time() ? $boostTill + $till : time() + $till);
        $statement = $db->run("UPDATE users SET b{$resourceId}=? WHERE id=?", [$showTo, $uid]);
        if (!$statement || $statement->affected_rows !== 1) {
            return false;
        }
        (new AutoExtendModel())->refreshAfterExtension($uid, 1 + $resourceId, $showTo);
        if ($refreshVillageResources) {
            $villages = $db->query("SELECT kid FROM vdata WHERE owner=$uid");
            while ($villages && ($row = $villages->fetch_assoc())) {
                ResourcesHelper::updateVillageResources((int) $row['kid'], false);
            }
        }
        return true;
    }

    /**
     * @param array<string, mixed> $quest daily_quest row
     * @return array<string, array<string, mixed>> the quests that are not completed yet
     */
    public function getQuestData(array $quest): array
    {
        $quest = $this->normalizeQuestProgress($quest);
        $json = [];
        for ($i = 1; $i <= 11; ++$i) {
            if (!$this->isQuestCompleted($i, $quest['qst' . $i])) {
                if ($i < 10) {
                    $i = '0' . $i;
                }
                $json['AchievementQuest_' . $i] = $this->quest_json_data['AchievementQuest_' . $i];
                $json['AchievementQuest_' . $i]['currentStep'] = $quest['qst' . (int)$i];
            }
        }
        return $json;
    }

    public function isQuestCompleted(int|string $questId, int|string $stepId): bool
    {
        return (int) $stepId >= $this->quest_data[(int) $questId]['stepCount'];
    }

    public function getAllianceContribution(int|string $uid): int
    {
        $uid = (int) $uid;

        return (int) DB::getInstance()->fetchScalar("SELECT alliance_contribution FROM daily_quest WHERE uid=$uid");
    }

    public function addDailyQuestRow(int $uid): void
    {
        DB::getInstance()->run('INSERT INTO daily_quest SET uid=?', [$uid]);
    }

    /**
     * @param array<string, mixed> $quest daily_quest row
     */
    public function calcTotalPoints(array $quest): int
    {
        $quest = $this->normalizeQuestProgress($quest);
        $points = 0;
        for ($i = 1; $i <= 11; ++$i) {
            $points += $this->getQuestReward($i, $quest['qst' . $i]);
        }

        return $points;
    }

    public function getQuestReward(int|string $questId, int|string $stepId): int
    {
        $questId = (int) $questId;
        $step = min($this->getStepCount($questId), max(0, (int)$stepId));

        return $this->quest_data[$questId]['reward'] * $step;
    }

    public function getTotalQuestReward(int|string $questId): int
    {
        return $this->quest_data[(int) $questId]['reward'] * $this->quest_data[(int) $questId]['stepCount'];
    }

    /**
     * @return array<string, mixed>|null the player's daily_quest row with normalised progress
     */
    public function getQuest(int|string $uid): ?array
    {
        $uid = (int) $uid;
        $quest = DB::getInstance()->query("SELECT * FROM daily_quest WHERE uid=$uid")->fetch_assoc();
        return is_array($quest) ? $this->normalizeQuestProgress($quest) : null;
    }

    /** True when at least one daily-reward milestone is reached and not yet collected. */
    public function hasClaimableReward(int $uid): bool
    {
        $quest = $this->getQuest($uid);
        if ($quest === null) {
            return false;
        }
        $points = $this->calcTotalPoints($quest);
        foreach ([1 => 25, 2 => 50, 3 => 75, 4 => 100] as $which => $required) {
            if ($points >= $required && (int)($quest['reward' . $which . 'Done'] ?? 1) === 0) {
                return true;
            }
        }
        return false;
    }

    public function getStepCount(int|string $id): int
    {
        return $this->quest_data[(int)$id]['stepCount'];
    }

    /**
     * Legacy worlds can contain daily-quest values written before step limits
     * were enforced. Treat those as the completed final step everywhere: one
     * corrupted counter must not make an extra reward available or render a
     * nonsensical “3 / 1” task state.
     *
     * @param array<string, mixed> $quest
     * @return array<string, mixed>
     */
    private function normalizeQuestProgress(array $quest): array
    {
        for ($questId = 1; $questId <= 11; ++$questId) {
            $column = 'qst' . $questId;
            $quest[$column] = min(
                $this->getStepCount($questId),
                max(0, (int)($quest[$column] ?? 0))
            );
        }

        return $quest;
    }

    public function resetDailyQuest(): void
    {
        $db = DB::getInstance();
        $config = Config::getInstance();
        $lastDailyQuestReset = $db->fetchScalar("SELECT lastDailyQuestReset FROM config");
        if ($lastDailyQuestReset == 0) {
            $today = strtotime('today 12:00', $config->game->start_time);
            $db->query("UPDATE config SET lastDailyQuestReset=$today");
            return;
        }
        if (($lastDailyQuestReset + getGame("dailyQuestInterval")) > time()) {
            return;
        }
        if ($lastDailyQuestReset == 0) {
            $lastDailyQuestReset = $config->game->start_time;
        }
        $db->query("UPDATE daily_quest SET alliance_contribution=0, qst1=0, qst2=0, qst3=0, qst4=0, qst5=0, qst6=0, qst7=0, qst8=0, qst9=0, qst10=0, qst11=0, reward1Type=0, reward2Type=0, reward3Type=0, reward4Type=0, reward1Done=0, reward2Done=0, reward3Done=0, reward4Done=0");
        $db->query("UPDATE config SET lastDailyQuestReset=" . ($lastDailyQuestReset + getGame("dailyQuestInterval")));
    }

    public function setQuestAsCompleted(int|string $uid, int|string $questId): void
    {
        $uid = (int) $uid;
        $questId = (int) $questId;
        $column = 'qst' . $questId;
        $max = $this->quest_data[$questId]['stepCount'];
        DB::getInstance()->query("UPDATE daily_quest SET $column=LEAST($column+1, $max) WHERE uid=$uid");
    }
}
