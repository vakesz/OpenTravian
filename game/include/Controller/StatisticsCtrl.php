<?php

namespace Controller;

use Core\Config;
use Core\Database\DB;
use Core\Helper\PageNavigator;
use Core\Helper\TimezoneHelper;
use Core\Session;
use Game\Formulas;
use Model\AutomationModel;
use Model\ReportsModel;
use Model\Quest;
use Model\StatisticsGeneralModel;
use Model\StatisticsModel;
use Model\SummaryModel;
use resources\View\GameView;
use resources\View\PHPBatchView;

use function getDisplay;
use function get_gpack_cdn_mainPage_url;
use function isServerFinished;

class StatisticsCtrl extends GameCtrl
{
    private $pageSize = 20;
    private $content;
    private $showFakeUsers = true;

    private function getSearchName()
    {
        $name = $_REQUEST['name'] ?? '';

        return is_string($name) ? trim(strip_tags($name)) : '';
    }

    private function getEscapedSearchName($name)
    {
        return htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    }

    public function __construct()
    {
        parent::__construct();
        Quest::getInstance()->setQuestBitwise('world', 1, \Model\Quest::QUEST_FINISHED);
        $this->view = new GameView();
        $this->view->vars['titleInHeader'] = T("Statistics", "titleInHeader");
        // Reference body token is `statistics` (no village perspective shell).
        $this->view->vars['bodyCssClass'] = 'statistics';
        $this->view->vars['contentCssClass'] = 'statistics';
        $this->view->vars['showHeaderBar'] = true;
        $this->view->vars['showCloseButton'] = true;
        $selectedTabIndex = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : (Session::getInstance()->getStoredFavoriteTab('statistics') ?? 0);
        $selectedSubIndex = isset($_REQUEST['idSub']) ? (int)$_REQUEST['idSub'] : Session::getInstance()->getFavoriteTab($selectedTabIndex == 0 ? 'statisticsTablePlayer' : 'statisticsTableAlly');
        $availableTabs = [0, 1, 2, 3, 5, 6];
        if (getDisplay('showBonusTabInStatistics')) {
            $availableTabs[] = 7;
        }
        if (getDisplay('showFarmsInStatistics')) {
            $availableTabs[] = 8;
        }
        if (!in_array($selectedTabIndex, $availableTabs, true)) {
            $selectedTabIndex = 0;
        }
        if ($selectedTabIndex === 6 && !(new StatisticsModel())->hasWonderVillages()) {
            // No wonder exists yet: the reference serves the player statistics for this route.
            $selectedTabIndex = 0;
            $selectedSubIndex = 0;
        }
        if ($selectedTabIndex < 2) {
            $selectedSubIndex = $selectedSubIndex >= 0 && $selectedSubIndex < 4 ? $selectedSubIndex : 0;
        } else {
            $selectedSubIndex = 0;
        }
        $statisticsTabFallbacks = [
            'players' => 'Player(s)',
            'alliances' => 'Alliance',
            'villages' => 'Village',
            'hero' => 'Hero',
            'General' => 'General',
            'WonderOfTheWorld' => 'WonderOfTheWorld',
            'Bonus' => 'Bonuses',
            'farm' => 'Farms',
        ];
        $statisticsTabLabel = static function (string $key, string $fallback): string {
            $label = T('Statistics', $key, $fallback);
            return is_string($label) && trim($label) !== '' ? $label : $fallback;
        };
        $this->content = new PHPBatchView("statistics/main");
        $this->content->vars = [
            "selectedTabIndex" => $selectedTabIndex,
            "selectedSubIndex" => $selectedSubIndex,
            "playersTabProperties" => [
                "id" => get_button_id(),
                "active" => $selectedTabIndex == 0,
                "text" => $statisticsTabLabel('tabs.players', $statisticsTabFallbacks['players']),
            ],
            "alliancesTabProperties" => [
                "id" => get_button_id(),
                "active" => $selectedTabIndex == 1,
                "text" => $statisticsTabLabel('tabs.alliances', $statisticsTabFallbacks['alliances']),
            ],
            "villagesTabProperties" => [
                "id" => get_button_id(),
                "active" => $selectedTabIndex == 2,
                "text" => $statisticsTabLabel('tabs.villages', $statisticsTabFallbacks['villages']),
            ],
            "heroTabProperties" => [
                "id" => get_button_id(),
                "active" => $selectedTabIndex == 3,
                "text" => $statisticsTabLabel('tabs.hero', $statisticsTabFallbacks['hero']),
            ],
            "GeneralTabProperties" => [
                "id" => get_button_id(),
                "active" => $selectedTabIndex == 5,
                "text" => $statisticsTabLabel('tabs.General', $statisticsTabFallbacks['General']),
            ],
            "WonderTabProperties" => [
                "id" => get_button_id(),
                "active" => $selectedTabIndex == 6,
                "text" => $statisticsTabLabel('tabs.WonderOfTheWorld', $statisticsTabFallbacks['WonderOfTheWorld']),
            ],
            "BonusTabProperties" => [
                "id" => get_button_id(),
                "active" => $selectedTabIndex == 7,
                "text" => $statisticsTabLabel('tabs.Bonus', $statisticsTabFallbacks['Bonus']),
            ],
            "farmTabProperties" => [
                "id" => get_button_id(),
                "active" => $selectedTabIndex == 8,
                "text" => $statisticsTabLabel('tabs.farm', $statisticsTabFallbacks['farm']),
            ],
            "overviewSubTabProperties" => [
                "id" => get_button_id(),
                "active" => $selectedSubIndex == 0,
                "text" => $statisticsTabLabel('subTabs.overview', 'Overview'),
            ],
            "attackerSubTabProperties" => [
                "id" => get_button_id(),
                "active" => $selectedSubIndex == 1,
                "text" => $statisticsTabLabel('subTabs.attacker', 'Attacker'),
            ],
            "defenderSubTabProperties" => [
                "id" => get_button_id(),
                "active" => $selectedSubIndex == 2,
                "text" => $statisticsTabLabel('subTabs.defender', 'Defender'),
            ],
            "top10SubTabProperties" => [
                "id" => get_button_id(),
                "active" => $selectedSubIndex == 3,
                "text" => $statisticsTabLabel('subTabs.Top 10', 'Top 10'),

            ],
            "error" => '',
            "other" => '',
        ];
        switch ($selectedTabIndex) {
            case 0:
                $this->view->vars['contentCssClass'] .= ' statisticsPlayer';
                break;
            case 1:
                $this->view->vars['contentCssClass'] .= ' statisticsAlliance';
                break;
            case 2:
                $this->view->vars['contentCssClass'] .= ' statisticsVillage';
                break;
            case 3:
                $this->view->vars['contentCssClass'] .= ' statisticsHero';
                break;
            case 5:
                $this->view->vars['contentCssClass'] .= ' statisticsGeneral';
                break;
            case 6:
                $this->view->vars['contentCssClass'] .= ' statisticsWonderoftheworld';
                break;
            case 7:
                $this->view->vars['contentCssClass'] .= ' statisticsBonus';
                break;
            case 8:
                $this->view->vars['contentCssClass'] .= ' statisticsFarm';
                break;

        }

        // the favourite-tab buttons carry a title whose quotes cut it to "Set tab".
        $this->content->vars['favorText'] = T('inGame', 'Set tab');
        if (Session::getInstance()->isAdmin()) {
            if (isset($_GET['showFakeUsers'])) {
                Session::setCookie('showFakeUsers', $_GET['showFakeUsers'] == 1 ? 1 : 0, 7 * 86400);
            }
            $this->showFakeUsers = Session::getCookie('showFakeUsers', 0);
        }
        $this->content->vars['other'] = $this->handle($selectedTabIndex, $selectedSubIndex);
        $this->view->vars['content'] .= $this->content->output();
    }

