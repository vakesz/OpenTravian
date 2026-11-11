<?php

namespace Controller\Ajax\map;

use Controller\Ajax\AjaxBase;
use Core\Database\DB;
use Core\Helper\TimezoneHelper;
use Core\Session;
use Game\Formulas;
use Model\ArtefactsModel;
use Model\ReportsModel;
use Model\MapPageModel;
use Model\WonderOfTheWorldModel;

class position extends AjaxBase
{
    public function dispatch(): void
    {
        if (!Session::getInstance()->isValid()) {
            return;
        }
        if (!isset($_POST['data']) || !isset($_POST['data']['zoomLevel']) || !isset($_POST['data']['x']) || !isset($_POST['data']['y'])) {
            return;
        }
        $data = $_POST['data'];
        $zoomLevel = isset($data['zoomLevel']) && $data['zoomLevel'] <= 3 && $data['zoomLevel'] >= 1 ? $data['zoomLevel'] : 1;
        $areaAroundPosition = [
            1 => [
                'left' => -5,
                'top' => -4,
                'right' => 5,
                'bottom' => 4,
            ],
            2 => [
                'left' => -10,
                'top' => -8,
                'right' => 10,
                'bottom' => 8,
            ],
            3 => [
                'left' => -15,
                'top' => -15,
                'right' => 15,
                'bottom' => 15,
            ],
        ];
        $x1 = $data['x'] + $areaAroundPosition[$zoomLevel]['top'];
        $x2 = $data['x'] + $areaAroundPosition[$zoomLevel]['bottom'];
        $y1 = $data['y'] + $areaAroundPosition[$zoomLevel]['left'];
        $y2 = $data['y'] + $areaAroundPosition[$zoomLevel]['right'];
        if ($x1 < -MAP_SIZE) {
            $x1 = -MAP_SIZE;
        }
        if ($x2 > MAP_SIZE) {
            $x2 = MAP_SIZE;
        }
        if ($y1 < -MAP_SIZE) {
            $y1 = -MAP_SIZE;
        }
        if ($y2 > MAP_SIZE) {
            $y2 = MAP_SIZE;
        }
        $db = DB::getInstance();
        $m = new MapPageModel();
        $return = $db->query("SELECT * FROM `wdata` WHERE (x BETWEEN '" . $x1 . "' AND '" . $x2 . "') AND (y BETWEEN '" . $y1 . "' AND '" . $y2 . "') ORDER BY id ASC");
        $cap_kid = Formulas::xy2kid(0, 0);
        $wwPlansReleased = ArtefactsModel::wwPlansReleased();
        while ($row = $return->fetch_assoc()) {
            if ($row['id'] == $cap_kid && !$wwPlansReleased) {
                $row['occupied'] = FALSE;
            }
            $tile =& $this->response['tiles'][];
            $tile['position']['x'] = $row['x'];
            $tile['position']['y'] = $row['y'];
            if ($row['fieldtype'] && !$row['occupied']) {
                $tile['title'] = '{k.vt} {k.f' . $row['fieldtype'] . '}';
                $tile['text'] = $this->tileText($row);
            } else if ($row['fieldtype'] && $row['occupied']) {
                $tile['uid'] = $m->getVillageOwner($row['id']);
                if ($alliance = $m->getPlayerAllianceId((int) $tile['uid'])) {
                    $tile['aid'] = $alliance;
                }
                $tile['did'] = $row['id'];
                $tile['title'] = '{k.dt} ' . $m->getVillageName($row['id']);
                $allianceTag = $alliance ? $m->getAllianceTag((int) $alliance) : '';
                $tile['text'] = $this->tileText($row, '<br />{k.spieler} ' . $m->getPlayerName((int) $tile['uid']) . '<br />{k.einwohner} ' . $m->getVillagePop($row['id'])
                    . '<br />{k.allianz} ' . $allianceTag . '<br />{k.volk} {a.v' . $m->getPlayerTribe((int) $tile['uid']) . '}' . $this->getReport($row['id']));
            } else if ($row['oasistype']) {
                $tile['did'] = $row['id'];
                $tile['title'] = $row['occupied'] ? '{k.bt}' : '{k.fo}';
                $effects = '';
                foreach (Formulas::getOasisEffect($row['oasistype']) as $k => $v) {
                    if ($v) {
                        $effects .= '<br />{a:r' . $k . '} {a.r' . $k . '} ' . ($v * 25) . '%';
                    }
                }
                if (!$row['occupied']) {
                    $tile['text'] = $this->tileText($row, $effects . $this->oasisAnimals((int)$row['id']) . $this->getReport($row['id']));
                } else {
                    $owner = $m->getOasisOwner($row['id']);
                    $tile['uid'] = $owner;
                    $allianceTag = '';
                    if ($alliance = $m->getPlayerAllianceId((int) $owner)) {
                        $tile['aid'] = $alliance;
                        $allianceTag = $m->getAllianceTag((int) $alliance);
                    }
                    $tile['text'] = $this->tileText($row, $effects . '<br />{k.spieler} ' . $m->getPlayerName((int) $owner) . '<br />{k.allianz} ' . $allianceTag
                        . '<br />{k.volk} {a.v' . $m->getPlayerTribe((int) $owner) . '}' . $this->getReport($row['id']));
                }
            } else if ($row['landscape']) {
                $tile['did'] = NULL;
                $tile['title'] = T('HeroAdventure', ['wald', 'clay', 'hill', 'lake', 'vulcano'][$row['landscape'] - 1]);
                $tile['text'] = $this->tileText($row);
            }
        }
        $return->free();
    }

