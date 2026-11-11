<?php

namespace Model;

use Core\Config;
use Core\Database\DB;
use Game\AllianceBonus\AllianceBonus;
use Game\Formulas;
use Game\Hero\HeroHelper;
use Game\Tribes;

/**
 * Data behind the General statistics dashboard (/statistics/general).
 *
 * Everything is derived from live tables except two series that need history:
 * the daily population-rank snapshot and the daily production snapshot in
 * `statistics_daily` (written by the engine job `gameProgress:statisticsSnapshot`).
 */
class StatisticsGeneralModel
{
    private const DAY = 86400;

    private DB $db;
    private StatisticsModel $statistics;

    public function __construct()
    {
        $this->db = DB::getInstance();
        $this->statistics = new StatisticsModel();
    }

    /** Midnight (UTC) of the world start day; day 0 of the progression charts. */
    public static function worldDayStart(): int
    {
        return intdiv((int)getGame('start_time'), self::DAY) * self::DAY;
    }

    public static function currentDay(?int $now = null): int
    {
        return max(0, intdiv(($now ?? time()) - self::worldDayStart(), self::DAY));
    }

    /** Engine job: upsert today's rank and production rows for every public player. */
    public function snapshotDaily(): void
    {
        $day = self::currentDay();
        $users = $this->db->query(
            'SELECT u.id, u.aid, u.cp, u.lastupdate, u.alliance_join_time, COALESCE(a.cp_bonus_level, 0) AS cp_bonus_level'
            . ' FROM users u LEFT JOIN alidata a ON a.id=u.aid WHERE ' . $this->statistics->publicUserCondition('u')
            . ' ORDER BY u.total_pop DESC, u.id ASC'
        )->fetch_all(MYSQLI_ASSOC);
        if (!$users) {
            return;
        }
        $production = $this->productionPerDay();
        $culturePoints = $this->culturePointMetrics($users);
        $allianceRank = [];
        $values = [];
        foreach ($users as $index => $user) {
            $aid = (int)$user['aid'];
            $allianceRank[$aid] = ($allianceRank[$aid] ?? 0) + 1;
            $prod = $production[(int)$user['id']] ?? [0, 0, 0, 0];
            $values[] = sprintf(
                '(%d,%d,%d,%d,%d,%d,%d,%d,%d,%d)',
                (int)$user['id'],
                $day,
                $index + 1,
                $aid > 0 ? $allianceRank[$aid] : 0,
                $prod[0],
                $prod[1],
                $prod[2],
                $prod[3],
                (int)($culturePoints[(int)$user['id']]['perDay'] ?? 0),
                (int)($culturePoints[(int)$user['id']]['soFar'] ?? 0)
            );
        }
        foreach (array_chunk($values, 500) as $chunk) {
            $this->db->query(
                'INSERT INTO statistics_daily (uid, day, pop_rank, alliance_pop_rank, prod1, prod2, prod3, prod4, cp_per_day, cp_so_far) VALUES ' . implode(',', $chunk)
                . ' ON DUPLICATE KEY UPDATE pop_rank=VALUES(pop_rank), alliance_pop_rank=VALUES(alliance_pop_rank), prod1=VALUES(prod1), prod2=VALUES(prod2), prod3=VALUES(prod3), prod4=VALUES(prod4), cp_per_day=VALUES(cp_per_day), cp_so_far=VALUES(cp_so_far)'
            );
        }
    }

    /** @return array<int,array{0:int,1:int,2:int,3:int}> production per day (hourly production x 24) per player */
    private function productionPerDay(): array
    {
        $rows = $this->db->query(
            'SELECT owner, SUM(woodp) w, SUM(clayp) c, SUM(ironp) i, SUM(cropp) k FROM vdata WHERE owner > 1 AND hidden = 0 GROUP BY owner'
        )->fetch_all(MYSQLI_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row['owner']] = [(int)$row['w'] * 24, (int)$row['c'] * 24, (int)$row['i'] * 24, (int)$row['k'] * 24];
        }