    public function handle($selectedTabIndex, $selectedSubIndex)
    {
        switch ($selectedTabIndex) {
            case 0:
                switch ($selectedSubIndex) {
                    case 0:
                        return $this->processPlayersOverview($selectedTabIndex, $selectedSubIndex);
                        break;
                    case 1:
                    case 2:
                        return $this->processPlayerAttackerOrDefender(
                            $selectedTabIndex,
                            $selectedSubIndex,
                            $selectedSubIndex == 2
                        );
                        break;
                    case 3:
                        return $this->processTOP10($selectedTabIndex == 1);
                        break;
                }
                break; //players
            case 1:
                switch ($selectedSubIndex) {
                    case 0:
                        return $this->processAlliancesOverview($selectedTabIndex, $selectedSubIndex);
                        break;
                    case 1:
                    case 2:
                        return $this->processAllianceAttackerOrDefender(
                            $selectedTabIndex,
                            $selectedSubIndex,
                            $selectedSubIndex == 2
                        );
                        break;
                    case 3:
                        return $this->processTOP10($selectedTabIndex == 1);
                        break;
                }
                break; //alliances
            case 2:
                return $this->processVillagesOverview($selectedTabIndex, $selectedSubIndex);
                break;
            case 3:
                return $this->processHerosOverview($selectedTabIndex, $selectedSubIndex);
                break;
            case 5:
                return $this->processGeneralOverview();
            case 6:
                return $this->processWonderOverview();
            case 7:
                if (getDisplay("showBonusTabInStatistics")) {
                    return PHPBatchView::render('statistics/bonus');
                }
                break;
            case 8: //farms
                if (getDisplay("showFarmsInStatistics")) {
                    return $this->showFarms();
                }
                break;
        }
    }

    private function statsNum($value): string
    {
        return "\u{202D}" . $value . "\u{202C}";
    }

    /** @var array<int,string> */
    private array $statsBannerCache = [];
    private int $statsBannerSeq = 0;

    /** Small alliance banner for td.banner (empty when the alliance has none). */
    private function statsBanner(int $aid): string
    {
        if ($aid <= 0) {
            return '';
        }
        $model = new \Model\AllianceBannerModel();
        if (!array_key_exists($aid, $this->statsBannerCache)) {
            $banner = $model->getAllianceBanner($aid);
            $this->statsBannerCache[$aid] = $banner === null ? '' : 'y';
            if ($banner !== null) {
                $this->statsBannerCache[-$aid] = serialize($banner);
            }
        }
        if ($this->statsBannerCache[$aid] === '') {
            return '';
        }
        return \Model\AllianceBannerModel::renderSvg(
            unserialize($this->statsBannerCache[-$aid], ['allowed_classes' => false]),
            'statsBanner_' . $aid . '_' . (++$this->statsBannerSeq)
        );
    }

    /** @param array<int,array{0:string,1:string}|string|null> $cells */
    private function statsHead(array $cells): string
    {
        $html = '<tr>';
        foreach ($cells as $cell) {
            if ($cell === null) {
                continue;
            }
            $html .= is_array($cell) ? '<td class="' . $cell[0] . ' ">' . $cell[1] . '</td>' : $cell;
        }
        return $html . '</tr>';
    }

    /** @param array<int,array{0:string,1:string}|string|null> $cells */
    private function statsRow(bool $highlight, array $cells): string
    {
        $cells = array_values(array_filter($cells, static fn ($c) => $c !== null));
        $indexes = array_keys(array_filter($cells, 'is_array'));
        $first = $indexes[0] ?? -1;
        $last = $indexes ? $indexes[count($indexes) - 1] : -1;
        $html = '<tr class="' . ($highlight ? 'hl' : 'hover') . '">';
        foreach ($cells as $i => $cell) {
            if (!is_array($cell)) {
                $html .= $cell;
                continue;
            }
            $class = $cell[0] . ' ';
            if ($highlight && $i === $first) {
                $class .= ' fc';
            }
            if ($highlight && $i === $last) {
                $class .= ' lc';
            }
            $html .= '<td class="' . $class . '">' . $cell[1] . '</td>';
        }
        return $html . '</tr>';
    }

