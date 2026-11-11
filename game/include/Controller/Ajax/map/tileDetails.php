<?php

namespace Controller\Ajax\map;

use Controller\Ajax\AjaxBase;
use function array_key_exists;
use Core\Caching\Caching;
use Core\Config;
use Core\Database\DB;
use Core\Helper\TimezoneHelper;
use Core\Session;
use Core\Village;
use Game\Formulas;
use Game\Hero\HeroHelper;
use Game\Hero\SessionHero;
use Core\Locale;
use Game\NoticeHelper;
use Model\AccountDeleter;
use Model\ReportsModel;
use Model\FarmListModel;
use Model\MapPageModel;
use Model\OasesModel;
use function redirect;
use resources\View\PHPBatchView;

class tileDetails extends AjaxBase
{
    /**
     * @param array<mixed> $response
     */
    public function __construct(&$response)
    {
        parent::__construct($response);
    }

    public function dispatch(): void
    {
        if (!Session::getInstance()->isValid()) {
            return;
        }
        $x = Formulas::coordinateFixer($_POST['x']);
        $y = Formulas::coordinateFixer($_POST['y']);
        $this->response['html'] = $this->tileHtml((new MapPageModel())->getMapTileByCoordinates($x, $y));
    }

    /**
     * @param mixed $x
     * @param mixed $y
     */
    public function render($x, $y): string
    {
        return $this->tileHtml((new MapPageModel())->getMapTileByCoordinates(
            Formulas::coordinateFixer($x),
            Formulas::coordinateFixer($y)
        ));
    }

    /**
     * @param array<string, mixed> $tile
     */
    private function tileHtml(array $tile): string
    {
        if ($tile['deepwater'] || $tile['landscape']) {
            return $this->dispatchLandscape($tile);
        }

        return $tile['oasistype'] ? $this->dispatchOasis($tile) : $this->dispatchField($tile);
    }

    /**
     * The tile heading: label (village name or tile type), optional capital marker, then the coordinates.
     *
     * @param array<mixed> $tile
     */
    private function heading(string $label, array $tile, ?string $mainVillage = null): string
    {
        $coordinates = "\u{202D}" . getCoordinatesHTML($tile['x'], $tile['y']) . "\u{202C}&nbsp;";
        $capital = $mainVillage === null ? ' ' : ' <span class="mainVillage">' . $mainVillage . '</span><span class="clear"></span> ';

        return '<h1 class="titleInHeader">' . $label . $capital . $coordinates . '</h1>';
    }

    /**
     * @param array<mixed> $tile
     */
    private function centerMapOption(array $tile): string
    {
        $label = T("map", "centerMap");

        return '<div class="option"><a class="a arrow" title="' . $label . '" href="/karte.php?x=' . $tile['x'] . '&amp;y=' . $tile['y']
            . '" data-centermap="true"> ' . $label . ' </a></div>';
    }

    /**
     * The second option group of a tile: the farm-list control. $withButton is false on the player's own village.
     *
     * @param array<mixed> $tile
     */
    private function farmListOptions(array $tile, bool $withButton): string
    {
        if (!$withButton) {
            return '';
        }
        $session = Session::getInstance();
        $m = new FarmListModel();
        $farmList = $m->getVillageFarmList($session->getKid(), $session->getPlayerId());
        if ($farmList == false) {
            $html = '<div class="option"><span class="a arrow disabled" title="' . T("map", "noFarmList") . '">' . T("map", "Add to farm list") . '</span></div>';
        } else {
            $html = '<div class="option"><a href="#" onclick="Travian.Game.FarmList.openSlotDialog({type: \'create\', listId: ' . $farmList . ', coordinates: {x: ' . $tile['x'] . ', y: ' . $tile['y'] . '}}); return false;" id="crud-raidlist-button" class="a arrow" title="' . T("map", "Add to farm list") . '">' . T("map", "Add to farm list") . '</a></div>';
        }
        $exists = $m->getWhereIsKidInFarmList($tile['id'], $session->getPlayerId());
        if ($exists !== false) {
            $label = sprintf(T("map", "Edit farm list (Village already in farm list x)"), $exists['name']);
            $html .= '<div class="option"><a href="#" onclick="Travian.Game.FarmList.openSlotDialog({type: \'edit\', listId: ' . $exists['id'] . ', editSlotIds: [' . $exists['slotId'] . ']}); return false;" id="crud-raidlist-button" class="a arrow" title="' . $label . '">' . $label . '</a></div>';
        }

        return $html;
    }

