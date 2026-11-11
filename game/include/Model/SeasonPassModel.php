<?php

declare(strict_types=1);

namespace Model;

use Core\Database\DB;

use function logError;

/**
 * Local-world Season Pass progress: missions → points → free/premium rewards
 * and a shards inventory. Scope is the world/account DB, not lobby SSO.
 */
final class SeasonPassModel
{
    public const MISSION_DAILY_LOGIN = 'dailyLogin';
    public const MISSION_TASK_REWARDS = 'taskRewards';

    public const GOVERNOR_KEYS = ['cleopatra', 'apollodorus', 'teutobod'];

    /** Cumulative star thresholds for Maximum Levels 1–5 (support Cleopatra table). */
    public const GOVERNOR_STAR_THRESHOLDS = [0, 100, 1000, 2800, 6400, 13600];

    private const DAILY_MISSIONS = [
        ['key' => self::MISSION_DAILY_LOGIN, 'points' => 10, 'target' => 1],
        ['key' => self::MISSION_TASK_REWARDS, 'points' => 15, 'target' => 1],
    ];

    private const RANDOM_MISSION_POOL = [
        ['key' => 'trainTroops', 'points' => 20, 'target' => 1],
        ['key' => 'gainPopulation', 'points' => 20, 'target' => 1],
        ['key' => 'defeatAnimals', 'points' => 20, 'target' => 1],
        ['key' => 'gainCulturePoints', 'points' => 20, 'target' => 1],
        ['key' => 'resourceBoost', 'points' => 20, 'target' => 1],
    ];

    private static ?bool $schemaReady = null;

    /** World tables required before Season Pass reads/writes are safe. */
    private const SCHEMA_TABLES = [
        'season_pass',
        'season_pass_rewards',
        'season_pass_progress',
        'season_pass_missions',
        'season_pass_claims',
        'season_pass_shards',
        'season_pass_governor_progress',
    ];

    public function isAvailable(): bool
    {
        return $this->schemaReady();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getActiveSeason(?int $now = null): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }

        $now ??= time();