    /**
     * Tooltip body of a tile: its coordinates, the distance placeholder the client fills in, then the details.
     *
     * @param array<mixed> $row
     */
    private function tileText(array $row, string $details = ''): string
    {
        return '&#x202d;<span class="coordinates coordinatesWrapper"><span class="coordinateX">(' . coordinateNumberHTML($row['x'])
            . '</span><span class="coordinatePipe">|</span><span class="coordinateY">' . coordinateNumberHTML($row['y']) . ')</span></span>&#x202c;'
            . '<span class="tileDistance">{k.distance} [[DISTANCE]]</span>' . $details;
    }

    /** The animals of a free oasis, one icon with its count per kind. */
    private function oasisAnimals(int $kid): string
    {
        $units = DB::getInstance()->query("SELECT * FROM units WHERE kid=$kid")->fetch_assoc() ?: [];
        $html = '';
        for ($i = 1; $i <= 10; ++$i) {
            if (!empty($units['u' . $i])) {
                $html .= '<div class="inlineIcon tooltipUnit" title=""><i class="unit u' . nrToUnitId($i, 4) . '"></i><span class="value ">' . (int)$units['u' . $i] . '</span></div>';
            }
        }

        return $html === '' ? '' : '<br />{k.animals}<br />' . $html;
    }

    /**
     * @param mixed $kid
     * @return string|null
     */
    public function getReport($kid)
    {
        $m = new ReportsModel();
        $notice = $m->getJustOnReportForPositionData($kid, Session::getInstance()->getPlayerId());
        if (!is_array($notice)) {
            return NULL;
        }
        $report = '<br />{b:ri' . $notice['type'] . '} ' . TimezoneHelper::autoDateString($notice['time'],
                TRUE) . ' {b.ri' . $notice['type'] . '}';
        $bounty = explode(',', $notice['bounty']);
        if (isset($bounty[4]) && $bounty[4]) {
            $totalBounty = array_sum($bounty) - (int) $bounty[4];
            if ($totalBounty == 0) {
                $class = 0;
            } else if ($totalBounty == $bounty[4]) {
                $class = 2;
            } else {
                $class = 1;
            }
            $report .= '<br />{b:bi' . $class . '} ' . number_format_x($totalBounty) . '/' . number_format_x($bounty[4]);
        }
        return $report;
    }
}