    private const FIELD_DISTRIBUTION = [
        1 => [3, 3, 3, 9],
        2 => [3, 4, 5, 6],
        3 => [4, 4, 4, 6],
        4 => [4, 5, 3, 6],
        5 => [5, 3, 4, 6],
        6 => [1, 1, 1, 15],
        7 => [4, 4, 3, 7],
        8 => [3, 4, 4, 7],
        9 => [4, 3, 4, 7],
        10 => [3, 5, 4, 6],
        11 => [4, 3, 5, 6],
        12 => [5, 4, 3, 6],
    ];

    /**
     * Distance from the selected village in fields, to two decimals.
     *
     * @param array<mixed> $tile
     */
    private function distance(array $tile): float
    {
        return round(Formulas::getDistance($tile['id'], Session::getInstance()->getSelectedVillageID()), 2);
    }

    /** The resource split of a village tile: table rows with a description when free, table cells when occupied. */
    private function landDistribution(int $fieldType, bool $occupied): string
    {
        $names = [1 => 'fieldWoodcutters', 2 => 'fieldClayPits', 3 => 'fieldIronMines', 4 => 'fieldCroplands'];
        $html = '';
        foreach (self::FIELD_DISTRIBUTION[$fieldType] as $index => $count) {
            $resource = $index + 1;
            $title = T('inGame', 'resources.r' . $resource);
            if ($occupied) {
                $html .= '<td><i class="r' . $resource . '" title="' . $title . '"></i> ' . $count . ' </td>';
            } else {
                $html .= '<tr><td class="ico"><i class="r' . $resource . '" src="/img/spacer.gif" title="' . $title . '"></i></td><td class="val">'
                    . $count . '</td><td class="desc">' . T('Tasks', $names[$resource]) . '</td></tr>';
            }
        }

        return $html;
    }

    private function linkOption(string $label, string $href): string
    {
        return '<div class="option"><a class="a arrow" title="' . $label . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" > ' . $label . ' </a></div>';
    }

    private function disabledOption(string $label, string $title): string
    {
        // Open above the label. A bottom tooltip on the last map option falls onto the page footer,
        // where the translucent bubble disappears and the reason looks like loose text.
        return '<div class="option"><span class="a arrow disabled" data-tippy-placement="top-start" data-tippy-followCursor="false" title="'
            . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">' . $label . '</span></div>';
    }

    /**
     * Rally Point send-troops form pre-filled with the target tile (and the attack type when the link names one).
     *
     * @param array<mixed> $tile
     */
    private function targetUrl(array $tile, ?int $eventType): string
    {
        return '/build.php?gid=16&tt=2' . ($eventType === null ? '' : '&eventType=' . $eventType) . '&targetMapId=' . $tile['id'];
    }

    /**
     * Hero's Mansion oasis action. Without any Mansion it is a locked link on occupied oases and absent
     * on free ones; with one it links while a slot is free.
     *
     * @param array<mixed> $tile
     */
    private function annexOption(array $tile, bool $lockedWithoutMansion): string
    {
        $label = T("map", "Annex Oasis");
        $level = max(Village::getInstance()->getTypeLevel(37));
        if ($level == 0) {
            return $lockedWithoutMansion ? $this->disabledOption($label, T("map", "buildHeroMansion")) : '';
        }
        $slots = floor(($level - 5) / 5) - (int)DB::getInstance()->fetchScalar("SELECT COUNT(kid) FROM odata WHERE did=" . Village::getInstance()->getKid());

        return $slots > 0
            ? $this->linkOption($label, $this->targetUrl($tile, null))
            : $this->disabledOption($label, T("map", "no free slots"));
    }

