<?php

namespace Controller\Build;

use Controller\AnyCtrl;
use Core\Config;
use Core\Database\DB;
use Core\Helper\StringChecker;
use Core\Helper\WebService;
use Core\Session;
use Core\Village;
use Game\Formulas;
use Model\AllianceModel;
use Model\MessageModel;
use resources\View\PHPBatchView;
use Controller\BuildCtrl;

use function array_values;

class EmbassyCtrl extends AnyCtrl
{
    /** Tab slugs in favourite-storage order (see Session::getFavoriteTab). */
    // `leave` is the Quit-alliance deep link; it renders the same leave form as `info`.
    public const TABS = ['info', 'villages_nearby', 'join', 'leave'];

    /** Radius (in map fields) of the "Villages Nearby" list and its row cap. */
    private const NEARBY_RADIUS = 25;
    private const NEARBY_LIMIT = 50;

    private int $slot = 0;

    public function __construct(BuildCtrl $build)
    {
        parent::__construct();
        $this->view = new PHPBatchView("build/building/Embassy");
        $this->view->vars['content'] = null;
        $level = (int)Village::getInstance()->getField($build->selectedBuildingIndex)['level'];
        $this->view->vars['showTabs'] = $level > 0;
        if ($level <= 0) {
            return;
        }
        $this->slot = (int)$build->selectedBuildingIndex;
        $this->view->vars['hasAlliance'] = Session::getInstance()->getAllianceId();
        $favorite = Session::getInstance()->getFavoriteTab('embassyBuildingSubTabs');
        $this->view->vars['favorTab'] = in_array($favorite, self::TABS, true) ? $favorite : 'info';
        $tab = $this->view->vars['favorTab'];
        if (isset($_REQUEST['tt']) && is_string($_REQUEST['tt']) && in_array($_REQUEST['tt'], self::TABS, true)) {
            $tab = $_REQUEST['tt'];
        }
        $this->view->vars['action'] = $tab;
        $this->view->vars['tabs'] = [
            'info' => T('Embassy', 'Alliance'),
            'villages_nearby' => T('Embassy', 'Villages Nearby', 'Villages Nearby'),
            'join' => T('Embassy', 'Invitations', 'Invitations'),
        ];

        // Join / refuse / leave all arrive as `action=alliance&do=<verb>`.
        $joinError = '';
        $leaveError = '';
        $do = $_REQUEST['do'] ?? '';
        if (($_REQUEST['action'] ?? '') === 'alliance' && is_string($do)) {
            if (($do === 'join' || $do === 'refuse') && isset($_REQUEST['d'])) {
                // State-changing: POST plus the session checker (GET links are never accepted).
                $joinError = $this->guardInvitationRequest();
                if ($joinError === '') {
                    if ($do === 'join') {
                        $joinError = $this->handleJoin((int)$_REQUEST['d']);
                    } else {
                        (new AllianceModel())->deleteInviteForPlayer(Session::getInstance()->getPlayerId(), (int)$_REQUEST['d']);
                    }
                }
                $tab = $this->view->vars['action'] = 'join';
            } elseif ($do === 'leave' && WebService::isPost() && $this->view->vars['hasAlliance']) {
                $leaveError = $this->handleLeave();
                $tab = $this->view->vars['action'] = 'info';
            }
        }
        switch ($tab) {
            case 'leave':
            case 'info':
                $this->view->vars['action'] = 'info';
                $this->info($leaveError);
                break;
            case 'villages_nearby':
                $this->villagesNearby();
                break;
            case 'join':
                $this->join($joinError);
                break;
        }
    }

    /** Returns an error message when a join/refuse request is not an authorised POST. */
    private function guardInvitationRequest(): string
    {
        $session = Session::getInstance();
        if (!WebService::isPost()) {
            return T('Embassy', 'Request expired', 'The request has expired. Please try again.');
        }
        if ($session->banned()) {
            $this->innerRedirect("InGameBannedPage");
        }
        if ($session->isSitter()) {
            return T('inGame', 'sitterNoPermForGold');
        }
        $token = $_POST[Session::getCheckerName()] ?? '';
        if (!Session::claimAction($token)) {
            return T('Embassy', 'Request expired', 'The request has expired. Please try again.');
        }

        return '';
    }

