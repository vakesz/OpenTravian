<?php

declare(strict_types=1);

namespace Model;

use Core\Config;
use Core\Database\DB;
use Core\Helper\TimezoneHelper;
use Core\Session;
use Game\Formulas;

use mysqli_result;

use function getCustom;

class StatisticsModel
{
    private DB $db;

    public function __construct()
    {
        $this->db = DB::getInstance();
    }

    /**
     * Public leaderboards must only include accounts that can participate in
     * the world.  In particular, the reserved Multihunter account and banned
     * accounts use access=0 and must not consume a rank, a page slot or a
     * village/hero position.
     */
    public function publicUserCondition(string $table = ''): string
    {
        $prefix = $table === '' ? '' : $table . '.';

        return $prefix . 'id > 1 AND ' . $prefix . 'hidden = 0 AND ' . $prefix . 'access > 0';
    }

    private function publicVillageCondition(string $table = ''): string
    {
        $prefix = $table === '' ? '' : $table . '.';

        return $prefix . 'owner > 1 AND ' . $prefix . 'hidden = 0 AND EXISTS (SELECT 1 FROM users WHERE users.id = '
            . $prefix . 'owner AND ' . $this->publicUserCondition('users') . ')';
    }

    /** Whether the Wonder of the World list has any entry to show (same visibility rule as the list itself). */
    public function hasWonderVillages(): bool
    {
        $config = Config::getInstance();
        $constructionStarted = $config->timers->WWConstructStartTime > 0 && $config->timers->WWConstructStartTime < time();

        return (int)$this->db->fetchScalar(
            'SELECT COUNT(kid) FROM vdata WHERE isWW=1' . ($constructionStarted ? '' : ' AND IF(owner=1, capital=0, TRUE)')
        ) > 0;
    }