    /**
     * The send-troops action of an oasis: the reference link, or the reason it is locked.
     *
     * @param array<mixed> $tile
     * @param array<string, mixed>|null $owner
     */
    private function oasisTroopsOption(array $tile, string $label, ?int $eventType, ?array $owner): string
    {
        $session = Session::getInstance();
        $m = new MapPageModel();
        if ($session->banned()) {
            return $this->disabledOption($label, T("map", "YouAreBanned"));
        }
        if ($session->isInVacationMode()) {
            return $this->disabledOption($label, T("map", "YouAreInVacationMode"));
        }
        if ($owner !== null) {
            if ($protect = $m->hasBeginnerProtection($tile['id'])) {
                return $this->disabledOption($label, sprintf(T("map", "x is under protection to y"), TimezoneHelper::autoDateString($protect, true)));
            }
            if ($m->getPlayerAccesses($owner['owner']) == 0) {
                return $this->disabledOption($label, T("map", "banned"));
            }
            if ($m->isInVacation($tile['id'])) {
                return $this->disabledOption($label, T("map", "vacationModeActive"));
            }
        }
        if (max(Village::getInstance()->getTypeLevel(16))) {
            return $this->linkOption($label, $this->targetUrl($tile, $eventType));
        }
        if ($owner !== null && $owner['owner'] != $session->getPlayerId() && ($protect = $m->hasBeginnerProtection($owner['did']))) {
            return $this->disabledOption($label, sprintf(T("map", "x is under protection to y"), TimezoneHelper::autoDateString($protect, true)));
        }

        return $this->disabledOption($label, T("map", "constructRallyPoint"));
    }

