<?php

namespace Controller;

use Controller\Build\TreasuryCtrl;
use Core\Database\DB;
use Core\Helper\CombatIcon;
use Core\Helper\PageNavigator;
use Core\Helper\TimezoneHelper;
use Core\Helper\WebService;
use Core\Session;
use Game\Formulas;
use Game\Hero\HeroItems;
use Game\NoticeHelper;
use Model\ReportsModel;
use Model\FarmListModel;
use Model\Quest;
use resources\View\GameView;
use resources\View\PHPBatchView;
use Core\Caching\Caching;

use function filter_var;
use function number_format_x;

class ReportsCtrl extends GameCtrl
{
    private $content;
    private $categories = [
        0 => [],
        1 => [
            //attacks.navigator
            NoticeHelper::TYPE_WON_AS_ATTACK_WITHOUT_CASUALTIES,
            NoticeHelper::TYPE_WON_AS_ATTACK_WITH_CASUALTIES,
            NoticeHelper::TYPE_LOST_AS_ATTACKER,
        ],
        2 => [
            NoticeHelper::TYPE_WON_DEFENSE_WITHOUT_LOSSES,
            NoticeHelper::TYPE_WON_DEFENSE_WITH_LOSSES,
            NoticeHelper::TYPE_LOST_AS_DEFENDER_WITH_CASUALTIES,
            NoticeHelper::TYPE_LOST_AS_DEFENDER_WITHOUT_CASUALTIES,
            NoticeHelper::TYPE_REINFORCEMENT,
        ],
        3 => [
            NoticeHelper::TYPE_WON_SPY_WITHOUT_CASUALTIES,
            NoticeHelper::TYPE_WON_SPY_WITH_CASUALTIES,
            NoticeHelper::TYPE_LOST_AS_SPY, //spy defend.
            NoticeHelper::TYPE_WON_SPY_AS_DEFENDER_REFUSED_ATTACKER_SPY,
            NoticeHelper::TYPE_WON_SPY_AS_DEFENDER_LETTING_ATTACKER_SPY,
        ],
        4 => [
            //resources.
            0,
            NoticeHelper::TYPE_RESOURCES_MOST_WOOD,
            NoticeHelper::TYPE_RESOURCES_MOST_CLAY,
            NoticeHelper::TYPE_RESOURCES_MOST_IRON,
            NoticeHelper::TYPE_RESOURCES_MOST_CROP,
            NoticeHelper::TYPE_CAGED_ATTACK, //adventure
            NoticeHelper::TYPE_ADVENTURE,
            self::SETTLING_FILTER_ICON,
        ],
    ];
    /**
     * The Other tab's "Settling" button. The reference never encodes it: settling reports (types 22 and 23) are
     * always listed there and ride along in every token as hidden filter ids.
     */
    public const SETTLING_FILTER_ICON = 33;
    public const OTHER_TAB_HIDDEN_TYPES = [NoticeHelper::TYPE_NEW_VILLAGE, 23];
    public const AUTH_TYPE_OWN = 1;
    public const AUTH_TYPE_ALLIANCE = 2;
    public const AUTH_TYPE_PRVIVATE_KEY = 3;
    private $customReportTypes = [1 => [], 2 => [], 3 => [], 4 => []];
    private $authType = 0;
    //enforcement.
    private $reportId;
    private $selectedTabIndex;

    public function __construct()
    {
        parent::__construct();
        $this->view = new GameView();
        $this->view->vars['titleInHeader'] = T('inGame', 'Navigation.Reports');
        // body.reports (dialogTemplates scoped there).
        $this->view->vars['bodyCssClass'] = 'reports';
        $this->view->vars['showHeaderBar'] = true;
        $this->view->vars['showCloseButton'] = true;
        $selectedTabIndex = isset($_REQUEST['t']) ? (int) $_REQUEST['t'] : ($this->session->getStoredFavoriteTab('reports') ?? 0);
        if ($selectedTabIndex < 0 || $selectedTabIndex > 6) {
            $selectedTabIndex = 0;
        }
        if ($selectedTabIndex == 5 && !$this->session->hasGoldClub()) {
            // The reference sends a player without the Gold club from the locked Archive tab to /report.
            WebService::redirect('/report');
        }

        $this->view->vars['contentCssClass'] = 'reports reports' . [
            'Overview', 'Offensive', 'Defensive', 'Scouting', 'Other', 'Archive', 'Surrounding',
        ][$selectedTabIndex];

        $reportTabLabels = [
            0 => T('Reports', 'Tabs.All'),
            1 => T('Reports', 'Tabs.Attacks'),
            2 => T('Reports', 'Tabs.Defense'),
            3 => T('Reports', 'Tabs.Spy'),
            4 => T('Reports', 'Tabs.Other'),
            5 => T('Reports', 'Tabs.Archive'),
            6 => T('Reports', 'Tabs.Surrounding'),
        ];

        $menuView = new PHPBatchView('reports/menu');
        $menuView->vars['favorText'] = sprintf(
            T('Global', 'Select tab %s as favourite'),
            $reportTabLabels[$selectedTabIndex] ?? $reportTabLabels[0]
        );
        $menuView->vars['selectedTab'] = $selectedTabIndex;
        $menuView->vars['selectedTabName'] = $selectedTabIndex;
        $menuView->vars['favorTabId'] = $this->session->getStoredFavoriteTab('reports');
        $menuView->vars['Archive'] = get_button_id();
        $menuView->vars['ArchiveGoldClubTitle'] = T('Reports', 'Archive function for reports and messages||For this feature you need the Gold club activated');
        $menuView->vars['goldClub'] = Session::getInstance()->hasGoldClub();
        $this->view->vars['content'] .= $menuView->output();

        $this->content = new PHPBatchView('reports/base');
        $this->selectedTabIndex = $selectedTabIndex;
        $this->content->vars['favorTabId'] = $this->session->getFavoriteTab('reports');
        $this->content->vars['Tabs']['selectedTab'] = $selectedTabIndex;
        $this->content->vars['Tabs']['selectedTabName'] = $selectedTabIndex;
        //$this->content->vars['Tabs']['Archive'] = get_button_id();
        //$this->content->vars['Tabs']['ArchiveGoldClubTitle'] = T("Reports", "Tabs.Archive") . '||' . T("Reports", "needClub");
        //$this->content->vars['content'] = '';
        $this->content->vars['goldClub'] = $this->session->hasGoldClub();
        if (isset($_GET['id'])) {
            list($id, $private_key) = $this->filter_reportId($_GET['id']);
            $m = new ReportsModel();
            $notice = $m->getReport($id);
            if (!is_array($notice) || !$this->isReportValid($notice, $private_key)) {
                goto finalize;
            }
            if (
                $notice['uid'] == $this->session->getPlayerId() &&
                !$notice['viewed'] &&
                !$this->session->isAdminInAnotherAccount()
            ) {
                $m->setReportAsViewed($id, $this->session->getPlayerId());
            }
            $this->renderReport($notice);
        } elseif ($selectedTabIndex <= 5) {
            $this->showOverview();
        } else {
            $this->showSurrounding();
        }
        finalize:
        Caching::getInstance()->delete("newReports{$this->session->getPlayerId()}");
        $this->view->vars['content'] .= $this->content->output();
    }

    private function filter_reportId($id)
    {
        $id = strip_tags(is_string($id) ? $id : '');
        $split = explode('|', $id);
        if (sizeof($split) === 2) {
            return [filter_var($split[0], FILTER_SANITIZE_NUMBER_INT), $split[1]];
        }
        return [filter_var($id, FILTER_SANITIZE_NUMBER_INT), null];
    }

    private function isReportValid($row, $private_key)
    {
        if ($row['private_key'] === $private_key) {
            $this->authType = self::AUTH_TYPE_PRVIVATE_KEY;
            return true;
        } elseif ($row['uid'] == $this->session->getPlayerId()) {
            $this->authType = self::AUTH_TYPE_OWN;
            return true;
        } elseif ($row['aid'] > 0 && $row['aid'] == $this->session->getAllianceId()) {
            $this->authType = self::AUTH_TYPE_ALLIANCE;
            return true;
        } // else if($row['aid'] > 0) {
        //     $aid = $this->session->getAllianceId();
        //     $db = DB::GetInstance();
        //     $diplos = $db->query("SELECT aid1, aid2 FROM diplomacy WHERE (aid1={$row['aid']} OR aid2={$row['aid']}) AND accepted<>0");
        //     while ($r = $diplos->fetch_assoc()) {
        //         if($r['aid1'] == $aid || $r['aid2'] == $aid){
        //             $this->authType = self::AUTH_TYPE_ALLIANCE;
        //             return TRUE;
        //         }
        //     }
        // }
        return false;
    }