    /** @return list<array{kid:int,owner:int,playerName:string,aid:int,allianceTag:string,name:string,level:int,attackArrival:?string}> */
    public function getWonderVillages(): array
    {
        $config = Config::getInstance();
        // Before construction starts, the Natars' wonder villages that are still their capitals stay hidden.
        $constructionStarted = $config->timers->WWConstructStartTime > 0 && $config->timers->WWConstructStartTime < time();
        $mainNatarsVillageID = Formulas::xy2kid(0, 0);
        $types = implode(",", [MovementsModel::ATTACKTYPE_NORMAL, MovementsModel::ATTACKTYPE_RAID,]);
        $wonderVillages = $this->db->query("SELECT v.kid, v.owner, f.f99 AS `wonder_level`, f.lastWWUpgrade AS `last_WWUpgrade`, f.wwname AS `wonder_name`,
            u.name AS `playerName`, u.aid, a.tag AS `allianceTag`,
            (SELECT MIN(m.end_time) FROM movement m WHERE m.kid=$mainNatarsVillageID AND m.to_kid=v.kid AND m.mode=0 AND m.attack_type IN($types)) AS `attackEnd`
            FROM vdata v
            LEFT JOIN fdata f ON f.kid=v.kid
            LEFT JOIN users u ON u.id=v.owner
            LEFT JOIN alidata a ON a.id=u.aid
            WHERE v.isWW=1" . ($constructionStarted ? '' : ' AND IF(v.owner=1, v.capital=0, TRUE)') . "
            ORDER BY `wonder_level` DESC, `last_WWUpgrade` ASC, v.kid ASC")->fetch_all(MYSQLI_ASSOC);
        $rows = [];
        foreach ($wonderVillages as $row) {
            $rows[] = [
                'kid' => (int)$row['kid'],
                'owner' => (int)$row['owner'],
                'playerName' => (string)$row['playerName'],
                'aid' => (int)$row['aid'],
                'allianceTag' => $row['aid'] ? (string)$row['allianceTag'] : '',
                'name' => (string)$row['wonder_name'],
                'level' => (int)$row['wonder_level'],
                'attackArrival' => $row['attackEnd'] === null ? null : TimezoneHelper::autoDateString((int) ((int) $row['attackEnd'] / 1000), true),
            ];
        }
        return $rows;
    }

    public function getFarmVillages(int $page, int $pageSize): string
    {
        $startIndex = ($page - 1) * $pageSize;
        $farmVillages = $this->db->query("SELECT * FROM vdata WHERE isFarm=1 ORDER BY name ASC LIMIT $startIndex, $pageSize")->fetch_all(MYSQLI_ASSOC);
        $x_style = null;
        $farms = '';
        $rowIndex = 0 + $startIndex;
        $direction = strtolower(getDirection());
        $kkk = 0;
        $farmList = new FarmListModel();
        foreach ($farmVillages as $row) {
            ++$rowIndex;
            $xy = Formulas::kid2xy($row['kid']);
            $farms .= '<tr class="hover">';
            $farms .= '<td>' . $rowIndex . '.</td>';
            $farms .= '<td class="pla"><a href="/karte.php?d=' . $row['kid'] . '">' . $row['name'] . '</a></td>';
            $farms .= '<td class="inhabitants" style="text-align: center;">' . $row['pop'] . '</td>';
            $farms .= '<td class="coords" style="text-align: center;"><a class="" href="/karte.php?x=' . $xy['x'] . '&amp;y=' . $xy['y'] . '">‎‭<span class="coordinates coordinatesWrapper coordinatesAligned coordinates' . $direction . '"><span class="coordinateX">(' . coordinateNumberHTML($xy['x']) . '</span><span class="coordinatePipe">|</span><span class="coordinateY">' . coordinateNumberHTML($xy['y']) . ')</span></span>‬‎</a></td>';
            $farms .= '<td class="buttons">';
            $exists = $farmList->getWhereIsKidInFarmList($row['kid'], Session::getInstance()->getPlayerId());
            $icon = '<img class="reportButton" ' . $x_style . ' src="' . get_gpack_cdn_mainPage_url() . gpack_image_path('report/raid-list-small.png', $direction) . '">';
            if ($exists !== false) {
                $farms .= '<button type="button" id="raidListGoldclub' . (++$kkk) . '" class="icon" title="' . T("map", "Add to farm list") . '||'
                    . sprintf(T("map", "Edit farm list (Village already in farm list x)"), $exists['name'])
                    . '" onclick="Travian.Game.FarmList.openSlotDialog({type: \'edit\', listId: ' . $exists['id'] . ', editSlotIds: [' . $exists['slotId'] . ']}); return false;">' . $icon . '</button>';
            } else {
                $list = $farmList->getVillageFarmList((int)Session::getInstance()->getKid(), Session::getInstance()->getPlayerId());
                if ($list === false) {
                    $farms .= '<button type="button" id="raidListGoldclub' . (++$kkk) . '" class="icon disabled" title="' . T("map", "Add to farm list") . '||' . T("map", "noFarmList") . '">' . $icon . '</button>';
                } else {
                    $farms .= '<button type="button" id="raidListGoldclub' . (++$kkk) . '" class="icon" title="' . T("map", "Add to farm list")
                        . '" onclick="Travian.Game.FarmList.openSlotDialog({type: \'create\', listId: ' . $list . ', coordinates: {x: ' . $xy['x'] . ', y: ' . $xy['y'] . '}}); return false;">' . $icon . '</button>';
                }
            }
            $farms .= ' <a href="/build.php?id=39&amp;tt=2&amp;z=' . $row['kid'] . '&c=4"><button type="button" title="' . T(
                "map",
                "sendTroops"
            ) . '" class="icon"><img class="reportButton" ' . $x_style . ' src="' . get_gpack_cdn_mainPage_url() . gpack_image_path('report/simulate-small.png', $direction) . '"></button></a>';
            $farms .= '</td>';
            $farms .= '</tr>';
        }
        return $farms;
    }

    /**
     * @return array{Registered: array<int, int>, Percent: array<int, float>}
     */
    public function getTribesStatus(): array
    {
        $m = new SummaryModel();
        $summary = $m->getSummary();
        $total = max(1, (int) $summary['players_count']);
        $registered = [];
        $percent = [];
        foreach (\Game\Tribes::SELECTABLE as $tribe) {
            $count = (int) ($summary[\Game\Tribes::summaryColumn($tribe)] ?? 0);
            $registered[$tribe] = $count;
            $percent[$tribe] = round($count / $total * 100, 1);
        }

        return ['Registered' => $registered, 'Percent' => $percent];
    }

    /** Population rank of a player, 0 when the player is not on the public board. */
    public function getPlayerRankById(int $playerId): int
    {
        $row = $this->firstRow("SELECT id, total_pop `score` FROM users WHERE id={$playerId} AND " . $this->publicUserCondition() . ' LIMIT 1');

        return $row === null ? 0 : $this->getPlayerRank((int) $row['id'], (int) $row['score']);
    }

    public function getPlayerRankByName(string $playerName): int
    {
        $row = $this->firstPreparedRow(
            'SELECT id, total_pop `score` FROM users WHERE name LIKE ? AND ' . $this->publicUserCondition() . ' LIMIT 1',
            [$playerName . '%']
        );

        return $row === null ? 0 : $this->getPlayerRank((int) $row['id'], (int) $row['score']);
    }

    private function getPlayerRank(int $playerId, int $score): int
    {
        $publicUsers = $this->publicUserCondition();
        $rank = $this->db->fetchScalar("SELECT (((SELECT COUNT(id) FROM users WHERE {$publicUsers} AND total_pop>{$score}))+(SELECT COUNT(id) FROM users WHERE {$publicUsers} AND id<{$playerId} AND total_pop={$score}) + 1) `rank`");

        return max((int) $rank, 1);
    }

    public function getPlayerListCount(): int
    {
        return (int) $this->db->fetchScalar('SELECT COUNT(id) FROM users WHERE ' . $this->publicUserCondition());
    }

    public function getPlayersPointsListCount(): int
    {
        return $this->getPlayerListCount();
    }

    public function getPlayersPointsById(int|string $playerId, bool $isDefense): int
    {
        $playerId = (int) $playerId;
        $field = $this->pointsColumn($isDefense);
        $row = $this->firstRow("SELECT id, {$field} `score` FROM users WHERE id={$playerId} AND " . $this->publicUserCondition() . ' LIMIT 1');

        return $row === null ? 0 : $this->getPlayersPointsRank((int) $row['id'], (int) $row['score'], $isDefense);
    }

    public function getPlayersPointsByName(string $playerName, bool $isDefense): int
    {
        $field = $this->pointsColumn($isDefense);
        $row = $this->firstPreparedRow(
            "SELECT id, {$field} `score` FROM users WHERE name LIKE ? AND " . $this->publicUserCondition() . ' LIMIT 1',
            [$playerName . '%']
        );

        return $row === null ? 0 : $this->getPlayersPointsRank((int) $row['id'], (int) $row['score'], $isDefense);
    }

    private function getPlayersPointsRank(int $playerId, int $score, bool $isDefense): int
    {
        $field = $this->pointsColumn($isDefense);
        $publicUsers = $this->publicUserCondition();

        return (int) $this->db->fetchScalar("SELECT ((SELECT COUNT(id) FROM users WHERE {$publicUsers} AND {$field}>{$score}) + (SELECT COUNT(id) FROM users WHERE {$publicUsers} AND id<{$playerId} AND {$field}={$score})) + 1 `rank`");
    }

    /** Weekly population change of an alliance, false when it does not exist. */
    public function getAllianceClimbersPointById(int $id): string|false
    {
        $points = $this->db->fetchScalar("SELECT week_pop_changes AS `points` FROM alidata WHERE id=$id");

        return $points === false || $points === null ? false : (string) $points;
    }

    public function getAllianceRankByName(string $allianceName): int|false
    {
        $row = $this->firstPreparedRow(
            'SELECT id, (SELECT SUM(total_pop) FROM users WHERE alidata.id=users.aid) `score` FROM alidata WHERE tag LIKE ? LIMIT 1',
            [$allianceName . '%']
        );

        return $row === null ? false : $this->getAllianceRank((int) $row['id'], (int) $row['score']);
    }

    public function getAllianceRankById(int $allianceId): int|false
    {
        $row = $this->firstRow("SELECT id, (SELECT SUM(total_pop) FROM users WHERE alidata.id=users.aid) `score` FROM alidata WHERE id={$allianceId} LIMIT 1");

        return $row === null ? false : $this->getAllianceRank((int) $row['id'], (int) $row['score']);
    }

    private function getAllianceRank(int $allianceId, int $score): int
    {
        return (int) $this->db->fetchScalar("SELECT ( (SELECT COUNT(id) FROM alidata WHERE (SELECT SUM(total_pop) FROM users WHERE alidata.id=users.aid)>{$score}) + (SELECT COUNT(id) FROM alidata WHERE (SELECT SUM(total_pop) FROM users WHERE alidata.id=users.aid)={$score} AND id<{$allianceId}) ) + 1 `rank`");
    }

    public function getAlliancePointsRankByName(string $allianceName, bool $isDefense): int|false
    {
        $field = $this->pointsColumn($isDefense);
        $row = $this->firstPreparedRow("SELECT id, {$field} `score` FROM alidata WHERE tag LIKE ? LIMIT 1", [$allianceName . '%']);

        return $row === null ? false : $this->getAlliancePointsRank((int) $row['id'], (int) $row['score'], $isDefense);
    }

    public function getAlliancePointsRankById(int|string $allianceId, bool $isDefense): int|false
    {
        $allianceId = (int) $allianceId;
        $field = $this->pointsColumn($isDefense);
        $row = $this->firstRow("SELECT id, {$field} `score` FROM alidata WHERE id={$allianceId} LIMIT 1");

        return $row === null ? false : $this->getAlliancePointsRank((int) $row['id'], (int) $row['score'], $isDefense);
    }

    private function getAlliancePointsRank(int $allianceId, int $score, bool $isDefense): int
    {
        $field = $this->pointsColumn($isDefense);

        return (int) $this->db->fetchScalar("SELECT ( (SELECT COUNT(id) FROM alidata WHERE {$field}>{$score}) + (SELECT COUNT(id) FROM alidata WHERE {$field}={$score} AND id<{$allianceId}) ) + 1 `rank`");
    }

    public function getVillageRankByName(string $villageName): int|false
    {
        $row = $this->firstPreparedRow(
            'SELECT kid, pop AS `score` FROM vdata WHERE ' . $this->publicVillageCondition() . ' AND name LIKE ? LIMIT 1',
            [$villageName . '%']
        );

        return $row === null ? false : $this->getVillageRank((int) $row['kid'], (int) $row['score']);
    }

    public function getVillageRankById(int $villageId): int|false
    {
        $row = $this->firstRow("SELECT kid, pop `score` FROM vdata WHERE kid={$villageId} AND " . $this->publicVillageCondition() . ' LIMIT 1');

        return $row === null ? false : $this->getVillageRank((int) $row['kid'], (int) $row['score']);
    }

    private function getVillageRank(int $villageId, int $score): int
    {
        $publicVillages = $this->publicVillageCondition();

        return (int) $this->db->fetchScalar("SELECT ((SELECT COUNT(kid) FROM vdata WHERE {$publicVillages} AND pop>{$score}) + (SELECT COUNT(kid) FROM vdata WHERE {$publicVillages} AND pop={$score} AND kid>{$villageId})) + 1 `rank`");
    }

    public function getHeroListCount(): int
    {
        return (int) $this->db->fetchScalar('SELECT COUNT(hero.uid) FROM hero INNER JOIN users ON users.id = hero.uid WHERE ' . $this->publicUserCondition('users'));
    }

    public function getHeroRankById(int $playerId): int|false
    {
        $row = $this->firstRow("SELECT hero.uid, hero.exp AS `score` FROM hero INNER JOIN users ON users.id = hero.uid WHERE hero.uid={$playerId} AND " . $this->publicUserCondition('users') . ' LIMIT 1');

        return $row === null ? false : $this->getHeroRank((int) $row['uid'], (int) $row['score']);
    }

    public function getHeroRankByName(string $playerName): int|false
    {
        $row = $this->firstPreparedRow('SELECT id FROM users WHERE name LIKE ? AND ' . $this->publicUserCondition() . ' LIMIT 1', [$playerName . '%']);

        return $row === null ? false : $this->getHeroRankById((int) $row['id']);
    }

    private function getHeroRank(int $playerId, int $score): int
    {
        $publicUsers = $this->publicUserCondition('users');

        return (int) $this->db->fetchScalar("SELECT ((SELECT COUNT(hero.uid) FROM hero INNER JOIN users ON users.id = hero.uid WHERE {$publicUsers} AND hero.exp>{$score}) + (SELECT COUNT(hero.uid) FROM hero INNER JOIN users ON users.id = hero.uid WHERE {$publicUsers} AND hero.exp={$score} AND hero.uid<{$playerId})) + 1 `rank`");
    }

    public function getTop10Climbers(bool $isPlayer): mysqli_result|bool
    {
        $publicUsers = $this->publicUserCondition('users');
        $totalPlayers = (int) $this->db->fetchScalar("SELECT COUNT(id) FROM users WHERE {$publicUsers}");
        if ($isPlayer) {
            if (getCustom("usePopulationAsClimbersRank")) {
                $rank = "cast(total_pop as SIGNED)-oldRank";
                $result = $this->db->query("SELECT b.id, b.name, b.total_villages, b.total_pop, ($rank) `points` FROM users b WHERE " . $this->publicUserCondition('b') . " AND ($rank)>0 ORDER BY ($rank) DESC, b.id ASC LIMIT 10");
            } else {
                $lastMedalsGiven = (int) Config::getProperty('dynamic', 'lastMedalsGiven');
                $cond1 = '(SELECT COUNT(id) FROM users WHERE ' . $publicUsers . ' AND users.total_pop > b.total_pop)';
                $cond2 = '(SELECT COUNT(id) FROM users WHERE ' . $publicUsers . ' AND users.id < b.id AND users.total_pop=b.total_pop)';
                $rank = "IF(b.signupTime > $lastMedalsGiven, $totalPlayers, oldRank)-(SELECT $cond1+$cond2)";
                $result = $this->db->query("SELECT b.id, b.name, b.total_villages, b.total_pop, ($rank) AS `points` FROM users b WHERE " . $this->publicUserCondition('b') . " AND ($rank)>0 ORDER BY points DESC, b.id ASC LIMIT 10");
            }
        } else {
            $result = $this->db->query("SELECT id, tag, week_pop_changes AS `points` FROM alidata WHERE week_pop_changes>0 ORDER BY `points` DESC, id ASC LIMIT 10");
        }
        return $result;
    }

    public function getTop10(bool $isPlayer, string $columnName): mysqli_result|bool
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $columnName)) {
            throw new \InvalidArgumentException('Invalid statistics column.');
        }
        $table = $isPlayer ? "users" : "alidata";
        $publicPlayers = $isPlayer ? ' AND ' . $this->publicUserCondition() : '';
        if ($columnName === 'max_off_point' || $columnName === 'max_def_point') {
            if ($columnName === 'max_off_point') {
                $result = $this->db->query("SELECT id, max_off_time, max_off_nid, " . ($isPlayer ? "name" : "tag") . ", {$columnName} `points` FROM {$table} WHERE {$columnName}>0{$publicPlayers} ORDER BY {$columnName} DESC, id ASC LIMIT 5");
            } else {
                $result = $this->db->query("SELECT id, max_def_time, max_def_nid, " . ($isPlayer ? "name" : "tag") . ", {$columnName} `points` FROM {$table} WHERE {$columnName}>0{$publicPlayers} ORDER BY {$columnName} DESC, id ASC LIMIT 5");
            }
        } else {
            $result = $this->db->query("SELECT id, " . ($isPlayer ? "name, race" : "tag") . ", {$columnName} `points` FROM {$table} WHERE {$columnName}>0{$publicPlayers} ORDER BY {$columnName} DESC, id ASC LIMIT 10");
        }
        return $result;
    }

    public function getPlayerList(int $pageIndex, int $pageSize): mysqli_result|bool
    {
        $from = $pageIndex * $pageSize;
        $to = $pageSize;
        $result = $this->db->query("SELECT id, race, access, name, countryFlag, last_login_time, aid, IF(aid>0, (SELECT tag FROM alidata WHERE id=users.aid LIMIT 1), 0) `alliance_name`, total_pop `total_population`, total_villages `total_villages_count` FROM users WHERE " . $this->publicUserCondition() . " ORDER BY total_pop DESC, id ASC LIMIT {$from}, {$to}");
        return $result;
    }

    public function getPlayersPointsList(int $pageIndex, int $pageSize, bool $isDefense): mysqli_result|bool
    {
        $field = $this->pointsColumn($isDefense);
        $from = $pageIndex * $pageSize;
        $to = $pageSize;
        $result = $this->db->query("SELECT id, race, aid, IF(aid>0, (SELECT tag FROM alidata WHERE id=users.aid LIMIT 1), 0) `alliance_name`, name, total_pop `total_population`, total_villages `total_villages_count`, $field `points` FROM users WHERE " . $this->publicUserCondition() . " ORDER BY $field DESC, id ASC LIMIT $from,$to");
        return $result;
    }

    public function getHerosList(int $pageIndex, int $pageSize): mysqli_result|bool
    {
        $from = $pageIndex * $pageSize;
        $to = $pageSize;
        $result = $this->db->query("SELECT hero.uid, users.name AS `player_name`, users.race, users.aid, IF(users.aid>0, (SELECT tag FROM alidata WHERE id=users.aid LIMIT 1), 0) `alliance_name`, hero.exp FROM hero INNER JOIN users ON users.id = hero.uid WHERE " . $this->publicUserCondition('users') . " ORDER BY hero.exp DESC, hero.uid ASC LIMIT $from,$to");
        return $result;
    }

    public function getAllianceListCount(): int
    {
        return (int) $this->db->fetchScalar('SELECT COUNT(id) FROM alidata');
    }

    public function getAlliancesList(int $pageIndex, int $pageSize): mysqli_result|bool
    {
        $from = $pageIndex * $pageSize;
        $to = $pageSize;
        $result = $this->db->query("SELECT
    id,
    tag,
    (SELECT SUM(total_pop) FROM users
WHERE alidata.id=users.aid) AS `points`,
(SELECT COUNT(id) FROM users WHERE users.aid=alidata.id) AS `memcount`

FROM alidata
ORDER BY points DESC
LIMIT $from,$to");
        return $result;
    }

    public function getAlliancePointsList(int $pageIndex, int $pageSize, bool $isDefense): mysqli_result|bool
    {
        $from = $pageIndex * $pageSize;
        $to = $pageSize;
        $field = $this->pointsColumn($isDefense);
        $result = $this->db->query("SELECT id, tag, (SELECT COUNT(id) FROM users WHERE users.aid=alidata.id) AS `memcount`, (SELECT SUM(total_pop) FROM users
WHERE alidata.id=users.aid) as `totalPop`, {$field} points  FROM alidata ORDER BY {$field} DESC LIMIT $from, $to");
        return $result;
    }

    public function getVillagesList(int $pageIndex, int $pageSize): mysqli_result|bool
    {
        $from = $pageSize * $pageIndex;
        $to = $pageSize;
        $result = $this->db->query("SELECT (SELECT name FROM users WHERE id=vdata.owner LIMIT 1) AS `player_name`, kid, owner, name, pop FROM vdata WHERE " . $this->publicVillageCondition() . " ORDER BY pop DESC, kid DESC LIMIT $from, $to");
        return $result;
    }

    public function getTotalVillagesCount(): int
    {
        return (int)$this->db->fetchScalar('SELECT COUNT(kid) FROM vdata WHERE ' . $this->publicVillageCondition());
    }

    public function getTotalFarmVillagesCount(): int
    {
        return (int) $this->db->fetchScalar('SELECT COUNT(kid) FROM vdata WHERE isFarm=1');
    }

    private function pointsColumn(bool $isDefense): string
    {
        return $isDefense ? 'total_defense_points' : 'total_attack_points';
    }

    /**
     * First row of a query whose interpolated values are all ints, read over the text protocol.
     *
     * @return array<string, mixed>|null
     */
    private function firstRow(string $query): ?array
    {
        $result = $this->db->query($query);
        $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;

        return is_array($row) ? $row : null;
    }

    /**
     * First row of a prepared query (used when player-typed text is bound).
     *
     * @param list<string> $parameters
     * @return array<string, mixed>|null
     */
    private function firstPreparedRow(string $query, array $parameters): ?array
    {
        $statement = $this->db->run($query, $parameters);
        $row = $statement === false ? null : $statement->get_result()->fetch_assoc();

        return is_array($row) ? $row : null;
    }
}