    /**
     * @param array<mixed> $tile
     * @return mixed
     */
    private function dispatchField($tile)
    {
        $view = new PHPBatchView("map/viewTileDetails_field" . ($tile['occupied'] ? "Occupied" : "Free"));
        $view->vars['fieldType'] = $tile['fieldtype'];
        $view->vars['shore'] = (int)$tile['shore'];
        $view->vars['x'] = $tile['x'];
        $view->vars['y'] = $tile['y'];
        $view->vars['options'] = $this->centerMapOption($tile);
        $view->vars['distribution'] = $this->landDistribution((int)$tile['fieldtype'], (bool)$tile['occupied']);
        $view->vars['distance'] = $this->distance($tile);
        if ($tile['occupied']) {
            $m = new MapPageModel();
            $village = $m->getVillageInfo($tile['id'], "owner, name, pop, capital, isWW");
            if (!array_key_exists('isWW', $village)) {
                (new AccountDeleter())->deleteVillage($tile['id']);
                redirect('/dorf1.php');
            }
            $view->vars['Tribe'] = $m->getPlayerTribe($village['owner']);
            $view->vars['aid'] = $m->getPlayerAllianceId($village['owner']);
            if ($view->vars['aid']) {
                $view->vars['allianceTag'] = $m->getAllianceTag((int) $view->vars['aid']);
            }
            $view->vars['coordText'] = $village['name'];
            $view->vars['uid'] = $village['owner'];
            $view->vars['playerName'] = $m->getPlayerName($village['owner']);
            $view->vars['pop'] = $village['pop'];
            $view->vars['mainVillage'] = $village['capital'] ? '(' . T("map", "capital") . ')' : '';
            $view->vars['options'] .= $this->linkOption(T("map", "showReportsOfVillage"), '/report/overview?villageId=' . $tile['id']);
            if ($tile['id'] == Village::getInstance()->getKid()) {
                goto finalize;
            }
            $access = $m->getPlayerAccesses($village['owner']);
            if ($village['isWW'] && !Config::getInstance()->dynamic->WWPlansReleased && !Config::getProperty("custom",
                    "wwPlansEnabled")) $access = 0;
            //banned
            //vacation
            //beginner protection
            if (Session::getInstance()->banned()) {
                $view->vars['options'] .= '<div class="option"><span class="a arrow disabled" title="' . T("map",
                        "YouAreBanned") . '">' . T("map", "sendTroops") . '</span></div>';
            } else if (Session::getInstance()->isInVacationMode()) {
                $view->vars['options'] .= '<div class="option"><span class="a arrow disabled" title="' . T("map",
                        "YouAreInVacationMode") . '">' . T("map", "sendTroops") . '</span></div>';
            } else if (!Config::getProperty("custom",
                    "skipProtectionOnAttack") && Session::getInstance()->hasProtection() && !($village['owner'] == 1) && Session::getInstance()->getPlayerId() != $village['owner']) {
                $view->vars['options'] .= '<div class="option"><span class="a arrow disabled" title="' . T("map",
                        "YouAreProtected") . '">' . T("map", "sendTroops") . '</span></div>';
            } else if ($village['owner'] != Session::getInstance()->getPlayerId() && ($protect = $m->hasBeginnerProtection($tile['id']))) {
                $view->vars['options'] .= '<div class="option"><span class="a arrow disabled" title="' . sprintf(T("map",
                        "x is under protection to y"),
                        TimezoneHelper::autoDateString($protect, TRUE)) . '">' . T("map",
                        "sendTroops") . '</span></div>';
            } else if ($access == 0) {
                $view->vars['options'] .= '<div class="option"><span class="a arrow disabled" title="' . T("map",
                        "banned") . '">' . T("map", "sendTroops") . '</span></div>';
            } else if ($m->isInVacation($tile['id'])) {
                $view->vars['options'] .= '<div class="option"><span class="a arrow disabled" title="' . T("map",
                        "vacationModeActive") . '">' . T("map", "sendTroops") . '</span></div>';
            } else if (max(Village::getInstance()->getTypeLevel(16))) {
                $view->vars['options'] .= $this->linkOption(T("map", "sendTroops"), $this->targetUrl($tile, 5));
            } else {
                $view->vars['options'] .= '<div class="option"><span class="a arrow disabled" title="' . T("map",
                        "constructRallyPoint") . '">' . T("map", "sendTroops") . '</span></div>';
            }
            if (Session::getInstance()->banned()) {
                $view->vars['options'] .= '<div class="option"><span class="a arrow disabled" title="' . T("map",
                        "YouAreBanned") . '">' . T("map", "sendMerchants") . '</span></div>';
            } else if (Session::getInstance()->isInVacationMode()) {
                $view->vars['options'] .= '<div class="option"><span class="a arrow disabled" title="' . T("map",
                        "YouAreInVacationMode") . '">' . T("map", "sendMerchants") . '</span></div>';
            } else if ($access == 0) {
                $view->vars['options'] .= '<div class="option"><span class="a arrow disabled" title="' . T("map",
                        "banned") . '">' . T("map", "sendMerchants") . '</span></div>';
            } else if (Village::getInstance()->hasMarketPlace()) {
                $view->vars['options'] .= '<div class="option"><a href="/build.php?z=' . $tile['id'] . '&amp;gid=17&amp;t=5" class="a arrow" title="' . T("map",
                        "sendMerchants") . '">' . T("map", "sendMerchants") . '</a></div>';
            } else {
                $view->vars['options'] .= '<div class="option"><span class="a arrow disabled" title="' . T("map",
                        "constructMarketplace") . '">' . T("map", "sendMerchants") . '</span></div>';
            }
            finalize:
            $view->vars['top5Attacks'] = $this->getReports($tile['id']);
            $view->vars['top5Surrounding'] = $this->getTop5Surrounding($tile['id']);
            $view->vars['farmOptions'] = $this->farmListOptions($tile, $tile['id'] != Village::getInstance()->getKid());
        } else {
            $db = DB::getInstance();
            $kid = Village::getInstance()->getKid();
            $settlers = $db->fetchScalar("SELECT u10 FROM units WHERE kid={$kid}");
            $foundLabel = T("map", "foundNewVillage");
            if ($settlers < 3) {
                $view->vars['options'] .= $this->disabledOption($foundLabel, $settlers . '/3 ' . T("map", "readySettlers"));
            } else {
                $cp = Session::getInstance()->getCP();
                $total = $db->fetchScalar("SELECT COUNT(kid) FROM vdata WHERE owner=" . Session::getInstance()->getPlayerId());
                $cpNeeded = Formulas::newVillageCP($total + 1);
                if ($cp < $cpNeeded) {
                    $view->vars['options'] .= $this->disabledOption($foundLabel, T("map", "culturePointsForNewVillage") . ' ' . $cp . '/' . $cpNeeded);
                } elseif (Village::getInstance()->isResourcesAvailable(array_fill(0, 4, Formulas::newVillageCost()))) {
                    $view->vars['options'] .= '<div class="option"><a href="/build.php?id=39&tt=2&kid=' . $tile['id'] . '&a=6" class="a arrow" title="' . $foundLabel . '">' . $foundLabel . '</a></div>';
                } else {
                    $view->vars['options'] .= $this->disabledOption($foundLabel, sprintf(T("map", "noResourcesForNewVillage"), $this->number_format(Formulas::newVillageCost())));
                }
            }
        }
        $view->vars['vid'] = $tile['occupied'] ? (int)$view->vars['Tribe'] : (int)Session::getInstance()->getRace();
        $view->vars['heading'] = $tile['occupied']
            ? $this->heading($view->vars['coordText'], $tile, $view->vars['mainVillage'])
            : $this->heading(T("Global", !empty($view->vars['shore']) ? "Abandoned shore" : "Abandoned valley"), $tile);

        return $view->output();
    }