    private function renderReport($notice)
    {
        $view = new PHPBatchView('reports/report_layout');
        $view->vars = [
            'subject' => $this->getNoticeSubject($notice, true),
            'reportId' => $notice['id'],
            'showImage' =>
                $this->session->showImagesInReports(),
            'imgType' => $notice['type'] == 0 || $notice['type'] == 22 ? 30 : $notice['type'],
            'sendTime' => $this->getTimeByTimezone($notice['time']),
            'showForward' => false,
            'hasGoldClub' => $this->session->hasGoldClub(),
            'isMine' => $notice['uid'] == $this->session->getPlayerId(),
            'content' => null,
            'showArchiveButton' => $this->session->getPlayerId() == $notice['uid'],
            'archived' => $notice['archive'] == 1,
            'showAddToFarmList' => $this->session->getPlayerId() == $notice['uid'],
            'showDeleteButton' => !$notice['deleted'] && $this->session->getPlayerId() == $notice['uid'],
            'showSimulateButton' => $this->session->getPlayerId() == $notice['uid'],
            'showRepeatButton' => $this->session->getPlayerId() == $notice['uid'],
            'showPermissionsButton' => false,
            'rightsText' => [
                'anonymOpponent' => T('Reports', 'make opponent anonymous'),
                'anonymMyself' => T('Reports', 'make myself anonymous'),
                'hiddenOwnTroops' => T('Reports', 'hide own troops'),
                'hiddenOtherTroops' => T('Reports', 'hide opposing troops'),
                'description' => T('Reports', 'Description:'),
                'buttonTextOk' => T('Global', 'General.ok'),
                'title' => T('Reports', 'Access permissions'),
            ],
            'selectedTabIndex' => $this->selectedTabIndex,
        ];
        if (isset($_GET['id'])) {
            $this->view->newVillagePrefix['id'] = strip_tags(is_string($_GET['id']) ? $_GET['id'] : '');
        }
        $isAttackOrSpy = false;
        switch ($notice['type']) {
            case NoticeHelper::TYPE_WON_AS_ATTACK_WITHOUT_CASUALTIES:
            case NoticeHelper::TYPE_WON_AS_ATTACK_WITH_CASUALTIES:
            case NoticeHelper::TYPE_LOST_AS_ATTACKER:
            case NoticeHelper::TYPE_WON_DEFENSE_WITHOUT_LOSSES:
            case NoticeHelper::TYPE_WON_DEFENSE_WITH_LOSSES:
            case NoticeHelper::TYPE_LOST_AS_DEFENDER_WITH_CASUALTIES:
            case NoticeHelper::TYPE_LOST_AS_DEFENDER_WITHOUT_CASUALTIES:
            case NoticeHelper::TYPE_WON_SPY_WITHOUT_CASUALTIES:
            case NoticeHelper::TYPE_WON_SPY_WITH_CASUALTIES:
            case NoticeHelper::TYPE_LOST_AS_SPY:
            case NoticeHelper::TYPE_WON_SPY_AS_DEFENDER_REFUSED_ATTACKER_SPY:
            case NoticeHelper::TYPE_WON_SPY_AS_DEFENDER_LETTING_ATTACKER_SPY:
                $isAttackOrSpy = true;
                break;
        }
        $this->reportId = $view->vars['reportId'];
        if ($view->vars['showAddToFarmList']) {
            $m = new FarmListModel();
            $exists = $m->getWhereIsKidInFarmList($notice['to_kid'], $this->session->getPlayerId());
            if ($exists !== false) {
                $xy = Formulas::kid2xy($notice['to_kid']);
                $view->vars['addToFarmListHTML'] =
                    '<button type="button" id="raidListGoldclub" class="icon" title="' .
                    T('map', 'Add to farm list') .
                    '||' .
                    sprintf(T('map', 'Edit farm list (Village already in farm list x)'), $exists['name']) .
                    '" onclick="var tWindows = Travian.WindowManager.getWindows(); var tWindow = tWindows[tWindows.length - 1]; if (tWindow) { tWindow.close(); } Travian.Game.FarmList.openSlotDialog({type: \'edit\', listId: ' . $exists['id'] . ', editSlotIds: [' . $exists['slotId'] . ']}); return false;"><i class="reportButton raidList"></i></button>';
            } else {
                $farmList = $m->getVillageFarmList($this->session->getKid(), $this->session->getPlayerId());
                if ($farmList === false) {
                    $view->vars['addToFarmListHTML'] =
                        '<button type="button" id="raidListGoldclub" class="icon disabled" title="' .
                        T('map', 'Add to farm list') .
                        '||' .
                        T('map', 'noFarmList') .
                        '"><i class="reportButton raidList"></i></button>';
                } else {
                    $xy = Formulas::kid2xy($notice['to_kid']);
                    $view->vars['addToFarmListHTML'] =
                        '<button type="button" id="raidListGoldclub" class="icon" title="' .
                        T('map', 'Add to farm list') .
                        '" onclick="var tWindows = Travian.WindowManager.getWindows(); var tWindow = tWindows[tWindows.length - 1]; if (tWindow) { tWindow.close(); } Travian.Game.FarmList.openSlotDialog({type: \'create\', listId: ' . $farmList . ', coordinates: {x: ' . $xy['x'] . ', y: ' . $xy['y'] . '}}); return false;"><i class="reportButton raidList"></i></button>';
                }
            }
        }
        if ($notice['uid'] == $this->session->getPlayerId()) {
            if (!$isAttackOrSpy) {
                $view->vars['showAddToFarmList'] = false;
                $view->vars['showSimulateButton'] = false;
                $view->vars['showRepeatButton'] = false;
            } else {
                if (
                    !in_array($notice['type'], [
                        NoticeHelper::TYPE_WON_AS_ATTACK_WITHOUT_CASUALTIES,
                        NoticeHelper::TYPE_WON_AS_ATTACK_WITH_CASUALTIES,
                        NoticeHelper::TYPE_LOST_AS_ATTACKER,
                    ])
                ) {
                    $view->vars['showRepeatButton'] = false;
                    $view->vars['showAddToFarmList'] = false;
                }
            }
            $view->vars['showPermissionsButton'] = $isAttackOrSpy;
            $view->vars['showForward'] = true;
            $view->vars['old'] = $this->getNextReport($notice['id']);
            $view->vars['next'] = $this->getBeforeReport($notice['id']);
        }
        $view->vars['showSimulateButton'] = $view->vars['showSimulateButton'] && $this->session->hasPlus();
        $view->vars['showRepeatButton'] = $view->vars['showRepeatButton'] && $this->session->hasPlus();
        $data = NoticeHelper::parseReport($notice['type'], $notice['data']);
        if (!isset($data['permissions'])) {
            $data['permissions'] = ['prem' => 0, 'desc' => ''];
        }
        $data['notice'] = $notice;
        switch ($notice['type']) {
            case NoticeHelper::TYPE_CAGED_ATTACK:
                $xy = Formulas::kid2xy($notice['to_kid']);
                $view->vars['includeFile'] = 'reports/caged_report';
                $view->vars['cagedReport'] = [];
                $view->vars['cagedReport']['units'] = array_map([$this, 'number_format'], $data['caged']);
                $view->vars['cagedReport']['notice'] = sprintf(
                    T('Reports', 'you used x cages'),
                    $this->number_format(array_sum($data['caged']))
                );
                $view->vars['cagedReport']['participants'] =
                    T('Reports', 'from oasis') .
                    ' <a class="" href="/karte.php?x=' .
                    $xy['x'] .
                    '&amp;y=' .
                    $xy['y'] .
                    '">‎&#x202d;<span class="coordinates coordinatesWrapper"><span class="coordinateX">(' . coordinateNumberHTML($xy['x']) . '</span><span class="coordinatePipe">|</span><span class="coordinateY">' . coordinateNumberHTML($xy['y']) . ')</span></span>&#x202c;‎</a>';
                $view->vars['cagedReport']['tdStyle'] = $this->getStyle();
                break;
            case NoticeHelper::TYPE_NEW_VILLAGE:
                $xy = Formulas::kid2xy($notice['to_kid']);
                $karte =
                    '<a class="" href="/karte.php?x=' .
                    $xy['x'] .
                    '&amp;y=' .
                    $xy['y'] .
                    '">&rlm;&#8238;<span class="coordinates coordinatesWrapper"><span class="coordinateX">(' . coordinateNumberHTML($xy['x']) . '</span><span class="coordinatePipe">|</span><span class="coordinateY">' . coordinateNumberHTML($xy['y']) . ')</span></span>&#8236;&rlm;</a>';
                $view->vars['content'] .= PHPBatchView::render('reports/newVillage', [
                    'karte' => $karte,
                ]);
                break;
            case NoticeHelper::TYPE_RESOURCES_MOST_WOOD:
            case NoticeHelper::TYPE_RESOURCES_MOST_CLAY:
            case NoticeHelper::TYPE_RESOURCES_MOST_IRON:
            case NoticeHelper::TYPE_RESOURCES_MOST_CROP:
                $this->checkPlayerName($data['sender']['uid'], $data['sender']['uname']);
                $this->checkPlayerName($data['receiver']['uid'], $data['receiver']['uname']);
                $view->vars['includeFile'] = 'reports/trade';
                $view->vars['trade'] = [
                    'tdStyle' => $this->getStyle(),
                    'participants' => [
                        'from' => $this->getPlayerHeadline(
                            $data['sender']['uid'],
                            $data['sender']['kid'],
                            $data['sender']['uname']
                        ),
                        'to' => $this->getPlayerHeadline(
                            $data['receiver']['uid'],
                            $notice['to_kid'],
                            $data['receiver']['uname']
                        ),
                    ],
                    'duration' => secondsToString($data['timeTaken']),
                    'res' => array_map([$this, 'number_format'], $data['resources']),
                ];
                break;
            case NoticeHelper::TYPE_REINFORCEMENT:
                $view->vars['includeFile'] = 'reports/reinforcement';
                $view->vars['reinforcement'] = [
                    'duration' => secondsToString($data['timeTaken']),
                    'from' => [
                        'kid' => $data['sender']['kid'],
                        'vname' => $this->getVillageName($data['sender']['kid']),
                    ],
                    'to' => [
                        'kid' => $notice['to_kid'],
                        'vname' => $this->getVillageName($notice['to_kid']),
                    ],
                ];
                $view->vars['reinforcement']['from']['headLine'] = $this->getPlayerHeadline(
                    $data['sender']['uid'],
                    $data['sender']['kid'],
                    $data['sender']['uname']
                );
                $view->vars['reinforcement']['to']['headLine'] = $this->getPlayerHeadline(
                    $data['receiver']['uid'],
                    $notice['to_kid'],
                    $data['receiver']['uname']
                );
                $view->vars['reinforcement']['tdStyle'] = $this->getStyle();
                $view->vars['reinforcement']['race'] = $data['units']['race'];
                $view->vars['reinforcement']['duration'] = secondsToString($data['timeTaken']);
                $view->vars['reinforcement']['units'] = array_map([$this, 'number_format'], $data['units']['num']);
                $view->vars['reinforcement']['consumption'] = $this->number_format($data['consumption']);
                break;
            case NoticeHelper::TYPE_ADVENTURE:
                $content = '';
                $units = array_fill(1, 11, 0);
                $units[11] = 1;
                $adventure = new PHPBatchView('reports/adventure');
                $adventure->vars['race'] = $data['race'];
                $adventure->vars['title'] = isset($data['adventureNumber'])
                    ? htmlspecialchars(sprintf(T('Reports', 'adventureTitle'), $data['adventureNumber']), ENT_QUOTES, 'UTF-8')
                    : null;
                $adventure->vars['failed'] = $notice['bounty'] == '0';
                $adventure->vars['lose'] = !($notice['bounty'] !== '-1');
                if ($notice['bounty'] !== '-1') {
                    $adventure->vars['exp'] = floor($data['exp']);
                    $adventure->vars['injury'] = floor($data['damage']);
                }
                $adventure->vars['style'] = null;
                $adventure->vars['Bounty'] = '';
                if (!$adventure->vars['failed']) {
                    $bounty = explode(',', $notice['bounty']);
                    switch ($bounty[0]) {
                        case 1:
                            $HeroItems = new HeroItems();
                            //item bonus.
                            $item = $HeroItems->getHeroItemProperties($bounty[1], $bounty[2]);
                            $adventure->vars['Bounty'] =
                                \Game\Hero\HeroItemTile::standalone((int) $bounty[1], (int) $bounty[2], (int) $bounty[3])
                                . ' ' . $item['name'];
                            break;
                        case 2:
                            $adventure->vars['style'] = $this->getStyle();
                            $adventure->vars['Bounty'] = '<div class="inlineIconList resourceWrapper">';
                            for ($i = 1; $i <= 4; ++$i) {
                                $adventure->vars['Bounty'] .=
                                    '<div class="inlineIcon resources"><i class="r' .
                                    $i .
                                    '"></i><span class="value ">' .
                                    $this->number_format($bounty[$i]) .
                                    '</span></div>';
                            }
                            $adventure->vars['Bounty'] .= '</div>';
                            break;
                        case 3:
                            $adventure->vars['Bounty'] =
                                '<img title="' .
                                $bounty[1] .
                                ' ' .
                                T('Reports', 'Silver') .
                                '" class="silver" src="img/spacer.gif">';
                            $adventure->vars['Bounty'] .= ' ' . T('Reports', 'Silver') . ' (' . $bounty[1] . 'x)';
                            break;
                        case 4:
                            $unitId = nrToUnitId($bounty[2], $bounty[1]);
                            $adventure->vars['Bounty'] =
                                '<img title="' .
                                T('Troops', "{$unitId}.title") .
                                ' (' .
                                $bounty[3] .
                                'x)" alt="" class="unit u' .
                                $unitId .
                                '" src="img/spacer.gif">';
                            $adventure->vars['Bounty'] .=
                                ' ' . T('Troops', "{$unitId}.title") . ' (' . $this->number_format($bounty[3]) . 'x)';
                            break;
                    }
                }
                $content .= $adventure->output();
                $view->vars['content'] .= $content;
                break;

            case NoticeHelper::TYPE_WON_SPY_WITHOUT_CASUALTIES:
            case NoticeHelper::TYPE_WON_SPY_WITH_CASUALTIES:
            case NoticeHelper::TYPE_LOST_AS_SPY:
            case NoticeHelper::TYPE_WON_SPY_AS_DEFENDER_REFUSED_ATTACKER_SPY:
            case NoticeHelper::TYPE_WON_SPY_AS_DEFENDER_LETTING_ATTACKER_SPY:
                $view->vars['bodyImage'] = $this->victoryHtml($notice, $data);
                $prm = $data['permissions']['prem'];
                if (
                    $this->isNotMine($notice) &&
                    (($prm & RIGHT_OPPONENT_HIDE && $data['attacker']['uid'] != $notice['uid']) ||
                        ($prm & RIGHT_MYSELF_HIDE && $data['attacker']['uid'] == $notice['uid']))
                ) {
                    $headline = T('Reports', 'unknown');
                } else {
                    $this->checkPlayerName($data['attacker']['uid'], $data['attacker']['uname']);
                    $headline = $this->getPlayerHeadline(
                        $data['attacker']['uid'],
                        $notice['kid'],
                        $data['attacker']['uname'],
                        true
                    );
                }
                $view->vars['content'] .= $this->renderTroopsTable(
                    $notice,
                    $data,
                    $data['attacker']['uid'],
                    false,
                    T('Reports', 'Attacker'),
                    $headline,
                    $data['attacker']['race'],
                    $data['attacker']['num'],
                    $data['attacker']['dead'],
                    isset($data['attacker']['trapped']) ? $data['attacker']['trapped'] : null,
                    $this->renderAttackerInfos($notice, $data, true),
                    $view->vars
                );
                $size = sizeof($data['defender']);
                $hideUnits = false;
                if (isset($data['info']['none_return']) && $this->authType == self::AUTH_TYPE_ALLIANCE) {
                    $size = 1;
                    $hideUnits = true;
                }
                foreach ($data['defender'] as $row) {
                    --$size;
                    if ($hideUnits) {
                        $row['dead'] = $row['num'] = array_fill(1, $row['race'] == 4 ? 10 : 11, '?');
                    }
                    $uid = isset($row['uid']) ? $row['uid'] : 0;
                    if (isset($row['kid'])) {
                        if (
                            $this->isNotMine($notice) &&
                            isset($row['uid']) &&
                            (($prm & RIGHT_OPPONENT_HIDE && $data['defender'][0]['uid'] != $notice['uid']) ||
                                ($prm & RIGHT_MYSELF_HIDE && $data['defender'][0]['uid'] == $notice['uid']))
                        ) {
                            $headline = T('Reports', 'unknown');
                        } else {
                            if (!isset($row['uid'])) {
                                $row['uid'] = $row['uname'] = 0;
                            }
                            $this->checkPlayerName($row['uid'], $row['uname']);
                            $headline = $this->getPlayerHeadline($row['uid'], $row['kid'], $row['uname'], true);
                        }
                    } else {
                        $headline = T('Reports', 'Reinforcement');
                    }
                    $content = '';
                    if (!$size) {
                        //show escape result only when we are defender!
                        //last one so description and escape result here.
                        if (
                            $this->session->getPlayerId() != $notice['uid'] &&
                            isset($data['permissions']['desc']) &&
                            !empty($data['permissions']['desc'])
                        ) {
                            $content .=
                                '<tr><th>' . T('Reports', 'Description:') . '</th><td><div>' . $data['permissions']['desc'] . '</div></td></tr>';
                        }
                    }
                    $view->vars['content'] .= $this->renderTroopsTable(
                        $notice,
                        $data,
                        $uid,
                        true,
                        T('Reports', 'Defender'),
                        $headline,
                        $row['race'],
                        $row['num'],
                        null,
                        null,
                        $this->infoTable($content),
                        $view->vars
                    );
                    if (!$size) {
                        break;
                    }
                }
                break;
            case 0:
            case NoticeHelper::TYPE_WON_AS_ATTACK_WITHOUT_CASUALTIES:
            case NoticeHelper::TYPE_WON_AS_ATTACK_WITH_CASUALTIES:
            case NoticeHelper::TYPE_LOST_AS_ATTACKER:
            case NoticeHelper::TYPE_WON_DEFENSE_WITHOUT_LOSSES:
            case NoticeHelper::TYPE_WON_DEFENSE_WITH_LOSSES:
            case NoticeHelper::TYPE_LOST_AS_DEFENDER_WITH_CASUALTIES:
            case NoticeHelper::TYPE_LOST_AS_DEFENDER_WITHOUT_CASUALTIES:
                $view->vars['bodyImage'] = $this->victoryHtml($notice, $data);
                $prm = $data['permissions']['prem'];
                if (
                    $this->isNotMine($notice) &&
                    (($prm & RIGHT_OPPONENT_HIDE && $data['attacker']['uid'] != $notice['uid']) ||
                        ($prm & RIGHT_MYSELF_HIDE && $data['attacker']['uid'] == $notice['uid']))
                ) {
                    $headline = T('Reports', 'unknown');
                } else {
                    $this->checkPlayerName($data['attacker']['uid'], $data['attacker']['uname']);
                    $headline = $this->getPlayerHeadline(
                        $data['attacker']['uid'],
                        $notice['kid'],
                        $data['attacker']['uname'],
                        true
                    );
                }
                $view->vars['content'] .= $this->renderTroopsTable(
                    $notice,
                    $data,
                    $data['attacker']['uid'],
                    false,
                    T('Reports', 'Attacker'),
                    $headline,
                    $data['attacker']['race'],
                    $data['attacker']['num'],
                    $data['attacker']['dead'],
                    isset($data['attacker']['trapped']) ? $data['attacker']['trapped'] : null,
                    $this->renderAttackerInfos($notice, $data),
                    $view->vars
                );
                if (isset($data['defender'])) {
                    $size = sizeof($data['defender']);
                    $hideUnits = false;
                    if (array_key_exists('losses', $data)) {
                        if ($this->authType == self::AUTH_TYPE_ALLIANCE && $data['losses'][1] < 0.25) {
                            $size = 1;
                            $hideUnits = true;
                        }
                    }
                    if (!$hideUnits) {
                        foreach ($data['defender'] as $row) {
                            $params = $data['permissions']['prem'];
                            if ($params & RIGHT_HIDE_OPPONENT_TROOPS) {
                                if ($row['uid'] != $notice['uid']) {
                                    $size = 1;
                                    break;
                                }
                            }
                            if ($params & RIGHT_HIDE_OWN_TROOPS) {
                                if ($row['uid'] == $notice['uid']) {
                                    $size = 1;
                                    break;
                                }
                            }
                        }
                    }
                    $escape = '';
                    switch ($notice['type']) {
                        case NoticeHelper::TYPE_WON_DEFENSE_WITHOUT_LOSSES:
                        case NoticeHelper::TYPE_WON_DEFENSE_WITH_LOSSES:
                        case NoticeHelper::TYPE_LOST_AS_DEFENDER_WITH_CASUALTIES:
                        case NoticeHelper::TYPE_LOST_AS_DEFENDER_WITHOUT_CASUALTIES:
                            if (isset($data['info']['escape'])) {
                                switch ($data['info']['escape']) {
                                    case -1: //no escape happend.
                                        break;
                                    case 0: //success
                                        break;
                                    case 1: //just capital
                                        $escape = T('Reports', 'escapeNonCapitalErr');
                                        break;
                                    case 2: //troops coming within 10seconds
                                        $escape = T('Reports', 'escapeTroopsComingErr');
                                        break;
                                    case 3:
                                        $escape = T('Reports', 'evasionNotEnabled');
                                        break;
                                    case 4:
                                        $escape = T('Reports', 'escapeDisabledBecauseYourPopulationIsTooLow');
                                        break;
                                }
                            }
                            break;
                    }
                    foreach ($data['defender'] as $row) {
                        if ($hideUnits) {
                            $row['dead'] = $row['num'] = array_fill(1, $row['race'] == 4 ? 10 : 11, '?');
                            if (array_key_exists('trapped', $row)) {
                                $row['trapped'] = $row['num'];
                            }
                        }
                        --$size;
                        $uid = isset($row['uid']) ? $row['uid'] : 0;
                        if (isset($row['kid'])) {
                            if (
                                $this->isNotMine($notice) &&
                                isset($row['uid']) &&
                                (($prm & RIGHT_OPPONENT_HIDE && $data['defender'][0]['uid'] != $notice['uid']) ||
                                    ($prm & RIGHT_MYSELF_HIDE && $data['defender'][0]['uid'] == $notice['uid']))
                            ) {
                                $headline = T('Reports', 'unknown');
                            } else {
                                if (!isset($row['uid'])) {
                                    $row['uid'] = $row['uname'] = 0;
                                }
                                $this->checkPlayerName($row['uid'], $row['uname']);
                                $headline = $this->getPlayerHeadline($row['uid'], $row['kid'], $row['uname'], true);
                            }
                        } else {
                            $headline = T('Reports', 'Reinforcement');
                        }
                            $content = '';
                        if (!$size) {
                            //show escape result only when we are defender!
                            //last one so description and escape result here.
                            if (!empty($escape)) {
                                $content .=
                                    '<tr><th>' . T('Reports', 'Description:') . '</th><td><div>' . $escape . '</div></td></tr>';
                            }
                            if (
                                $this->isNotMine($notice) &&
                                isset($data['permissions']['desc']) &&
                                !empty($data['permissions']['desc'])
                            ) {
                                $content .=
                                    '<tr><th>' . T('Reports', 'Description:') . '</th><td><div>' . $data['permissions']['desc'] . '</div></td></tr>';
                            }
                        }
                        $view->vars['content'] .= $this->renderTroopsTable(
                            $notice,
                            $data,
                            $uid,
                            true,
                            T('Reports', 'Defender'),
                            $headline,
                            $row['race'],
                            $row['num'],
                            isset($row['dead']) ? $row['dead'] : null,
                            null,
                            $this->infoTable($content),
                            $view->vars
                        );
                        if (!$size) {
                            break;
                        }
                    }
                    $view->vars['content'] .= $this->combatStatistics($data);
                }
                break;
        }
        finalize:
        $this->content->vars['content'] = ($this->content->vars['content'] ?? '') . $view->output();
    }