        return $result;
    }

    /**
     * @return array<string,mixed> the view model rendered by Templates/statistics/general.php
     */
    public function viewData(int $uid): array
    {
        $day = self::currentDay();
        $users = $this->db->query(
            'SELECT u.id, u.aid, u.race, u.total_pop, u.cp, u.cp_prod, u.lastupdate, u.alliance_join_time, '
            . 'u.stat_trade_in, u.stat_trade_out, COALESCE(a.cp_bonus_level, 0) AS cp_bonus_level '
            . 'FROM users u LEFT JOIN alidata a ON a.id=u.aid WHERE ' . $this->statistics->publicUserCondition('u')
            . ' ORDER BY u.total_pop DESC, u.id ASC'
        )->fetch_all(MYSQLI_ASSOC);
        $byId = [];
        foreach ($users as $user) {
            $byId[(int)$user['id']] = $user;
        }
        $me = $byId[$uid] ?? null;
        $aid = $me ? (int)$me['aid'] : 0;
        $culturePoints = $this->culturePointMetrics($users);

        return [
            'serverProgression' => $this->serverProgression($day, count($users)),
            'tribeDistribution' => $this->tribeDistribution(),
            'populationRank' => $this->populationRank($uid, $day, $users, $aid),
            'resourceRank' => $this->resourceRank($uid, $users, $aid),
            'villageStrength' => $this->villageStrength($uid, $aid),
            'culturePointsRank' => $this->culturePointsRank($uid, $users, $aid, $culturePoints, $day),
            'hasPlayer' => $me !== null,
        ];
    }

    /**
     * CP production is not the cached users.cp_prod field. A repair script can
     * include a helmet in that field while the live ticker adds the helmet at
     * runtime, so derive building production from its canonical village rows.
     *
     * @param list<array<string,mixed>> $users
     * @return array<int,array{buildings:int,helmet:int,alliance:int,perDay:int,perDayValue:float,soFar:int,soFarValue:float}>
     */
    private function culturePointMetrics(array $users): array
    {
        if ($users === []) {
            return [];
        }

        $ids = array_map(static fn (array $user): int => (int)$user['id'], $users);
        $idList = implode(',', $ids);
        $buildingRows = $this->db->query(
            'SELECT owner, COALESCE(SUM(cp), 0) AS production FROM vdata WHERE owner IN (' . $idList . ') GROUP BY owner'
        )->fetch_all(MYSQLI_ASSOC);
        $buildings = [];
        foreach ($buildingRows as $row) {
            $buildings[(int)$row['owner']] = (int)$row['production'];
        }

        $heroRows = $this->db->query(
            'SELECT h.uid, i.helmet FROM hero h LEFT JOIN inventory i ON i.uid=h.uid WHERE h.health>0 AND h.uid IN (' . $idList . ')'
        )->fetch_all(MYSQLI_ASSOC);
        $heroHelper = new HeroHelper();
        $helmets = [];
        foreach ($heroRows as $row) {
            $helmets[(int)$row['uid']] = (int)$heroHelper->calcMoreCulturePoints($row['helmet'] ?? null);
        }

        $now = time();
        $metrics = [];
        foreach ($users as $user) {
            $id = (int)$user['id'];
            $buildingProduction = $buildings[$id] ?? 0;
            $helmetProduction = $helmets[$id] ?? 0;
            $baseProduction = $buildingProduction + $helmetProduction;
            $multiplier = AllianceBonus::culturePointProductionMultiplier(
                (int)$user['cp_bonus_level'],
                (int)$user['alliance_join_time']
            );
            $allianceProduction = (int)round($baseProduction * ($multiplier - 1));
            $perDayValue = $baseProduction * $multiplier;
            $elapsed = max(0, $now - (int)$user['lastupdate']);
            $soFarValue = (float)$user['cp'] + $perDayValue / self::DAY * $elapsed;
            $metrics[$id] = [
                'buildings' => $buildingProduction,
                'helmet' => $helmetProduction,
                'alliance' => $allianceProduction,
                'perDay' => (int)round($perDayValue),
                'perDayValue' => $perDayValue,
                'soFar' => (int)round($soFarValue),
                'soFarValue' => $soFarValue,
            ];
        }

        return $metrics;
    }

    /**
     * @return array<string, mixed>
     */
    private function serverProgression(int $day, int $activatedPlayers): array
    {
        $config = Config::getInstance();
        $now = time();
        $dayStart = self::worldDayStart();
        $second = $this->db->query(
            'SELECT t.owner, t.created, u.name, u.race FROM (
                SELECT owner, created, ROW_NUMBER() OVER (PARTITION BY owner ORDER BY created ASC, kid ASC) rn FROM vdata WHERE owner > 1 AND hidden = 0
             ) t JOIN users u ON u.id = t.owner WHERE t.rn = 2 ORDER BY t.created ASC LIMIT 1'
        )->fetch_assoc();
        $firstWw = (int)$this->db->fetchScalar('SELECT MIN(lastWWUpgrade) FROM fdata WHERE f99 >= 1 AND lastWWUpgrade > 0');
        $artifacts = (int)$config->timers->ArtifactsReleaseTime;
        $plans = (int)$config->timers->wwPlansReleaseTime;
        $end = $dayStart + (int)getGame('round_length') * self::DAY;
        $steps = [
            ['id' => 'start', 'achieved' => $dayStart <= $now, 'time' => $dayStart, 'tribe' => 0, 'text' => null],
            ['id' => 'secondVillage', 'achieved' => (bool)$second, 'time' => $second ? (int)$second['created'] : 0, 'tribe' => $second ? (int)$second['race'] : 0, 'text' => $second ? $second['name'] : null],
            ['id' => 'artifacts', 'achieved' => $artifacts > 0 && $artifacts <= $now, 'time' => $artifacts, 'tribe' => 0, 'text' => null],
            ['id' => 'constructionPlans', 'achieved' => $plans > 0 && $plans <= $now, 'time' => $plans, 'tribe' => 0, 'text' => null],
            ['id' => 'firstWWLevelOne', 'achieved' => $firstWw > 0, 'time' => $firstWw, 'tribe' => 0, 'text' => null],
            ['id' => 'serverEnd', 'achieved' => $end <= $now, 'time' => $end, 'tribe' => 0, 'text' => null],
        ];

        return ['steps' => $steps, 'ageInDays' => $day, 'activatedPlayers' => $activatedPlayers];
    }

    /** @return list<array{tribe:int,percent:float}> */
    private function tribeDistribution(): array
    {
        $status = $this->statistics->getTribesStatus();
        $rows = [];
        $playable = Tribes::playable();
        sort($playable);
        foreach ($playable as $tribe) {
            $rows[] = ['tribe' => (int)$tribe, 'percent' => (float)($status['Percent'][$tribe] ?? 0)];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $users
     * @return array<string, mixed>
     */
    private function populationRank(int $uid, int $day, array $users, int $aid): array
    {
        $rank = 0;
        $allianceRank = 0;
        foreach ($users as $index => $user) {
            if ((int)$user['id'] === $uid) {
                $rank = $index + 1;
            }
            if ($aid > 0 && (int)$user['aid'] === $aid && $rank === 0) {
                $allianceRank++;
            }
        }
        if ($rank > 0 && $aid > 0) {
            $allianceRank++;
        }
        $history = $this->db->query(
            'SELECT day, pop_rank, alliance_pop_rank FROM statistics_daily WHERE uid=' . $uid . ' AND day < ' . $day . ' AND pop_rank > 0 ORDER BY day ASC'
        )->fetch_all(MYSQLI_ASSOC);
        $server = [];
        $alliance = [];
        foreach ($history as $row) {
            $server[] = ['day' => (int)$row['day'], 'rank' => (int)$row['pop_rank']];
            if ((int)$row['alliance_pop_rank'] > 0) {
                $alliance[] = ['day' => (int)$row['day'], 'rank' => (int)$row['alliance_pop_rank']];
            }
        }
        if ($rank > 0) {
            $server[] = ['day' => $day, 'rank' => $rank];
            if ($aid > 0) {
                $alliance[] = ['day' => $day, 'rank' => $allianceRank];
            }
        }

        return ['ageInDays' => $day, 'hasAlliance' => $aid > 0, 'serverRank' => $server, 'allianceRank' => $alliance, 'rank' => $rank, 'allianceCurrent' => $allianceRank];
    }

    /**
     * @param list<array<string, mixed>> $users
     * @return array<string, mixed>
     */
    private function resourceRank(int $uid, array $users, int $aid): array
    {
        $perDay = $this->productionPerDay();
        $soFarRows = $this->db->query(
            'SELECT uid, SUM(prod1) p1, SUM(prod2) p2, SUM(prod3) p3, SUM(prod4) p4 FROM statistics_daily WHERE day < ' . self::currentDay() . ' GROUP BY uid'
        )->fetch_all(MYSQLI_ASSOC);
        $soFar = [];
        foreach ($soFarRows as $row) {
            $soFar[(int)$row['uid']] = [(int)$row['p1'], (int)$row['p2'], (int)$row['p3'], (int)$row['p4']];
        }
        $bounty = [];
        $loot = $this->db->query('SELECT uid, type, bounty FROM ndata WHERE type BETWEEN 1 AND 7 AND isEnforcement = 0');
        while ($row = $loot->fetch_assoc()) {
            $parts = array_map('intval', explode(',', (string)$row['bounty']));
            $resources = (int)$parts[0] + (int)($parts[1] ?? 0) + (int)($parts[2] ?? 0) + (int)($parts[3] ?? 0);
            $key = (int)$row['uid'];
            $bounty[$key] ??= ['won' => 0, 'lost' => 0];
            $bounty[$key][(int)$row['type'] <= 3 ? 'won' : 'lost'] += $resources;
        }

        $figures = [];
        foreach ($users as $user) {
            $id = (int)$user['id'];
            $day = $perDay[$id] ?? [0, 0, 0, 0];
            $total = array_map(static fn ($a, $b) => $a + $b, $soFar[$id] ?? [0, 0, 0, 0], $day);
            $raidWon = $bounty[$id]['won'] ?? 0;
            $raidLost = $bounty[$id]['lost'] ?? 0;
            $figures[$id] = [
                'aid' => (int)$user['aid'],
                'production' => array_sum($total),
                'productionSoFar' => $total,
                'perDay' => $day,
                'raidWon' => $raidWon,
                'raidLost' => $raidLost,
                'tradeIn' => (int)$user['stat_trade_in'],
                'tradeOut' => (int)$user['stat_trade_out'],
            ];
            $figures[$id]['balance'] = $raidWon + $figures[$id]['tradeIn'] + $figures[$id]['production'] - $raidLost - $figures[$id]['tradeOut'];
            $figures[$id]['daily'] = array_sum($day);
        }
        $mine = $figures[$uid] ?? null;
        $empty = ['raidWon' => 0, 'raidLost' => 0, 'tradeIn' => 0, 'tradeOut' => 0, 'production' => 0, 'balance' => 0, 'daily' => 0, 'perDay' => [0, 0, 0, 0], 'productionSoFar' => [0, 0, 0, 0]];
        $mine ??= $empty + ['aid' => 0];

        $rankOf = static function (string $metric) use ($figures, $mine, $aid): array {
            $server = 1;
            $alliance = 1;
            foreach ($figures as $figure) {
                if ($figure[$metric] > $mine[$metric]) {
                    $server++;
                    if ($aid > 0 && $figure['aid'] === $aid) {
                        $alliance++;
                    }
                }
            }

            return ['server' => $server, 'alliance' => $alliance];
        };

        return [
            'positive' => ['raid' => $mine['raidWon'], 'trade' => $mine['tradeIn'], 'production' => $mine['production']],
            'negative' => ['raid' => $mine['raidLost'], 'trade' => $mine['tradeOut']],
            'balance' => $mine['balance'],
            'production' => ['soFar' => $mine['productionSoFar'], 'perDay' => $mine['perDay']],
            'bounty' => ['won' => $mine['raidWon'], 'lost' => $mine['raidLost']],
            'trade' => ['in' => $mine['tradeIn'], 'out' => $mine['tradeOut']],
            'rank' => $rankOf('balance'),
            'rankDaily' => $rankOf('daily'),
            'hasAlliance' => $aid > 0,
            'known' => $uid > 0 && isset($figures[$uid]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function villageStrength(int $uid, int $aid): array
    {
        $rows = $this->db->query(
            'SELECT v.kid, v.owner, v.name, v.seq, u.aid, u.race AS owner_race, un.race AS unit_race,
                    un.u1, un.u2, un.u3, un.u4, un.u5, un.u6, un.u7, un.u8, un.u9, un.u10
             FROM vdata v JOIN users u ON u.id = v.owner LEFT JOIN units un ON un.kid = v.kid
             WHERE v.owner > 1 AND v.hidden = 0 AND ' . $this->statistics->publicUserCondition('u') . ' ORDER BY v.owner ASC, v.seq ASC, v.kid ASC'
        )->fetch_all(MYSQLI_ASSOC);
        $villages = [];
        foreach ($rows as $row) {
            $race = (int)($row['unit_race'] ?: $row['owner_race']);
            $off = $defInfantry = $defCavalry = 0;
            for ($i = 1; $i <= 10; $i++) {
                $count = (int)($row['u' . $i] ?? 0);
                if ($count <= 0 || $race < 1) {
                    continue;
                }
                $stats = Formulas::uCombatStats(($race - 1) * 10 + $i);
                $off += $count * $stats['off'];
                $defInfantry += $count * $stats['def_i'];
                $defCavalry += $count * $stats['def_c'];
            }
            $villages[(int)$row['kid']] = [
                'kid' => (int)$row['kid'],
                'owner' => (int)$row['owner'],
                'aid' => (int)$row['aid'],
                'name' => $row['name'],
                'offence' => $off,
                'defence' => (int)round(($defInfantry + $defCavalry) / 2),
                'infantry' => $defInfantry,
                'cavalry' => $defCavalry,
            ];
        }
        $own = array_values(array_filter($villages, static fn ($v) => $v['owner'] === $uid));
        $result = ['totalVillages' => count($villages), 'villages' => []];
        foreach ($own as $village) {
            $rankings = [];
            foreach (['offence', 'defence'] as $metric) {
                $better = $worse = $allianceBetter = 0;
                foreach ($villages as $other) {
                    if ($other['kid'] === $village['kid']) {
                        continue;
                    }
                    if ($other[$metric] > $village[$metric]) {
                        $better++;
                        if ($aid > 0 && $other['aid'] === $aid) {
                            $allianceBetter++;
                        }
                    } elseif ($other[$metric] < $village[$metric]) {
                        $worse++;
                    }
                }
                $rankings[$metric] = [
                    'better' => $better,
                    'worse' => $worse,
                    'serverRank' => $better + 1,
                    'allianceRank' => $allianceBetter + 1,
                ];
            }
            $result['villages'][] = $village + ['rankings' => $rankings];
        }
        $result['hasAlliance'] = $aid > 0;

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $users
     * @param array<int, array<string, int|float>> $metrics
     * @return array<string, mixed>
     */
    private function culturePointsRank(int $uid, array $users, int $aid, array $metrics, int $day): array
    {
        $mine = $metrics[$uid] ?? [
            'buildings' => 0,
            'helmet' => 0,
            'alliance' => 0,
            'perDay' => 0,
            'perDayValue' => 0.0,
            'soFar' => 0,
            'soFarValue' => 0.0,
        ];
        $rank = static function (string $metric) use ($uid, $users, $aid, $metrics, $mine): array {
            $better = $worse = $allianceBetter = 0;
            foreach ($users as $user) {
                $otherId = (int)$user['id'];
                if ($otherId === $uid) {
                    continue;
                }
                $other = $metrics[$otherId][$metric . 'Value'] ?? 0.0;
                $value = $mine[$metric . 'Value'];
                if ($other > $value) {
                    $better++;
                    if ($aid > 0 && (int)$user['aid'] === $aid) {
                        $allianceBetter++;
                    }
                } elseif ($other < $value) {
                    $worse++;
                }
            }

            return [
                'better' => $better,
                'worse' => $worse,
                'serverRank' => $better + 1,
                'allianceRank' => $allianceBetter + 1,
            ];
        };
        $daily = $rank('perDay');
        $soFar = $rank('soFar');

        $history = $this->db->query(
            'SELECT day, cp_per_day, cp_so_far FROM statistics_daily WHERE uid=' . $uid
            . ' AND day < ' . $day . ' AND cp_per_day IS NOT NULL AND cp_so_far IS NOT NULL ORDER BY day ASC'
        )->fetch_all(MYSQLI_ASSOC);
        $dailyHistory = [];
        $soFarHistory = [];
        foreach ($history as $row) {
            $dailyHistory[] = ['day' => (int)$row['day'], 'value' => (int)$row['cp_per_day']];
            $soFarHistory[] = ['day' => (int)$row['day'], 'value' => (int)$row['cp_so_far']];
        }
        $dailyHistory[] = ['day' => $day, 'value' => $mine['perDay']];
        $soFarHistory[] = ['day' => $day, 'value' => $mine['soFar']];

        return [
            'hasAlliance' => $aid > 0,
            'totalPlayers' => count($users),
            'comparison' => ['perDay' => $daily, 'soFar' => $soFar],
            'distribution' => ['buildings' => $mine['buildings'], 'helmet' => $mine['helmet'], 'alliance' => $mine['alliance']],
            'progression' => ['perDay' => $dailyHistory, 'soFar' => $soFarHistory],
            'cpProduction' => $mine['perDay'],
            'cpProduced' => $mine['soFar'],
            'known' => isset($metrics[$uid]),
        ];
    }
}