    /**
     * @param array<mixed> $tile
     * @return mixed
     */
    private function dispatchOasis($tile)
    {
        $m = new MapPageModel();
        $view = new PHPBatchView("map/viewTileDetails_oasis" . ($tile['occupied'] ? "Occupied" : "Free"));
        $view->vars['oasisType'] = $tile['oasistype'];
        $view->vars['x'] = $tile['x'];
        $view->vars['y'] = $tile['y'];
        $view->vars['distribution'] = '';
        foreach (Formulas::getOasisEffect($tile['oasistype']) as $k => $v) {
            if (!$v) {
                continue;
            }
            $title = T("inGame", "resources.r" . $k);
            $bonus = "\u{202D}\u{202D}" . ($v * 25) . "\u{202C}%\u{202C}";
            $view->vars['distribution'] .= '<tr><td class="ico"><i class="r' . $k . '" title="' . $title . '"></i></td><td class="val">' . $bonus
                . '</td><td class="desc">' . $title . '</td></tr>';
        }
        $view->vars['distance'] = $this->distance($tile);
        //options
        $view->vars['options'] = $this->centerMapOption($tile);
        if ($tile['occupied']) {
            $oasisOwner = $m->getOasisInfo($tile['id'], 'owner, did');
            if ($oasisOwner['did'] != Village::getInstance()->getKid()) {
                $view->vars['options'] .= $this->annexOption($tile, true);
            }
            $view->vars['options'] .= $this->oasisTroopsOption($tile, T("map", "sendTroops"), null, $oasisOwner);
        } else {
            $view->vars['options'] .= $this->oasisTroopsOption($tile, T("map", "raidFreeOasis"), 4, null);
            $view->vars['options'] .= $this->annexOption($tile, false);
        }
        $db = DB::getInstance();
        $view->vars['coordText'] = $tile['occupied'] ? T("map", "occupiedOasis") : T("map", "freeOasis");
        if (!$tile['occupied']) {
            OasesModel::oasesTrain($tile['id']);
            $view->vars['troops_info'] = '';
            $units = $db->query("SELECT * FROM units WHERE kid={$tile['id']}")->fetch_assoc();
            for ($i = 1; $i <= 10; ++$i) {
                if (!$units['u' . $i]) {
                    continue;
                }
                $unit = nrToUnitId($i, 4);
                $name = T("Troops", $unit . ".title");
                $view->vars['troops_info'] .= '<tr><td class="ico"><img class="unit u' . $unit . '" src="/img/spacer.gif" onclick="Travian.Game.Manual.open(\'troop\', ' . $unit
                    . ');" alt="' . $name . '" title="' . $name . '"></td><td class="val">' . $units['u' . $i] . '</td><td class="desc">' . $name . '</td></tr>';
            }
            $view->vars['troops_info'] = $view->vars['troops_info'] === ''
                ? '<tr><td>' . T("map", "noUnits") . '</td></tr>'
                : $view->vars['troops_info'] . '<tr><td colspan="3"><a href="/build.php?id=39&tt=3&screen=combatSimulator&kid=' . $tile['id']
                    . '" class="a arrow">' . T("map", "Simulate raid") . '</a></td></tr>';
        } else {
            $oasis = $m->getOasisInfo($tile['id'], "kid, did, type, owner");
            $village = $m->getVillageInfo($oasis['did'], "kid, owner, name");
            $xy = Formulas::kid2xy($village['kid']);
            $view->vars['village'] = ["x" => $xy['x'], 'y' => $xy['y'], 'name' => $village['name'],];
            $view->vars['Tribe'] = $m->getPlayerTribe($village['owner']);
            $view->vars['uid'] = $village['owner'];
            $view->vars['playerName'] = $m->getPlayerName($village['owner']);
            $view->vars['aid'] = $m->getPlayerAllianceId($village['owner']);
            if ($view->vars['aid']) {
                $view->vars['allianceTag'] = $m->getAllianceTag((int) $view->vars['aid']);
            }
        }
        $view->vars['top5Attacks'] = $this->getReports($tile['id']);
        $view->vars['top5Surrounding'] = $this->getTop5Surrounding($tile['id']);
        $view->vars['farmOptions'] = $this->farmListOptions($tile, true);
        $view->vars['heading'] = $this->heading($view->vars['coordText'], $tile);

        return $view->output();
    }

