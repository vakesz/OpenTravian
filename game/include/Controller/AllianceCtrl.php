<?php

namespace Controller;

use Core\Config;
use Core\Database\DB;
use Core\Helper\BBCode;
use Core\Helper\StringChecker;
use Core\Helper\TimezoneHelper;
use Core\Helper\WebService;
use Game\Formulas;
use Game\Map\Map;
use Game\NoticeHelper;
use Model\AllianceBannerModel;
use Model\AllianceBonusModel;
use Model\AllianceModel;
use Model\ArtefactsModel;
use Model\AutomationModel;
use Model\ForumModel;
use Model\MessageModel;
use Model\StatisticsModel;
use resources\View\GameView;
use resources\View\PHPBatchView;

class AllianceCtrl extends GameCtrl
{
    private int $selectedAllianceID = 0;

    /** @var array<string, mixed> */
    private array $selectedAllianceData = [];

    private function getPostString(string $key): string
    {
        $value = $_POST[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    private function getPostPlainText(string $key): string
    {
        return strip_tags($this->getPostString($key));
    }

    private function getPostInt(string $key): int
    {
        $value = $_POST[$key] ?? null;

        return is_scalar($value) ? (int) $value : 0;
    }

    private function getRequestInt(string $key): int
    {
        $value = $_REQUEST[$key] ?? null;

        return is_scalar($value) ? (int) $value : 0;
    }

    public function __construct()
    {
        parent::__construct();
        $this->view = new GameView();
        if (WebService::isPost() && !\Core\Session::validateChecker()) {
            // CSRF: treat a POST without a valid token as a plain GET
            $_POST = [];
            $_REQUEST = $_GET;
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $this->view->vars['content'] .= '<div class="error">' . T("Alliance", "Request expired") . '</div>';
        }
        if (!isset($_REQUEST['aid']) && $this->session->getAllianceId() == 0) {
            $this->noAlliance();
        } else {
            $this->view->vars['bodyCssClass'] = 'alliance';
            $this->view->vars['contentCssClass'] = 'alliance';
            $this->view->vars['showHeaderBar'] = true;
            $this->view->vars['showCloseButton'] = true;
            $this->selectedAllianceID = isset($_REQUEST['aid']) ? $this->getRequestInt('aid') : $this->session->getAllianceId();
            $db = DB::getInstance();
            $allianceResult = $db->query("SELECT * FROM alidata WHERE id={$this->selectedAllianceID}");
            if (!$allianceResult instanceof \mysqli_result || $allianceResult->num_rows < 1) {
                $this->view->vars['titleInHeader'] = '-';
                return;
            }
            $isMyAlliance = $this->session->getAllianceId() == $this->selectedAllianceID;
            $allianceRow = $allianceResult->fetch_assoc();
            if (!is_array($allianceRow)) {
                $this->view->vars['titleInHeader'] = '-';
                return;
            }
            $this->selectedAllianceData = $allianceRow;
            $this->view->vars['titleInHeader'] = T("Alliance", "Alliance") . ' - ' . $this->selectedAllianceData['tag'];
            $ownAllianceTabs = [7, 8, 1, 3, 2, 5];
            $selectedTabIndex = isset($_REQUEST['s']) ? $this->getRequestInt('s') : ($isMyAlliance ? 7 : 1);
            $selectedTabIndex = in_array($selectedTabIndex, $ownAllianceTabs, true)
                ? $selectedTabIndex
                : ($isMyAlliance ? 7 : 1);
            if ($isMyAlliance && !isset($_REQUEST['s'])) {
                $favoriteTab = (int)$this->session->getFavoriteTab('alliance');
                $selectedTabIndex = in_array($favoriteTab, $ownAllianceTabs, true) ? $favoriteTab : 7;
            }
            if (!$isMyAlliance && !in_array($selectedTabIndex, [1, 2], true)) {
                $selectedTabIndex = 1;
            }
            $this->appendTabs($selectedTabIndex);
            if ($selectedTabIndex == 1) {
                $this->showAllianceProfile();
            } elseif ($selectedTabIndex == 2) {
                $this->showAllianceForum();
            } elseif ($isMyAlliance && $selectedTabIndex == 7) {
                $this->showAllianceInternalPage();
            } elseif ($isMyAlliance && $selectedTabIndex == 3) {
                $this->showAllianceAttacks();
            } elseif ($isMyAlliance && $selectedTabIndex == 5) {
                $this->showAllianceOptions();
            } elseif ($isMyAlliance && $selectedTabIndex == 8) {
                $this->handleBonuses();
            }
        }
    }

    private function noAlliance(): void
    {
        $this->view->vars['titleInHeader'] = T('Alliance', 'Join an alliance!');
        $this->view->vars['bodyCssClass'] = 'universal';
        $this->view->vars['contentCssClass'] = 'universal';
        $view = new PHPBatchView('alliance/noAlliance');
        $this->view->vars['content'] .= $view->output();
    }

    private function appendTabs(int $selectedTabIndex): void
    {
        $view = new PHPBatchView("alliance/menu");
        $view->vars['favorTabId'] = $this->session->getStoredFavoriteTab('alliance');
        $view->vars['selectedTabId'] = $selectedTabIndex;
        $view->vars['aid'] = $this->selectedAllianceID;
        $view->vars['isMyAlliance'] = $this->session->getAllianceId() == $this->selectedAllianceID;
        $view->vars['ForumLink'] = $this->externalForumLink((string) ($this->selectedAllianceData['forumLink'] ?? ''));
        $this->view->vars['content'] .= $view->output();
    }

    private function showAllianceProfile(): void
    {
        $action = isset($_GET['action']) && is_string($_GET['action']) && in_array($_GET['action'], ['description', 'members'], true) ? $_GET['action'] : ($this->session->getFavoriteTab("allyPageProfile") == 0 ? 'description' : 'members');
        if ($this->getPostInt('a') === 3 && $this->session->hasAlliancePermission(AllianceModel::CHANGE_ALLIANCE_DESC)) {
            $db = DB::getInstance();
            $description1 = $this->getPostPlainText('be1');
            $description2 = $this->getPostPlainText('be2');
            if (StringChecker::isValidMessage($description1) && StringChecker::isValidMessage($description2)) {
                $escapedDescription1 = $db->real_escape_string($description1);
                $escapedDescription2 = $db->real_escape_string($description2);
                $db->query("UPDATE alidata SET desc1='$escapedDescription1', desc2='$escapedDescription2' WHERE id={$this->selectedAllianceID}");
                $this->selectedAllianceData['desc1'] = $description1;
                $this->selectedAllianceData['desc2'] = $description2;
            }
        }
        $view = new PHPBatchView("alliance/Profile");
        $view->vars['action'] = $action;
        $this->view->vars['contentCssClass'] .= ' allianceProfile';
        $storedProfile = $this->session->getStoredFavoriteTab('allyPageProfile');
        $view->vars['favorActionId'] = $storedProfile === null ? '' : ($storedProfile === 0 ? 'description' : 'members');
        list($view->vars['desc1'], $view->vars['desc2']) = BBCode::BBCodeProfile($this->selectedAllianceData['desc1'], $this->selectedAllianceData['desc2'], true, $this->selectedAllianceID);
        $view->vars['hasPosition'] = false;
        $view->vars['tag'] = $this->selectedAllianceData['tag'];
        $view->vars['name'] = $this->selectedAllianceData['name'];
        $statistics = new StatisticsModel();
        $view->vars['rank'] = $statistics->getAllianceRankById($this->selectedAllianceID);
        $view->vars['aid'] = $this->selectedAllianceID;
        $view->vars['hasForum'] = false;
        if ($this->session->getAllianceId() != $this->selectedAllianceID) {
            $m = new ForumModel();
            $view->vars['hasForum'] = $m->AllianceHasAnyForums($this->selectedAllianceID, $this->session->getPlayerId());
        }
        $view->vars['points'] = 0;
        $view->vars['attackerPoints'] = (int) ($this->selectedAllianceData['total_attack_points'] ?? 0);
        $view->vars['defenderPoints'] = (int) ($this->selectedAllianceData['total_defense_points'] ?? 0);
        $dbForRanks = DB::getInstance();
        $view->vars['attackerRank'] = (int) $dbForRanks->fetchScalar(
            'SELECT COUNT(id) + 1 FROM alidata WHERE total_attack_points > ' . $view->vars['attackerPoints']
        );
        $view->vars['defenderRank'] = (int) $dbForRanks->fetchScalar(
            'SELECT COUNT(id) + 1 FROM alidata WHERE total_defense_points > ' . $view->vars['defenderPoints']
        );
        $view->vars['Members'] = 0;
        $view->vars['MembersHTML'] = '';
        $view->vars['position'] = '';
        $db = DB::getInstance();
        $members = $db->query("SELECT users.id, users.name, users.race, users.vacationActiveTil, users.last_login_time, users.alliance_role, users.alliance_role_name,
        users.total_villages,
        users.total_pop
        FROM users WHERE users.aid={$this->selectedAllianceID} ORDER BY total_pop DESC");
        $rank = 0;
        $isMyAlliance = $this->selectedAllianceID == $this->session->getAllianceId();
        $view->vars['isMyAlliance'] = $isMyAlliance;
        while ($row = $members->fetch_assoc()) {
            ++$rank;
            $view->vars['points'] += $row['total_pop'];
            $memberId = (int) $row['id'];
            $memberName = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
            if ($row['alliance_role_name'] != '') {
                $view->vars['hasPosition'] = true;
                $view->vars['position'] .= '<tr><th>' . htmlspecialchars($row['alliance_role_name'], ENT_QUOTES, 'UTF-8') . '</th><td class="player"><a href="' . profileUrl((int) $memberId) . '">' . $memberName . '</a></td></tr>';
                //has role!
            }
            $login_stat = '';
            if ($isMyAlliance) {
                $login_stat = AutomationModel::getOnlineStatusAsImg($row['last_login_time']);
            }
            $view->vars['MembersHTML'] .= '<tr>';
            $view->vars['MembersHTML'] .= '<td class="counter">' . $rank . '.</td>';
            $view->vars['MembersHTML'] .= '<td class="tribe"><i class="tribe' . (int) $row['race'] . '_medium"></i></td>';
            $view->vars['MembersHTML'] .= '<td class="player">' . $login_stat . PHP_EOL . '<a href="' . profileUrl((int) $memberId) . '">' . $memberName . '</a>';
            if ($row['vacationActiveTil'] >= time()) {
                $view->vars['MembersHTML'] .= '<span class="greyInfo">(' . T("Alliance", "in vacation") . ')</span>';
            }
            $view->vars['MembersHTML'] .= '</td>';
            $msg = '<button type="button" value="' . $memberId . '" class="icon note editNote" title="' . T('Profile', 'Note') . '" data-spec="0" data-note="1"><img src="img/spacer.gif" class="" alt="" /></button><button type="button" class="icon sendMessage hover" title="' . T("inGame", "sendMessage") . '" onclick="window.location.href = \'/messages/write?to=' . $memberId . '\'; return false;"><img src="img/spacer.gif" class="" alt=""></button>';
            if ($memberId === $this->session->getPlayerId()) {
                $msg = null;
            }
            $view->vars['MembersHTML'] .= '<td class="population">' . (int) $row['total_pop'] . '</td><td class="villages">' . (int) $row['total_villages'] . '</td><td class="buttons">' . $msg . '</td></tr>';
        }
        $view->vars['Members'] = $rank;
        $bannerModel = new AllianceBannerModel();
        $allianceBanner = $bannerModel->getAllianceBanner($this->selectedAllianceID);
        $view->vars['bannerSvg'] = $allianceBanner !== null
            ? AllianceBannerModel::renderSvg($allianceBanner, 'allianceBanner_profile')
            : '';
        $view->vars['bannerTemplateSvg'] = (string) file_get_contents(
            __DIR__ . '/../resources/Templates/alliance/banner/banner-template-svg.svg'
        );
        $this->view->vars['content'] .= $view->output();
    }

    private function showAllianceForum(): void
    {
        $forumLink = $this->externalForumLink((string) ($this->selectedAllianceData['forumLink'] ?? ''));
        if ($forumLink !== '') {
            $this->redirect($forumLink);
        }
        $this->view->vars['bodyCssClass'] = 'forum';
        $this->view->vars['contentCssClass'] = 'forum forumForum';
        $dispatcher = new AllianceForum($this->selectedAllianceID);
        $this->view->vars['content'] .= $dispatcher->render();
    }

    private function showAllianceInternalPage(): void
    {
        if ($this->selectedAllianceID == $this->session->getAllianceId() && $this->getPostInt('a') === 7 && $this->session->hasAlliancePermission(AllianceModel::CHANGE_ALLIANCE_DESC)) {
            $db = DB::getInstance();
            $info1 = $this->getPostPlainText('info1');
            $info2 = $this->getPostPlainText('info2');
            $escapedInfo1 = $db->real_escape_string($info1);
            $escapedInfo2 = $db->real_escape_string($info2);
            $db->query("UPDATE alidata SET info1='$escapedInfo1', info2='$escapedInfo2' WHERE id={$this->selectedAllianceID}");
            $_REQUEST['s'] = 7;
            $this->selectedAllianceData['info1'] = $info1;
            $this->selectedAllianceData['info2'] = $info2;
        }
        $this->view->vars['contentCssClass'] .= ' allianceOverview';
        $isMy = $this->session->getAllianceId() == $this->selectedAllianceID;
        $favoriteOverview = (int) $this->session->getFavoriteTab('allyPageOverview') === 1 ? 'top10' : 'internalProfile';
        $overviewAction = isset($_REQUEST['action']) && is_string($_REQUEST['action']) && in_array($_REQUEST['action'], ['top10', 'internalProfile'], true)
            ? $_REQUEST['action']
            : $favoriteOverview;
        if ($isMy) {
            $sub = new PHPBatchView('alliance/overviewSubNavi');
            $sub->vars['aid'] = $this->selectedAllianceID;
            $sub->vars['ownAllianceRoutes'] = true;
            $sub->vars['overviewAction'] = $overviewAction;
            $storedOverview = $this->session->getStoredFavoriteTab('allyPageOverview');
            $sub->vars['favorAction'] = $storedOverview === null ? '' : ($storedOverview === 1 ? 'top10' : 'internalProfile');
            $this->view->vars['content'] .= $sub->output();
        }
        if ($overviewAction === 'top10' && $isMy) {
            $this->view->vars['content'] .= $this->renderAllianceTop10();
            return;
        }
        $view = new PHPBatchView("alliance/internal");
        $info1 = (string) $this->selectedAllianceData['info1'];
        $info2 = (string) $this->selectedAllianceData['info2'];
        if (trim($info1) === '' && trim($info2) === '') {
            $info1 = "[stats]strength[/stats]\n\n[stats]fighting points[/stats]";
            $info2 = '[news]10[/news]';
        }
        $view->vars['internInfo1'] = BBCode::BBCodeInternalPage($info1, $this->selectedAllianceID);
        $view->vars['internInfo2'] = BBCode::BBCodeInternalPage($info2, $this->selectedAllianceID);
        $this->view->vars['content'] .= $view->output();
    }

    private function renderAllianceTop10(): string
    {
        $db = DB::getInstance();
        $aid = $this->selectedAllianceID;
        $uid = $this->session->getPlayerId();
        $view = new PHPBatchView('alliance/top10');
        $view->vars['pvp'] = $this->renderAllianceTop10Rows(
            $db->query("SELECT id, name, race, week_attack_points AS pts FROM users WHERE aid={$aid} ORDER BY week_attack_points DESC, id ASC LIMIT 10"),
            $uid
        );
        $view->vars['pve'] = $this->renderAllianceTop10Rows(
            $db->query("SELECT u.id, u.name, u.race, u.success_adventures_count AS pts FROM users u WHERE u.aid={$aid} ORDER BY u.success_adventures_count DESC, u.id ASC LIMIT 10"),
            $uid
        );
        $view->vars['defs'] = $this->renderAllianceTop10Rows(
            $db->query("SELECT id, name, race, week_defense_points AS pts FROM users WHERE aid={$aid} ORDER BY week_defense_points DESC, id ASC LIMIT 10"),
            $uid
        );
        $view->vars['raiders'] = $this->renderAllianceTop10Rows(
            $db->query("SELECT id, name, race, week_robber_points AS pts FROM users WHERE aid={$aid} ORDER BY week_robber_points DESC, id ASC LIMIT 10"),
            $uid
        );

        return $view->output();
    }

    /**
     * @param \mysqli_result|false $result
     */
    private function renderAllianceTop10Rows($result, int $selfUid): string
    {
        if (!$result instanceof \mysqli_result || $result->num_rows < 1) {
            return '<tr><td colspan="4" class="noData">' . T("Alliance", "none") . '</td></tr>';
        }
        $html = '<tr class="noGapTop noGapBottom"><td colspan="4" class="empty"></td></tr>';
        $rank = 0;
        while ($row = $result->fetch_assoc()) {
            $rank++;
            $id = (int) $row['id'];
            $cls = $id === $selfUid ? 'own hl' : '';
            $html .= '<tr class="' . $cls . '">';
            $html .= '<td class="ra fc">' . $rank . '</td>';
            $html .= '<td class="tribe"><i class="tribe' . (int) $row['race'] . '_medium"></i></td>';
            $html .= '<td class="pla"><a href="' . profileUrl((int) $id) . '">' . htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8') . '</a></td>';
            $html .= '<td class="val lc">' . number_format_x((int) $row['pts']) . '</td>';
            $html .= '</tr>';
        }

        return $html;
    }

    private function showAllianceAttacks(): void
    {
        $this->view->vars['contentCssClass'] .= ' allianceReports';
        $view = new PHPBatchView("alliance/attacks");
        $view->vars['aid'] = $this->selectedAllianceID;
        $view->vars['ownAllianceRoutes'] = $this->session->getAllianceId() == $this->selectedAllianceID;
        $view->vars['fn'] = isset($_GET['fn']) && $_GET['fn'] == 1;
        $valid_report_types = [
            NoticeHelper::TYPE_WON_AS_ATTACK_WITHOUT_CASUALTIES,
            NoticeHelper::TYPE_WON_AS_ATTACK_WITH_CASUALTIES,
            NoticeHelper::TYPE_LOST_AS_ATTACKER,
            NoticeHelper::TYPE_WON_SPY_WITHOUT_CASUALTIES,
            NoticeHelper::TYPE_WON_SPY_WITH_CASUALTIES,
            NoticeHelper::TYPE_LOST_AS_SPY,
            NoticeHelper::TYPE_WON_DEFENSE_WITHOUT_LOSSES,
            NoticeHelper::TYPE_WON_DEFENSE_WITH_LOSSES,
            NoticeHelper::TYPE_LOST_AS_DEFENDER_WITH_CASUALTIES,
            NoticeHelper::TYPE_LOST_AS_DEFENDER_WITHOUT_CASUALTIES,
            NoticeHelper::TYPE_WON_SPY_AS_DEFENDER_REFUSED_ATTACKER_SPY,
            NoticeHelper::TYPE_WON_SPY_AS_DEFENDER_LETTING_ATTACKER_SPY,
        ];
        $filter = isset($_GET['f']) && ($_GET['f'] == 31 || $_GET['f'] == 32 || in_array($_GET['f'], $valid_report_types)) ? (int)$_GET['f'] : -1;
        $view->vars['filter'] = $filter;
        $attack_types_31 = [
            NoticeHelper::TYPE_WON_AS_ATTACK_WITHOUT_CASUALTIES,
            NoticeHelper::TYPE_WON_AS_ATTACK_WITH_CASUALTIES,
            NoticeHelper::TYPE_LOST_AS_ATTACKER,
            NoticeHelper::TYPE_WON_SPY_WITHOUT_CASUALTIES,
            NoticeHelper::TYPE_WON_SPY_WITH_CASUALTIES,
            NoticeHelper::TYPE_LOST_AS_SPY,
        ];
        $attack_types_32 = [
            NoticeHelper::TYPE_WON_DEFENSE_WITHOUT_LOSSES,
            NoticeHelper::TYPE_WON_DEFENSE_WITH_LOSSES,
            NoticeHelper::TYPE_LOST_AS_DEFENDER_WITH_CASUALTIES,
            NoticeHelper::TYPE_LOST_AS_DEFENDER_WITHOUT_CASUALTIES,
            NoticeHelper::TYPE_WON_SPY_AS_DEFENDER_REFUSED_ATTACKER_SPY,
            NoticeHelper::TYPE_WON_SPY_AS_DEFENDER_LETTING_ATTACKER_SPY,
        ];
        if ($filter == 31) {
            $attack_types = $attack_types_31;
        } elseif ($filter == 32) {
            $attack_types = $attack_types_32;
        } elseif ($filter == -1) {
            $attack_types = $valid_report_types;
        } else {
            $attack_types = [(int)$_GET['f']];
        }
        $view->vars['attacks'] = '';
        $db = DB::getInstance();
        $alliances = [$this->selectedAllianceID];
        if ($view->vars['fn']) {
            $reports = $db->query("SELECT * FROM ndata WHERE aid IN (" . implode(",", $alliances) . ") AND losses>0 AND type IN(" . implode(",", $attack_types) . ") ORDER BY id DESC LIMIT 20");
        } else {
            $reports = $db->query("SELECT * FROM ndata WHERE aid IN (" . implode(",", $alliances) . ") AND type IN(" . implode(",", $attack_types) . ") ORDER BY id DESC LIMIT 20");
        }
        $ctrl = new ReportsCtrl();
        $total = 0;
        while ($row = $reports->fetch_assoc()) {
            $data = NoticeHelper::parseReport($row['type'], $row['data']);
            if (in_array((int) $row['type'], $attack_types_32, true)) {
                $uid = isset($data['defender'][0]['uid']) ? $data['defender'][0]['uid'] : 0;
                //we are defender
            } else {
                $uid = isset($data['attacker']['uid']) ? $data['attacker']['uid'] : 0;
                //we are the attacker.
                if ($view->vars['fn'] && (array_sum($data['attacker']['num']) < 100 || !array_sum($data['attacker']['dead']))) {
                    continue;
                }
            }
            $view->vars['attacks'] .= '<tr>';
            $aid = (int)$db->fetchScalar("SELECT aid FROM users WHERE id=$uid");
            $title = $ctrl->getNoticeSubject($row, false, true);
            $attacksBase = $this->isOwnAllianceRoute()
                ? '/alliance/reports'
                : ('/alliance/' . $this->selectedAllianceID . '/attacks');
            $view->vars['attacks'] .= '<td class="sub"><a href="' . $attacksBase . '?f=' . $row['type'] . '&amp;fn=' . ($view->vars['fn'] ? '1' : '0') . '"><img src="img/spacer.gif" class="iReport iReport' . $row['type'] . '" alt="' . T(
                "Reports",
                "reportTypes." . $row['type']
            ) . '"></a><div><a href="/report?id=' . $row['id'] . ($row['uid'] == $this->session->getPlayerId() ? '|b4d1c687' : '') . '">' . $title . '</a></div></td>';
            $view->vars['attacks'] .= '<td class="al">';
            if (!$aid) {
                $view->vars['attacks'] .= '-';
            } else {
                $tag = $db->fetchScalar("SELECT tag FROM alidata WHERE id=$aid");
                if ($tag) {
                    $view->vars['attacks'] .= '<a href="/alliance/' . $aid . '">' . htmlspecialchars((string) $tag, ENT_QUOTES, 'UTF-8') . '</a>';
                } else {
                    $view->vars['attacks'] .= '-';
                }
            }
            $view->vars['attacks'] .= '</td><td class="dat">' . TimezoneHelper::autoDateString(
                $row['time'],
                true
            ) . '</td>';
            $view->vars['attacks'] .= '</td></tr>';
            ++$total;
        }
        if (!$total) {
            $view->vars['attacks'] = '<tr class="none"><td colspan="4">' . T("Alliance", "noReports") . '</td></tr>';
        }
        $this->view->vars['content'] .= $view->output();
    }

    private function isOwnAllianceRoute(): bool
    {
        return $this->session->getAllianceId() == $this->selectedAllianceID;
    }

    private function optionsPath(string $slug, int $legacyO): string
    {
        if ($this->isOwnAllianceRoute()) {
            return '/alliance/options/' . $slug;
        }

        return '/alliance/' . $this->selectedAllianceID . '/options?o=' . $legacyO;
    }

    /**
     * @return null
     */
    private function showAllianceOptions()
    {
        if ($this->session->isSitter()) {
            return null;
        }
        $this->view->vars['contentCssClass'] .= ' allianceOptions';
        $bannerScreen = isset($_REQUEST['bannerScreen']) && is_string($_REQUEST['bannerScreen'])
            ? $_REQUEST['bannerScreen']
            : '';
        if (in_array($bannerScreen, ['create', 'my', 'gallery'], true)) {
            $this->showAllianceBanner($bannerScreen);
            return null;
        }
        if (!isset($_REQUEST['o'])) {
            $this->showAllianceOptionMenus();
            return null;
        }
        $session = $this->session;
        $option = $this->getRequestInt('o');
        if ($option == 100 && $session->hasAlliancePermission(AllianceModel::CHANGE_ALLIANCE_DESC)) {
            $this->processAllianceChangeName();
        }
        if ($option == 3 && $session->hasAlliancePermission(AllianceModel::CHANGE_ALLIANCE_DESC)) {
            $this->processAllianceChangeDesc();
        }
        if ($option == 7 && $session->hasAlliancePermission(AllianceModel::CHANGE_ALLIANCE_DESC)) {
            $this->processChangeAllianceInfo();
        }
        if ($option == 1 && $session->hasAlliancePermission(AllianceModel::ASSIGN_TO_POSITION)) {
            $this->processAssignPosition();
        }
        if ($option == 4 && $session->hasAlliancePermission(AllianceModel::INVITE_PLAYER) && !Config::getInstance()->dynamic->serverFinished) {
            $this->showAllianceInviting();
        }
        if ($option == 5 && $session->hasAlliancePermission(AllianceModel::MANAGE_FORUM)) {
            $this->processForumLink();
        }
        ///  kick.
        if ($option == 6 && $session->hasAlliancePermission(AllianceModel::ALLIANCE_DIPLOMACY)) {
            $this->processAllianceDiplomacy();
        }
        if ($option == 2 && $session->hasAlliancePermission(AllianceModel::KICK_PLAYER) && !Config::getInstance()->dynamic->serverFinished) {
            $this->showAllianceKick();
        }
        if ($option == 11) {
            $this->quitAlliance();
        }
        return null;
    }

    private function showAllianceOptionMenus(): void
    {
        $view = new PHPBatchView("alliance/options");
        $m = new AllianceModel();
        $embassy = $m->getMaxPlayerEmbassyLvl((int)$this->session->getPlayerId());
        $view->vars['aid'] = $this->selectedAllianceID;
        $view->vars['ownAllianceRoutes'] = $this->isOwnAllianceRoute();
        $view->vars['hasEmbassy'] = is_array($embassy) && (int)($embassy['embassy'] ?? 0) > 0;
        $view->vars['quitHref'] = $m->getQuitAllianceHref((int)$this->session->getPlayerId());
        $view->vars['finish'] = Config::getInstance()->dynamic->serverFinished;
        $view->vars['options']['perm'] = [
            $this->session->hasAlliancePermission(AllianceModel::CHANGE_ALLIANCE_DESC),
            $this->session->hasAlliancePermission(AllianceModel::ASSIGN_TO_POSITION),
            $this->session->hasAlliancePermission(AllianceModel::MANAGE_FORUM),
            $this->session->hasAlliancePermission(AllianceModel::INVITE_PLAYER),
            $this->session->hasAlliancePermission(AllianceModel::ALLIANCE_DIPLOMACY),
            $this->session->hasAlliancePermission(AllianceModel::KICK_PLAYER),
        ];
        $view->vars['options']['total'] = +($this->session->hasAlliancePermission(AllianceModel::CHANGE_ALLIANCE_DESC) ? 1 : 0) + ($this->session->hasAlliancePermission(AllianceModel::ASSIGN_TO_POSITION) ? 1 : 0) + ($this->session->hasAlliancePermission(AllianceModel::MANAGE_FORUM) ? 1 : 0);
        $this->view->vars['content'] .= $view->output();
    }

    private function showAllianceBanner(string $screen): void
    {
        $bannerModel = new AllianceBannerModel();
        $uid = (int) $this->session->getPlayerId();
        $aid = (int) $this->selectedAllianceID;
        $playerBanner = $bannerModel->getPlayerBanner($uid);
        $allianceBanner = $bannerModel->getAllianceBanner($aid);
        $canSetAllianceBanner = $this->session->hasAlliancePermission(AllianceModel::CHANGE_ALLIANCE_DESC)
            || $this->session->hasAlliancePermission(AllianceModel::ASSIGN_TO_POSITION);

        if (WebService::isPost()) {
            $action = $this->getPostPlainText('bannerAction');
            $raw = $this->getPostString('bannerJson');
            $decoded = $raw !== '' ? json_decode($raw, true) : null;
            $banner = is_array($decoded) ? AllianceBannerModel::normalizeBanner($decoded) : null;
            if ($action === 'save' && $banner !== null) {
                $bannerModel->setPlayerBanner($uid, $banner);
                $this->redirect('/alliance/options/banner/my');
                return;
            }
            if ($action === 'submitGallery' && $playerBanner !== null) {
                $bannerModel->submitToGallery($aid, $uid, $playerBanner);
                $this->redirect('/alliance/options/banner/gallery');
                return;
            }
            if ($action === 'setAlliance' && $playerBanner !== null && $canSetAllianceBanner) {
                $bannerModel->setAllianceBanner($aid, $playerBanner);
                $this->redirect('/alliance/profile');
                return;
            }
            if ($action === 'setAllianceFromGallery' && $banner !== null && $canSetAllianceBanner) {
                $bannerModel->setAllianceBanner($aid, $banner);
                $this->redirect('/alliance/profile');
                return;
            }
        }

        $view = new PHPBatchView('alliance/banner');
        $view->vars['screen'] = $screen;
        $view->vars['aid'] = $aid;
        $view->vars['playerBanner'] = $playerBanner;
        $view->vars['allianceBanner'] = $allianceBanner;
        $view->vars['gallery'] = $bannerModel->getGallery($aid);
        $view->vars['canSetAllianceBanner'] = $canSetAllianceBanner;
        $view->vars['config'] = [
            'baseForm' => AllianceBannerModel::BASE_FORMS,
            'pattern' => AllianceBannerModel::PATTERNS,
            'backSymbol' => AllianceBannerModel::BACK_SYMBOLS,
            'frontSymbol' => AllianceBannerModel::FRONT_SYMBOLS,
            'patternsWithSecondaryColors' => AllianceBannerModel::PATTERNS_WITH_SECONDARY,
            'colorPalette' => AllianceBannerModel::COLOR_PALETTE,
            'currentBanner' => $playerBanner,
        ];
        $view->vars['bannerTemplateSvg'] = (string) file_get_contents(
            __DIR__ . '/../resources/Templates/alliance/banner/banner-template-svg.svg'
        );
        $this->view->vars['content'] .= $view->output();
    }

    private function processAllianceChangeName(): void
    {
        //change name.
        $db = DB::getInstance();
        $view = new PHPBatchView("alliance/optionChangeName");
        $view->vars['aid'] = $this->selectedAllianceID;
        $view->vars['formAction'] = $this->optionsPath('name', 100);
        $view->vars['tag'] = $this->selectedAllianceData['tag'];
        $view->vars['name'] = $this->selectedAllianceData['name'];
        $view->vars['note'] = '';
        if (WebService::isPost()) {
            $view->vars['tag'] = $this->getPostPlainText('newTag');
            $view->vars['name'] = $this->getPostPlainText('newName');
            if (StringChecker::isValidName($view->vars['tag']) && StringChecker::isValidName($view->vars['name'])) {
                if ($view->vars['tag'] === '') {
                    $view->vars['tagError'] = T("Alliance", "Enter tag");
                } else {
                    $tag = $db->real_escape_string($view->vars['tag']);
                    $tagExists = 0 < $db->fetchScalar("SELECT COUNT(id) FROM alidata WHERE tag='$tag' AND id!={$this->selectedAllianceID}");
                    if ($tagExists) {
                        $view->vars['tagError'] = T("Alliance", "Tag exists");
                    }
                    if (mb_strlen($view->vars['tag']) > 5) {
                        $view->vars['tagError'] = T("Alliance", "Tag too long");
                    }
                }
                if ($view->vars['name'] === '') {
                    $view->vars['nameError'] = T("Alliance", "Enter name");
                } elseif (mb_strlen($view->vars['name']) > 15) {
                    $view->vars['nameError'] = T("Alliance", "Name too long");
                }
                if (!isset($view->vars['nameError']) && !isset($view->vars['tagError']) && mb_strlen($view->vars['tag']) <= 5 && mb_strlen($view->vars['name']) <= 15) {
                    $tag = $db->real_escape_string($view->vars['tag']);
                    $name = $db->real_escape_string($view->vars['name']);
                    if ($db->mysqli->query("UPDATE alidata SET name='$name', tag='$tag' WHERE id={$this->selectedAllianceID}")) {
                        $this->selectedAllianceData['tag'] = $view->vars['tag'];
                        $this->selectedAllianceData['name'] = $view->vars['name'];
                        $view->vars['note'] = T("Alliance", "Changes saved");
                    } else {
                        // The UNIQUE tag was taken meanwhile.
                        $view->vars['tagError'] = T("Alliance", "Tag exists");
                    }
                }
            }
        }
        $this->view->vars['content'] .= $view->output();
    }

    private function processAllianceChangeDesc(): void
    {
        $view = new PHPBatchView("alliance/optionChangeDesc");
        $db = DB::getInstance();
        $view->vars['aid'] = $this->selectedAllianceID;
        $view->vars['formAction'] = $this->optionsPath('description', 3);
        $view->vars['note'] = '';
        if (WebService::isPost()) {
            $description1 = $this->getPostPlainText('be1');
            $description2 = $this->getPostPlainText('be2');
            if (StringChecker::isValidMessage($description1) && StringChecker::isValidMessage($description2)) {
                $escapedDescription1 = $db->real_escape_string($description1);
                $escapedDescription2 = $db->real_escape_string($description2);
                $db->query("UPDATE alidata SET desc1='$escapedDescription1', desc2='$escapedDescription2' WHERE id={$this->selectedAllianceID}");
                $this->selectedAllianceData['desc1'] = $description1;
                $this->selectedAllianceData['desc2'] = $description2;
                $view->vars['note'] = T("Alliance", "Changes saved");
            }
        }
        $view->vars['desc1'] = $this->selectedAllianceData['desc1'];
        $view->vars['desc2'] = $this->selectedAllianceData['desc2'];
        $medals = $db->query("SELECT * FROM allimedal WHERE aid={$this->selectedAllianceID} ORDER BY week, category");
        $view->vars['hasMedal'] = $medals->num_rows > 0;
        $view->vars['medals'] = '';
        while ($row = $medals->fetch_assoc()) {
            $view->vars['medals'] .= '<tr>';
            $view->vars['medals'] .= '<td class="typ">' . BBCode::getMedalCategory($row['category']) . '</td>';
            $view->vars['medals'] .= '<td class="ra">' . $row['rank'] . '</td>';
            $view->vars['medals'] .= '<td class="we">' . $row['week'] . '</td>';
            $view->vars['medals'] .= '<td class="bb">[#' . $row['id'] . ']</td>';
            $view->vars['medals'] .= '</tr>';
        }
        $this->view->vars['content'] .= $view->output();
    }

    private function processChangeAllianceInfo(): void
    {
        $view = new PHPBatchView("alliance/changeInternalPage");
        $view->vars['aid'] = $this->selectedAllianceID;
        $view->vars['formAction'] = $this->optionsPath('info', 7);
        if (WebService::isPost()) {
            $db = DB::getInstance();
            $info1 = $this->getPostPlainText('info1');
            $info2 = $this->getPostPlainText('info2');
            $escapedInfo1 = $db->real_escape_string($info1);
            $escapedInfo2 = $db->real_escape_string($info2);
            $db->query("UPDATE alidata SET info1='$escapedInfo1', info2='$escapedInfo2' WHERE id={$this->selectedAllianceID}");
            $this->selectedAllianceData['info1'] = $info1;
            $this->selectedAllianceData['info2'] = $info2;
        }
        $view->vars['info1'] = $this->selectedAllianceData['info1'];
        $view->vars['info2'] = $this->selectedAllianceData['info2'];
        $this->view->vars['content'] .= $view->output();
    }

    private function processAssignPosition(): void
    {
        $db = DB::getInstance();
        $view = new PHPBatchView("alliance/optionAssignPosition");
        $view->vars['aid'] = $this->selectedAllianceID;
        $view->vars['formAction'] = $this->optionsPath('rights', 1);
        if (WebService::isPost()) {
            $rights = isset($_POST['rights']) ? $_POST['rights'] : null;
            if (is_array($rights)) {
                foreach ($rights as $uid => $right) {
                    if (!ctype_digit((string)$uid) || (int)$uid <= 0 || !is_array($right)) {
                        continue;
                    }
                    $uid = (int)$uid;
                    $permission = 0;
                    if (isset($right['e1']) && $right['e1']) {
                        $permission |= AllianceModel::ASSIGN_TO_POSITION;
                    }
                    if (isset($right['e2']) && $right['e2']) {
                        $permission |= AllianceModel::KICK_PLAYER;
                    }
                    if (isset($right['e3']) && $right['e3']) {
                        $permission |= AllianceModel::CHANGE_ALLIANCE_DESC;
                    }
                    if (isset($right['e6']) && $right['e6']) {
                        $permission |= AllianceModel::ALLIANCE_DIPLOMACY;
                    }
                    if (isset($right['e7']) && $right['e7']) {
                        $permission |= AllianceModel::IGM_MESSAGE;
                    }
                    if (isset($right['e4']) && $right['e4']) {
                        $permission |= AllianceModel::INVITE_PLAYER;
                    }
                    if (isset($right['e5']) && $right['e5']) {
                        $permission |= AllianceModel::MANAGE_FORUM;
                    }
                    if (isset($right['e9']) && $right['e9']) {
                        $permission |= AllianceModel::MANAGE_MARKS;
                    }
                    if (isset($right['e10']) && $right['e10']) {
                        $permission |= AllianceModel::MANAGE_SPECIALIZATION;
                    }
                    $rang = isset($right['rang']) && is_string($right['rang']) ? strip_tags($right['rang']) : '';
                    if (strlen($rang) > 20) {
                        continue;
                    }
                    $db->run("UPDATE users SET alliance_role=?, alliance_role_name=? WHERE aid=? AND id=?", [$permission, $rang, (int)$this->selectedAllianceID, $uid]);
                }
                $view->vars['note'] = T("Alliance", "Changes saved");
            }
        }
        $view->vars['newRights'] = '';
        $membersWithPermission = $db->query("SELECT id, name, alliance_role, alliance_role_name FROM users WHERE aid={$this->selectedAllianceID}");
        $view->vars['membersWithPermission'] = '';
        while ($row = $membersWithPermission->fetch_assoc()) {
            $view->vars['membersWithPermission'] .= '<tr>';
            $view->vars['membersWithPermission'] .= '<td class="name">' . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . '</td>';
            $hasPermission = ($row['alliance_role'] & AllianceModel::ASSIGN_TO_POSITION);
            $view->vars['membersWithPermission'] .= '<td class="member"><input class="check" type="checkbox" name="rights[' . $row['id'] . '][e1]" value="1" ' . ($hasPermission ? 'checked="checked"' : '') . ' /></td>';
            $hasPermission = ($row['alliance_role'] & AllianceModel::KICK_PLAYER);
            $view->vars['membersWithPermission'] .= '<td class="member"><input class="check" type="checkbox" name="rights[' . $row['id'] . '][e2]" value="1" ' . ($hasPermission ? 'checked="checked"' : '') . ' /></td>';
            $hasPermission = ($row['alliance_role'] & AllianceModel::CHANGE_ALLIANCE_DESC);
            $view->vars['membersWithPermission'] .= '<td class="member"><input class="check" type="checkbox" name="rights[' . $row['id'] . '][e3]" value="1" ' . ($hasPermission ? 'checked="checked"' : '') . ' /></td>';
            $hasPermission = ($row['alliance_role'] & AllianceModel::ALLIANCE_DIPLOMACY);
            $view->vars['membersWithPermission'] .= '<td class="member"><input class="check" type="checkbox" name="rights[' . $row['id'] . '][e6]" value="1" ' . ($hasPermission ? 'checked="checked"' : '') . ' /></td>';
            $hasPermission = ($row['alliance_role'] & AllianceModel::IGM_MESSAGE);
            $view->vars['membersWithPermission'] .= '<td class="member"><input class="check" type="checkbox" name="rights[' . $row['id'] . '][e7]" value="1" ' . ($hasPermission ? 'checked="checked"' : '') . ' /></td>';
            $hasPermission = ($row['alliance_role'] & AllianceModel::INVITE_PLAYER);
            $view->vars['membersWithPermission'] .= '<td class="member"><input class="check" type="checkbox" name="rights[' . $row['id'] . '][e4]" value="1" ' . ($hasPermission ? 'checked="checked"' : '') . ' /></td>';
            $hasPermission = ($row['alliance_role'] & AllianceModel::MANAGE_FORUM);
            $view->vars['membersWithPermission'] .= '<td class="member"><input class="check" type="checkbox" name="rights[' . $row['id'] . '][e5]" value="1" ' . ($hasPermission ? 'checked="checked"' : '') . ' /></td>';
            $hasPermission = ($row['alliance_role'] & AllianceModel::MANAGE_MARKS);
            $view->vars['membersWithPermission'] .= '<td class="member"><input class="check" type="checkbox" name="rights[' . $row['id'] . '][e9]" value="1" ' . ($hasPermission ? 'checked="checked"' : '') . ' /></td>';
            $hasPermission = ($row['alliance_role'] & AllianceModel::MANAGE_SPECIALIZATION);
            $view->vars['membersWithPermission'] .= '<td class="member"><input class="check" type="checkbox" name="rights[' . $row['id'] . '][e10]" value="1" ' . ($hasPermission ? 'checked="checked"' : '') . ' /></td>';
            $view->vars['membersWithPermission'] .= '<td class="title"><input class="text" type="text" name="rights[' . (int) $row['id'] . '][rang]" value="' . htmlspecialchars($row['alliance_role_name'], ENT_QUOTES, 'UTF-8') . '" maxlength="20" /></td>';
            $view->vars['membersWithPermission'] .= '</tr>';

        }
        $this->view->vars['content'] .= $view->output();
    }

    private function showAllianceInviting(): void
    {
        $disAllowInvite = getGame("disallowAllianceInviteAfterWWRelease") && ArtefactsModel::wwPlansReleased();
        $view = new PHPBatchView("alliance/optionInvite");
        $view->vars['aid'] = $this->selectedAllianceID;
        $view->vars['formAction'] = $this->optionsPath('invite', 4);
        $view->vars['disallowInvite'] = $disAllowInvite;
        $view->vars['note'] = '';
        $view->vars['error'] = '';
        $db = DB::getInstance();
        if (WebService::isPost() && !$disAllowInvite) {
            $a_name = $this->getPostPlainText('a_name');
            $escapedName = $db->real_escape_string($a_name);
            $m = new AllianceModel();
            $uid = $db->fetchScalar("SELECT id FROM users WHERE name='$escapedName'");
            if (!$uid || $uid == $this->session->getPlayerId()) {
                $view->vars['error'] = sprintf(T("Alliance", 'The player x does`t exists'), $a_name);
            } else {
                $aid = $db->fetchScalar("SELECT aid FROM users WHERE id=$uid");
                if (!($aid == $this->selectedAllianceID)) {
                    $result = $m->sendInvite($this->session->getPlayerId(), $this->selectedAllianceID, $uid);
                    if ($result == 1) {
                        $view->vars['error'] = sprintf(T("Alliance", "test has already received an invitation"), $a_name);
                    } else {
                        $view->vars['note'] = sprintf(T("Alliance", 'invite sent to x'), $a_name);
                    }
                }

            }
        }
        if (isset($_GET['d'])) {
            $result = $db->query("SELECT * FROM ali_invite WHERE id=" . (int)$_GET['d']);
            if ($result->num_rows) {
                $row = $result->fetch_assoc();
                $name = $db->fetchScalar("SELECT name FROM users WHERE id={$row['uid']}");
                if ($name) {
                    $allianceNotificationEnabled = $db->fetchScalar("SELECT allianceNotificationEnabled FROM users WHERE id={$row['uid']}");
                    if ($allianceNotificationEnabled) {
                        $now = time();
                        $sentBefore = $db->fetchScalar("SELECT COUNT(id) FROM alliance_notification WHERE aid={$this->selectedAllianceID} AND to_uid={$row['uid']} AND type=2 AND $now-time <= 86400*2");
                        if (!$sentBefore) {
                            $data = [
                                $name,
                                $this->selectedAllianceData['name'],
                                $this->session->getName(),
                            ];
                            (new MessageModel())->sendMessage(1, $row['uid'], '', serialize($data), 2);
                            $db->query("INSERT INTO alliance_notification (aid, to_uid, type, time) VALUES ({$this->selectedAllianceID}, {$row['uid']}, 2, $now)");
                        }
                    }
                }
                if ($row['aid'] == $this->selectedAllianceID) {
                    $db->query("DELETE FROM ali_invite WHERE id=" . (int)$_GET['d']);
                }
            }
        }
        $view->vars['invitations'] = '';
        $inviteDeleteBase = $this->isOwnAllianceRoute()
            ? '/alliance/options/invite?d='
            : ('/alliance/' . $this->selectedAllianceID . '/options?o=4&d=');
        $invitations = $db->query("SELECT i.*, u.name FROM ali_invite i LEFT JOIN users u ON u.id=i.uid WHERE i.aid={$this->selectedAllianceID}");
        while ($row = $invitations->fetch_assoc()) {
            $name = $row['name'];
            $view->vars['invitations'] .= '<tr>
				<td class="abo">
					<button type="button" class="icon " title="' . T(
                "Alliance",
                "draw back"
            ) . '" onclick="window.location.href = \'' . $inviteDeleteBase . (int) $row['id'] . '\'; return false;">
					<img src="img/spacer.gif" class="del" alt="del" /></button>				</td>
				<td>
					<a href="' . profileUrl((int) (int) $row['uid']) . '">' . sprintf(
                T("Alliance", "invitation for x"),
                htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8')
            ) . '</a>
				</td>
			</tr>';
        }
        if (!$invitations->num_rows) {
            $view->vars['invitations'] = '<tr><td class="noData">' . T("Alliance", "none") . '</td></tr>';
        }
        $this->view->vars['content'] .= $view->output();
    }

    /**
     * An alliance forum link may only point to an external http(s) forum;
     * anything else (internal forum paths, script or data URLs) uses the
     * native alliance forum.
     */
    private function externalForumLink(string $link): string
    {
        $link = trim($link);
        if ($link === '' || strlen($link) > 200 || filter_var($link, FILTER_VALIDATE_URL) === false) {
            return '';
        }
        $scheme = strtolower((string) parse_url($link, PHP_URL_SCHEME));
        $host = (string) parse_url($link, PHP_URL_HOST);

        return in_array($scheme, ['http', 'https'], true) && $host !== '' ? $link : '';
    }

    private function processForumLink(): void
    {
        $view = new PHPBatchView("alliance/optionLinkToForum");
        $view->vars['aid'] = $this->selectedAllianceID;
        $view->vars['formAction'] = $this->optionsPath('forum', 5);
        if (WebService::isPost()) {
            $link = trim($this->getPostString('f_link'));
            if ($link === '' || $this->externalForumLink($link) !== '') {
                $db = DB::getInstance();
                $escapedLink = $db->real_escape_string($link);
                $db->query("UPDATE alidata SET forumLink='$escapedLink' WHERE id={$this->selectedAllianceID}");
                $this->selectedAllianceData['forumLink'] = $link;
            }
        }
        $view->vars['f_link'] = $this->selectedAllianceData['forumLink'];
        $this->view->vars['content'] .= $view->output();
    }

    private function processAllianceDiplomacy(): void
    {
        $view = new PHPBatchView("alliance/diplomacy");
        $view->vars['aid'] = $this->selectedAllianceID;
        $view->vars['formAction'] = $this->optionsPath('diplomacy', 6);
        $db = DB::getInstance();
        // The form posts the reference values (4 confederacy, 2 NAP, 3 war); the diplomacy table stores 1, 2 and 3.
        $dipl = [4 => 1, 2 => 2, 3 => 3][$this->getPostInt('diplomacy')] ?? 0;
        $diplBase = $this->isOwnAllianceRoute()
            ? '/alliance/options/diplomacy'
            : ('/alliance/' . $this->selectedAllianceID . '/options?o=6');
        if (WebService::isPost() && $dipl >= 1 && $dipl <= 3) {
            $aTag = $this->getPostPlainText('toAlliance');
            $escapedTag = $db->real_escape_string($aTag);
            $aid = (int) $db->fetchScalar("SELECT id FROM alidata WHERE tag='$escapedTag'");
            if (!$aid) {
                $view->vars['error'] = sprintf(T("Alliance", "Alliance x does not exists"), $aTag);
            } else {
                $count = (int)$db->fetchScalar("SELECT COUNT(id) FROM diplomacy WHERE
                (aid1={$this->selectedAllianceID} AND aid2=$aid)
                OR (aid2={$this->selectedAllianceID} AND aid1=$aid)
                ");
                if ($count > 0) {
                    $view->vars['error'] = sprintf(T("Alliance", "There is already an offer"), $aTag);
                } else {
                    $db->query("INSERT INTO diplomacy (aid1, aid2, type, accepted) VALUES ({$this->selectedAllianceID}, {$aid}, $dipl, 0)");
                    $type = $dipl == 1 ? AllianceModel::LOG_DIPLOMACY_CONF : ($dipl == 2 ? AllianceModel::LOG_DIPLOMACY_NAP : AllianceModel::LOG_DIPLOMACY_WAR);
                    $m = new AllianceModel();
                    $m->addLog($this->selectedAllianceID, [$type, $this->selectedAllianceID, $aid], time());
                    $m->addLog($aid, [$type, $this->selectedAllianceID, $aid], time());
                }
            }
        }
        if (isset($_GET['a'])) {
            //accept
            $find = $db->query("SELECT * FROM diplomacy WHERE accepted=0 AND aid2={$this->selectedAllianceID} AND id=" . (int)$_GET['a']);
            if ($find->num_rows) {
                //yes! accept it
                $row = $find->fetch_assoc();
                $db->query("UPDATE diplomacy SET accepted=1 WHERE id=" . (int)$_GET['a']);
                $m = new AllianceModel();
                $dipl = $row['type'];
                $type = $dipl == 1 ? AllianceModel::LOG_DIPLOMACY_CONF_ACCEPTED : ($dipl == 2 ? AllianceModel::LOG_DIPLOMACY_NAP_ACCEPTED : AllianceModel::LOG_DIPLOMACY_WAR_ACCEPTED);
                $m->addLog($row['aid1'], [$type, $row['aid1'], $row['aid2'],], time());
                $m->addLog($row['aid2'], [$type, $row['aid1'], $row['aid2'],], time());
                Map::allianceDiplomacyCacheUpdate($row['aid1'], $row['aid2']);
            }
        }
        if (isset($_GET['d'])) {
            //accept
            $find = $db->query("SELECT * FROM diplomacy WHERE accepted=0 AND aid1={$this->selectedAllianceID} AND id=" . (int)$_GET['d']);
            if ($find->num_rows) {
                //yes! accept it
                $row = $find->fetch_assoc();
                $db->query("DELETE FROM diplomacy WHERE id=" . (int)$_GET['d']);
                $m = new AllianceModel();
                $dipl = $row['type'];
                $type = $dipl == 1 ? AllianceModel::LOG_DIPLOMACY_CONF_REFUSE : ($dipl == 2 ? AllianceModel::LOG_DIPLOMACY_NAP_REFUSE : AllianceModel::LOG_DIPLOMACY_WAR_REFUSE);
                $m->addLog($row['aid1'], [$type, $row['aid1'], $row['aid2'],], time());
                $m->addLog($row['aid2'], [$type, $row['aid1'], $row['aid2'],], time());
                Map::allianceDiplomacyCacheUpdate($row['aid1'], $row['aid2']);
            }
        }
        if (isset($_GET['c'])) {
            //accept
            $find = $db->query("SELECT * FROM diplomacy WHERE accepted=1 AND (aid1={$this->selectedAllianceID} OR aid2={$this->selectedAllianceID}) AND id=" . (int)$_GET['c']);
            if ($find->num_rows) {
                //yes! accept it
                $row = $find->fetch_assoc();
                $db->query("DELETE FROM diplomacy WHERE id=" . (int)$_GET['c']);
                Map::allianceDiplomacyCacheUpdate($row['aid1'], $row['aid2']);
            }
        }
        $view->vars['ownOffers'] = '';
        $ownOffers = $db->query("SELECT d.*, a.tag FROM diplomacy d LEFT JOIN alidata a ON a.id=d.aid2 WHERE d.accepted=0 AND d.aid1={$this->selectedAllianceID}");
        while ($row = $ownOffers->fetch_assoc()) {
            if ($row['type'] == 1) {
                $text = T("Alliance", "confederacy with x");
            } elseif ($row['type'] == 2) {
                $text = T("Alliance", "NAP with x");
            } else {
                $text = T("Alliance", "war with x");
            }
            $tag = $row['tag'];
            $drawHref = $this->isOwnAllianceRoute()
                ? ($diplBase . '?d=' . (int) $row['id'])
                : ($diplBase . '&d=' . (int) $row['id']);
            $view->vars['ownOffers'] .= '<tr>
        <td class="abo">
            <button type="button" class="icon " title="' . T(
                "Alliance",
                "draw back"
            ) . '" onclick="window.location.href = \'' . $drawHref . '\'; return false;"><img src="img/spacer.gif" class="del" alt="del" /></button>
        </td>
        <td>
            <a href="/alliance/' . $row['aid2'] . '">' . htmlspecialchars(sprintf($text, (string) $tag), ENT_QUOTES, 'UTF-8') . '</a>
        </td>
    </tr>';
        }
        $view->vars['foreign'] = '';
        $ownOffers = $db->query("SELECT d.*, a.tag FROM diplomacy d LEFT JOIN alidata a ON a.id=d.aid1 WHERE d.accepted=0 AND d.aid2={$this->selectedAllianceID}");
        while ($row = $ownOffers->fetch_assoc()) {
            if ($row['type'] == 1) {
                $text = T("Alliance", "confederacy with x");
            } elseif ($row['type'] == 2) {
                $text = T("Alliance", "NAP with x");
            } else {
                $text = T("Alliance", "war with x");
            }
            $tag = $row['tag'];
            $refuseHref = $this->isOwnAllianceRoute()
                ? ($diplBase . '?d=' . (int) $row['id'])
                : ($diplBase . '&d=' . (int) $row['id']);
            $acceptHref = $this->isOwnAllianceRoute()
                ? ($diplBase . '?a=' . (int) $row['id'])
                : ($diplBase . '&a=' . (int) $row['id']);
            $view->vars['foreign'] .= '<tr>
        <td class="abo">
            <button type="button" class="icon " title="' . T(
                "Alliance",
                "draw back"
            ) . '" onclick="window.location.href = \'' . $refuseHref . '\'; return false;"><img src="img/spacer.gif" class="del" alt="del" /></button>
                    &nbsp;
                    <button type="button" class="icon " title="' . T(
                "Alliance",
                "accept"
            ) . '" onclick="window.location.href = \'' . $acceptHref . '\'; return false;"><img src="img/spacer.gif" class="accept" alt="accept" /></button>

        </td>
        <td>
            <a href="/alliance/' . $row['aid1'] . '">' . htmlspecialchars(sprintf($text, (string) $tag), ENT_QUOTES, 'UTF-8') . '</a>
        </td>
    </tr>';
        }
        $view->vars['exiting'] = '';
        // A pact lists the other alliance, whichever side proposed it.
        $ownOffers = $db->query("SELECT d.*, IF(d.aid1={$this->selectedAllianceID}, d.aid2, d.aid1) AS partner, a.tag FROM diplomacy d
            LEFT JOIN alidata a ON a.id=IF(d.aid1={$this->selectedAllianceID}, d.aid2, d.aid1)
            WHERE d.accepted=1 AND (d.aid1={$this->selectedAllianceID} OR d.aid2={$this->selectedAllianceID})");
        while ($row = $ownOffers->fetch_assoc()) {
            if ($row['type'] == 1) {
                $text = T("Alliance", "confederacy with x");
            } elseif ($row['type'] == 2) {
                $text = T("Alliance", "NAP with x");
            } else {
                $text = T("Alliance", "war with x");
            }
            $tag = $row['tag'];
            $cancelHref = $this->isOwnAllianceRoute()
                ? ($diplBase . '?c=' . (int) $row['id'])
                : ($diplBase . '&c=' . (int) $row['id']);
            $view->vars['exiting'] .= '<tr>
        <td class="abo">
            <button type="button" class="icon " title="' . T(
                "Alliance",
                "draw back"
            ) . '" onclick="window.location.href = \'' . $cancelHref . '\'; return false;"><img src="img/spacer.gif" class="del" alt="del" /></button>

        </td>
        <td>
            <a href="/alliance/' . (int) $row['partner'] . '">' . htmlspecialchars(sprintf($text, (string) $tag), ENT_QUOTES, 'UTF-8') . '</a>
        </td>
    </tr>';
        }
        $this->view->vars['content'] .= $view->output();
    }

    private function showAllianceKick(): void
    {
        $disAllowKick = getGame("disallowAllianceKickAfterWWRelease") && ArtefactsModel::wwPlansReleased();
        $db = DB::getInstance();
        $view = new PHPBatchView("alliance/optionKick");
        $view->vars['aid'] = $this->selectedAllianceID;
        $view->vars['formAction'] = $this->optionsPath('kick', 2);
        $view->vars['disallowKick'] = $disAllowKick;
        $view->vars['note'] = '';
        $view->vars['error'] = '';
        $kickPlayerId = $this->getPostInt('kickPlayer');
        $confirmation = $this->getPostString('confirmation_kick');
        if (WebService::isPost() && !$disAllowKick) {
            if ($confirmation !== 'kick') {
                $view->vars['error'] = T("Alliance", "Please type: kick");
            } elseif ($kickPlayerId > 0 && $kickPlayerId !== (int) $this->session->getPlayerId()) {
                $member = $db->query(
                    "SELECT id, name, alliance_join_time FROM users WHERE aid={$this->selectedAllianceID} AND id=$kickPlayerId"
                )->fetch_assoc();
                if (is_array($member)) {
                    $joinedAt = (int) ($member['alliance_join_time'] ?? 0);
                    if ($joinedAt > 0 && (time() - $joinedAt) < 86400) {
                        $view->vars['error'] = T(
                            "Alliance",
                            "Players who joined recently cannot be kicked from the alliance within 24 hours after joining."
                        );
                    } else {
                        $m = new AllianceModel();
                        $m->kickPlayer(
                            $this->session->getPlayerId(),
                            $kickPlayerId,
                            $this->selectedAllianceID
                        );
                        $view->vars['note'] = sprintf(
                            T("Alliance", 'x has been kicked from the alliance'),
                            (string) $member['name']
                        );
                    }
                }
            }
        }
        $members = $db->query("SELECT id, name FROM users WHERE aid={$this->selectedAllianceID} ORDER BY name");
        $view->vars['members'] = '';
        $view->vars['hasMembers'] = false;
        while ($row = $members->fetch_assoc()) {
            $view->vars['hasMembers'] = true;
            $view->vars['members'] .= '<option value="' . (int) $row['id'] . '">' . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . '</option>';
        }
        $this->view->vars['content'] .= $view->output();
    }

    private function quitAlliance(): void
    {
        $m = new AllianceModel();
        $link = $m->getQuitAllianceHref((int)$this->session->getPlayerId());
        if ($link === null) {
            return;
        }
        $this->redirect($link);
    }

    private function handleBonuses(): void
    {
        $this->view->vars['contentCssClass'] .= ' allianceBonuses';
        $view = new PHPBatchView("alliance/bonuses/main");
        $m = new AllianceBonusModel();
        $bonuses = $m->getAllianceBonusesLevel($this->selectedAllianceID);
        $available_times = [];
        foreach ($bonuses as $bonus) {
            $available_times[] = Formulas::getAllianceBonusCoolDownForNewPlayers($bonus);
        }
        $max = max($available_times);
        $view->vars['newlyJoined'] = time() - $this->session->getAllianceJoinTime() <= $max;
        $this->view->vars['content'] .= $view->output();
    }
}
