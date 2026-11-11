<?php

declare(strict_types=1);

namespace Game;

use Core\Caching\Caching;
use Core\Config;
use Core\Database\DB;
use Model\StatisticsModel;

use function getGameSpeed;

/**
 * Aggregate, public-only numbers for the world stats page (`/homepage/world`).
 *
 * Only what any signed-in player can already read in the rankings is exposed,
 * and only as aggregates: counts, population per rank, and alliance names and
 * tags. No player name, id, e-mail, coordinate, village or activity ever
 * enters the snapshot. It is computed at most once per TTL and cached.
 */
final class WorldStatsSnapshot
{
    public const CACHE_KEY = 'Statistics:PublicWorld';
    public const TTL = 300;
    public const TOP_SIZE = 10;

    private function __construct()
    {
    }

    /**
     * @return array{
     *   startTime:int, ageDays:int, speed:int, players:int, alliances:int,
     *   topPlayers:list<array{rank:int,population:int}>,
     *   topAlliances:list<array{rank:int,name:string,tag:string,population:int}>,
     *   wonder:array{level:int,allianceName:string,allianceTag:string,artefactsAt:int,plansAt:int,finished:bool}
     * }
     */
    public static function get(): array
    {
        return Caching::getInstance()->returnCacheWithCallBack(self::CACHE_KEY, self::TTL, [], [self::class, 'compute']);
    }

    /** @return array<string, mixed> */
    public static function compute(): array
    {
        $db = DB::getInstance();
        $config = Config::getInstance();
        $public = (new StatisticsModel())->publicUserCondition('u');
        $startTime = (int) $config->game->start_time;

        $topPlayers = [];
        $result = $db->query('SELECT u.total_pop FROM users u WHERE ' . $public . ' ORDER BY u.total_pop DESC, u.id ASC LIMIT ' . self::TOP_SIZE);
        foreach ($result->fetch_all(MYSQLI_ASSOC) as $index => $row) {
            $topPlayers[] = ['rank' => $index + 1, 'population' => (int) $row['total_pop']];
        }

        $topAlliances = [];
        $result = $db->query(
            'SELECT a.name, a.tag, SUM(u.total_pop) AS population FROM alidata a JOIN users u ON u.aid = a.id WHERE ' . $public
            . ' GROUP BY a.id ORDER BY population DESC, a.id ASC LIMIT ' . self::TOP_SIZE
        );
        foreach ($result->fetch_all(MYSQLI_ASSOC) as $index => $row) {
            $topAlliances[] = [
                'rank' => $index + 1,
                'name' => (string) $row['name'],
                'tag' => (string) $row['tag'],
                'population' => (int) $row['population'],
            ];
        }

        return [
            'startTime' => $startTime,
            'ageDays' => max(0, intdiv(time() - $startTime, 86400)),
            'speed' => (int) getGameSpeed(),
            'players' => (int) $db->fetchScalar('SELECT COUNT(u.id) FROM users u WHERE ' . $public),
            'alliances' => (int) $db->fetchScalar('SELECT COUNT(DISTINCT u.aid) FROM users u JOIN alidata a ON a.id = u.aid WHERE ' . $public),
            'topPlayers' => $topPlayers,
            'topAlliances' => $topAlliances,
            'wonder' => self::wonder($public),
        ];
    }

    /** @return array{level:int,allianceName:string,allianceTag:string,artefactsAt:int,plansAt:int,finished:bool} */
    private static function wonder(string $public): array
    {
        $db = DB::getInstance();
        $config = Config::getInstance();
        $level = 0;
        $allianceName = '';
        $allianceTag = '';
        // Before construction opens the Natars' wonders are not part of the public list.
        $started = $config->timers->WWConstructStartTime > 0 && $config->timers->WWConstructStartTime < time();
        if ($started) {
            $row = $db->query(
                'SELECT f.f99 AS level, a.name, a.tag FROM vdata v JOIN fdata f ON f.kid = v.kid JOIN users u ON u.id = v.owner'
                . ' LEFT JOIN alidata a ON a.id = u.aid WHERE v.isWW = 1 AND ' . $public
                . ' ORDER BY f.f99 DESC, f.lastWWUpgrade ASC, v.kid ASC LIMIT 1'
            )->fetch_assoc();
            if ($row !== null && (int) $row['level'] > 0) {
                $level = (int) $row['level'];
                $allianceName = (string) $row['name'];
                $allianceTag = (string) $row['tag'];
            }
        }

        return [
            'level' => $level,
            'allianceName' => $allianceName,
            'allianceTag' => $allianceTag,
            'artefactsAt' => (int) $config->timers->ArtifactsReleaseTime,
            'plansAt' => (int) $config->timers->wwPlansReleaseTime,
            'finished' => !empty($config->dynamic->serverFinished),
        ];
    }
}