    public function getPlayerHeadline($uid, $kid = null, $username = null, $linear = false)
    {
        $HTML = null;
        if ($uid > 1) {
            $HTML .= $this->getAllianceHTMLLinkByUid($uid, $linear);
            $HTML .= $linear ? ' ' : '&nbsp;';
        }
        $HTML .=
            '<a class="player" href="' . profileUrl((int) $uid) . '">' .
            ($username ? $username : $this->getPlayerName($uid)) .
            '</a>';
        if ($kid > 0) {
            $HTML .= $linear
                ? ' ' . T('Reports', 'fromVillage') . ' '
                : '<br /><span>' . T('Reports', 'From') . '</span><br />';
            $HTML .= '<a class="village" href="/karte.php?d=' . $kid . '">' . $this->getVillageName($kid) . '</a>';
        }
        return $HTML;
    }

    public function getNoticeSubject($notice, $usePermissions = false, $isAlliance = false)
    {
        if ($notice['isEnforcement'] == 1) {
            //permissions doesn't work in this situation
            return sprintf(
                T('Reports', 'you troops in village x were attacked'),
                $this->getVillageName($notice['to_kid'])
            );
        }
        switch ($notice['type']) {
            case NoticeHelper::TYPE_ADVENTURE:
                // The reference subject names the hero's village only; the trailing argument keeps
                // locale strings that still carry a second placeholder valid.
                return sprintf(T('Reports', 'x is on adventure'), $this->getVillageName($notice['kid']), '');
                break;
            case NoticeHelper::TYPE_CAGED_ATTACK:
                return T('Reports', 'AnimalsCaught');
                break;
            case NoticeHelper::TYPE_REINFORCEMENT:
                return sprintf(
                    T('Reports', 'x reinforced y'),
                    $this->getVillageName($notice['kid']),
                    $this->getVillageName($notice['to_kid'])
                );
                break;
            case NoticeHelper::TYPE_WON_SPY_WITHOUT_CASUALTIES:
            case NoticeHelper::TYPE_WON_SPY_WITH_CASUALTIES:
            case NoticeHelper::TYPE_LOST_AS_SPY:
            case NoticeHelper::TYPE_WON_SPY_AS_DEFENDER_REFUSED_ATTACKER_SPY:
            case NoticeHelper::TYPE_WON_SPY_AS_DEFENDER_LETTING_ATTACKER_SPY:
                if ($isAlliance) {
                    return sprintf(
                        T('Reports', 'x spies y'),
                        $this->getPlayerNameByKid($notice['kid']),
                        $this->getPlayerNameByKid($notice['to_kid'])
                    );
                }
                return sprintf(
                    T('Reports', 'x spies y'),
                    $this->getVillageName($notice['kid']),
                    $this->getVillageName($notice['to_kid'])
                );
                break;
            case NoticeHelper::TYPE_NEW_VILLAGE:
                return sprintf(T('Reports', 'x founds a new village'), $this->getVillageName($notice['to_kid']));
                break;
            case NoticeHelper::TYPE_RESOURCES_MOST_WOOD:
            case NoticeHelper::TYPE_RESOURCES_MOST_CLAY:
            case NoticeHelper::TYPE_RESOURCES_MOST_IRON:
            case NoticeHelper::TYPE_RESOURCES_MOST_CROP:
                return sprintf(
                    T('Reports', 'x send resources to y'),
                    $this->getVillageName($notice['kid']),
                    $this->getVillageName($notice['to_kid'])
                );
                break;
            case 0:
            case NoticeHelper::TYPE_WON_AS_ATTACK_WITHOUT_CASUALTIES:
            case NoticeHelper::TYPE_WON_AS_ATTACK_WITH_CASUALTIES:
            case NoticeHelper::TYPE_LOST_AS_ATTACKER:
            case NoticeHelper::TYPE_WON_DEFENSE_WITHOUT_LOSSES:
            case NoticeHelper::TYPE_WON_DEFENSE_WITH_LOSSES:
            case NoticeHelper::TYPE_LOST_AS_DEFENDER_WITH_CASUALTIES:
            case NoticeHelper::TYPE_LOST_AS_DEFENDER_WITHOUT_CASUALTIES:
                $verb = $this->isRaidReport($notice) ? 'x raids y' : 'x attacks y';
                if ($isAlliance) {
                    return sprintf(
                        T('Reports', $verb),
                        $this->getPlayerNameByKid($notice['kid']),
                        $this->getPlayerNameByKid($notice['to_kid'])
                    );
                }
                $fromVillageName = $this->getVillageName($notice['kid']);
                $toVillageName = $this->getVillageName($notice['to_kid']);
                if ($usePermissions && $this->isNotMine($notice)) {
                    $data = NoticeHelper::parseReport($notice['type'], $notice['data']);
                    if (isset($data['permissions'])) {
                        $params = $data['permissions']['prem'];
                        if ($params & RIGHT_MYSELF_HIDE) {
                            if ($data['attacker']['uid'] == $notice['uid']) {
                                $fromVillageName = '<span class="none2">[?]</span>';
                            }
                            if (isset($data['defender'][0]['uid']) && $data['defender'][0]['uid'] == $notice['uid']) {
                                $toVillageName = '<span class="none2">[?]</span>';
                            }
                        }
                        if ($params & RIGHT_OPPONENT_HIDE) {
                            if ($data['attacker']['uid'] != $notice['uid']) {
                                $fromVillageName = '<span class="none2">[?]</span>';
                            }
                            if (isset($data['defender'][0]['uid']) && $data['defender'][0]['uid'] != $notice['uid']) {
                                $toVillageName = '<span class="none2">[?]</span>';
                            }
                        }
                    }
                }
                return sprintf(T('Reports', $verb), $fromVillageName, $toVillageName);
                break;
        }
    }