    private function processWonderOverview(): string
    {
        $body = '';
        foreach ((new StatisticsModel())->getWonderVillages() as $index => $wonder) {
            $xy = Formulas::kid2xy($wonder['kid']);
            $coordinates = '<span class="coordinates coordinatesWrapper"><span class="coordinateX">(' . coordinateNumberHTML($xy['x']) . '</span><span class="coordinatePipe">|</span><span class="coordinateY">' . coordinateNumberHTML($xy['y']) . ')</span></span>';
            $label = htmlspecialchars($wonder['name'] !== '' ? $wonder['name'] : T('Global', 'wwName'), ENT_QUOTES, 'UTF-8')
                . " \u{202D}" . $coordinates . "\u{202C}";
            $attack = '-';
            if ($wonder['attackArrival'] !== null) {
                $title = htmlspecialchars(sprintf(T("Statistics", "WonderOfTheWorld.attackToWonder"), $wonder['attackArrival']), ENT_QUOTES, 'UTF-8');
                $attack = '<img class="att1" alt="' . $title . '" title="' . $title . '" src="/img/spacer.gif">';
            }
            $body .= $this->statsRow(false, [
                ['ra', ($index + 1) . '.'],
                ['pla', '<a href="' . profileUrl($wonder['owner']) . '">' . htmlspecialchars($wonder['playerName'], ENT_QUOTES, 'UTF-8') . '</a>'],
                ['nam', '<a href="/karte.php?d=' . $wonder['kid'] . '">' . $label . '</a>'],
                ['banner', $this->statsBanner($wonder['aid'])],
                ['al', $wonder['aid'] > 0 ? '<a href="' . allianceUrl($wonder['aid']) . '">' . htmlspecialchars($wonder['allianceTag'], ENT_QUOTES, 'UTF-8') . '</a>' : '-'],
                ['lev', (string)$wonder['level']],
                ['at', $attack],
            ]);
        }

        return PHPBatchView::render('statistics/WonderOfTheWorld', [
            'tableColumns' => $this->statsHead([
                ['ra', ''],
                ['pla', T("Statistics", "tabs.players", "Player(s)")],
                ['nam', T("Statistics", "WonderOfTheWorld.name", "Name")],
                ['banner', ''],
                ['al', T("Statistics", "tabs.alliances", "Alliance")],
                ['lev', T("Statistics", "WonderOfTheWorld.level", "Level")],
                ['at', ''],
            ]),
            'tableBody' => $body,
        ]);
    }

    private function processGeneralOverview()
    {
        return PHPBatchView::render(
            'statistics/general',
            ['dashboard' => (new StatisticsGeneralModel())->viewData((int) Session::getInstance()->getPlayerId())]
        );
    }

