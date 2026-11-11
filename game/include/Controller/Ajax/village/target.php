<?php

declare(strict_types=1);

namespace Controller\Ajax\village;

use Controller\Ajax\AjaxBase;
use Core\Database\DB;
use Game\FarmList\FarmListData;
use Game\Formulas;

use const MAP_SIZE;

use function T;

/** GET village/target/<x>/<y>: who lives at a map position, for the target selection of the farm list dialog. */
final class target extends AjaxBase
{
    public function dispatch(): void
    {
        $x = (int)($_GET['x'] ?? 0);
        $y = (int)($_GET['y'] ?? 0);
        if (!$this->session->isValid() || abs($x) > MAP_SIZE || abs($y) > MAP_SIZE) {
            $this->error(T('MarketPlace', 'noVillageInCoordinate'), 404);
            return;
        }
        $kid = (int)Formulas::xy2kid($x, $y);
        $statement = DB::getInstance()->run(
            'SELECT w.id, w.x, w.y, v.name, v.capital, u.id AS uid, u.name AS playerName, a.id AS aid, a.name AS allianceName FROM wdata w'
            . ' LEFT JOIN vdata v ON v.kid = w.id LEFT JOIN users u ON u.id = v.owner AND u.id > 1 AND u.hidden = 0 AND u.access > 0'
            . ' LEFT JOIN alidata a ON a.id = u.aid WHERE w.id = ? AND (w.oasistype > 0 OR (w.occupied = 1 AND w.fieldtype > 0)) LIMIT 1',
            [$kid]
        );
        $row = $statement === false ? null : $statement->get_result()->fetch_assoc();
        if (!is_array($row)) {
            $this->error(T('MarketPlace', 'noVillageInCoordinate'), 404);
            return;
        }
        $village = $row['name'] !== null;
        $this->response = [
            'did' => (int)$row['id'],
            'name' => $village ? FarmListData::plain((string)$row['name']) : T('Global', 'unOccupiedOasis'),
            'x' => (int)$row['x'],
            'y' => (int)$row['y'],
            'mapId' => (int)$row['id'],
            'uid' => $row['uid'] === null ? null : (int)$row['uid'],
            'playerName' => $row['playerName'] === null ? null : FarmListData::plain((string)$row['playerName']),
            'aid' => !$village ? null : (int)($row['aid'] ?? 0),
            'allianceName' => $row['allianceName'] === null ? null : FarmListData::plain((string)$row['allianceName']),
            'typ' => $village ? (int)$row['capital'] : 3,
        ];
    }
}