    /** @param array<string, mixed> $notice */
    private function isRaidReport(array $notice): bool
    {
        $data = NoticeHelper::parseReport($notice['type'], $notice['data']);

        return is_array($data) && !empty($data['info']['raid']);
    }

    private function getVillageName($kid)
    {
        $db = DB::getInstance();
        $kid = (int) $kid;
        $status = $db->query("SELECT oasistype, occupied FROM wdata WHERE id=$kid");
        if (!$status->num_rows) {
            return '<span class="none2">[?]</span>';
        }
        $status = $status->fetch_assoc();
        if ($status['oasistype']) {
            $xy = Formulas::kid2xy($kid);
            return ($status['occupied'] ? T('Reports', 'occupiedOasis') : T('Reports', 'unoccupiedOasis')) .
                ' <span class="coordinates coordinatesWrapper"><span class="coordinateX">(' . coordinateNumberHTML($xy['x']) . '</span><span class="coordinatePipe">|</span><span class="coordinateY">' . coordinateNumberHTML($xy['y']) . ')</span></span>';
        }
        $name = $db->fetchScalar("SELECT name FROM vdata WHERE kid=$kid");
        if (empty($name)) {
            return '<span class="none2">[?]</span>';
        }
        return $name;
    }

    private function getPlayerNameByKid($kid)
    {
        $db = DB::getInstance();
        $uid = $db->fetchScalar("SELECT owner FROM vdata WHERE kid=$kid");
        if ($uid === false) {
            $isOasis = $db->fetchScalar("SELECT COUNT(kid) FROM odata WHERE kid=$kid") >= 1;
            if ($isOasis) {
                return T('Global', 'NatureName');
            }
            return '<span class="none2">[?]</span>';
        }
        return $this->getPlayerName($uid);
    }

    private function getPlayerName($uid)
    {
        if ($uid == $this->session->getPlayerId()) {
            return '&nbsp;' . $this->session->getName();
        }
        if ($uid == -1) {
            return '<span class="none2">' . T('Reports', 'unknown') . '</span>';
        }
        if ($uid == 0) {
            return '<span class="none2">' . T('Global', 'NatureName') . '</span>';
        }
        if ($uid == 1) {
            return '<span>' . T('Global', 'NatarsName') . '</span>';
        }
        $db = DB::getInstance();
        $name = $db->fetchScalar("SELECT name FROM users WHERE id=$uid");
        if ($name === false) {
            return '<span class="none2">[?]</span>';
        }
        return $name;
    }

    private function isNotMine($notice)
    {
        if (
            ($notice['aid'] > 0 && $notice['aid'] == $this->session->getAllianceId()) ||
            $this->session->getPlayerId() == $notice['uid']
        ) {
            return false;
        }
        return true;
    }

    public function getTimeByTimezone($time, $relative = false)
    {
        return TimezoneHelper::reportDateString((int) $time, (bool) $relative, !$relative);
    }

    private function getNextReport($id)
    {
        $db = DB::getInstance();
        $return = ['disabled' => true, 'link' => null];
        $condition = $this->sqlTabCondition();
        if (!is_null($condition)) {
            $condition = 'AND ' . $condition;
        }
        $find = $db->query(
            'SELECT id, private_key FROM ndata WHERE uid=' .
                $this->session->getPlayerId() .
                " AND deleted=0 AND id>$id $condition ORDER BY id ASC LIMIT 1"
        );
        if ($find->num_rows) {
            $find = $find->fetch_assoc();
            $return['disabled'] = false;
            $return['link'] =
                $this->getReportTabPath((int)$this->content->vars['Tabs']['selectedTab']) .
                '?id=' .
                $find['id'] .
                '|' .
                $find['private_key'];
        }
        return $return;
    }

