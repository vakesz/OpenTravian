<?php

declare(strict_types=1);

namespace Model;

use Core\Config;
use Core\Database\DB;
use Core\Log;
use function getCustom;
use function getGame;

class MedalsModel
{
    public const TOP_ATTACKER = 'topAttacker';
    public const TOP_DEFENDER = 'topDefender';
    public const TOP_RAIDER = 'topRaider';
    public const TOP_CLIMBER = 'topClimber';

    /**
     * Weekly top-10 boards, in award order: the board's medal category, its
     * StatisticsModel column (null for the climbers board), the image prefix
     * for player and alliance medals, the prize-bonus type, and for the
     * "3rd / 5th / 10th time" ribbons the medal categories and image
     * prefixes for ranks <= 3 and <= 10.
     */
    private const BOARDS = [
        'attack' => ['category' => 1, 'column' => 'week_attack_points', 'player' => 't2_', 'alliance' => 'a2_', 'bonus' => self::TOP_ATTACKER, 'top3' => [6, 't12'], 'top10' => [12, 't13']],
        'defense' => ['category' => 2, 'column' => 'week_defense_points', 'player' => 't3_', 'alliance' => 'a3_', 'bonus' => self::TOP_DEFENDER, 'top3' => [7, 't14'], 'top10' => [13, 't15']],
        'climbers' => ['category' => 3, 'column' => null, 'player' => 't1_', 'alliance' => 'a1_', 'bonus' => self::TOP_CLIMBER, 'top3' => [8, 't10'], 'top10' => [14, 't11']],
        'robber' => ['category' => 4, 'column' => 'week_robber_points', 'player' => 't4_', 'alliance' => 'a4_', 'bonus' => self::TOP_RAIDER, 'top3' => [9, 't16'], 'top10' => [15, 't17']],
    ];

    /** Appearance counts that earn a ribbon, with the image-name digit for each. */
    private const RIBBON_STEPS = [3 => 0, 5 => 1, 10 => 2];

    private function handlePrizeBonus(string $type, int $uid, int $rank): void
    {
        $bonus = Config::getInstance()->bonus->top10->{$type};
        if (!$bonus->enabled || !array_key_exists($rank, $bonus->ranks) || $bonus->ranks[$rank] <= 0) {
            return;
        }
        $gold = $bonus->ranks[$rank];
        DB::getInstance()->run('UPDATE users SET gift_gold=gift_gold+? WHERE id=?', [$gold, $uid]);
        (new MessageModel())->sendMessage(0, $uid, $type, $gold, 5);
    }