    /**
     * @param mixed $kid
     * @return mixed
     */
    private function getReports($kid)
    {
        $uid = Session::getInstance()->getPlayerId();
        $key = "reports:$uid:$kid";
        $cache = Caching::getInstance();
        if ($cache->exists($key)) {
            return $cache->get($key);
        }
        $m = new ReportsModel();
        $results = $m->getLast5Reports(Session::getInstance()->getAllianceId(),
            Session::getInstance()->getPlayerId(),
            $kid);
        $reports = '';
        while ($row = $results->fetch_assoc()) {
            $reports .= '<tr><td><img src="img/spacer.gif" class="iReport iReport' . $row['type'] . '" alt="' . T("Reports",
                    "reportTypes." . $row['type']) . '" title="' . T("Reports",
                    "reportTypes." . $row['type']) . '"> <a href="/report?id=' . $row['id'] . '">' . TimezoneHelper::autoDateString($row['time'],
                    TRUE) . '</a>';

            switch ($row['type']) {
                case NoticeHelper::TYPE_WON_AS_ATTACK_WITHOUT_CASUALTIES:
                case NoticeHelper::TYPE_WON_AS_ATTACK_WITH_CASUALTIES:
                case NoticeHelper::TYPE_LOST_AS_ATTACKER:
                case NoticeHelper::TYPE_WON_DEFENSE_WITHOUT_LOSSES:
                case NoticeHelper::TYPE_WON_DEFENSE_WITH_LOSSES:
                case NoticeHelper::TYPE_LOST_AS_DEFENDER_WITH_CASUALTIES:
                case NoticeHelper::TYPE_LOST_AS_DEFENDER_WITHOUT_CASUALTIES:
                    $bounty = explode(",", $row['bounty']);
                    if (!isset($bounty[4]) || !$bounty[4]) {
                        break;
                    }
                    $resources = array_sum($bounty) - (int) $bounty[4];
                    $style = $resources == $bounty[4] ? 'full' : ($resources == 0 ? 'empty' : 'half');
                    if (Session::getInstance()->hasPlus()) {
                        $reports .= '<a class="reportInfoIcon" href="/build.php?id=39&amp;tt=2&amp;bid=' . $row['id'] . '"><img title="' . $this->number_format($resources) . '/' . $this->number_format($bounty[4]) . '" alt="' . $this->number_format($resources) . '/' . $this->number_format($bounty[4]) . '" src="img/spacer.gif" class="reportInfo carry ' . $style . '" /></a>';
                    } else {
                        $reports .= '<a class="reportInfoIcon" href="#"><img title="' . $this->number_format($resources) . '/' . $this->number_format($bounty[4]) . '" alt="' . $this->number_format($resources) . '/' . $this->number_format($bounty[4]) . '" src="img/spacer.gif" class="reportInfo carry ' . $style . '" /></a>';
                    }
                    break;
            }

            $reports .= '</td></tr>';
        }
        if (empty($reports)) {
            $reports = '<tr><td>' . T("map", "No information<br>available!") . '</td></tr>';
        }
        $cache->add($key, $reports, 10);
        return $reports;
    }