    private function sqlTabCondition()
    {
        if ($this->content->vars['Tabs']['selectedTab'] == 0) {
            return null;
        }
        if (in_array($this->content->vars['Tabs']['selectedTab'], [1, 2, 3])) {
            return 'type IN(' .
                implode(',', $this->categories[$this->content->vars['Tabs']['selectedTab']]) .
                ') AND deleted=0 AND archive=0';
        } elseif ($this->content->vars['Tabs']['selectedTab'] == 5) {
            return 'archive=1 AND deleted=0';
        }
        return null;
    }

    private function getBeforeReport($id)
    {
        $db = DB::getInstance();
        $return = ['disabled' => true, 'link' => null];
        $condition = $this->sqlTabCondition();
        if (!is_null($condition)) {
            $condition = 'AND ' . $condition;
        }
        $find = $db->query(
            'SELECT id, private_key FROM ndata WHERE uid=' .
                $this->session->getPlayerId() .
                " AND deleted=0 AND id<$id $condition ORDER BY id DESC LIMIT 1"
        );
        if ($find->num_rows) {
            $find = $find->fetch_assoc();
            $return['disabled'] = false;
            $return['link'] =
                $this->getReportTabPath((int)$this->content->vars['Tabs']['selectedTab']) .
                '?id=' .
                $find['id'] .
                '|' .
                $find['private_key'];
        }
        return $return;
    }

    /** Report types in which the attacker took the fight (the reference marks the other side "lost"). */
    private function attackerWon(array $notice): bool
    {
        return in_array((int) $notice['type'], [
            NoticeHelper::TYPE_WON_AS_ATTACK_WITHOUT_CASUALTIES,
            NoticeHelper::TYPE_WON_AS_ATTACK_WITH_CASUALTIES,
            NoticeHelper::TYPE_LOST_AS_DEFENDER_WITH_CASUALTIES,
            NoticeHelper::TYPE_LOST_AS_DEFENDER_WITHOUT_CASUALTIES,
        ], true);
    }

    /** The tribe banner on top of a battle report: a tribe is "lost" once it has casualties. */
    private function victoryHtml(array $notice, array $data): string
    {
        $defenderDead = 0;
        foreach ($data['defender'] as $row) {
            $defenderDead += is_array($row['dead'] ?? null) ? (int) array_sum($row['dead']) : 0;
        }
        $attackerDead = is_array($data['attacker']['dead'] ?? null) ? (int) array_sum($data['attacker']['dead']) : 0;
        $banner = static fn(int $race, string $role, bool $lost): string =>
            '<img src="img/spacer.gif" class="tribe tribe' . $race . ' ' . $role . ' ' . ($lost ? 'lost' : 'won') . '" alt="">';

        return '<div class="victory"><img src="img/spacer.gif" class="reportImage outcome wonLost" alt="">'
            . $banner((int) $data['attacker']['race'], 'attacker', !$this->attackerWon($notice) && $attackerDead > 0)
            . $banner((int) $data['defender'][0]['race'], 'defender', $defenderDead > 0)
            . '</div>';
    }

    /** Isolates a run of digits like the reference: left-to-right override around the value. */
    private function ltr(string $value): string
    {
        return "\u{202D}" . $value . "\u{202C}";
    }

    /**
     * One side's outcome icons: the fight result, then the loot bag (attacker) or the skull when nothing survived.
     * The skull and the "empty" loot bag carry no title until the reference shows their copy.
     */
    private function outcomeIcons(array $notice, bool $isDefender, int $units, int $dead, ?string $loot): string
    {
        $attackerWon = $this->attackerWon($notice);
        $lost = $isDefender ? $attackerWon : !$attackerWon;
        $role = $isDefender ? 'defend' : 'attack';
        $percent = $units > 0 ? (int) round($dead / $units * 100) : 0;
        if (!$dead) {
            $title = T('Reports', $isDefender ? 'defenderWithNoLosses' : 'attackerWithNoLosses');
        } elseif ($isDefender) {
            $title = sprintf(T('Reports', 'defenderWithLosses'), $this->ltr($this->ltr((string) $percent) . '%'));
        } else {
            $title = T('Reports', 'attackerWithLosses');
        }
        $name = $role . ($lost ? 'Lost' : '');
        $html = CombatIcon::svg(CombatIcon::fileName($name), 'losses ' . $name, $title, $name);
        if ($units > 0 && $units === $dead) {
            return $html . CombatIcon::svg('svg-skull.svg', 'skull', $title, 'skull');
        }
        if ($loot !== null) {
            $lootTitle = ['lootFull' => 'fullLootBag', 'lootHalf' => 'halfFullLootBag'][$loot] ?? null;
            $html .= CombatIcon::svg(CombatIcon::fileName($loot), 'loot ' . $loot, $lootTitle ? T('Reports', $lootTitle) : null, $loot);
        }

        return $html;
    }

    private function renderTroopsTable(
        $notice,
        $data,
        $uid,
        $isDefender,
        $role,
        $troopHeadline,
        $race,
        $units,
        $killed = null,
        $trapped = null,
        $other = null,
        $params = []
    ) {
        //hide opponents units.
        if ($this->isNotMine($data['notice'])) {
            $permissionMask = $data['permissions']['prem'];
            if (
                ($permissionMask & RIGHT_HIDE_OPPONENT_TROOPS && $uid != $data['notice']['uid']) ||
                ($permissionMask & RIGHT_HIDE_OWN_TROOPS && $uid == $data['notice']['uid'])
            ) {
                $units = array_fill(1, $race == 4 ? 10 : 11, '?');
                $killed = null;
                $trapped = null;
            }
        }

        $loot = null;
        if (!$isDefender) {
            $loot = 'lootEmpty';
            $bounty = !empty($notice['bounty']) ? explode(',', $notice['bounty']) : [];
            if (isset($bounty[4])) {
                $carried = array_sum($bounty) - $bounty[4];
                if ($carried > 0) {
                    $loot = $carried == $bounty[4] ? 'lootFull' : 'lootHalf';
                }
            }
        }
        $count = static fn($list): int => is_array($list) ? (int) array_sum(array_filter($list, 'is_numeric')) : 0;
        $params['outcomeIcons'] = $this->outcomeIcons(
            $notice,
            (bool) $isDefender,
            $count($units),
            $count($killed) + $count($trapped),
            $loot
        );
        $params['race'] = $race;
        $params['isDefender'] = $isDefender;
        $params['role'] = $role;
        $params['troopHeadline'] = $troopHeadline;
        $end = $race == 4 ? 10 : 11;
        $icons = '<tbody class="units"><tr><th class="coords"></th>';
        for ($i = 1; $i <= $end; ++$i) {
            $unitId = $i == 11 ? 'hero' : nrToUnitId($i, $race);
            $title = T('Troops', $unitId . '.title');
            $icons .= '<td class="uniticon' . ($i == $end ? ' last' : '') . '"><img src="/img/spacer.gif" class="unit u' . $unitId
                . '" title="' . $title . '" alt="' . $title . '"></td>';
        }
        $content = $icons . '</tr></tbody>';
        $rows = [[
            '<i class="troopCount_small" title="' . ($race == 4 ? T('Reports', 'units') : T('Reports', 'Troops')) . '"> </i>',
            $units,
        ]];
        if (!is_null($killed)) {
            $rows[] = ['<i class="troopDead_small" title="' . T('Reports', 'casualties') . '"> </i>', $killed];
        }
        if (!is_null($trapped)) {
            $rows[] = [T('Reports', 'Trapped'), $trapped];
        }
        foreach ($rows as [$label, $counts]) {
            $content .= '<tbody class="units"><tr><th>' . $label . '</th>';
            for ($i = 1; $i <= $end; ++$i) {
                $content .= '<td class="unit' . ($i == $end ? ' last' : '') . '"' . ($this->getStyle() ? ' ' . $this->getStyle() : '')
                    . '>' . $this->number_format($counts[$i]) . '</td>';
            }
            $content .= '</tr></tbody>';
        }
        $params['tbody'] = $content;
        $params['other'] = $other;
        $params['reportId'] = $this->reportId;
        $view = new PHPBatchView('reports/TroopsTable');
        $view->vars = $params;
        return $view->output();
    }

    private function getStyle()
    {
        if (!getDisplay('smallTroopsNumFontSize')) {
            return null;
        }
        return 'style="font-size: 11px;"';
    }

    private function number_format($x, $dec = 0)
    {
        return number_format_x($x, $dec);
    }

    private function checkPlayerName($uid, &$name)
    {
        if ($uid == 0) {
            $name = '<span class="none2">' . T('Global', 'NatureName') . '</span>';
        } elseif ($uid == 1) {
            $name = T('Global', 'NatarsName');
        }
    }

    private function getAllianceLink($aid)
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

    private function getPlayerAllianceId($uid)
    {
        $db = DB::getInstance();
        $aid = $db->fetchScalar("SELECT aid FROM users WHERE id=$uid");
        if (!$aid) {
            return -1;
        }
        return $aid;
    }

    private function getAllianceHTMLLinkByUid($uid, bool $withEmptyTitle = false)
    {
        if ($uid == 0) {
            return null;
        }
        $db = DB::getInstance();
        $aid = $db->fetchScalar("SELECT aid FROM users WHERE id=$uid");
        if (!$aid) {
            return null;
        }
        $alliance = $db->fetchScalar("SELECT tag FROM alidata WHERE id=$aid");
        return '<span class="inline-block">[<a href="' . allianceUrl((int) $aid) . '"' . ($withEmptyTitle ? ' title=""' : '') . '>'
            . $alliance . '</a>]</span>';
    }