    private function processPlayersOverview($selectedTabIndex, $selectedSubIndex)
    {
        $statistics = new StatisticsModel();
        $selectedRank = 0;
        $searchName = $this->getSearchName();
        if ($searchName !== '') {
            $selectedRank = $statistics->getPlayerRankByName($searchName);
            if (!$selectedRank) {
                $this->content->vars['error'] = sprintf(
                    T("Statistics", "errors.userNotFound"),
                    $this->getEscapedSearchName($searchName)
                );
            }
        } elseif (isset($_REQUEST['rank']) && is_numeric($_REQUEST['rank'])) {
            $selectedRank = (int)$_REQUEST['rank'];
            if ($selectedRank < 1) {
                $selectedRank = 1;
            }
        } elseif (!isset($_GET['page'])) {
            $selectedRank = $statistics->getPlayerRankById(Session::getInstance()->getPlayerId());
        }
        $pageIndex = !$selectedRank ? (isset($_GET['page']) ? (int)$_GET['page'] - 1 : 0) : floor(($selectedRank - 1) / $this->pageSize);
        $prefix = [];
        $formAction = self::cleanPath($selectedTabIndex, $selectedSubIndex);
        if ($pageIndex < 0) {
            $pageIndex = 0;
        }
        $total = $statistics->getPlayerListCount();
        $tot_pages = ceil($total / $this->pageSize);
        if ($pageIndex + 1 > $tot_pages) {
            $pageIndex = 0;
        }
        $nav = new PageNavigator(
            $pageIndex + 1,
            $statistics->getPlayerListCount(),
            $this->pageSize,
            [],
            $formAction
        );
        $rows = $statistics->getPlayerList($pageIndex, $this->pageSize);
        $tableBody = '';
        $rowIndex = 0;
        foreach ($rows as $row) {
            $rank = ++$rowIndex + $pageIndex * $this->pageSize;
            if (!$this->showFakeUsers && $row['access'] == 3) {
                continue;
            }
            $alliance = '-';
            if ($row['aid']) {
                $alliance = '<a href="' . allianceUrl((int) $row['aid']) . '">' . $row['alliance_name'] . '</a>';
            }
            $tribeRace = (int) $row['race'];
            $class = $rank == $selectedRank ? 'hl' : 'hover';
            $admin = null;
            $flag = null;
            if (Session::getInstance()->isAdmin()) {
                $admin = '<td class="buttons" style="padding: 2px 0 2px 2px;white-space: nowrap;width: 1%;"><a href="/admin.php?action=editPlayer&uid=' . $row['id'] . '"><button type="button" class="icon"><img style="' . (getDirection() == 'LTR' ? 'left' : 'right') . ': 6px;" src="' . get_gpack_cdn_mainPage_url() . 'img_common/f/edit.gif"></button></a></td>';
            }
            if (Session::getInstance()->isAdmin() || getDisplay("showFlagsInStatistics")) {
                $flag = '<td class="flags " >';
                if ($row['access'] <> 3) {
                    if (!empty($row['countryFlag']) && $row['countryFlag'] != "-" && $row['id'] > 2) {
                        $flag .= '<img src="img/spacer.gif" class="flags flag_' . $row['countryFlag'] . '" title="' . $row['countryFlag'] . '" alt="' . $row['countryFlag'] . '">';
                    } elseif ($row['countryFlag'] == "-" || empty($row['countryFlag'])) {
                        $flag .= '?';
                    } elseif ($row['id'] <= 2) {
                        $flag .= '!';
                    }
                } else {
                    $flag .= '!';
                }
                $flag .= '</td>';
            }
            if (Session::getInstance()->isAdmin() && $row['access'] <> 3) {
                if ($row['access'] == 0 && $row['id'] > 2) {
                    $row['name'] = '<span style="color:red;">' . $row['name'] . '</span>';
                } elseif ($row['access'] == 0 && $row['id'] == 2) {
                    $row['name'] = '<span style="color:blue;">' . $row['name'] . '</span>';
                }
                $login_stat = AutomationModel::getOnlineStatusAsImg($row['last_login_time']);
                $row['name'] = $login_stat . ' ' . $row['name'];
            }
            $profileHref = profileUrl((int) $row['id']);
            $tableBody .= $this->statsRow($class === 'hl', [
                ['ra', $rank . '.'],
                ['tribe', '<i class="tribe' . $tribeRace . '_medium"></i>'],
                ['pla', '<a href="' . $profileHref . '">' . $row['name'] . '</a>'],
                ['banner', $this->statsBanner((int) $row['aid'])],
                ['al', $alliance],
                $flag,
                ['pop', $this->statsNum($row['total_population'])],
                ['vil', (string) $row['total_villages_count']],
                $admin,
            ]);
        }
        return PHPBatchView::render(
            'statistics/table',
            [


                "tableId" => "player",
                "tableColumns" => $this->statsHead([
                    ['ra', ''], ['tribe', ''], ['pla', T("Statistics", "tabs.players", "Player(s)")], ['banner', ''],
                    ['al', T("Statistics", "alliance", "Alliance")],
                    Session::getInstance()->isAdmin() || getDisplay("showFlagsInStatistics") ? '<td class="flags">' . T("Statistics", "General.CountryFlag") . '</td>' : null,
                    ['pop', T("Statistics", "population", "Population")],
                    ['vil', T("Statistics", "village", "Village")],
                    Session::getInstance()->isAdmin() ? '<td class="buttons" style="padding: 2px 0 2px 2px;white-space: nowrap;width: 1%;">' . T("Statistics", "Actions") . '</td>' : null,
                ]),
                "headerRound" => T("Statistics", "largestPlayers"),
                "formAction" => $formAction,
                "tableBody" => $tableBody,
                "Navigator" => $nav->get(),
                "selectedRank" => $selectedRank,
                "selectedName" => $searchName,

            ]
        );
    }

    /** players */
    private function processPlayerAttackerOrDefender($selectedTabIndex, $selectedSubIndex, $isDefender)
    {
        $statistics = new StatisticsModel();
        $selectedRank = 0;
        $searchName = $this->getSearchName();
        if ($searchName !== '') {
            $selectedRank = $statistics->getPlayersPointsByName(
                $searchName,
                $isDefender
            );
            if (!$selectedRank) {
                $this->content->vars['error'] = sprintf(
                    T("Statistics", "errors.userNotFound"),
                    $this->getEscapedSearchName($searchName)
                );
            }
        } elseif (isset($_REQUEST['rank']) && is_numeric($_REQUEST['rank'])) {
            $selectedRank = (int)$_REQUEST['rank'];
            if ($selectedRank < 1) {
                $selectedRank = 1;
            }
        } elseif (!isset($_GET['page'])) {
            $selectedRank = $statistics->getPlayersPointsById(Session::getInstance()->get("id"), $isDefender);
        }
        $pageIndex = !$selectedRank ? (isset($_GET['page']) ? (int)$_GET['page'] - 1 : 0) : floor(($selectedRank - 1) / $this->pageSize);
        $prefix = [];
        $formAction = self::cleanPath($selectedTabIndex, $selectedSubIndex);
        if ($pageIndex < 0) {
            $pageIndex = 0;
        }
        $total = $statistics->getPlayersPointsListCount();
        $tot_pages = ceil($total / $this->pageSize);
        if ($pageIndex + 1 > $tot_pages) {
            $pageIndex = 0;
        }
        $nav = new PageNavigator($pageIndex + 1, $total, $this->pageSize, [], $formAction);
        $rows = $statistics->getPlayersPointsList($pageIndex, $this->pageSize, $isDefender);
        $tableBody = '';
        $rowIndex = 0;
        foreach ($rows as $row) {
            $rank = ++$rowIndex + $pageIndex * $this->pageSize;
            $class = $rank == $selectedRank ? 'hl' : 'hover';
            $profileHref = profileUrl((int) $row['id']);
            $tableBody .= $this->statsRow($class === 'hl', [
                ['ra', $rank . '.'],
                ['tribe', '<i class="tribe' . (int) $row['race'] . '_medium"></i>'],
                ['pla', '<a href="' . $profileHref . '">' . $row['name'] . '</a>'],
                ['banner', $this->statsBanner((int) $row['aid'])],
                ['al', (int) $row['aid'] > 0 ? '<a href="' . allianceUrl((int) $row['aid']) . '">' . $row['alliance_name'] . '</a>' : '-'],
                ['pop', $this->statsNum($row['total_population'])],
                ['vil', (string) $row['total_villages_count']],
                ['po', $this->statsNum($row['points'])],
            ]);
        }

        return PHPBatchView::render(
            "statistics/table",
            [
                "tableId" => "player",
                "tableColumns" => $this->statsHead([
                    ['ra', ''], ['tribe', ''], ['pla', T("Statistics", "tabs.players", "Player(s)")], ['banner', ''],
                    ['al', T("Statistics", "alliance", "Alliance")],
                    ['pop', T("Statistics", "population", "Population")],
                    ['vil', T("Statistics", "village", "Village")],
                    ['po', T("Statistics", "points", "Points")],
                ]),
                "headerRound" => $isDefender ? T("Statistics", "mostSuccessfulDefenders", "The most successful defenders") : T("Statistics", "mostSuccessfulAttackers", "The most successful attackers"),
                "formAction" => $formAction,
                "tableBody" => $tableBody,
                "Navigator" => $nav->get(),
                "selectedRank" => $selectedRank,
                "selectedName" => $searchName,
            ]
        );
    }