    /**
     * @param mixed $x
     * @param int $dec
     * @return mixed
     */
    private function number_format($x, $dec = 0)
    {
        return number_format_x($x, $dec);
    }

    /**
     * @param mixed $kid
     * @return mixed
     */
    private function getTop5Surrounding($kid)
    {
        $key = "surrounding:$kid";
        $cache = Caching::getInstance();
        if ($cache->exists($key)) {
            return $cache->get($key);
        }
        $db = DB::getInstance();
        $reports = '';
        $results = $db->query("SELECT * FROM surrounding WHERE kid=$kid ORDER BY id DESC LIMIT 5");
        while ($notice = $results->fetch_assoc()) {
            $reports .= $this->getSurrounding($notice);
        }
        if (empty($reports)) {
            $reports = '<tr><td>' . T("map", "No information<br>available!") . '</td></tr>';
        }
        $cache->add($key, $reports, 1800);
        return $reports;
    }

    /**
     * @param mixed $row
     */
    private function getSurrounding($row): string
    {
        $HTML = '<tr>';
        switch ($row['type']) {
            case NoticeHelper::SURROUNDING_OASIS_RAID:
                $icon = 'oasisOccupy';
                $title = T("Reports", "An Oasis was plundered");
                break;
            case NoticeHelper::SURROUNDING_OASIS_OCCUPY:
                $icon = 'oasisOccupy';
                $data = explode(":", $row['params']);
                $title = sprintf(T("Reports", "x has conquered an oasis"),
                    '<a href="' . profileUrl((int) $data[0]) . '">' . $data[1] . '</a>');
                break;
            case NoticeHelper::SURROUNDING_OASIS_ABANDON:
                $icon = 'oasisAbandon';
                $title = T("Reports", "An Oasis was abandoned");
                break;
            case NoticeHelper::SURROUNDING_FIGHT:
                $icon = 'fight';
                $data = explode(":", $row['params']);
                $title = sprintf(T("Reports", "A fight took at village name of player name"),
                    '<a href="' . profileUrl((int) $data[0]) . '">' . $data[1] . '</a>',
                    '<a href="/karte.php?d=' . $data[2] . '">' . $this->getVillageName($data[2]) . '</a>');
                break;
            case NoticeHelper::SURROUNDING_VILLAGE_FOUND:
                $icon = 'villageFound';
                $data = explode(":", $row['params']);
                $title = sprintf(T("Reports", "x has founded y"),
                    '<a href="' . profileUrl((int) $data[0]) . '">' . $data[1] . '</a>',
                    '<a href="/karte.php?d=' . $data[2] . '">' . $this->getVillageName($data[2]) . '</a>');
                break;
            case NoticeHelper::SURROUNDING_VILLAGE_CONQUER:
                $icon = 'villageConquer';
                $data = explode(":", $row['params']);
                $title = sprintf(T("Reports", "x has conquered y"),
                    '<a href="' . profileUrl((int) $data[0]) . '">' . $data[1] . '</a>',
                    '<a href="/karte.php?d=' . $data[2] . '">' . $this->getVillageName($data[2]) . '</a>');
                break;
            case NoticeHelper::SURROUNDING_VILLAGE_LOST:
                $icon = 'villageLost';
                $data = explode(":", $row['params']);
                $title = sprintf(T("Reports", "x has lost y"),
                    '<a href="' . profileUrl((int) $data[0]) . '">' . $data[1] . '</a>',
                    '<a href="/karte.php?d=' . $data[2] . '">' . $data[3] . '</a>');
                break;
            case NoticeHelper::SURROUNDING_VILLAGE_RENAME:
                $icon = 'villageRename';
                $data = explode(":", $row['params']);
                $title = sprintf(T("Reports", "x renamed y to z"),
                    '<a href="' . profileUrl((int) $data[0]) . '">' . $data[1] . '</a>',
                    '<a href="/karte.php?d=' . $data[2] . '">' . $data[3] . '</a>',
                    '<a href="/karte.php?d=' . $data[2] . '">' . $data[4] . '</a>');
                break;
            case NoticeHelper::SURROUNDING_ALLIANCE:
                $icon = 'alliance';
                $data = explode(":", $row['params']);
                $title = sprintf(T("Reports", "x switched from y to z"),
                    '<a href="' . profileUrl((int) $data[0]) . '">' . $data[1] . '</a>',
                    $this->getAllianceLink($data[2]),
                    $this->getAllianceLink($data[3]));
                break;
            default:
                $icon = '';
                $title = '';
        }
        $HTML .= '<td title="' . htmlspecialchars($title) . '"><div class="reportIcon ' . $icon . '"></div> ' . TimezoneHelper::autoDateString($row['time']) . '</td>';
        $HTML .= '<tr>';
        return $HTML;
    }