    private function renderAttackerInfos(array $notice, array $data, $spy = false)
    {
        $information = $goods = [];
        if (isset($data['truceActive'])) {
            $information[] = sprintf(
                T('Truce', "report_descriptions.{$data['truceReason']}"),
                $data['attacker']['uname']
            );
        }
        if (isset($data['no_village'])) {
            $information[] = T('Reports', 'There was no village at target destination');
        }
        if (isset($data['info']['rams'])) {
            if ($data['info']['rams'][1] == $data['info']['rams'][2]) {
                //no damage.
                $text = sprintf(T('Reports', 'x didnt damaged'), T('Buildings', "{$data['info']['rams'][0]}.title"));
            } elseif ($data['info']['rams'][2] == 0) {
                $text = sprintf(T('Reports', 'x destroyed'), T('Buildings', "{$data['info']['rams'][0]}.title"));
            } else {
                $text = sprintf(
                    T('Reports', 'reduced lvl from x to y'),
                    T('Buildings', "{$data['info']['rams'][0]}.title"),
                    $data['info']['rams'][1],
                    $data['info']['rams'][2]
                );
            }
            $unitTitle = T('Buildings', "{$data['info']['rams'][0]}.title");
            $information[] =
                '<img class="gebIcon g' .
                $data['info']['rams'][0] .
                'Icon" src="img/spacer.gif" alt="' .
                $unitTitle .
                '" title="' .
                $unitTitle .
                '" /> ' .
                $text;
        }

        if (isset($data['info']['cata'])) {
            for ($i = 1; $i >= 0; --$i) {
                if (!isset($data['info']['cata'][$i])) {
                    continue;
                }
                $cur_cata = $data['info']['cata'][$i];
                if ($cur_cata[1] == $cur_cata[2]) {
                    //no damage.
                    $text = sprintf(T('Reports', 'x didnt damaged'), T('Buildings', "{$cur_cata[0]}.title"));
                } elseif ($cur_cata[2] == 0) {
                    $text = sprintf(T('Reports', 'x destroyed'), T('Buildings', "{$cur_cata[0]}.title"));
                } else {
                    $text = sprintf(
                        T('Reports', 'reduced lvl from x to y'),
                        T('Buildings', "{$cur_cata[0]}.title"),
                        $cur_cata[1],
                        $cur_cata[2]
                    );
                }
                if (isset($cur_cata[3]) && $cur_cata[3] == 1) {
                    $text .= '<br>' . T('Reports', 'randomTargetsWereChosen');
                }
                $unitTitle = T('Buildings', "{$cur_cata[0]}.title");
                $information[] =
                    '<img class="gebIcon g' .
                    $cur_cata[0] .
                    'Icon" src="img/spacer.gif" alt="' .
                    $unitTitle .
                    '" title="' .
                    $unitTitle .
                    '" /> ' .
                    $text;
            }
        }
        if (isset($data['info']['totally_destroyed'])) {
            $information[] =
                '<img src="img/spacer.gif" class="gebIcon g0Icon" alt="' . htmlspecialchars(T('Reports', 'Village'), ENT_QUOTES, 'UTF-8') . '"> ' .
                T('Reports', 'village totally destroyed');
        }
        if (isset($data['info']['not_destroyed_reason'])) {
            $info = T('Reports', 'not_destroyed_reason.' . $data['info']['not_destroyed_reason']);
            $information[] = '<img src="img/spacer.gif" class="gebIcon g0Icon" alt="' . htmlspecialchars(T('Reports', 'Village'), ENT_QUOTES, 'UTF-8') . '"> ' . $info;
        }
        if (isset($data['info']['ram_not_working_on_insider']) && $data['info']['ram_not_working_on_insider']) {
            $information[] = T('Reports', 'Ram does not work on alliance members');
        }
        if (isset($data['info']['cata_is_disabled']) && $data['info']['cata_is_disabled']) {
            $information[] = T('Reports', 'Cata is disabled');
        }
        if (isset($data['info']['cata_not_working_on_insider']) && $data['info']['cata_not_working_on_insider']) {
            $information[] = T('Reports', 'Cata does not work on alliance members');
        }
        if (isset($data['info']['none_return'])) {
            if ($spy) {
                $information[] = T('Reports', 'None of your spies returned');
            } else {
                $information[] = T('Reports', 'None of your soldiers returned');
            }
        }
        if (isset($data['info']['oasisCapture'])) {
            $text =
                '<img src="img/spacer.gif" class="unit uhero" title="' .
                T('Troops', 'hero.title') .
                '" alt="' .
                T('Troops', 'hero.title') .
                '">';
            $result = $data['info']['oasisCapture'];
            if ($result == -1) {
                $text .= T('Reports', 'NoFreeSlotsToCaptureOasis');
            } elseif ($result == 0) {
                $text .= T('Reports', 'OasisCaptured');
            } else {
                $text .= sprintf(T('Reports', 'LoyaltyLowered'), round($result[0]), round($result[1]));
            }
            $information[] = $text;
        }
        if (isset($data['info']['free'])) {
            $free = $data['info']['free'];
            if ($free[0] && $free[1]) {
                $text = sprintf(T('Reports', 'TrapFreeAllianceAndMe'), $free[0], $free[1]);
            } elseif ($free[0] && !$free[1]) {
                $text = sprintf(T('Reports', 'TrapFreeMe'), $free[0]);
            } elseif ($free[1]) {
                $text = sprintf(T('Reports', 'TrapFreeAlliance'), $free[1]);
            }
            $information[] = $text;
        }
        if (isset($data['info']['captureResult'])) {
            $unitId = nrToUnitId(9, $data['attacker']['race']);
            $text =
                '<img src="img/spacer.gif" class="unit u' .
                $unitId .
                '" title="' .
                T('Troops', "$unitId.title") .
                '" alt="' .
                T('Troops', "$unitId.title") .
                '"> ';
            $result = $data['info']['captureResult'];
            if ($result == 0) {
                $text .= sprintf(T('Reports', 'VillageCaptured'), $this->getVillageName($notice['to_kid']));
            } elseif ($result == -1) {
                $text .= T('Reports', 'CantCaptureCapital');
            } elseif ($result == -2) {
                $text .= T('Reports', 'culturePointsErr');
            } elseif ($result == -3) {
                $text .= T('Reports', 'rpExists');
            } elseif ($result == -4) {
                $text .= T('Reports', 'You can only have 1 ww village at a time');
            } elseif ($result == -5) {
                $text .= T('Reports', "Couldn't decrease loyalty because you have NAP with that alliance");
            } else {
                $text .= sprintf(T('Reports', 'LoyaltyLowered'), round($result[0]), round($result[1]));
            }
            $information[] = $text;
        }
        if (isset($data['info']['protectedByArtifact'])) {
            $treasuryTitle = T('Buildings', '27.title');
            foreach ($data['info']['protectedByArtifact'] as $artifact) {
                $artifactName =
                    '<strong>' .
                    TreasuryCtrl::getArtifactName($artifact['type'], $artifact['size'], $artifact['num']) .
                    '</strong>';
                $text = sprintf(T('Reports', 'defenderIsSupportedByTheFollowingArtifact'), $artifactName);
                $information[] =
                    '<img src="img/spacer.gif" class="gebIcon g27Icon" alt="' .
                    $treasuryTitle .
                    '" title="' .
                    $treasuryTitle .
                    '"> ' .
                    $text;
            }
        }

        if (isset($data['info']['heroPlanCapture'])) {
            $information[] =
                '<img src="img/spacer.gif" class="unit uhero" title="' .
                T('Troops', 'hero.title') .
                '" alt="' .
                T('Troops', 'hero.title') .
                '">' .
                T('Reports', 'WWPlanCaptured');
        }
        if (isset($data['info']['heroArtifactCapture'])) {
            $information[] =
                '<img src="img/spacer.gif" class="unit uhero" title="' .
                T('Troops', 'hero.title') .
                '" alt="' .
                T('Troops', 'hero.title') .
                '">' .
                T('Reports', 'heroArtifactCapture');
        }
        if (isset($data['info']['captureError'])) {
            $text =
                '<img src="img/spacer.gif" class="unit uhero" title="' .
                T('Troops', 'hero.title') .
                '" alt="' .
                T('Troops', 'hero.title') .
                '"> ';
            switch ($data['info']['captureError']) {
                case -1:
                    $text .= T('Reports', 'NoFreeAttackerTreasurySpace');
                    break;
                case -3:
                    $text .= T('Reports', 'TreasuryExists');
                    break;
            }
            $information[] = $text;
        }

        if (isset($data['info']['rp'])) {
            $information[] =
                '<img src="img/spacer.gif" class="gebIcon g' .
                $data['info']['rp'][0] .
                'Icon" title="' .
                T('Buildings', $data['info']['rp'][0] . '.title') .
                '" alt="' .
                T('Buildings', $data['info']['rp'][0] . '.title') .
                '"><b>' .
                T('Buildings', $data['info']['rp'][0] . '.title') .
                ' ' .
                T('Buildings', 'level') .
                ' ' .
                $data['info']['rp'][1] .
                '</b>';
        }
        if (isset($data['info']['wall'])) {
            $information[] =
                '<img src="img/spacer.gif" class="gebIcon g' .
                $data['info']['wall'][0] .
                'Icon" title="' .
                T('Buildings', $data['info']['wall'][0] . '.title') .
                '" alt="' .
                T('Buildings', $data['info']['wall'][0] . '.title') .
                '"><b>' .
                T('Buildings', $data['info']['wall'][0] . '.title') .
                ' ' .
                T('Buildings', 'level') .
                ' ' .
                $data['info']['wall'][1];
        }

        if (isset($data['info']['res'])) {
            $goods[] = [T('Reports', 'Resources'), '<div class="res">' . $this->resourceIcons($data['info']['res']) . '</div>'];
        }
        if (isset($data['info']['cranny'])) {
            $goods[] = [
                null,
                '<div class="res"><div class="rArea"><img class="gebIcon g23Icon" src="img/spacer.gif" title="' . T('Buildings', '23.title')
                    . '" alt="' . T('Buildings', '23.title') . '">&nbsp;' . number_format_x($data['info']['cranny']) . '</div></div>',
            ];
        }
        $bounty = !empty($notice['bounty']) ? explode(',', $notice['bounty']) : [];
        if (!empty($bounty[4])) {
            $carried = array_sum($bounty) - $bounty[4];
            $state = $carried == 0 ? 'empty' : ($carried == $bounty[4] ? 'full' : 'half');
            $goods[] = [
                T('Reports', 'Bounty'),
                '<div class="res">' . $this->resourceIcons([1 => $bounty[0], $bounty[1], $bounty[2], $bounty[3]]) . '</div>'
                    . '<div class="inlineIcon carry" title="' . T('Reports', 'carry') . '"><i class="carry ' . $state . '"></i><span class="value ">'
                    . $this->ltr($this->ltr((string)(int)$carried) . '/' . $this->ltr((string)(int)$bounty[4])) . '</span></div>',
            ];
        }
        $rows = '';
        foreach ($information as $index => $info) {
            $rows .= '<tr><th>' . ($index === 0 ? T('Reports', 'Information') : '') . '</th><td><div>' . $info . '</div></td></tr>';
        }
        foreach ($goods as [$title, $html]) {
            $rows .= '<tr><th>' . $title . '</th><td><div>' . $html . '</div></td></tr>';
        }

        return $this->infoTable($rows);
    }

