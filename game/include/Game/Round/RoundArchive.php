<?php

declare(strict_types=1);

namespace Game\Round;

use Core\Config;
use Core\Database\DB;

/**
 * Writes the finished round into the rounds and round_standings tables before a reset clears the world:
 * the winner, the summary row, every player and alliance ranked by population, and the medals. The rows
 * are plain SQL so a hall-of-fame page can read them without knowing the game schema.
 */
final class RoundArchive
{
    /** Accounts above this id are players; 0 to 2 are Support, the Natars and Multihunter. */
    public const FIRST_PLAYER_ID = 3;

    /** Bots are never part of a standing. */
    private const BOT_ACCESS = 3;

    private const BATCH = 500;

    private function __construct()
    {
    }

    /**
     * @return int the id of the archived round
     */
    public static function write(string $accountMode, string $adminName): int
    {
        $db = DB::getInstance();
        $config = Config::getInstance();
        $dynamic = $config->dynamic;
        $winner = self::winner((int) $dynamic->serverFinished);
        $summary = $db->query('SELECT * FROM summary LIMIT 1');
        $summaryRow = $summary && $summary->num_rows ? $summary->fetch_assoc() : [];
        unset($summaryRow['id']);
        $players = self::players();

        $db->run(
            'INSERT INTO rounds (startTime, finishTime, resultCode, speed, mapSize, playerCount, winnerUid, winnerName, winnerAid, winnerAllianceName, summary, accountMode, archivedAt, archivedBy) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int) $dynamic->startTime,
                (int) $dynamic->serverFinishTime,
                (int) $dynamic->serverFinished,
                (float) $config->game->speed,
                (int) $dynamic->map_size,
                count($players),
                $winner['uid'],
                $winner['name'],
                $winner['aid'],
                $winner['allianceName'],
                json_encode($summaryRow, JSON_THROW_ON_ERROR),
                $accountMode,
                time(),
                mb_substr($adminName, 0, 100),
            ]
        );
        $roundId = (int) $db->lastInsertId();

        self::insertStandings($roundId, 'player', $players);
        self::insertStandings($roundId, 'alliance', self::alliances());
        self::insertStandings($roundId, 'medal', self::medals('medal', 'uid', 'users'));
        self::insertStandings($roundId, 'allimedal', self::medals('allimedal', 'aid', 'alidata'));