    public function resetMedals(): void
    {
        $db = DB::getInstance();
        $config = Config::getInstance();
        $lastMedalsGiven = (int) $db->fetchScalar('SELECT lastMedalsGiven FROM config');
        if ($lastMedalsGiven === 0 || $lastMedalsGiven <= (getGame('start_time') - 7 * 86400)) {
            if (getCustom('startMedalsAtStartOfWeek')) {
                $startTime = (int) $config->game->start_time;
                $lastMedalsGiven = (int) strtotime('Tuesday 00:00', $startTime);
                if ($lastMedalsGiven > time()) {
                    $lastMedalsGiven = (int) strtotime('next Tuesday -1 week 00:00', $startTime);
                }
            } else {
                $lastMedalsGiven = (int) $config->game->start_time;
            }
            $db->query("UPDATE config SET lastMedalsGiven=$lastMedalsGiven");
        }
        if ($lastMedalsGiven + $config->game->medals_interval > time()) {
            return;
        }
        if (getCustom('refillFarmsOnTop10Reset')) {
            $db->query('UPDATE vdata SET wood=wood+woodp*2, clay=clay+clayp*2, iron=iron+ironp*2, crop=crop+cropp*2 WHERE isFarm=1');
        }
        $db->query('UPDATE config SET lastMedalsGiven=' . ($lastMedalsGiven + $config->game->medals_interval));
        $week = (int) $db->fetchScalar('SELECT week FROM medal ORDER BY week DESC LIMIT 1', [], 0) + 1;
        $statistics = new StatisticsModel();

        $top = [];
        foreach (self::BOARDS as $name => $board) {
            $top[$name] = $this->topTen($statistics, true, $board['column']);
        }

        foreach (self::BOARDS as $name => $board) {
            $rank = 0;
            foreach ($top[$name] as $row) {
                if ($row['points'] <= 0) {
                    continue;
                }
                ++$rank;
                $this->addMedal(false, (int) $row['id'], $board['category'], $week, $rank, $row['points'], $board['player'] . $rank);
                $this->handlePrizeBonus($board['bonus'], (int) $row['id'], $rank);
            }
        }
        // The ribbons below store the last board's rank, as they always have.

        // Share bonus for attack + defense from top 10.
        foreach ($top['attack'] as $row) {
            if ($row['points'] <= 0) {
                continue;
            }
            foreach ($top['defense'] as $row2) {
                if ($row['id'] !== $row2['id']) {
                    continue;
                }
                $count = (int) $db->fetchScalar('SELECT COUNT(id) FROM medal WHERE uid=? AND category=5', [(int) $row['id']]);
                //look what color the ribbon must have
                if ($count <= 2) {
                    $this->addMedal(false, (int) $row['id'], 5, $week, $rank, $count + 1, "t22{$count}_1");
                }
            }
        }

        // You stand for the 3rd / 5th / 10th time in the top 3 and the top 10.
        foreach (self::BOARDS as $name => $board) {
            foreach ($top[$name] as $row) {
                if ($row['points'] <= 0) {
                    continue;
                }
                foreach (['top3' => 3, 'top10' => 10] as $key => $maxRank) {
                    $count = (int) $db->fetchScalar(
                        "SELECT COUNT(id) FROM medal WHERE uid=? AND category=? AND rank<=$maxRank",
                        [(int) $row['id'], $board['category']]
                    );
                    if (isset(self::RIBBON_STEPS[$count])) {
                        [$category, $prefix] = $board[$key];
                        $this->addMedal(false, (int) $row['id'], $category, $week, $rank, $count, $prefix . self::RIBBON_STEPS[$count] . '_1');
                    }
                }
            }
        }

        if (getCustom('usePopulationAsClimbersRank')) {
            $db->query('UPDATE users SET week_attack_points=0, week_defense_points=0, week_robber_points=0, oldRank=cast(total_pop AS SIGNED)');
        } else {
            $players = $db->query('SELECT id FROM users');
            while ($row = $players->fetch_assoc()) {
                $oldRank = (int) $statistics->getPlayerRankById((int) $row['id']);
                $db->query("UPDATE users SET week_attack_points=0, week_defense_points=0, week_robber_points=0, oldRank=$oldRank WHERE id={$row['id']}");
            }
        }
        $db->query('UPDATE users SET week_alliance_training_contributions=0, week_alliance_armor_contributions=0, week_alliance_cp_contributions=0, week_alliance_trade_contributions=0');

        foreach (self::BOARDS as $board) {
            $rank = 0;
            foreach ($this->topTen($statistics, false, $board['column']) as $row) {
                if ($row['points'] <= 0) {
                    continue;
                }
                ++$rank;
                $this->addMedal(true, (int) $row['id'], $board['category'], $week, $rank, $row['points'], $board['alliance'] . $rank);
            }
        }
        $alliances = $db->query('SELECT id FROM alidata');
        while ($row = $alliances->fetch_assoc()) {
            $oldPop = (int) $db->fetchScalar('SELECT SUM(total_pop) FROM users WHERE users.aid=?', [(int) $row['id']]);
            $db->query("UPDATE alidata SET week_attack_points=0, week_defense_points=0, week_robber_points=0, week_pop_changes=0, oldPop=$oldPop WHERE id={$row['id']}");
        }
        Log::addLog(0, 'Top10Reset', 'Top10 has been reseted.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topTen(StatisticsModel $statistics, bool $players, ?string $column): array
    {
        $result = $column === null ? $statistics->getTop10Climbers($players) : $statistics->getTop10($players, $column);

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    private function addMedal(bool $alliance, int $uid, int $category, int $week, int $rank, int|string $points, string $img): void
    {
        DB::getInstance()->run(
            $alliance
                ? 'INSERT INTO allimedal (aid, category, week, rank, points, img) VALUES (?, ?, ?, ?, ?, ?)'
                : 'INSERT INTO medal (uid, category, week, rank, points, img) VALUES (?, ?, ?, ?, ?, ?)',
            [$uid, $category, $week, $rank, $points, $img]
        );
    }
}