    /** top 10 both alliances and players */
    private function processTOP10($isAlliance)
    {
        $session = Session::getInstance();
        $statistics = new StatisticsModel();
        $find = $isAlliance ? $session->getAllianceId() : $session->getPlayerId();
        $db = DB::getInstance();
        $table = $isAlliance ? 'alidata' : 'users';
        $nameColumn = $isAlliance ? 'tag' : 'name';
        $publicCondition = $isAlliance ? '1' : $statistics->publicUserCondition();

        // [title key, fallback, h4 modifier, table id, value header, column or null for climbers]
        $definitions = $isAlliance ? [
            ['top10.attackers of the week', 'Attackers of the week', '', 'top10_offs', 'top10.points', 'week_attack_points'],
            ['top10.climbers of the week', 'Climbers of the week', '', 'top10_climbers', 'top10.pop', null],
            ['top10.defenders of the week', 'Defenders of the week', ' spacer', 'top10_defs', 'top10.points', 'week_defense_points'],
            ['top10.robbers of the week', 'Robbers of the week', ' spacer', 'top10_raiders', 'top10.resources', 'week_robber_points'],
        ] : [
            ['top10.pvp of the week', 'PvP of the week', '', 'top10_offs', 'top10.points', 'week_attack_points'],
            // Stand-in until a dedicated PvE points column exists (same source as the alliance PvE table).
            ['top10.pve of the week', 'PvE of the week', '', 'top10_offs', 'top10.points', 'success_adventures_count'],
            ['top10.defenders of the week', 'Defenders of the week', ' spacer', 'top10_defs', 'top10.points', 'week_defense_points'],
            ['top10.robbers of the week', 'Robbers of the week', ' spacer', 'top10_raiders', 'top10.resources', 'week_robber_points'],
        ];

        $tables = [];
        foreach ($definitions as [$titleKey, $titleFallback, $spacer, $tableId, $valueKey, $column]) {
            if ($column === null) {
                $rows = $statistics->getTop10Climbers(!$isAlliance);
                $ownValue = $find > 0 ? (int) $statistics->getAllianceClimbersPointById($find) : 0;
                $ownRank = null;
                $position = 0;
                foreach ($statistics->getTop10Climbers(false) as $r) {
                    ++$position;
                    if ((int) $r['id'] === (int) $find) {
                        $ownRank = $position;
                    }
                }
                if ($ownRank === null && $find > 0) {
                    $ownRank = 1 + (int) $db->fetchScalar('SELECT COUNT(id) FROM alidata WHERE week_pop_changes > ' . $ownValue);
                }
            } else {
                $rows = $statistics->getTop10(!$isAlliance, $column);
                $ownValue = $find > 0 ? (int) $db->fetchScalar("SELECT {$column} FROM {$table} WHERE id=" . (int) $find) : 0;
                $ownRank = $find > 0
                    ? 1 + (int) $db->fetchScalar("SELECT COUNT(id) FROM {$table} WHERE {$publicCondition} AND {$column} > {$ownValue}")
                    : null;
            }

            $body = '';
            $rank = 0;
            $ownRow = null;
            foreach ($rows as $row) {
                ++$rank;
                if ((int) $row['id'] === (int) $find) {
                    $ownRow = $row;
                }
                $body .= $this->statsTop10Row(false, $isAlliance, $row, $row['points'], $rank . '.&nbsp;');
            }
            $columnCount = $isAlliance ? 3 : 4;
            $body .= '<tr class="noGapTop noGapBottom"><td colspan="' . $columnCount . '" class="empty"></td></tr>';
            if ($find > 0 && $ownRank !== null) {
                $ownData = $ownRow ?? $db->query(
                    'SELECT id, ' . ($isAlliance ? 'tag' : 'name, race') . ' FROM ' . $table . ' WHERE id=' . (int) $find
                )->fetch_assoc();
                if ($ownData) {
                    $body .= $this->statsTop10Row(true, $isAlliance, $ownData, $ownValue, (string) $ownRank);
                }
            }

            $head = $isAlliance
                ? $this->statsHead([
                    ['ra', T('Statistics', 'top10.rank', 'Rank')],
                    ['al', T('Statistics', 'alliance', 'Alliance')],
                    ['val', T('Statistics', $valueKey)],
                ])
                : $this->statsHead([
                    ['ra', T('Statistics', 'top10.noDot', 'No.')],
                    "<td>\n</td>",
                    ['pla', T('Statistics', 'tabs.players', 'Player(s)')],
                    ['val', T('Statistics', $valueKey)],
                ]);
            $tables[] = [
                'h4Class' => 'round small ' . $spacer . ' top ' . $tableId,
                'title' => T('Statistics', $titleKey, $titleFallback),
                'id' => $tableId,
                'head' => $head,
                'body' => $body,
            ];
        }

        return PHPBatchView::render('statistics/top10', ['tables' => $tables, 'isAlliance' => $isAlliance]);
    }