        return $roundId;
    }

    /**
     * The player who finished the World Wonder (result 1); nobody when the Natars did.
     *
     * @return array{uid: int|null, name: string|null, aid: int|null, allianceName: string|null}
     */
    private static function winner(int $resultCode): array
    {
        $none = ['uid' => null, 'name' => null, 'aid' => null, 'allianceName' => null];
        if ($resultCode !== 1) {
            return $none;
        }
        $db = DB::getInstance();
        $kid = $db->fetchScalar('SELECT kid FROM fdata WHERE f99=100 LIMIT 1');
        if ($kid === false) {
            return $none;
        }
        $row = self::rows(
            'SELECT u.id, u.name, u.aid, a.name AS allianceName FROM vdata v JOIN users u ON u.id=v.owner LEFT JOIN alidata a ON a.id=u.aid WHERE v.kid=?',
            [(int) $kid]
        )[0] ?? null;
        if ($row === null) {
            return $none;
        }

        return [
            'uid' => (int) $row['id'],
            'name' => (string) $row['name'],
            'aid' => (int) $row['aid'] > 0 ? (int) $row['aid'] : null,
            'allianceName' => $row['allianceName'] !== null ? (string) $row['allianceName'] : null,
        ];
    }

    /**
     * @return list<array{refId: int, name: string, value: int, detail: array<string, mixed>}>
     */
    private static function players(): array
    {
        $found = self::rows(
            'SELECT u.id, u.name, u.race, u.aid, a.name AS allianceName, a.tag, u.total_pop, u.total_villages, u.total_attack_points, u.total_defense_points, u.max_off_point, u.max_def_point
             FROM users u LEFT JOIN alidata a ON a.id=u.aid
             WHERE u.id >= ? AND u.access <> ? AND u.hidden=0
             ORDER BY u.total_pop DESC, u.id ASC',
            [self::FIRST_PLAYER_ID, self::BOT_ACCESS]
        );
        $rows = [];
        foreach ($found as $row) {
            $rows[] = [
                'refId' => (int) $row['id'],
                'name' => (string) $row['name'],
                'value' => (int) $row['total_pop'],
                'detail' => [
                    'race' => (int) $row['race'],
                    'aid' => (int) $row['aid'],
                    'alliance' => $row['allianceName'],
                    'tag' => $row['tag'],
                    'villages' => (int) $row['total_villages'],
                    'attack' => (int) $row['total_attack_points'],
                    'defense' => (int) $row['total_defense_points'],
                    'maxOff' => (int) $row['max_off_point'],
                    'maxDef' => (int) $row['max_def_point'],
                ],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{refId: int, name: string, value: int, detail: array<string, mixed>}>
     */
    private static function alliances(): array
    {
        $found = self::rows(
            'SELECT a.id, a.name, a.tag, a.total_attack_points, a.total_defense_points, COUNT(u.id) AS members, COALESCE(SUM(u.total_pop), 0) AS pop
             FROM alidata a JOIN users u ON u.aid=a.id AND u.id >= ? AND u.access <> ? AND u.hidden=0
             GROUP BY a.id
             ORDER BY pop DESC, a.id ASC',
            [self::FIRST_PLAYER_ID, self::BOT_ACCESS]
        );
        $rows = [];
        foreach ($found as $row) {
            $rows[] = [
                'refId' => (int) $row['id'],
                'name' => (string) $row['name'],
                'value' => (int) $row['pop'],
                'detail' => [
                    'tag' => (string) $row['tag'],
                    'members' => (int) $row['members'],
                    'attack' => (int) $row['total_attack_points'],
                    'defense' => (int) $row['total_defense_points'],
                ],
            ];
        }

        return $rows;
    }

    /**
     * @param 'medal'|'allimedal' $table
     * @param 'uid'|'aid' $ownerColumn
     * @param 'users'|'alidata' $ownerTable
     * @return list<array{refId: int, name: string, value: int, detail: array<string, mixed>, position: int}>
     */
    private static function medals(string $table, string $ownerColumn, string $ownerTable): array
    {
        $found = self::rows(
            "SELECT m.$ownerColumn AS ref, o.name, m.category, m.week, m.rank, m.points, m.img FROM $table m JOIN $ownerTable o ON o.id=m.$ownerColumn ORDER BY m.week, m.category, m.rank"
        );
        $rows = [];
        foreach ($found as $row) {
            $rows[] = [
                'refId' => (int) $row['ref'],
                'name' => (string) $row['name'],
                'value' => (int) $row['points'],
                'position' => (int) $row['rank'],
                'detail' => ['category' => (int) $row['category'], 'week' => (int) $row['week'], 'img' => (string) $row['img'], 'points' => (string) $row['points']],
            ];
        }

        return $rows;
    }

    /**
     * @param list<int|string> $parameters
     * @return list<array<string, mixed>>
     */
    private static function rows(string $sql, array $parameters = []): array
    {
        $statement = DB::getInstance()->run($sql, $parameters);
        if ($statement === false) {
            throw new \RuntimeException('The round archive could not read the world.');
        }
        $rows = [];
        $result = $statement->get_result();
        while ($result && ($row = $result->fetch_assoc())) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param 'player'|'alliance'|'medal'|'allimedal' $kind
     * @param list<array{refId: int, name: string, value: int, detail: array<string, mixed>, position?: int}> $rows
     */
    private static function insertStandings(int $roundId, string $kind, array $rows): void
    {
        $db = DB::getInstance();
        foreach (array_chunk($rows, self::BATCH, true) as $chunk) {
            $values = [];
            $parameters = [];
            foreach ($chunk as $index => $row) {
                $values[] = '(?, ?, ?, ?, ?, ?, ?)';
                array_push(
                    $parameters,
                    $roundId,
                    $kind,
                    $row['position'] ?? $index + 1,
                    $row['refId'],
                    mb_substr($row['name'], 0, 30),
                    $row['value'],
                    json_encode($row['detail'], JSON_THROW_ON_ERROR)
                );
            }
            $db->run('INSERT INTO round_standings (roundId, kind, position, refId, name, value, detail) VALUES ' . implode(', ', $values), $parameters);
        }
    }
}