    /**
     * The "Statistics" block. Supply (crop upkeep) and resource losses follow from the stored troop counts; the
     * combat strength row only appears for reports that store it (`$data['strength']`, attacker and defender).
     * Reports with hidden troop counts show no block.
     */
    private function combatStatistics(array $data): string
    {
        $sides = ['attacker' => [$data['attacker']], 'defender' => $data['defender']];
        $totals = [];
        foreach ($sides as $side => $groups) {
            $totals[$side] = ['supplyBefore' => 0, 'supplyLost' => 0, 'resourcesLost' => 0];
            foreach ($groups as $group) {
                foreach ([$group['num'], $group['dead'] ?? []] as $counts) {
                    if (in_array('?', $counts, true)) {
                        return '';
                    }
                }
                for ($nr = 1; $nr <= ($group['race'] == 4 ? 10 : 11); ++$nr) {
                    $unitId = $nr == 11 ? 98 : nrToUnitId($nr, $group['race']);
                    $upkeep = Formulas::uUpkeep($unitId, 0, true);
                    $dead = (int) ($group['dead'][$nr] ?? 0);
                    $totals[$side]['supplyBefore'] += (int) $group['num'][$nr] * $upkeep;
                    $totals[$side]['supplyLost'] += $dead * $upkeep;
                    if ($nr != 11) {
                        $totals[$side]['resourcesLost'] += $dead * array_sum(Formulas::uTrainingCost($unitId));
                    }
                }
            }
        }
        $cell = fn(string $icon, int $value): string => '<td><div class="inlineIcon " title=""><i class="' . $icon . '"></i><span class="value ">'
            . $this->ltr((string) number_format_x($value)) . '</span></div></td>';
        $rows = '';
        if (isset($data['strength'])) {
            $rows .= '<tr title="' . T('Reports', 'combatStrengthHint') . '"><th>' . T('Reports', 'combatStrength') . '</th>'
                . $cell('offence_medium', (int) $data['strength']['attacker']) . $cell('defence_medium', (int) $data['strength']['defender']) . '</tr>';
        }
        foreach (['supplyBefore' => 'r5Big', 'supplyLost' => 'r5Big', 'resourcesLost' => 'resources_medium'] as $key => $icon) {
            $rows .= '<tr><th>' . T('Reports', $key) . '</th>' . $cell($icon, $totals['attacker'][$key]) . $cell($icon, $totals['defender'][$key]) . '</tr>';
        }

        return '<div class="combatStatistics"><div class="header"><h2>' . T('Reports', 'combatStatistics') . '</h2></div>'
            . '<table class="combatStatistic"><thead><tr><th></th><th>' . T('Reports', 'Attacker') . '</th><th>' . T('Reports', 'Defender')
            . '</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    /** Wraps ready-made `<tr>` rows in the report's information table. */
    private function infoTable(string $rows): string
    {
        return $rows === '' ? '' : '<table class="additionalInformation"><tbody class="infos">' . $rows . '</tbody></table>';
    }

    /** @param array<int, int|string> $amounts lumber, clay, iron, crop at keys 1-4 */
    private function resourceIcons(array $amounts): string
    {
        $html = '<div class="inlineIconList resourceWrapper">';
        foreach (['lumber', 'clay', 'iron', 'crop'] as $index => $class) {
            $html .= '<div class="inlineIcon resources" title="' . T('inGame', 'resources.r' . ($index + 1)) . '"><i class="' . $class
                . '"></i><span class="value ">' . (int)$amounts[$index + 1] . '</span></div>';
        }

        return $html . '</div>';
    }

    /** Report type ids encoded in the list sort token (reference `opt` value). */
    /** Report types the overview lists (the `opt` token of the All tab). */
    public const OVERVIEW_TOKEN_TYPES = [0, 1, 2, 3, 4, 5, 6, 7, 8, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23];

    /**
     * `opt` query value of the sort and filter links: the order flag (1 = oldest first) followed by the
     * report types left active, as little-endian 16-bit integers.
     *
     * @param list<int> $types
     */
    public static function sortToken(int $oldestFirst, array $types): string
    {
        return base64_encode(pack('v*', $oldestFirst, ...$types));
    }

    /** @return array{order: int, types: list<int>}|null null when `opt` is not a sort/filter token */
    private function parseOptToken(?string $opt): ?array
    {
        $raw = $opt === null ? false : base64_decode($opt, true);
        if ($raw === false || $raw === '' || strlen($raw) % 2 !== 0) {
            return null;
        }
        $values = array_values(unpack('v*', $raw));

        return ['order' => $values[0] & 1, 'types' => array_slice($values, 1)];
    }

    private function showOverview($percent = false)
    {
        $view = new PHPBatchView('reports/All');
        $this->populateFilters();
        $tab = (int) $this->content->vars['Tabs']['selectedTab'];
        $optToken = $this->parseOptToken(is_string($_GET['opt'] ?? null) ? $_GET['opt'] : null);
        if ($optToken !== null && $tab > 0 && $tab < 5) {
            $reportFilters = $this->session->getReportFilters();
            $hex = 0;
            foreach ($this->categories[$tab] as $bit => $category) {
                if (in_array($category, $optToken['types'], true)) {
                    $hex |= 1 << $bit;
                }
            }
            if ($hex !== 0) {
                $reportFilters[$tab + 2] = $hex;
                $this->session->setReportFilters($reportFilters);
            }
        }
        $this->populateFilters();
        $recursive = ($optToken['order'] ?? 0) === 1;
        $view->vars['selectedTabIndex'] = $this->selectedTabIndex;
        $view->vars['recursive'] = $recursive;
        $view->vars['selectedTabId'] = $this->content->vars['Tabs']['selectedTab'];
        $view->vars['navigator'] = $view->vars['reports'] = '';
        $view->vars['goldClub'] = $this->session->hasGoldClub();
        $view->vars['hasPermission'] = $this->session->checkSitterPermission(
            Session::SITTER_CAN_REMOVE_ARCHIVE_MESSAGES_OR_REPORTS
        );
        $view->vars['confirmDeleteMultipleReports'] = (int) ($this->session->getDisplay()[8] ?? 0) === 1;
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $prefix = [];
        $view->vars['page'] = $page;
        $view->vars['allCategories'] = $this->categories;
        $view->vars['customCategories'] = $this->customReportTypes;
        $entity = $this->entityFilter();
        $view->vars['entityQuery'] = $entity === [] ? '' : '&' . http_build_query($entity);
        $prefix += $entity;
        $m = new ReportsModel();
        $db = DB::getInstance();
        if ($percent !== false) {
            $losses = (int) $_GET['percent'];
            $result = $db->query(
                'SELECT * FROM ndata WHERE uid=' .
                    $this->session->getPlayerId() .
                    " AND losses >= $losses ORDER BY id " .
                    ($recursive ? 'ASC' : 'DESC') .
                    ', id ' .
                    ($recursive ? 'ASC' : 'DESC') .
                    ' LIMIT ' .
                    ($page - 1) * $this->session->getReportsRecordsPerPage() .
                    ', ' .
                    $this->session->getReportsRecordsPerPage()
            );
        } else {
            $result = $m->getAllReports(
                $this->session->getPlayerId(),
                $page,
                $recursive,
                $view->vars['selectedTabId'],
                $this->customReportTypes,
                $entity
            );
        }
        if (!$result->num_rows) {
            $view->vars['reports'] = '<tr><td colspan="3" class="noData">' . T('Reports', 'noData') . '</td></tr>';
            $view->vars['navigator'] = (new PageNavigator(
                1,
                0,
                $this->session->getReportsRecordsPerPage(),
                [],
                $this->getReportTabPath($view->vars['selectedTabId']),
                true
            ))->get();
            goto finalize;
        }
        $i = 0;
        while ($row = $result->fetch_assoc()) {
            $view->vars['reports'] .= $this->reportOverviewLayout($row, ++$i);
        }
        if ($recursive) {
            $prefix['opt'] = self::sortToken(1, $optToken['types'] ?? []);
        }
        if ($percent !== false) {
            $losses = (int) $_GET['percent'];
            $prefix['percent'] = $losses;
            $allCount = (int) $db->fetchScalar(
                'SELECT COUNT(id) FROM ndata WHERE uid=' . $this->session->getPlayerId() . " AND losses >= $losses"
            );
        } else {
            $allCount = $m->getAllReportsCount(
                $this->session->getPlayerId(),
                $view->vars['selectedTabId'],
                $this->customReportTypes,
                $entity
            );
        }
        $p = new PageNavigator(
            $page,
            $allCount,
            $this->session->getReportsRecordsPerPage(),
            $prefix,
            $this->getReportTabPath($view->vars['selectedTabId']),
            true
        );
        $view->vars['navigator'] = $p->get();
        finalize:
        $view->vars['inboxSize'] = '';
        if ((int)$view->vars['selectedTabId'] === 0) {
            $view->vars['inboxSize'] = (new PHPBatchView('reports/reportOverflow'))->output();
        }
        $this->content->vars['content'] = ($this->content->vars['content'] ?? '') . $view->output();
    }

    /** @return array{villageId?: int, playerId?: int} the `villageId`/`playerId` list filter; a village wins */
    private function entityFilter(): array
    {
        foreach (['villageId', 'playerId'] as $key) {
            $id = filter_var($_GET[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id !== false) {
                return [$key => $id];
            }
        }

        return [];
    }

    private function populateFilters()
    {
        $this->customReportTypes = [1 => [], 2 => [], 3 => [], 4 => []];
        $tab = (int) $this->content->vars['Tabs']['selectedTab'];
        if ($tab < 1 || $tab > 4) {
            return;
        }
        $hex = (int) $this->session->getReportFilters()[2 + $tab];
        foreach ($this->categories[$tab] as $bit => $category) {
            if ($category !== self::SETTLING_FILTER_ICON && ($hex & (1 << $bit))) {
                $this->customReportTypes[$tab][] = $category;
            }
        }
        if ($tab === 4) {
            $this->customReportTypes[4] = [...$this->customReportTypes[4], ...self::OTHER_TAB_HIDDEN_TYPES];
        }
    }

    private function getReportTabPath($tabId)
    {
        $paths = [
            0 => '/report/overview',
            1 => '/report/offensive',
            2 => '/report/defensive',
            3 => '/report/scouting',
            4 => '/report/other',
            5 => '/report/archive',
            6 => '/report/surrounding',
        ];

        return $paths[(int) $tabId] ?? '/report/overview';
    }

    public function reportOverviewLayout($row, $i)
    {
        $rpt = new PHPBatchView('reports/overviewLayout');
        $rpt->vars['subject'] = $this->getNoticeSubject($row, false);
        $rpt->vars['selectedTabIndex'] = $this->selectedTabIndex;
        $rpt->vars['i'] = $i;
        $rpt->vars['noticeId'] = $row['id'];
        $rpt->vars['private_key'] = $row['private_key'];
        $rpt->vars['reportUrl'] = $this->getReportTabPath($this->selectedTabIndex) .
            '?id=' . $row['id'] . '%7C' . $row['private_key'] . '&s=' . max(1, (int) ($_GET['page'] ?? 1));
        $rpt->vars['linkClass'] = (int) $row['type'] === NoticeHelper::TYPE_ADVENTURE ? 'adventure' : '';
        $rpt->vars['tabId'] = $this->content->vars['Tabs']['selectedTab'];
        $rpt->vars['type'] = $row['type'];
        $rpt->vars['viewed'] = $row['viewed'];
        $rpt->vars['reportIcon'] = '';
        $rpt->vars['date'] = $this->getTimeByTimezone($row['time'], true);
        $rpt->vars['hasPermission'] = $this->session->checkSitterPermission(
            Session::SITTER_CAN_REMOVE_ARCHIVE_MESSAGES_OR_REPORTS
        );
        switch ($row['type']) {
            case NoticeHelper::TYPE_WON_AS_ATTACK_WITHOUT_CASUALTIES:
            case NoticeHelper::TYPE_WON_AS_ATTACK_WITH_CASUALTIES:
            case NoticeHelper::TYPE_LOST_AS_ATTACKER:
            case NoticeHelper::TYPE_WON_DEFENSE_WITHOUT_LOSSES:
            case NoticeHelper::TYPE_WON_DEFENSE_WITH_LOSSES:
            case NoticeHelper::TYPE_LOST_AS_DEFENDER_WITH_CASUALTIES:
            case NoticeHelper::TYPE_LOST_AS_DEFENDER_WITHOUT_CASUALTIES:
                $bounty = explode(',', $row['bounty']);
                if (!isset($bounty[4]) || !$bounty[4]) {
                    break;
                }
                $resources = array_sum($bounty) - $bounty[4];
                $style = $resources == $bounty[4] ? 'full' : ($resources == 0 ? 'empty' : 'half');
                if ($this->session->hasPlus()) {
                    $rpt->vars['reportIcon'] =
                        '<a class="reportInfoIcon" href="/build.php?id=39&amp;tt=2&amp;reportId=' .
                        $row['id'] .
                        '&amp;readReport=1"><img title="' .
                        $this->number_format($resources) .
                        '/' .
                        $this->number_format($bounty[4]) .
                        '" alt="' .
                        $this->number_format($resources) .
                        '/' .
                        $this->number_format($bounty[4]) .
                        '" src="img/spacer.gif" class="reportInfo carry ' .
                        $style .
                        '" /></a>';
                } else {
                    $rpt->vars['reportIcon'] =
                        '<span class="reportInfo"><img title="' .
                        $this->number_format($resources) .
                        '/' .
                        $this->number_format($bounty[4]) .
                        '" alt="' .
                        $this->number_format($resources) .
                        '/' .
                        $this->number_format($bounty[4]) .
                        '" src="img/spacer.gif" class="reportInfo carry ' .
                        $style .
                        '" /></span>';
                }
                break;
            case NoticeHelper::TYPE_ADVENTURE:
                if ($row['bounty'] == '-1') {
                    //dead //
                    $rpt->vars['reportIcon'] =
                        '<span class="reportInfo"><img title="' .
                        T('Reports', 'adventureFailed') .
                        '" alt="' .
                        T('Reports', 'adventureFailed') .
                        '" class="adventureDifficulty0" src="img/spacer.gif"></span>';
                } elseif ($row['bounty'] == '0') {
                } else {
                    $bounty = explode(',', $row['bounty']);
                    switch ($bounty[0]) {
                        case 1:
                            $HeroItems = new HeroItems();
                            //item bonus.
                            $typeArray = [
                                '',
                                'helmet',
                                'body',
                                'leftHand',
                                'rightHand',
                                'shoes',
                                'horse',
                                'bandage25',
                                'bandage33',
                                'cage',
                                'scroll',
                                'ointment',
                                'bucketOfWater',
                                'bookOfWisdom',
                                'lawTables',
                                'artWork',
                            ];
                            $class = $typeArray[$bounty[1]];
                            $item = $HeroItems->getHeroItemProperties($bounty[1], $bounty[2]);
                            $rpt->vars['reportIcon'] =
                                '<span class="reportInfo"><img title="' .
                                $item['name'] .
                                ' (' . "\u{202D}\u{202D}" . $this->number_format($bounty[3]) . "\u{202C}×\u{202C}" .
                                ')" alt="' . $item['name'] . '" class="reportInfo itemCategory itemCategory_' .
                                $class .
                                '" src="img/spacer.gif"></span>';
                            break;
                        case 2:
                            $rpt->vars['reportIcon'] =
                                '<span class="reportInfo"><img title="' .
                                T('Reports', 'Resources') . ': ' . (array_sum($bounty) - 2) .
                                '" alt="' . T('Reports', 'Resources') .
                                '" src="img/spacer.gif" class="reportInfo carry full" /></span>';
                            break;
                        case 3:
                            $rpt->vars['reportIcon'] =
                                '<span class="reportInfo"><img title="' .
                                $bounty[1] .
                                ' ' .
                                T('Reports', 'Silver') .
                                '" class="silver" src="img/spacer.gif"></span>';
                            break;
                        case 4:
                            $unitId = nrToUnitId($bounty[2], $bounty[1]);
                            $rpt->vars['reportIcon'] =
                                '<span class="reportInfo"><img title="' .
                                T('Troops', "{$unitId}.title") .
                                ' (' .
                                $this->number_format($bounty[3]) .
                                'x)" alt="" class="unit u' .
                                $unitId .
                                '" src="img/spacer.gif"></span>';
                            break;
                    }
                }
                break;
        }
        return $rpt->output();
    }

    private function showSurrounding()
    {
        Quest::getInstance()->setQuestBitwise('world', 13, 1);
        $view = new PHPBatchView('reports/Surrounding');
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $prefix = [];
        $view->vars['tbody'] = $view->vars['navigator'] = '';
        $m = new ReportsModel();
        $result = $m->getSurrounding($this->session->getKid(), $page);
        if (!$result->num_rows) {
            $view->vars['tbody'] = '<tr><td colspan="4" class="noData">' . T('Reports', 'noData') . '</td></tr>';
            goto finalize;
        }
        while ($row = $result->fetch_assoc()) {
            $view->vars['tbody'] .= $this->getSurrounding($row);
        }
        $p = new PageNavigator(
            $page,
            $m->getSurroundingCount($this->session->getKid()),
            $this->session->getReportsRecordsPerPage(),
            $prefix,
            '/report/surrounding'
        );
        $view->vars['navigator'] = $p->get();
        finalize:
        $this->content->vars['content'] .= $view->output();
    }

    private function getSurrounding($row)
    {
        $view = new PHPBatchView('reports/SurroundingLayout');
        $view->vars['reportIcon'] = NoticeHelper::getSurroundingStyle($row['type']);
        $view->vars['x'] = $row['x'];
        $view->vars['y'] = $row['y'];
        $view->vars['distance'] = round($row['distance'], 1);
        switch ($row['type']) {
            case NoticeHelper::SURROUNDING_OASIS_RAID:
                $view->vars['reportText'] = T('Reports', 'An Oasis was plundered');
                break;
            case NoticeHelper::SURROUNDING_OASIS_OCCUPY:
                $data = explode(':', $row['params']);
                $view->vars['reportText'] = sprintf(
                    T('Reports', 'x has conquered an oasis'),
                    '<a href="' . profileUrl((int) $data[0]) . '">' . $data[1] . '</a>'
                );
                break;
            case NoticeHelper::SURROUNDING_OASIS_ABANDON:
                $view->vars['reportText'] = T('Reports', 'An Oasis was abandoned');
                break;
            case NoticeHelper::SURROUNDING_FIGHT:
                $data = explode(':', $row['params']);
                $view->vars['reportText'] = sprintf(
                    T('Reports', 'A fight took at village name of player name'),
                    '<a href="' . profileUrl((int) $data[0]) . '">' . $data[1] . '</a>',
                    '<a href="/karte.php?d=' . $data[2] . '">' . $this->getVillageName($data[2]) . '</a>'
                );
                break;
            case NoticeHelper::SURROUNDING_VILLAGE_FOUND:
                $data = explode(':', $row['params']);
                $view->vars['reportText'] = sprintf(
                    T('Reports', 'x has founded y'),
                    '<a href="' . profileUrl((int) $data[0]) . '">' . $data[1] . '</a>',
                    '<a href="/karte.php?d=' . $data[2] . '">' . $this->getVillageName($data[2]) . '</a>'
                );
                break;
            case NoticeHelper::SURROUNDING_VILLAGE_CONQUER:
                $data = explode(':', $row['params']);
                $view->vars['reportText'] = sprintf(
                    T('Reports', 'x has conquered y'),
                    '<a href="' . profileUrl((int) $data[0]) . '">' . $data[1] . '</a>',
                    '<a href="/karte.php?d=' . $data[2] . '">' . $this->getVillageName($data[2]) . '</a>'
                );
                break;
            case NoticeHelper::SURROUNDING_VILLAGE_LOST:
                $data = explode(':', $row['params']);
                $view->vars['reportText'] = sprintf(
                    T('Reports', 'x has lost y'),
                    '<a href="' . profileUrl((int) $data[0]) . '">' . $data[1] . '</a>',
                    '<a href="/karte.php?d=' . $data[2] . '">' . $data[3] . '</a>'
                );
                break;
            case NoticeHelper::SURROUNDING_VILLAGE_RENAME:
                $data = explode(':', $row['params']);
                $view->vars['reportText'] = sprintf(
                    T('Reports', 'x renamed y to z'),
                    '<a href="' . profileUrl((int) $data[0]) . '">' . $data[1] . '</a>',
                    '<a href="/karte.php?d=' . $data[2] . '">' . $data[3] . '</a>',
                    '<a href="/karte.php?d=' . $data[2] . '">' . $data[4] . '</a>'
                );
                break;
            case NoticeHelper::SURROUNDING_ALLIANCE:
                $data = explode(':', $row['params']);
                $view->vars['reportText'] = sprintf(
                    T('Reports', 'x switched from y to z'),
                    '<a href="' . profileUrl((int) $data[0]) . '">' . $data[1] . '</a>',
                    $this->getAllianceLink($data[2]),
                    $this->getAllianceLink($data[3])
                );
                break;
        }
        $view->vars['date'] = $this->getTimeByTimezone($row['time'], true);
        return $view->output();
    }
}