    private function statsTop10Row(bool $own, bool $isAlliance, array $row, $value, string $rank): string
    {
        $link = $isAlliance ? allianceUrl((int) $row['id']) : profileUrl((int) $row['id']);
        $name = $isAlliance ? $row['tag'] : $row['name'];
        $cells = [['ra fc', $rank]];
        if ($isAlliance) {
            $cells[] = ['al', '<a href="' . $link . '">' . $name . '</a>'];
        } else {
            $cells[] = [$own ? 'tribe' : 'tribe fc', '<i class="tribe' . (int) $row['race'] . '_medium"></i>'];
            $cells[] = ['pla', '<a href="' . $link . '">' . $name . '</a>'];
        }
        $cells[] = ['val lc', $this->statsNum($this->number_format($value))];
        $html = '<tr class="' . ($own ? 'own hl' : 'hover') . '">';
        foreach ($cells as [$class, $inner]) {
            $html .= '<td class="' . $class . '">' . $inner . '</td>';
        }
        return $html . '</tr>';
    }

    private function number_format($x, $dec = 0)
    {
        return number_format_x($x, $dec);
    }

    private function processAlliancesOverview($selectedTabIndex, $selectedSubIndex)
    {
        $statistics = new StatisticsModel();
        $selectedRank = 0;
        $searchName = $this->getSearchName();
        if ($searchName !== '') {
            $selectedRank = $statistics->getAllianceRankByName($searchName);
            if (!$selectedRank) {
                $this->content->vars['error'] = sprintf(
                    T("Statistics", "errors.allianceNotFound"),
                    $this->getEscapedSearchName($searchName)
                );
            }
        } elseif (isset($_REQUEST['rank']) && is_numeric($_REQUEST['rank'])) {
            $selectedRank = (int)$_REQUEST['rank'];
            if ($selectedRank < 1) {
                $selectedRank = 1;
            }
        } elseif (!isset($_GET['page'])) {
            $selectedRank = Session::getInstance()->getAllianceId() <= 0 ? 0 : $statistics->getAllianceRankById(Session::getInstance()->getAllianceId());
        }
        $pageIndex = !$selectedRank ? (isset($_GET['page']) ? (int)$_GET['page'] - 1 : 0) : floor(($selectedRank - 1) / $this->pageSize);
        $prefix = [];
        $formAction = self::cleanPath($selectedTabIndex, $selectedSubIndex);
        if ($pageIndex < 0) {
            $pageIndex = 0;
        }
        $total = $statistics->getAllianceListCount();
        $tot_pages = ceil($total / $this->pageSize);
        if ($pageIndex + 1 > $tot_pages) {
            $pageIndex = 0;
        }
        $nav = new PageNavigator($pageIndex + 1, $total, $this->pageSize, [], $formAction);
        $rows = $statistics->getAlliancesList($pageIndex, $this->pageSize);
        $tableBody = '';
        $rowIndex = 0;
        foreach ($rows as $row) {
            $rank = ++$rowIndex + $pageIndex * $this->pageSize;
            $class = $rank == $selectedRank ? 'hl' : 'hover';
            $avg = $row['memcount'] == 0 ? 0 : round($row['points'] / $row['memcount']);
            $admin = null;
            if (Session::getInstance()->isAdmin()) {
                $admin = '<td class="buttons" style="padding: 2px 0 2px 2px;white-space: nowrap;width: 1%;"><button type="button" class="icon" onclick="window.location.href=\'/admin.php?action=editAlliance&aid=' . $row['id'] . '\'"><img style="' . (getDirection() == 'LTR' ? 'left' : 'right') . ': 6px;" src="' . get_gpack_cdn_mainPage_url() . 'img_common/f/edit.gif"></button></td>';
            }
            $allianceHref = allianceUrl((int) $row['id']);
            $tableBody .= $this->statsRow($class === 'hl', [
                ['ra', $rank . '.'],
                ['banner', $this->statsBanner((int) $row['id'])],
                ['al', '<a href="' . $allianceHref . '">' . $row['tag'] . '</a>'],
                ['pla', (string) $row['memcount']],
                ['av', $this->statsNum($avg)],
                ['po', $this->statsNum($row['points'])],
                $admin,
            ]);
        }

        return PHPBatchView::render(
            'statistics/table',
            [
                "tableId" => "",
                "tableColumns" => $this->statsHead([
                    ['ra', ''], ['banner', ''], ['al', T("Statistics", "tabs.alliances", "Alliance")],
                    ['pla', T("Statistics", "tabs.players", "Player(s)")], ['av', 'Ø'],
                    ['po', T("Statistics", "points", "Points")],
                    Session::getInstance()->isAdmin() ? '<td class="buttons">' . T("Statistics", "Actions") . '</td>' : null,
                ]),
                "headerRound" => T("Statistics", "largestAlliances"),
                "formAction" => $formAction,
                "tableBody" => $tableBody,
                "Navigator" => $nav->get(),
                "selectedRank" => $selectedRank,
                "selectedName" => $searchName,
            ]
        );
    }

