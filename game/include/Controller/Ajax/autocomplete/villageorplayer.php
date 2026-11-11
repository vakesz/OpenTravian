<?php

namespace Controller\Ajax\autocomplete;

use Core\Database\DB;
use Model\StatisticsModel;

use function array_map;
use function array_merge;
use function array_slice;

/**
 * Suggestions for the report list's "Player or village" filter: matching players first, then matching
 * villages, at most REPORT_FILTER_LIMIT entries. The ids feed the `playerId` / `villageId` list filters.
 */
class villageorplayer extends playername
{
    public const REPORT_FILTER_LIMIT = 20;

    public function dispatch(): void
    {
        $this->response = [];
        $term = $_POST['search'] ?? '';
        $pattern = $this->likePattern(is_string($term) ? $term : '');
        if ($pattern === '') {
            return;
        }
        $limit = self::REPORT_FILTER_LIMIT;
        $statistics = new StatisticsModel();
        $players = $this->rows(
            "SELECT id, name FROM users WHERE {$statistics->publicUserCondition()} AND name LIKE ? ORDER BY name LIMIT $limit",
            [$pattern]
        );
        $villages = $this->rows(
            'SELECT v.kid AS id, v.name, w.x, w.y FROM vdata v INNER JOIN wdata w ON v.kid=w.id '
            . "INNER JOIN users u ON u.id=v.owner WHERE {$statistics->publicUserCondition('u')} AND v.name LIKE ? "
            . "ORDER BY v.name LIMIT $limit",
            [$pattern]
        );
        $this->response = array_slice(array_merge(
            array_map(static fn(array $row): array => ['type' => 'Player', 'id' => (int) $row['id'], 'name' => $row['name']], $players),
            array_map(static fn(array $row): array => [
                'type' => 'Village',
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'x' => (int) $row['x'],
                'y' => (int) $row['y'],
            ], $villages)
        ), 0, $limit);
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @param list<string> $parameters
     */
    private function rows(string $sql, array $parameters): array
    {
        $statement = DB::getInstance()->run($sql, $parameters);
        $result = $statement ? $statement->get_result() : false;

        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
}