        return $this->fetchOne(
            'SELECT * FROM season_pass
             WHERE active=1 AND starts_at<=? AND claim_until>=?
             ORDER BY starts_at DESC LIMIT 1',
            [$now, $now]
        );
    }

    public function hasActiveSeason(?int $now = null): bool
    {
        return $this->getActiveSeason($now) !== null;
    }

    /**
     * Called from authenticated game pages: ensure today's missions exist and
     * mark the daily-login mission complete for the current day.
     */
    public function onPlayerActivity(int $uid): void
    {
        if ($uid <= 2 || !$this->schemaReady()) {
            return;
        }

        try {
            $season = $this->getActiveSeason();
            if ($season === null || (int)$season['ends_at'] < time()) {
                return;
            }
            $seasonId = (int)$season['id'];
            $this->ensureProgress($uid, $seasonId);
            $this->ensureDailyMissions($uid, $seasonId);
            $this->completeMissionByKey($uid, $seasonId, self::MISSION_DAILY_LOGIN, 1);
        } catch (\Throwable $exception) {
            logError('Season Pass activity hook failed for uid %d: %s', [$uid, $exception->getMessage()]);
        }
    }

    /** Called after a successful task-board reward collection. */
    public function onTaskRewardCollected(int $uid): void
    {
        if ($uid <= 2 || !$this->schemaReady()) {
            return;
        }

        try {
            $season = $this->getActiveSeason();
            if ($season === null || (int)$season['ends_at'] < time()) {
                return;
            }
            $seasonId = (int)$season['id'];
            $this->ensureProgress($uid, $seasonId);
            $this->ensureDailyMissions($uid, $seasonId);
            $this->completeMissionByKey($uid, $seasonId, self::MISSION_TASK_REWARDS, 1);
        } catch (\Throwable $exception) {
            logError('Season Pass task-reward hook failed for uid %d: %s', [$uid, $exception->getMessage()]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPlayerBoard(int $uid): ?array
    {
        $season = $this->getActiveSeason();
        if ($season === null) {
            return null;
        }

        $seasonId = (int)$season['id'];
        $this->ensureProgress($uid, $seasonId);
        $this->ensureDailyMissions($uid, $seasonId);

        $progress = $this->getProgress($uid, $seasonId);
        $missions = $this->getMissionsForDay($uid, $seasonId, $this->missionDay());
        $rewards = $this->getRewardsWithClaimState($uid, $seasonId, (int)$progress['points'], (bool)$progress['has_premium']);
        $shards = $this->getShards($uid);
        $governors = $this->getGovernorsBoard($uid);

        return [
            'season' => $season,
            'progress' => $progress,
            'missions' => $missions,
            'rewards' => $rewards,
            'shards' => $shards,
            'governors' => $governors,
            'missionsOpen' => (int)$season['ends_at'] >= time(),
            'claimsOpen' => (int)$season['claim_until'] >= time(),
        ];
    }

    /**
     * Progress a random (or matching) mission by key. Safe no-op when the key
     * is not among today's missions or the season is closed.
     */
    public function onMissionEvent(int $uid, string $missionKey, int $increment = 1): void
    {
        if ($uid <= 2 || $missionKey === '' || !$this->schemaReady()) {
            return;
        }

        try {
            $season = $this->getActiveSeason();
            if ($season === null || (int)$season['ends_at'] < time()) {
                return;
            }
            $seasonId = (int)$season['id'];
            $this->ensureProgress($uid, $seasonId);
            $this->ensureDailyMissions($uid, $seasonId);
            $this->completeMissionByKey($uid, $seasonId, $missionKey, $increment);
        } catch (\Throwable $exception) {
            logError(
                'Season Pass mission event failed for uid %d key %s: %s',
                [$uid, $missionKey, $exception->getMessage()]
            );
        }
    }

    /**
     * Reroll a random mission once before first progress. Consumes one
     * videofeatureSkip shard (local substitute for the live video feature;
     * no payment/SSO video stack).
     */
    public function rerollRandomMission(int $uid, int $missionId): bool
    {
        if ($uid <= 0 || $missionId <= 0 || !$this->schemaReady()) {
            return false;
        }

        $db = DB::getInstance();
        if (!$db->begin_transaction()) {
            return false;
        }

        try {
            $season = $this->getActiveSeason();
            if ($season === null || (int)$season['ends_at'] < time()) {
                throw new \RuntimeException('Season Pass missions are closed.');
            }
            $seasonId = (int)$season['id'];
            $day = $this->missionDay();
            $mission = $this->fetchOne(
                'SELECT * FROM season_pass_missions
                 WHERE id=? AND uid=? AND season_id=? AND mission_day=? LIMIT 1',
                [$missionId, $uid, $seasonId, $day]
            );
            if ($mission === null || $mission['mission_type'] !== 'random') {
                throw new \RuntimeException('Not a random Season Pass mission.');
            }
            if ((int)$mission['progress'] > 0 || (int)$mission['points_granted'] === 1) {
                throw new \RuntimeException('Mission already started.');
            }
            if ((int)($mission['rerolled'] ?? 0) === 1) {
                throw new \RuntimeException('Mission already rerolled today.');
            }
            if (!$this->consumeShard($uid, 'videofeatureSkip', 1, $db)) {
                throw new \RuntimeException('Video skip shard required to reroll.');
            }

            $pool = array_values(array_filter(
                self::RANDOM_MISSION_POOL,
                static fn (array $row): bool => $row['key'] !== (string)$mission['mission_key']
            ));
            if ($pool === []) {
                throw new \RuntimeException('No alternate random mission available.');
            }
            $replacement = $pool[array_rand($pool)];
            $updated = $db->run(
                'UPDATE season_pass_missions
                 SET mission_key=?, points=?, progress=0, target=?, completed_at=NULL, points_granted=0, rerolled=1
                 WHERE id=? AND points_granted=0 AND progress=0 AND rerolled=0',
                [
                    $replacement['key'],
                    $replacement['points'],
                    $replacement['target'],
                    $missionId,
                ]
            );
            if ($updated === false || $updated->affected_rows !== 1) {
                throw new \RuntimeException('Could not reroll mission.');
            }
            if (!$db->commit()) {
                throw new \RuntimeException('Could not commit mission reroll.');
            }

            return true;
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            logError(
                'Season Pass reroll failed for uid %d mission %d: %s',
                [$uid, $missionId, $exception->getMessage()]
            );
            return false;
        }
    }

    public function claimReward(int $uid, int $rewardId): bool
    {
        if ($uid <= 0 || $rewardId <= 0 || !$this->schemaReady()) {
            return false;
        }

        $db = DB::getInstance();
        if (!$db->begin_transaction()) {
            return false;
        }

        try {
            $season = $this->getActiveSeason();
            if ($season === null || (int)$season['claim_until'] < time()) {
                throw new \RuntimeException('Season Pass claims are closed.');
            }
            $seasonId = (int)$season['id'];
            $reward = $this->fetchOne(
                'SELECT * FROM season_pass_rewards WHERE id=? AND season_id=? LIMIT 1',
                [$rewardId, $seasonId]
            );
            if ($reward === null) {
                throw new \RuntimeException('Unknown Season Pass reward.');
            }

            $progress = $this->getProgress($uid, $seasonId);
            if ((int)$progress['points'] < (int)$reward['points_required']) {
                throw new \RuntimeException('Not enough Season Pass points.');
            }
            if ($reward['track'] === 'premium' && !(int)$progress['has_premium']) {
                throw new \RuntimeException('Premium Season Pass required.');
            }

            $insert = $db->run(
                'INSERT INTO season_pass_claims (uid, season_id, reward_id, claimed_at) VALUES (?, ?, ?, ?)',
                [$uid, $seasonId, $rewardId, time()]
            );
            if ($insert === false) {
                throw new \RuntimeException('Reward already claimed.');
            }

            if ($reward['reward_type'] === 'shard') {
                $this->addShard($uid, (string)$reward['reward_key'], (int)$reward['amount'], $db);
            } elseif ($reward['reward_type'] === 'governor_star') {
                $governorKey = (string)$reward['reward_key'];
                if (!in_array($governorKey, self::GOVERNOR_KEYS, true)) {
                    $governorKey = 'cleopatra';
                }
                $this->addGovernorStars($uid, $governorKey, (int)$reward['amount'], $db);
                $updated = $db->run(
                    'UPDATE season_pass_progress SET governor_stars=governor_stars+?, updated_at=? WHERE uid=? AND season_id=?',
                    [(int)$reward['amount'], time(), $uid, $seasonId]
                );
                if ($updated === false) {
                    throw new \RuntimeException('Could not grant governor stars.');
                }
            }

            if (!$db->commit()) {
                throw new \RuntimeException('Could not commit Season Pass claim.');
            }

            return true;
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            logError(
                'Season Pass claim failed for uid %d reward %d: %s',
                [$uid, $rewardId, $exception->getMessage()]
            );
            return false;
        }
    }

    /** Multihunter / local-admin stub for premium track unlock. */
    public function grantPremium(int $uid): bool
    {
        $season = $this->getActiveSeason();
        if ($season === null || $uid <= 0) {
            return false;
        }

        $seasonId = (int)$season['id'];
        $this->ensureProgress($uid, $seasonId);
        $updated = DB::getInstance()->run(
            'UPDATE season_pass_progress SET has_premium=1, updated_at=? WHERE uid=? AND season_id=?',
            [time(), $uid, $seasonId]
        );

        return $updated !== false;
    }

    public function missionLabel(string $missionKey): string
    {
        return match ($missionKey) {
            self::MISSION_DAILY_LOGIN => 'Daily Login',
            self::MISSION_TASK_REWARDS => 'Task Rewards',
            'trainTroops' => 'Train troops',
            'gainPopulation' => 'Gain population',
            'defeatAnimals' => 'Defeat animals',
            'gainCulturePoints' => 'Gain culture points',
            'resourceBoost' => 'Resource boost',
            default => $missionKey,
        };
    }

    public function governorLabel(string $governorKey): string
    {
        return match ($governorKey) {
            'cleopatra' => 'Cleopatra',
            'apollodorus' => 'Apollodorus',
            'teutobod' => 'Teutobod',
            default => $governorKey,
        };
    }

    /**
     * @return list<array{key:string,label:string,stars:int,maxLevel:int,locked:bool,current:int,threshold:int}>
     */
    public function getGovernorsBoard(int $uid): array
    {
        $rows = [];
        foreach (self::GOVERNOR_KEYS as $key) {
            $stars = $this->getGovernorStars($uid, $key);
            $maxLevel = $this->maxLevelForStars($stars);
            $nextLevel = min(5, $maxLevel + 1);
            $progress = $this->starsProgressToward($stars, $nextLevel);
            $rows[] = [
                'key' => $key,
                'label' => $this->governorLabel($key),
                'stars' => $stars,
                'maxLevel' => $maxLevel,
                'locked' => $maxLevel < 1,
                'current' => $progress['current'],
                'threshold' => $progress['threshold'],
            ];
        }

        return $rows;
    }

    public function maxLevelForStars(int $stars): int
    {
        $level = 0;
        foreach (self::GOVERNOR_STAR_THRESHOLDS as $index => $needed) {
            if ($index === 0) {
                continue;
            }
            if ($stars >= $needed) {
                $level = $index;
            }
        }

        return $level;
    }

    /** @return array{current:int,threshold:int} */
    public function starsProgressToward(int $stars, int $targetMaxLevel): array
    {
        $targetMaxLevel = max(1, min(5, $targetMaxLevel));
        $high = self::GOVERNOR_STAR_THRESHOLDS[$targetMaxLevel];
        $low = self::GOVERNOR_STAR_THRESHOLDS[$targetMaxLevel - 1];
        $span = max(1, $high - $low);

        return [
            'current' => max(0, min($span, $stars - $low)),
            'threshold' => $span,
        ];
    }

    public function missionImageClass(string $missionKey): string
    {
        $map = [
            self::MISSION_DAILY_LOGIN => 'mission_dailyLogin',
            self::MISSION_TASK_REWARDS => 'mission_progressionTask',
            'trainTroops' => 'mission_trainTroops',
            'gainPopulation' => 'mission_gainPopulation',
            'defeatAnimals' => 'mission_defeatAnimals',
            'gainCulturePoints' => 'mission_gainCulturePoints',
            'resourceBoost' => 'mission_resourceBoost',
        ];

        return $map[$missionKey] ?? 'mission_progressionTask';
    }

    public function shardIconClass(string $shardKey, string $size = 'large'): string
    {
        $allowed = [
            'buildTimeShortening',
            'videofeatureSkip',
            'plus',
            'productionBoostLumber',
            'productionBoostClay',
            'productionBoostIron',
            'productionBoostCrop',
            'exchangeResourcesAnyAndAny',
            'cleopatraStars',
            'teutobodStars',
        ];
        $key = in_array($shardKey, $allowed, true) ? $shardKey : 'buildTimeShortening';
        if (in_array($shardKey, self::GOVERNOR_KEYS, true)) {
            $key = $shardKey . 'Stars';
        }

        return $key . '_' . $size;
    }

    private function schemaReady(): bool
    {
        if (self::$schemaReady !== null) {
            return self::$schemaReady;
        }

        $placeholders = implode(',', array_fill(0, count(self::SCHEMA_TABLES), '?'));
        $statement = DB::getInstance()->run(
            "SELECT COUNT(DISTINCT table_name) AS table_count
             FROM information_schema.tables
             WHERE table_schema=DATABASE() AND table_name IN ({$placeholders})",
            self::SCHEMA_TABLES
        );
        $row = $statement !== false ? $statement->get_result()->fetch_assoc() : null;
        self::$schemaReady = $row !== null
            && (int)($row['table_count'] ?? 0) === count(self::SCHEMA_TABLES);

        return self::$schemaReady;
    }

    private function missionDay(?int $now = null): int
    {
        return (int)date('Ymd', $now ?? time());
    }

    private function ensureProgress(int $uid, int $seasonId): void
    {
        DB::getInstance()->run(
            'INSERT IGNORE INTO season_pass_progress (uid, season_id, points, has_premium, governor_stars, updated_at)
             VALUES (?, ?, 0, 0, 0, ?)',
            [$uid, $seasonId, time()]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getProgress(int $uid, int $seasonId): array
    {
        $row = $this->fetchOne(
            'SELECT * FROM season_pass_progress WHERE uid=? AND season_id=? LIMIT 1',
            [$uid, $seasonId]
        );
        if ($row === null) {
            $this->ensureProgress($uid, $seasonId);
            $row = [
                'uid' => $uid,
                'season_id' => $seasonId,
                'points' => 0,
                'has_premium' => 0,
                'governor_stars' => 0,
                'updated_at' => time(),
            ];
        }

        return $row;
    }

    private function ensureDailyMissions(int $uid, int $seasonId): void
    {
        $day = $this->missionDay();
        $existing = DB::getInstance()->fetchScalar(
            'SELECT COUNT(*) FROM season_pass_missions WHERE uid=? AND season_id=? AND mission_day=?',
            [$uid, $seasonId, $day],
            0
        );
        if ((int)$existing >= 5) {
            return;
        }

        $db = DB::getInstance();
        $slot = 0;
        foreach (self::DAILY_MISSIONS as $mission) {
            $db->run(
                'INSERT IGNORE INTO season_pass_missions
                    (uid, season_id, mission_day, slot, mission_key, mission_type, points, progress, target, rerolled)
                 VALUES (?, ?, ?, ?, ?, \'daily\', ?, 0, ?, 0)',
                [$uid, $seasonId, $day, $slot, $mission['key'], $mission['points'], $mission['target']]
            );
            $slot++;
        }

        $pool = self::RANDOM_MISSION_POOL;
        $seed = crc32($uid . ':' . $seasonId . ':' . $day);
        mt_srand($seed);
        shuffle($pool);
        mt_srand();
        foreach (array_slice($pool, 0, 3) as $mission) {
            $db->run(
                'INSERT IGNORE INTO season_pass_missions
                    (uid, season_id, mission_day, slot, mission_key, mission_type, points, progress, target, rerolled)
                 VALUES (?, ?, ?, ?, ?, \'random\', ?, 0, ?, 0)',
                [$uid, $seasonId, $day, $slot, $mission['key'], $mission['points'], $mission['target']]
            );
            $slot++;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getMissionsForDay(int $uid, int $seasonId, int $day): array
    {
        $statement = DB::getInstance()->run(
            'SELECT * FROM season_pass_missions
             WHERE uid=? AND season_id=? AND mission_day=?
             ORDER BY slot ASC',
            [$uid, $seasonId, $day]
        );
        if ($statement === false) {
            return [];
        }

        $missions = [];
        $result = $statement->get_result();
        while ($row = $result->fetch_assoc()) {
            $missions[] = $row;
        }

        return $missions;
    }

    private function completeMissionByKey(int $uid, int $seasonId, string $missionKey, int $increment): void
    {
        $day = $this->missionDay();
        $db = DB::getInstance();
        $mission = $this->fetchOne(
            'SELECT * FROM season_pass_missions
             WHERE uid=? AND season_id=? AND mission_day=? AND mission_key=? LIMIT 1',
            [$uid, $seasonId, $day, $missionKey]
        );
        if ($mission === null) {
            return;
        }
        if ((int)$mission['points_granted'] === 1) {
            return;
        }

        $progress = min((int)$mission['target'], (int)$mission['progress'] + max(1, $increment));
        $completedAt = $progress >= (int)$mission['target'] ? time() : null;
        $updated = $db->run(
            'UPDATE season_pass_missions SET progress=?, completed_at=IFNULL(completed_at, ?) WHERE id=? AND points_granted=0',
            [$progress, $completedAt, (int)$mission['id']]
        );
        if ($updated === false) {
            return;
        }

        if ($progress < (int)$mission['target']) {
            return;
        }

        if (!$db->begin_transaction()) {
            return;
        }

        try {
            $grant = $db->run(
                'UPDATE season_pass_missions SET points_granted=1, completed_at=IFNULL(completed_at, ?), progress=?
                 WHERE id=? AND points_granted=0',
                [time(), (int)$mission['target'], (int)$mission['id']]
            );
            if ($grant === false || $grant->affected_rows !== 1) {
                throw new \RuntimeException('Mission points already granted.');
            }
            $points = $db->run(
                'UPDATE season_pass_progress SET points=points+?, updated_at=? WHERE uid=? AND season_id=?',
                [(int)$mission['points'], time(), $uid, $seasonId]
            );
            if ($points === false) {
                throw new \RuntimeException('Could not add Season Pass points.');
            }
            if (!$db->commit()) {
                throw new \RuntimeException('Could not commit mission completion.');
            }
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            logError(
                'Season Pass mission completion failed uid %d key %s: %s',
                [$uid, $missionKey, $exception->getMessage()]
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getRewardsWithClaimState(int $uid, int $seasonId, int $points, bool $hasPremium): array
    {
        $statement = DB::getInstance()->run(
            'SELECT r.*, c.claimed_at
             FROM season_pass_rewards r
             LEFT JOIN season_pass_claims c
               ON c.reward_id=r.id AND c.uid=? AND c.season_id=r.season_id
             WHERE r.season_id=?
             ORDER BY r.tier_index ASC, r.track ASC',
            [$uid, $seasonId]
        );
        if ($statement === false) {
            return [];
        }

        $rewards = [];
        $result = $statement->get_result();
        while ($row = $result->fetch_assoc()) {
            $unlocked = $points >= (int)$row['points_required']
                && ($row['track'] === 'free' || $hasPremium);
            $claimed = $row['claimed_at'] !== null;
            $row['unlocked'] = $unlocked;
            $row['claimed'] = $claimed;
            $row['claimable'] = $unlocked && !$claimed;
            $rewards[] = $row;
        }

        return $rewards;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getShards(int $uid): array
    {
        $statement = DB::getInstance()->run(
            'SELECT shard_key, amount FROM season_pass_shards WHERE uid=? AND amount>0 ORDER BY shard_key ASC',
            [$uid]
        );
        if ($statement === false) {
            return [];
        }

        $shards = [];
        $result = $statement->get_result();
        while ($row = $result->fetch_assoc()) {
            $shards[] = $row;
        }

        return $shards;
    }

    private function addShard(int $uid, string $shardKey, int $amount, ?DB $db = null): void
    {
        $db ??= DB::getInstance();
        $amount = max(1, $amount);
        $updated = $db->run(
            'INSERT INTO season_pass_shards (uid, shard_key, amount, updated_at) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE amount=amount+VALUES(amount), updated_at=VALUES(updated_at)',
            [$uid, $shardKey, $amount, time()]
        );
        if ($updated === false) {
            throw new \RuntimeException('Could not store shard inventory.');
        }
    }

    private function consumeShard(int $uid, string $shardKey, int $amount, DB $db): bool
    {
        $amount = max(1, $amount);
        $updated = $db->run(
            'UPDATE season_pass_shards SET amount=amount-?, updated_at=?
             WHERE uid=? AND shard_key=? AND amount>=?',
            [$amount, time(), $uid, $shardKey, $amount]
        );

        return $updated !== false && $updated->affected_rows === 1;
    }

    private function addGovernorStars(int $uid, string $governorKey, int $amount, ?DB $db = null): void
    {
        $db ??= DB::getInstance();
        $amount = max(1, $amount);
        $updated = $db->run(
            'INSERT INTO season_pass_governor_progress (uid, governor_key, stars, updated_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE stars=stars+VALUES(stars), updated_at=VALUES(updated_at)',
            [$uid, $governorKey, $amount, time()]
        );
        if ($updated === false) {
            throw new \RuntimeException('Could not store governor stars.');
        }
    }

    private function getGovernorStars(int $uid, string $governorKey): int
    {
        if (!$this->schemaReady()) {
            return 0;
        }
        $row = $this->fetchOne(
            'SELECT stars FROM season_pass_governor_progress WHERE uid=? AND governor_key=? LIMIT 1',
            [$uid, $governorKey]
        );

        return $row === null ? 0 : (int)$row['stars'];
    }

    /**
     * @param list<int|string> $parameters
     * @return array<string, mixed>|null
     */
    private function fetchOne(string $query, array $parameters): ?array
    {
        $statement = DB::getInstance()->run($query, $parameters);
        if ($statement === false) {
            return null;
        }
        $row = $statement->get_result()->fetch_assoc();

        return is_array($row) ? $row : null;
    }
}