    /** alliances */
    private function processAllianceAttackerOrDefender($selectedTabIndex, $selectedSubIndex, $isDefender)
    {
        $statistics = new StatisticsModel();
        $selectedRank = 0;
        $searchName = $this->getSearchName();
        if ($searchName !== '') {
            $selectedRank = $statistics->getAlliancePointsRankByName(
                $searchName,
                $isDefender
            );
            if (!$selectedRank) {
                $this->content->vars['error'] = sprintf(
                    T("Statistics", "errors.allianceNotFound"),
                    $this->getEscapedSearchName($searchName)
                );
            }
        } elseif (isset($_REQUEST['rank']) && is_numeric($_REQUEST['rank'])) {
            $selectedRank = (int)$_REQUEST['rank'];
            if ($selectedRank < 1) {
                $selectedRank = 1;
            }
        } elseif (!isset($_GET['page'])) {
            $selectedRank = Session::getInstance()->getAllianceId() <= 0 ? 0 : $statistics->getAlliancePointsRankById(
                Session::getInstance()->getAllianceId(),
                $isDefender
            );
        }
        $pageIndex = !$selectedRank ? (isset($_GET['page']) ? (int)$_GET['page'] - 1 : 0) : floor(($selectedRank - 1) / $this->pageSize);
        $prefix = [];
        $formAction = self::cleanPath($selectedTabIndex, $selectedSubIndex);
        if ($pageIndex < 0) {
            $pageIndex = 0;
        }
        $total = $statistics->getAllianceListCount();
        $tot_pages = ceil($total / $this->pageSize);
        if ($pageIndex + 1 > $tot_pages) {
            $pageIndex = 0;
        }
        $nav = new PageNavigator($pageIndex + 1, $total, $this->pageSize, [], $formAction);
        $rows = $statistics->getAlliancePointsList($pageIndex, $this->pageSize, $isDefender);
        $tableBody = '';
        $rowIndex = 0;
        foreach ($rows as $row) {
            $rank = ++$rowIndex + $pageIndex * $this->pageSize;
            $class = $rank == $selectedRank ? 'hl' : 'hover';
            $avg = $row['memcount'] == 0 ? 0 : round($row['totalPop'] / $row['memcount']);
            $allianceHref = allianceUrl((int) $row['id']);
            $tableBody .= $this->statsRow($class === 'hl', [
                ['ra', $rank . '.'],
                ['banner', $this->statsBanner((int) $row['id'])],
                ['al', '<a href="' . $allianceHref . '">' . $row['tag'] . '</a>'],
                ['pla', (string) $row['memcount']],
                ['av', $this->statsNum($avg)],
                ['po', $this->statsNum($row['points'])],
            ]);
        }

        return PHPBatchView::render(
            'statistics/table',
            [
                "tableId" => "",
                "tableColumns" => $this->statsHead([
                    ['ra', ''], ['banner', ''], ['al', T("Statistics", "tabs.alliances", "Alliance")],
                    ['pla', T("Statistics", "tabs.players", "Player(s)")], ['av', 'Ø'],
                    ['po', T("Statistics", "points", "Points")],
                ]),
                "headerRound" => $isDefender ? T("Statistics", "bestAlliancesDef", "The best alliances (def)") : T("Statistics", "bestAlliancesOff", "The best alliances (off)"),
                "formAction" => $formAction,
                "tableBody" => $tableBody,
                "Navigator" => $nav->get(),
                "selectedRank" => $selectedRank,
                "selectedName" => $searchName,
            ]
        );
    }

    /** villages */
    private function processVillagesOverview($selectedTabIndex, $selectedSubIndex)
    {
        $statistics = new StatisticsModel();
        $selectedRank = 0;
        $searchName = $this->getSearchName();
        if ($searchName !== '') {
            $selectedRank = $statistics->getVillageRankByName($searchName);
            if (!$selectedRank) {
                $this->content->vars['error'] = sprintf(
                    T("Statistics", "errors.villageNotFound"),
                    $this->getEscapedSearchName($searchName)
                );
            }
        } elseif (isset($_REQUEST['rank']) && is_numeric($_REQUEST['rank'])) {
            $selectedRank = (int)$_REQUEST['rank'];
            if ($selectedRank < 1) {
                $selectedRank = 1;
            }
        } elseif (!isset($_GET['page'])) {
            $selectedRank = $statistics->getVillageRankById(Session::getInstance()->getSelectedVillageID());
        }
        $pageIndex = !$selectedRank ? (isset($_GET['page']) ? (int)$_GET['page'] - 1 : 0) : floor(($selectedRank - 1) / $this->pageSize);
        $prefix = [];
        $formAction = self::cleanPath($selectedTabIndex, $selectedSubIndex);
        if ($pageIndex < 0) {
            $pageIndex = 0;
        }
        $total = $statistics->getTotalVillagesCount();
        $tot_pages = ceil($total / $this->pageSize);
        if ($pageIndex + 1 > $tot_pages) {
            $pageIndex = 0;
        }
        $nav = new PageNavigator($pageIndex + 1, $total, $this->pageSize, [], $formAction);
        $rows = $statistics->getVillagesList($pageIndex, $this->pageSize);
        $tableBody = '';
        $rowIndex = 0;
        $direction = strtolower(getDirection());
        $activeKid = Session::getInstance()->getSelectedVillageID();
        foreach ($rows as $row) {
            $rank = ++$rowIndex + $pageIndex * $this->pageSize;
            $class = $rank == $selectedRank ? 'hl' : 'hover';
            $xy = Formulas::kid2xy($row['kid']);
            $lre = "\u{202D}";
            $pdf = "\u{202C}";
            $distanceTitle = htmlspecialchars(
                sprintf(T('HeroGlobal', 'distanceTooltip'), round(Formulas::getDistance($activeKid, $row['kid']), 2)),
                ENT_QUOTES,
                'UTF-8'
            );
            $coords = '<a class="" href="/karte.php?x=' . $xy['x'] . '&amp;y=' . $xy['y'] . '" title="' . $distanceTitle . '">' . $lre . '<span class="coordinates coordinatesWrapper coordinatesAligned coordinates' . $direction . '"><span class="coordinateX">(' . coordinateNumberHTML($xy['x']) . '</span><span class="coordinatePipe">|</span><span class="coordinateY">' . coordinateNumberHTML($xy['y']) . ')</span></span>' . $pdf . '</a>';
            $profileHref = profileUrl((int) $row['owner']);
            $tableBody .= $this->statsRow($class === 'hl', [
                ['ra', $rank . '.'],
                ['villageName', '<a href="/karte.php?d=' . $row['kid'] . '">' . $row['name'] . '</a>'],
                ['pla', '<a href="' . $profileHref . '">' . $row['player_name'] . '</a>'],
                ['hab', $this->statsNum($row['pop'])],
                ['coords', $coords],
            ]);
        }

        return PHPBatchView::render(
            'statistics/table',
            [
                "tableId" => "villages",
                "tableColumns" => $this->statsHead([
                    ['ra', ''], ['villageName', T("Statistics", "tabs.villages", "Village")],
                    ['pla', T("Statistics", "tabs.players", "Player(s)")],
                    ['hab', T("Statistics", "popShort", "Pop")],
                    ['coords', T("Statistics", "coordinates", "Coordinates")],
                ]),
                "headerRound" => T("Statistics", "largestVillages"),
                "formAction" => $formAction,
                "tableBody" => $tableBody,
                "Navigator" => $nav->get(),
                "selectedRank" => $selectedRank,
                "selectedName" => $searchName,
            ]
        );
    }