    private function handleJoin(int $invitationId): string
    {
        if (Session::getInstance()->getAllianceId() > 0) {
            return '';
        }
        if (Session::getInstance()->isInVacationMode()) {
            $this->redirect('/options/vacation');
        }
        $result = (new AllianceModel())->acceptInvite(Session::getInstance()->getPlayerId(), $invitationId);
        if ($result === -1) {
            return T("Buildings", "allianceFull");
        }
        if ($result === false) {
            return '';
        }
        Session::getInstance()->setAllianceId($result);
        $this->redirect(buildTabUrl($this->slot, 18, 'tt', 'info'));
        return '';
    }

    /** Returns an error message, or redirects to the village overview on success. */
    private function handleLeave(): string
    {
        $session = Session::getInstance();
        if ($session->isSitter()) {
            return T('inGame', 'sitterNoPermForGold');
        }
        $token = $_POST[Session::getCheckerName()] ?? '';
        if (!Session::claimAction($token)) {
            return T('Embassy', 'Leave failed', 'Could not leave the alliance. Please try again.');
        }
        $confirmation = $_POST['confirmation_leave'] ?? '';
        if (!is_string($confirmation) || trim($confirmation) !== 'leave') {
            return T('Embassy', 'Please type leave to confirm', 'Please type "leave" to confirm.');
        }
        $uid = (int)$session->getPlayerId();
        $aid = (int)$session->getAllianceId();
        $db = DB::getInstance();
        if (!$db->begin_transaction()) {
            return T('Embassy', 'Leave failed', 'Could not leave the alliance. Please try again.');
        }
        try {
            $member = $db->query("SELECT aid FROM users WHERE id=$uid FOR UPDATE");
            $currentAid = $member && $member->num_rows ? (int)$member->fetch_assoc()['aid'] : 0;
            if ($currentAid !== $aid || $currentAid <= 0) {
                throw new \DomainException('You are no longer in this alliance.');
            }
            (new AllianceModel())->leaveAlliance($uid, $aid);
            if (!$db->commit()) {
                throw new \RuntimeException('commit failed');
            }
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            return $exception instanceof \DomainException
                ? $exception->getMessage()
                : T('Embassy', 'Leave failed', 'Could not leave the alliance. Please try again.');
        }
        $this->redirect('/dorf2.php');
        return '';
    }

    private function info(string $leaveError = ''): void
    {
        if ($this->view->vars['hasAlliance']) {
            $view = new PHPBatchView("build/building/EmbassyInfo");
            $alliance = DB::getInstance()->query("SELECT tag, name FROM alidata WHERE id=" . (int)$this->view->vars['hasAlliance'])->fetch_assoc();
            $view->vars['tag'] = $alliance['tag'];
            $view->vars['name'] = $alliance['name'];
            $view->vars['leaveError'] = $leaveError;
            $view->vars['formAction'] = buildTabUrl($this->slot, 18, 'tt', 'info');
            $this->view->vars['content'] = $view->output();
            return;
        }
        $this->found();
    }

    private function villagesNearby(): void
    {
        $session = Session::getInstance();
        $db = DB::getInstance();
        $xy = Formulas::kid2xy(Village::getInstance()->getKid());
        $x = (int)$xy['x'];
        $y = (int)$xy['y'];
        $ring = 1 + (2 * MAP_SIZE);
        $ring3 = 1 + (3 * MAP_SIZE);
        $distance = "SQRT(POW(((w.x-{$x}+{$ring3})%{$ring} - " . MAP_SIZE . "), 2) + POW(((w.y-{$y}+{$ring3})%{$ring} - " . MAP_SIZE . "), 2))";
        $radius = self::NEARBY_RADIUS;
        $limit = self::NEARBY_LIMIT;
        $uid = (int)$session->getPlayerId();
        $result = $db->query(
            "SELECT v.kid, v.name `village`, v.pop, u.id `uid`, u.name `owner`, u.aid, a.tag, {$distance} `distance`
             FROM vdata v
             JOIN wdata w ON w.id=v.kid
             JOIN users u ON u.id=v.owner
             LEFT JOIN alidata a ON a.id=u.aid
             WHERE v.owner<>{$uid} AND v.owner>1 AND {$distance} <= {$radius}
             ORDER BY `distance` ASC, v.kid ASC
             LIMIT {$limit}"
        );
        $view = new PHPBatchView("build/building/EmbassyVillagesNearby");
        $view->vars['rows'] = [];
        while ($result && ($row = $result->fetch_assoc())) {
            $view->vars['rows'][] = $row;
        }
        $this->view->vars['content'] = $view->output();
    }