    /**
     * @param string $aid
     */
    private function getAllianceLink($aid): string
    {
        if ($aid == 0 || $aid == -1) {
            return 'N/A';
        }
        $db = DB::getInstance();
        $find = $db->query("SELECT tag FROM alidata WHERE id=$aid LIMIT 1");
        if ($find->num_rows) {
            $find = $find->fetch_assoc();
            return ' <a href="' . allianceUrl((int) $aid) . '">' . $find['tag'] . '</a>';
        }
        return 'N/A';
    }

    /**
     * @param string $kid
     * @return mixed
     */
    private function getVillageName($kid)
    {
        $db = DB::getInstance();
        $kid = (int)$kid;
        $status = $db->query("SELECT oasistype, occupied FROM wdata WHERE id=$kid");
        if (!$status->num_rows) {
            return '<span class="none2">[?]</span>';
        }
        $status = $status->fetch_assoc();
        if ($status['oasistype']) {
            $xy = Formulas::kid2xy($kid);
            return ($status['occupied'] ? T("Reports", "occupiedOasis") : T("Reports",
                    "unoccupiedOasis")) . ' <span class="coordinates coordinatesWrapper"><span class="coordinateX">(' . coordinateNumberHTML($xy['x']) . '</span><span class="coordinatePipe">|</span><span class="coordinateY">' . coordinateNumberHTML($xy['y']) . ')</span></span>';
        }
        $name = $db->fetchScalar("SELECT name FROM vdata WHERE kid=$kid");
        if (empty($name)) {
            return '<span class="none2">[?]</span>';
        }
        return $name;
    }

    /**
     * @param array<mixed> $tile
     * @return mixed
     */
    private function dispatchLandscape($tile)
    {
        $view = new PHPBatchView("map/viewTileDetails_landscape");
        $view->vars['options'] = $this->centerMapOption($tile);
        $view->vars['landscapeType'] = $tile['deepwater'] ? 'sea' : ['forest', 'clay', 'hill', 'lake', 'vulcano',][$tile['landscape'] - 1];
        $view->vars['heading'] = $this->heading($tile['deepwater'] ? T("map", "deepWater") : T("map", "landscape_desc"), $tile);

        return $view->output();
    }
}