    /** hero */
    private function processHerosOverview($selectedTabIndex, $selectedSubIndex)
    {
        $statistics = new StatisticsModel();
        $selectedRank = 0;
        $searchName = $this->getSearchName();
        if ($searchName !== '') {
            $selectedRank = $statistics->getHeroRankByName($searchName);
            if (!$selectedRank) {
                $this->content->vars['error'] = sprintf(T("Statistics", "errors.userNotFound"), $this->getEscapedSearchName($searchName));
            }
        } elseif (isset($_REQUEST['rank']) && is_numeric($_REQUEST['rank'])) {
            $selectedRank = (int)$_REQUEST['rank'];
            if ($selectedRank < 1) {
                $selectedRank = 1;
            }
        } elseif (!isset($_GET['page'])) {
            $selectedRank = $statistics->getHeroRankById(Session::getInstance()->getPlayerId());
        }
        $pageIndex = !$selectedRank ? (isset($_GET['page']) ? (int)$_GET['page'] - 1 : 0) : floor(($selectedRank - 1) / $this->pageSize);
        $prefix = [];
        $formAction = self::cleanPath($selectedTabIndex, $selectedSubIndex);
        if ($pageIndex < 0) {
            $pageIndex = 0;
        }
        $total = $statistics->getHeroListCount();
        $tot_pages = ceil($total / $this->pageSize);
        if ($pageIndex + 1 > $tot_pages) {
            $pageIndex = 0;
        }
        $nav = new PageNavigator($pageIndex + 1, $total, $this->pageSize, [], $formAction);
        $rows = $statistics->getHerosList($pageIndex, $this->pageSize);
        $tableBody = '';
        $rowIndex = 0;
        foreach ($rows as $row) {
            $rank = ++$rowIndex + $pageIndex * $this->pageSize;
            $class = $rank == $selectedRank ? 'hl' : 'hover';
            $level = Formulas::heroLevel($row['exp']);
            $row['exp'] = number_format_x($row['exp']);
            $profileHref = profileUrl((int) $row['uid']);
            $heroRace = (int) $row['race'];
            $heroAlliance = $row['aid'] ? '<a href="' . allianceUrl((int) $row['aid']) . '">' . $row['alliance_name'] . '</a>' : '-';
            $tableBody .= $this->statsRow($class === 'hl', [
                ['ra', $rank . '.'],
                ['tribe', '<i class="tribe' . $heroRace . '_medium"></i>'],
                ['pla', '<a href="' . $profileHref . '">' . $row['player_name'] . '</a>'],
                ['banner', $this->statsBanner((int) $row['aid'])],
                ['al', $heroAlliance],
                ['lev', (string) $level],
                ['xp', $this->statsNum($row['exp'])],
            ]);
        }

        return PHPBatchView::render(
            'statistics/table',
            [
                "tableId" => "heroes",
                "tableColumns" => $this->statsHead([
                    ['ra', ''], ['tribe', ''], ['pla', T("Statistics", "tabs.players", "Player(s)")], ['banner', ''],
                    ['al', T("Statistics", "alliance", "Alliance")],
                    ['lev', T("Statistics", "level", "Level")],
                    ['xp', T("Statistics", "xp", "Experience")],
                ]),
                "headerRound" => T("Statistics", "mostExperiencedHeroes", "The most experienced heroes"),
                "formAction" => $formAction,
                "tableBody" => $tableBody,
                "Navigator" => $nav->get(),
                "selectedRank" => $selectedRank,
                "selectedName" => $searchName,
            ]
        );
    }

    private function showFarms()
    {
        $m = new StatisticsModel();
        $page = isset($_REQUEST['page']) ? max(abs((int)$_REQUEST['page']), 1) : 1;
        $navigator = new PageNavigator($page, $m->getTotalFarmVillagesCount(), $this->pageSize, [], self::cleanPath(8));
        $farms = $m->getFarmVillages($page, $this->pageSize);
        return PHPBatchView::render('statistics/farm', ['farms' => $farms, 'navigator' => $navigator->get()]);
    }

    /**
     * Player-facing statistics URLs match the reference nested path set.
     * Primary tabs omit the overview segment; subtabs and forms use the full path.
     */
    public static function cleanPath(int $id, int $idSub = 0, bool $includeSub = true): string
    {
        $primary = match ($id) {
            1 => '/statistics/alliance',
            2 => '/statistics/village',
            3 => '/statistics/hero',
            5 => '/statistics/general',
            6 => '/statistics/wonderoftheworld',
            7 => '/statistics/bonus',
            8 => '/statistics/farm',
            default => '/statistics/player',
        };
        if ($id >= 2 || !$includeSub) {
            return $primary;
        }

        $sub = match ($idSub) {
            1 => 'attackers',
            2 => 'defenders',
            3 => 'top10',
            default => 'overview',
        };

        return $primary . '/' . $sub;
    }
}
