<?php

declare(strict_types=1);

namespace Model;

use Core\Database\DB;
use mysqli_result;

class AllianceBonusModel
{
    const TYPE_TRAINING = 1;
    const TYPE_ARMOR = 2;
    const TYPE_CP = 3;
    const TYPE_TRADE = 4;
    const LEVEL_PARAMS = [
        self::TYPE_TRAINING => 'training_bonus_level',
        self::TYPE_ARMOR    => 'armor_bonus_level',
        self::TYPE_CP       => 'cp_bonus_level',
        self::TYPE_TRADE    => 'trade_bonus_level',
    ];
    const ALLIANCE_CONTRIBUTION_PARAMS = [
        self::TYPE_TRAINING => 'training_bonus_contributions',
        self::TYPE_ARMOR    => 'armor_bonus_contributions',
        self::TYPE_CP       => 'cp_bonus_contributions',
        self::TYPE_TRADE    => 'trade_bonus_contributions',
    ];
    const USER_WEEK_CONTRIBUTION_PARAMS = [
        self::TYPE_TRAINING => 'week_alliance_training_contributions',
        self::TYPE_ARMOR    => 'week_alliance_armor_contributions',
        self::TYPE_CP       => 'week_alliance_cp_contributions',
        self::TYPE_TRADE    => 'week_alliance_trade_contributions',
    ];
    const USER_TOTAL_CONTRIBUTION_PARAMS = [
        self::TYPE_TRAINING => 'total_alliance_training_contributions',
        self::TYPE_ARMOR    => 'total_alliance_armor_contributions',
        self::TYPE_CP       => 'total_alliance_cp_contributions',
        self::TYPE_TRADE    => 'total_alliance_trade_contributions',
    ];
    const USER_UNLOCK_PENDING_ANIMATION = [
        self::TYPE_TRAINING => 'pending_training_alliance_bonus_unlock_animation',
        self::TYPE_ARMOR    => 'pending_armor_alliance_bonus_unlock_animation',
        self::TYPE_CP       => 'pending_cp_alliance_bonus_unlock_animation',
        self::TYPE_TRADE    => 'pending_trade_alliance_bonus_unlock_animation',
    ];

    public function isUnlockingNextLevel(int $aid, int $type): bool
    {
        return (int) DB::getInstance()->fetchScalar(
            'SELECT COUNT(id) FROM alliance_bonus_upgrade_queue WHERE aid=? AND type=?',
            [$aid, $type]
        ) > 0;
    }

    public function setAnimationAsFinished(int $uid, int $type): void
    {
        $column = self::USER_UNLOCK_PENDING_ANIMATION[$type];
        DB::getInstance()->run("UPDATE users SET $column=0 WHERE id=?", [$uid]);
    }

    public function levelUpBonus(int $aid, int $type): void
    {
        $db = DB::getInstance();
        $pending = self::USER_UNLOCK_PENDING_ANIMATION[$type];
        $level = self::LEVEL_PARAMS[$type];
        $db->run("UPDATE alidata SET $level=$level+1 WHERE id=?", [$aid]);
        $db->run("UPDATE users SET $pending=1 WHERE aid=?", [$aid]);
    }

    public function getMaxAllianceBonusLevel(int $aid): int
    {
        return max($this->getAllianceBonusesLevel($aid) ?? [0]);
    }

    public function getMinAllianceBonusLevel(int $aid): int
    {
        return min($this->getAllianceBonusesLevel($aid) ?? [0]);
    }

    /**
     * @return array{level: int, contributions: int}|false
     */
    public function getAllianceBonusTypeParams(int $aid, int $type): array|false
    {
        $levelColumn = self::LEVEL_PARAMS[$type];
        $contributionColumn = self::ALLIANCE_CONTRIBUTION_PARAMS[$type];
        $result = DB::getInstance()->run("SELECT $levelColumn, $contributionColumn FROM alidata WHERE id=?", [$aid]);
        $alliance = $result === false ? null : $result->get_result()->fetch_assoc();
        if (!is_array($alliance)) {
            return false;
        }

        return [
            'level' => (int) $alliance[$levelColumn],
            'contributions' => (int) $alliance[$contributionColumn],
        ];
    }

    public function contribute(int $aid, int $uid, int $type, int $resources): bool
    {
        if ($aid <= 0 || $uid <= 0 || $resources <= 0
            || !isset(self::USER_WEEK_CONTRIBUTION_PARAMS[$type])) {
            return false;
        }

        $db = DB::getInstance();
        $week_type = self::USER_WEEK_CONTRIBUTION_PARAMS[$type];
        $overall_type = self::USER_TOTAL_CONTRIBUTION_PARAMS[$type];
        $column = self::ALLIANCE_CONTRIBUTION_PARAMS[$type];
        $questTarget = DailyQuestModel::getAllianceContributionNeededForQuest();

        $alliance = $db->run("UPDATE alidata SET $column=$column+? WHERE id=?", [$resources, $aid]);
        $quest = $db->run(
            'UPDATE daily_quest
             SET qst11=LEAST(FLOOR((alliance_contribution+?)/?), 6),
                 alliance_contribution=alliance_contribution+?
             WHERE uid=?',
            [$resources, $questTarget, $resources, $uid]
        );
        $user = $db->run(
            "UPDATE users
             SET alliance_contributions=alliance_contributions+?,
                 $week_type=$week_type+?,
                 $overall_type=$overall_type+?
             WHERE id=? AND aid=?",
            [$resources, $resources, $resources, $uid, $aid]
        );

        return $alliance !== false && $quest !== false && $user !== false
            && $db->affectedRows() === 1;
    }

    public function getContributorsOfTheWeek(int $aid, int $type): mysqli_result|bool
    {
        return $this->topContributors($aid, self::USER_WEEK_CONTRIBUTION_PARAMS[$type]);
    }

    /**
     * @return array<string, int>|null level column => level
     */
    public function getAllianceBonusesLevel(int $aid): ?array
    {
        $result = DB::getInstance()->run('SELECT ' . implode(',', self::LEVEL_PARAMS) . ' FROM alidata WHERE id=?', [$aid]);
        $levels = $result === false ? null : $result->get_result()->fetch_assoc();

        return is_array($levels) ? array_map('intval', $levels) : null;
    }

    public function getAllTimeContributors(int $aid, int $type): mysqli_result|bool
    {
        return $this->topContributors($aid, self::USER_TOTAL_CONTRIBUTION_PARAMS[$type]);
    }

    private function topContributors(int $aid, string $column): mysqli_result|bool
    {
        $statement = DB::getInstance()->run(
            "SELECT id, name, $column `points` FROM users WHERE aid=? AND $column > 0 ORDER BY $column DESC LIMIT 5",
            [$aid]
        );

        return $statement === false ? false : $statement->get_result();
    }
}