    private function join(string $error): void
    {
        $view = new PHPBatchView("build/building/EmbassyJoin");
        $view->vars['error'] = $error;
        $view->vars['invitations'] = [];
        $view->vars['formAction'] = buildTabUrl($this->slot, 18, 'tt', 'join');
        if (!$this->view->vars['hasAlliance']) {
            $db = DB::getInstance();
            $invites = (new AllianceModel())->getInvitesForUser(Session::getInstance()->getPlayerId());
            while ($invites && ($row = $invites->fetch_assoc())) {
                $alliance = $db->query("SELECT name, tag FROM alidata WHERE id=" . (int)$row['aid']);
                $alliance = $alliance && $alliance->num_rows ? $alliance->fetch_assoc() : ['name' => '?', 'tag' => '?'];
                $view->vars['invitations'][] = [
                    'id' => (int)$row['id'],
                    'aid' => (int)$row['aid'],
                    'name' => $alliance['name'],
                    'tag' => $alliance['tag'],
                    'fromUid' => (int)$row['from_uid'],
                    'from' => (new MessageModel())->getPlayerName($row['from_uid']),
                ];
            }
        }
        $this->view->vars['content'] = $view->output();
    }

    private function found(): void
    {
        if (Village::getInstance()->getField($this->slot)['level'] >= 3) {
            $view = new PHPBatchView("build/building/EmbassyFound");
            $view->vars['errorTag'] = $view->vars['errorName'] = '';
            $view->vars['tbody'] = $view->vars['tag'] = $view->vars['name'] = '';
            $view->vars['formAction'] = buildTabUrl($this->slot, 18, 'tt', 'info');
            if (Session::getInstance()->banned()) {
                $this->innerRedirect("InGameBannedPage");
            }
            if (WebService::isPost()) {
                if (Session::getInstance()->isSitter()) {
                    $view->vars['errorName'] = T('inGame', 'sitterNoPermForGold');
                    goto finalize;
                }
                if (Config::getInstance()->dynamic->serverFinished) {
                    $this->innerRedirect("InGameWinnerPage");
                } elseif (Session::getInstance()->isInVacationMode()) {
                    $this->redirect('/options/vacation');
                }
                if (isset($_POST['newTag'])) {
                    $view->vars['tag'] = is_string($_POST['newTag']) ? strip_tags($_POST['newTag']) : '';
                }
                if (isset($_POST['newName'])) {
                    $view->vars['name'] = is_string($_POST['newName']) ? strip_tags($_POST['newName']) : '';
                }
                if (empty($view->vars['tag']) || empty($view->vars['name'])) {
                    if (empty($view->vars['tag'])) {
                        $view->vars['errorTag'] = T("Buildings", "Enter Tag");
                    }
                    if (empty($view->vars['name'])) {
                        $view->vars['errorName'] = T("Buildings", "Enter Name");
                    }
                    goto finalize;
                }
                if (!empty($view->vars['tag'])) {
                    $db = DB::getInstance();
                    $tag = $db->real_escape_string($view->vars['tag']);
                    $e = $db->fetchScalar("SELECT COUNT(id) FROM alidata WHERE tag='$tag'");
                    if ($e) {
                        $view->vars['errorTag'] = T("Buildings", "Tag exists");
                        goto finalize;
                    }
                }
                if (!StringChecker::isValidName($view->vars['name'])) {
                    $view->vars['errorName'] = T("inGame", "Inadmissible name/message");
                } elseif (!StringChecker::isValidName($view->vars['tag'])) {
                    $view->vars['errorTag'] = T("inGame", "Inadmissible name/message");
                } elseif (mb_strlen($view->vars['tag']) > 5) {
                    $view->vars['errorTag'] = T("Alliance", "Tag too long");
                } elseif (mb_strlen($view->vars['name']) > 15) {
                    $view->vars['errorName'] = T("Alliance", "Name too long");
                } else {
                    $m = new AllianceModel();
                    $aid = $m->createAlliance(Session::getInstance()->getPlayerId(), $view->vars['name'], $view->vars['tag']);
                    if (!$aid) {
                        // Another founder took the UNIQUE tag between the check above and the insert.
                        $view->vars['errorTag'] = T("Buildings", "Tag exists");
                        goto finalize;
                    }
                    Session::getInstance()->setAllianceId($aid);
                    $this->view->vars['hasAlliance'] = $aid;
                    $this->view->vars['action'] = 'info';
                    $this->info();
                    return;
                }
            }
            finalize:
            $this->view->vars['content'] = $view->output();
        } else {
            $view = new PHPBatchView("build/building/EmbassyLevelLow");
            $this->view->vars['content'] = $view->output();
        }
    }
}
