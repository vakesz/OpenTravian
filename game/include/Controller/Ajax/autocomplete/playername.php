<?php

namespace Controller\Ajax\autocomplete;

use Controller\Ajax\AjaxBase;
use Core\Database\DB;
use Core\Session;
use Core\Village;
use Game\Formulas;
use Model\StatisticsModel;

use function array_values;

class playername extends AjaxBase
{
    public const AUTO_COMPLETE_LIMIT = 10;

    /** The client may ask for more rows, as the reference does, up to this many. */
    public const MAX_LIMIT = 50;

    /** An empty term returns nothing; one character already searches, as in the reference. */
    public const MIN_TERM_LENGTH = 1;

    /** Village suggestions carry what the farm list's target selection shows: position, owner, alliance and type (1 capital, 0 other). */
    private const VILLAGE_COLUMNS = 'v.kid AS did, v.name, w.x, w.y, v.kid AS mapId, u.id AS uid, u.name AS playerName, u.aid, a.name AS allianceName, v.capital AS typ'
        . ' FROM vdata v INNER JOIN wdata w ON w.id=v.kid INNER JOIN users u ON u.id=v.owner LEFT JOIN alidata a ON a.id=u.aid';

    public function dispatch()
    {
        $terms = $_POST['search'] ?? '';
        foreach (is_array($terms) ? $terms : [$terms] as $term) {
            $this->search($this->likePattern($term));
        }
        $this->response = array_values($this->response);
    }

    /**
     * A prefix pattern with the LIKE metacharacters escaped; the village search matches anywhere. The
     * term is HTML-encoded like the stored names; an empty string means "too short, do not search".
     *
     * @param mixed $term
     */
    protected function likePattern($term): string
    {
        $term = htmlspecialchars(is_string($term) ? trim($term) : '', ENT_QUOTES, 'UTF-8');
        if (mb_strlen($term) < self::MIN_TERM_LENGTH) {
            return '';
        }

        return ($_POST['context'] ?? '') === 'villagename'
            ? '%' . addcslashes($term, '%_\\') . '%'
            : addcslashes($term, '%_\\') . '%';
    }

    /**
     * Suggestions as the client reads them: `name` (HTML-encoded) and, for players, `uid`.
     *
     * @return list<array<string, mixed>>
     *
     * @param list<mixed>|list<mixed> $parameters
     */
    protected function names(string $sql, array $parameters): array
    {
        $statement = DB::getInstance()->run($sql, $parameters);
        $result = $statement ? $statement->get_result() : false;
        $names = [];
        while ($result && ($row = $result->fetch_assoc())) {
            $names[] = $row;
        }

        return $names;
    }

    /**
     * @param string $pattern
     */
    public function search($pattern): void
    {
        $this->response = [];
        if ($pattern === '') {
            return;
        }
        $limit = max(1, min(self::MAX_LIMIT, (int)($_POST['limit'] ?? self::AUTO_COMPLETE_LIMIT)));
        switch ($_POST['context'] ?? '') {
            case 'villagename':
                $kid = Village::getInstance()->getKid();
                $autoComplete = Session::getInstance()->getAutoComplete();
                $owner = Session::getInstance()->getPlayerId();
                if ($autoComplete[0]) {
                    $this->response = array_merge($this->response, $this->names(
                        'SELECT ' . self::VILLAGE_COLUMNS . ' WHERE v.owner=? AND v.kid!=? AND v.name LIKE ? LIMIT ' . $limit,
                        [$owner, $kid, $pattern]
                    ));
                }
                if ($autoComplete[1]) {
                    $mapSize = (int)MAP_SIZE;
                    $totalCoordinate = 1 + (2 * $mapSize);
                    $totalCoordinate2 = 1 + (3 * $mapSize);
                    $xy = Formulas::kid2xy(Session::getInstance()->getKid());
                    $x = (int)$xy['x'];
                    $y = (int)$xy['y'];
                    $distance = "SQRT(POW(((w.x-$x+$totalCoordinate2)%$totalCoordinate -$mapSize), 2) + POW(((w.y-$y+$totalCoordinate2)%$totalCoordinate -$mapSize), 2))";
                    $publicOwner = (new StatisticsModel())->publicUserCondition('u');
                    $this->response = array_merge($this->response, $this->names(
                        'SELECT ' . self::VILLAGE_COLUMNS
                        . " WHERE $distance <= 20 AND v.name LIKE ? "
                        . "AND EXISTS (SELECT 1 FROM users pu WHERE pu.id=v.owner AND " . str_replace('u.', 'pu.', $publicOwner) . ") LIMIT $limit",
                        [$pattern]
                    ));
                }
                if ($autoComplete[2]) {
                    $aid = (int)Session::getInstance()->getAllianceId();
                    if ($aid) {
                        $this->response = array_merge($this->response, $this->names(
                            'SELECT ' . self::VILLAGE_COLUMNS . ' WHERE u.aid=? AND v.owner != ? AND v.kid!=? AND v.name LIKE ? LIMIT ' . $limit,
                            [$aid, $owner, $kid, $pattern]
                        ));
                    }
                }
                $unique = [];
                foreach ($this->response as $village) {
                    $unique[$village['mapId']] = $village;
                }
                $this->response = array_slice(array_values($unique), 0, $limit);
                break;
            case 'messageRecipient':
            case 'username':
                $this->response = $this->names(
                    'SELECT id AS uid, name FROM users WHERE ' . (new StatisticsModel())->publicUserCondition() . " AND name LIKE ? ORDER BY name LIMIT $limit",
                    [$pattern]
                );
                break;
        }
    }
}
