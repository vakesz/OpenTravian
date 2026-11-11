<?php

namespace Controller;

use Core\Config;
use Core\Database\DB;
use Core\Helper\BBCode;
use Core\Helper\PageNavigator;
use Core\Helper\TimezoneHelper;
use Core\Helper\WebService;
use Core\Session;
use Model\AllianceModel;
use Model\ForumModel;
use resources\View\PHPBatchView;

class AllianceForum extends AnyCtrl
{
    /** @var \Model\ForumModel */
    private $model;
    /** @var mixed */
    private $selectedAllianceID;

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

    /**
     * State-changing forum actions are GET links; they only count when they carry the session checker.
     * (POST requests are already validated in AllianceCtrl.)
     */
    private function dropForgedStateChanges(): void
    {
        $actions = ['switch_admin', 'deltopic', 'delpost', 'delforum', 'pos', 'lock', 'unlock', 'pin', 'unpin'];
        $requested = array_filter($actions, function ($k) {
            return isset($_REQUEST[$k]);
        });
        if (!$requested || WebService::isPost() || \Core\Session::validateChecker()) {
            return;
        }
        foreach ($requested as $k) {
            unset($_REQUEST[$k], $_GET[$k]);
        }
    }

    private function csrfQuery(): string
    {
        return '&amp;' . \Core\Session::getCheckerForUrl();
    }

    /**
     * Keep all player-facing forum controls on the current Alliance route.
     * The controller still accepts the historical dispatcher internally, but
     * emitting it from the V2 surface would reintroduce a second URL shape.
     */
    private function forumRoute(): string
    {
        if ($this->session->getAllianceId() === $this->selectedAllianceID) {
            return '/alliance/forum';
        }

        return '/alliance/' . $this->selectedAllianceID . '/forum';
    }

    /**
     * @param int $selectedAllianceID
     */
    public function __construct($selectedAllianceID)
    {
        parent::__construct();
        $this->selectedAllianceID = $selectedAllianceID;
        $this->model = new ForumModel();
        $this->view = new PHPBatchView("empty");
        $this->view->vars['content'] = '';
        $this->dropForgedStateChanges();
        if (isset($_REQUEST['switch_admin']) && $this->session->hasAlliancePermission(AllianceModel::MANAGE_FORUM) && $this->session->getAllianceId() == $this->selectedAllianceID) {
            $_SESSION[WebService::fixSessionPrefix('FORUM_ADMIN')] = !(isset($_SESSION[WebService::fixSessionPrefix('FORUM_ADMIN')]) && $_SESSION[WebService::fixSessionPrefix('FORUM_ADMIN')]);
        }
        $isAdmin = isset($_SESSION[WebService::fixSessionPrefix('FORUM_ADMIN')]) && $_SESSION[WebService::fixSessionPrefix('FORUM_ADMIN')] && $this->session->hasAlliancePermission(AllianceModel::MANAGE_FORUM) && $this->session->getAllianceId() == $this->selectedAllianceID;
        if (isset($_REQUEST['pid']) && isset($_REQUEST['ac'])) {
            if ($_REQUEST['ac'] == 'editpost') {
                if (!$this->editPost($isAdmin)) {
                    return;
                }
            }
        }
        $adminAction = false;
        if (isset($_REQUEST['admin']) && $this->session->hasAlliancePermission(AllianceModel::MANAGE_FORUM) && $this->session->getAllianceId() == $this->selectedAllianceID) {
            if (isset($_REQUEST['newforum'])) {
                if (!$this->newForum()) {
                    return;
                }
            }
            if (isset($_REQUEST['tid']) && isset($_REQUEST['deltopic'])) {
                $done = $this->model->deleteTopic((int)$_REQUEST['tid'], $this->session->getAllianceId());
                if ($done) {
                    $_REQUEST['fid'] = $done;
                    if (!$this->showForum($isAdmin)) {
                        return;
                    }
                }
            }
            if (isset($_REQUEST['tid']) && isset($_REQUEST['edittopic'])) {
                if (!$this->editTopic($isAdmin)) {
                    return;
                }
            }
            if (isset($_REQUEST['tid']) && isset($_REQUEST['pid']) && isset($_REQUEST['delpost'])) {
                $topic = $this->model->getTopic((int)$_REQUEST['tid']);
                if ($topic === false) {
                    return;
                }
                $forum = $this->model->getForum(
                    $this->selectedAllianceID,
                    $this->session->getAllianceId(),
                    $topic['forumId']
                );
                if ($forum === false || $forum['aid'] != $this->session->getAllianceId()) {
                    return;
                }
                $answers = $this->model->getTopicAnswersCount($topic['id']);
                if ($answers <= 0) {
                    if (!$this->showTopic($isAdmin)) {
                        return;
                    }
                } else {
                    $this->model->deletePost((int)$_REQUEST['pid']);
                }
            }
            if (isset($_REQUEST['fid']) && isset($_REQUEST['pos'])) {
                $adminAction = true;
                $_REQUEST['fid'] = (int)$_REQUEST['fid'];
                $_REQUEST['pos'] = $_REQUEST['pos'] == 1 ? 1 : -1;
                $this->model->changeForumPosition(
                    $this->session->getAllianceId(),
                    $_REQUEST['fid'],
                    $_REQUEST['pos']
                );
            } elseif (isset($_REQUEST['fid']) && isset($_REQUEST['editforum'])) {
                $adminAction = true;
                if (!$this->editForum()) {
                    return;
                }
            } elseif (isset($_REQUEST['fid']) && isset($_REQUEST['delforum'])) {
                $adminAction = true;
                $forum = $this->model->getForum(
                    $this->selectedAllianceID,
                    $this->session->getAllianceId(),
                    (int)$_REQUEST['fid']
                );
                if ($forum === false || $forum['aid'] <> $this->session->getAllianceId()) {
                } else {
                    $this->model->deleteForum($_REQUEST['fid'], $this->session->getAllianceId());
                }
            } else {
                $adminAction = false;
            }
        }
        if ($this->session->getAllianceId() == $this->selectedAllianceID && $this->session->hasAlliancePermission(AllianceModel::MANAGE_FORUM) && isset($_REQUEST['fid']) && isset($_REQUEST['ac'])) {
            if ($_REQUEST['ac'] == 'newtopic') {
                if (!$this->newTopic()) {
                    return;
                }
            }
        }
        if (isset($_REQUEST['tid'])) {
            if (isset($_POST['vote'])) {
                $topic = $this->model->getTopic((int)$_REQUEST['tid']);
                if ($topic === false) {
                    goto finalize;
                }
                $forum = $this->model->getForum(
                    $this->selectedAllianceID,
                    $this->session->getAllianceId(),
                    $topic['forumId']
                );
                if ($forum === false || $forum['aid'] <> $this->session->getAllianceId()) {
                    goto finalize;
                }
                if ($topic['close'] && $forum['area'] == 3) {
                    $topic['close'] = !$this->model->isTopicOpenedForUser(
                        $forum['id'],
                        $this->session->getPlayerId()
                    );
                }
                $finished = $topic['end_time'] > 0 && time() > $topic['end_time'];
                $vote_option = $this->getPostInt('vote_option');
                if (!$finished && !$topic['close'] && $this->model->voteOptionExists($topic['id'], $vote_option)) {
                    if (!$this->model->haveIVoted($topic['id'], $this->session->getPlayerId())) {
                        $this->model->vote($this->session->getPlayerId(), $topic['id'], $vote_option);
                    }
                    if (!$this->showTopic($isAdmin)) {
                        return;
                    }
                }
            } elseif ($this->session->hasAlliancePermission(AllianceModel::MANAGE_FORUM) && (isset($_REQUEST['lock']) || isset($_REQUEST['unlock']) || isset($_REQUEST['pin']) || isset($_REQUEST['unpin'])) || isset($_REQUEST['ac'])) {
                $topic = $this->model->getTopic((int)$_REQUEST['tid']);
                if ($topic === false) {
                    goto finalize;
                }
                $forum = $this->model->getForum(
                    $this->selectedAllianceID,
                    $this->session->getAllianceId(),
                    $topic['forumId']
                );
                if ($forum === false || $forum['aid'] <> $this->session->getAllianceId()) {
                    goto finalize;
                }
                if (isset($_REQUEST['lock'])) {
                    $this->model->lockTopic($topic['id']);
                } elseif (isset($_REQUEST['unlock'])) {
                    $this->model->unlockTopic($topic['id']);
                } elseif (isset($_REQUEST['pin'])) {
                    $this->model->stickTopic($topic['id']);
                } elseif (isset($_REQUEST['unpin'])) {
                    $this->model->unstickTopic($topic['id']);
                } elseif (isset($_REQUEST['ac']) && $_REQUEST['ac'] == 'newpost') {
                    if ($topic['close'] && $forum['area'] == 3) {
                        $topic['close'] = !$this->model->isTopicOpenedForUser(
                            $forum['id'],
                            $this->session->getPlayerId()
                        );
                    }
                    $topic['close'] = $isAdmin ? false : $topic['close'];
                    if (!$topic['close'] && (!$this->session->isSitter() || $forum['sitter'] == 1)) {
                        $text = $this->getPostPlainText('text');
                        if ($this->getPostInt('a') === 1 && $text !== '' && hash_equals($this->getChecksum(), $this->getPostString('checkstr'))) {
                            $this->changeChecksum();
                            $this->model->addPost(
                                $this->session->getPlayerId(),
                                $this->session->getAllianceId(),
                                $forum['id'],
                                $topic['id'],
                                $text,
                                $this->session->isSitter(),
                                $this->session->hasAlliancePermission(AllianceModel::MANAGE_FORUM)
                            );
                            if (!$this->showTopic($isAdmin)) {
                                return;
                            }
                        } else {
                            if (!$this->newPost($topic, $forum)) {
                                return;
                            }
                        }
                    }
                }
                $_REQUEST['fid'] = $forum['id'];
                if (!$this->showForum($isAdmin)) {
                    return;
                }
            } else {
                if (!$this->showTopic($isAdmin)) {
                    return;
                }
            }
        }
        if (isset($_REQUEST['fid']) && !$adminAction) {
            if (!$this->showForum($isAdmin)) {
                return;
            }
        }
        finalize:
        $this->showForumRoot($isAdmin);
    }

    private function changeMd5Checksum(): void
    {
        $_SESSION[WebService::fixSessionPrefix('FORUM_MD5_CHECKSUM')] = sha1(time() . get_random_string(12));
    }

    /**
     * @return mixed
     */
    private function getMd5CheckSum()
    {
        if (!isset($_SESSION[WebService::fixSessionPrefix('FORUM_MD5_CHECKSUM')])) {
            $this->changeMd5Checksum();
        }
        return $_SESSION[WebService::fixSessionPrefix('FORUM_MD5_CHECKSUM')];
    }

    private function changeChecksum(): void
    {
        $_SESSION[WebService::fixSessionPrefix('FORUM_CHECKSUM')] = get_random_string(3);
    }

    /**
     * @return mixed
     */
    private function getChecksum()
    {
        if (!isset($_SESSION[WebService::fixSessionPrefix('FORUM_CHECKSUM')])) {
            $this->changeChecksum();
        }
        return $_SESSION[WebService::fixSessionPrefix('FORUM_CHECKSUM')];
    }


    /**
     * @param bool $isAdmin
     */
    private function editPost($isAdmin): bool
    {
        $post = $this->model->getPost((int)$_REQUEST['pid']);
        if ($post === false) {
            return true;
        }
        $topic = $this->model->getTopic($post['topicId']);
        if ($topic === false) {
            return true;
        }
        $forum = $this->model->getForum(
            $this->selectedAllianceID,
            $this->session->getAllianceId(),
            $topic['forumId']
        );
        if ($forum === false) {
            return true;
        }
        if ($forum['aid'] <> $this->session->getAllianceId() && in_array($forum['area'], [2, 3,])) {
            return true;
        }
        if ($post['uid'] != $this->session->getPlayerId()) {
            if (!$this->session->hasAlliancePermission(AllianceModel::MANAGE_FORUM) || $forum['aid'] <> $this->selectedAllianceID) {
                return true;
            }
        }
        $text = $this->getPostPlainText('text');
        if (WebService::isPost() && $this->getPostInt('a') === 1 && $text !== '' && hash_equals($this->getChecksum(), $this->getPostString('checkstr'))) {
            $this->changeChecksum();
            $this->model->editPost(
                $post['id'],
                $this->session->getPlayerId(),
                $text
            );
            $_REQUEST['tid'] = $topic['id'];
            $this->showTopic($isAdmin);
            return false;
        }
        $view = new PHPBatchView("alliance/newPost");
        $view->vars['topicName'] = $topic['thread'];
        $view->vars['tid'] = $topic['id'];
        $view->vars['pid'] = $post['id'];
        $view->vars['new'] = false;
        $view->vars['aid'] = $this->selectedAllianceID;
        $view->vars['forumRoute'] = $this->forumRoute();
        $view->vars['checkstr'] = $this->getChecksum();
        $view->vars['text'] = isset($_POST['text']) ? $this->getPostPlainText('text') : $post['post'];
        $this->view->vars['content'] .= $view->output();
        return false;
    }

    /**
     * @param array<string, mixed> $topic
     * @param array<string, mixed> $forum
     */
    private function newPost($topic, $forum): bool
    {
        $view = new PHPBatchView("alliance/newPost");
        $view->vars['topicName'] = $topic['thread'];
        $view->vars['tid'] = $topic['id'];
        $view->vars['aid'] = $this->selectedAllianceID;
        $view->vars['forumRoute'] = $this->forumRoute();
        $view->vars['new'] = true;
        $view->vars['checkstr'] = $this->getChecksum();
        $view->vars['text'] = isset($_POST['text']) ? $this->getPostPlainText('text') : '';
        $this->view->vars['content'] .= $view->output();
        return false;
    }

    /**
     * @param bool $isAdmin
     */
    private function editTopic($isAdmin): bool
    {
        $topic = $this->model->getTopic((int)$_REQUEST['tid']);
        if ($topic === false) {
            return true;
        }
        $forum = $this->model->getForum(
            $this->selectedAllianceID,
            $this->session->getAllianceId(),
            $topic['forumId']
        );
        if ($forum === false) {
            return true;
        }
        if ($forum['aid'] <> $this->session->getAllianceId()) {
            return true;
        }
        $topicName = $this->getPostPlainText('thema');
        if (WebService::isPost() && $topicName !== '') {
            if (strlen($topicName) > 45) {
                return true;
            }
            $this->model->editTopic(
                $topic,
                $this->session->getAllianceId(),
                $topicName,
                $this->getPostInt('fid')
            );
            $_REQUEST['fid'] = $forum['id'];
            $this->showForum($isAdmin);
            return false;
        }
        $view = new PHPBatchView("alliance/editTopic");
        $view->vars['tid'] = $topic['id'];
        $view->vars['forumRoute'] = $this->forumRoute();
        $view->vars['name'] = $topic['thread'];
        $view->vars['options'] = '';
        $db = DB::getInstance();
        $forums = $db->query("SELECT id, name FROM forum_forums WHERE aid=" . $this->session->getAllianceId());
        while ($row = $forums->fetch_assoc()) {
            $view->vars['options'] .= '<option value="' . (int) $row['id'] . '"' . ($row['id'] == $forum['id'] ? ' selected' : '') . '>' . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . '</option>';
        }
        $this->view->vars['content'] .= $view->output();
        return false;
    }

    private function newTopic(): bool
    {
        $forum = $this->model->getForum(
            $this->selectedAllianceID,
            $this->session->getAllianceId(),
            $this->getRequestInt('fid')
        );
        if ($forum === false) {
            return true;
        }
        $view = new PHPBatchView("alliance/new_topic");
        if (WebService::isPost() && hash_equals($this->getMd5CheckSum(), $this->getPostString('checkstr'))) {
            //$this->changeMd5Checksum();
            $text = $this->getPostPlainText('text');
            $topicName = $this->getPostPlainText('thema');
            $surveyName = $this->getPostPlainText('umfrage_thema');
            if ($text !== '' && $topicName !== '') {
                $options = [];
                if ($surveyName !== '') {
                    for ($i = 1; $i <= 8; ++$i) {
                        $x = $this->getPostPlainText('option_' . $i);
                        if (strlen($x) > 60) {
                            continue;
                        }
                        $options[] = $x;
                    }
                }
                if (!sizeof($options)) {
                    $surveyName = '';
                }
                $ends = 0;
                if ($this->getPostString('year') !== '') {
                    $ends = TimezoneHelper::strtotime("{$this->getPostString('year')}-{$this->getPostString('month')}-{$this->getPostString('day')} {$this->getPostString('hour')}:{$this->getPostString('minute')}");
                }
                if (strlen($topicName) > 35) {
                    return true;
                }
                if (strlen($surveyName) > 60) {
                    return true;
                }
                $tid = $this->model->addTopic(
                    $this->session->getPlayerId(),
                    $this->session->getAllianceId(),
                    $forum['id'],
                    $topicName,
                    $text,
                    $surveyName,
                    $options,
                    $ends
                );
                $this->redirect($this->forumRoute() . '?fid=' . (int) $forum['id'] . '&tid=' . (int) $tid);
            }
        }
        $view->vars['checkstr'] = $this->getMd5CheckSum();
        $view->vars['fid'] = $forum['id'];
        $view->vars['aid'] = $this->selectedAllianceID;
        $view->vars['forumRoute'] = $this->forumRoute();
        for ($i = 1; $i <= 8; ++$i) {
            $view->vars['option_' . $i] = $this->getPostPlainText('option_' . $i);
        }
        $view->vars['Survey'] = $this->getPostPlainText('umfrage_thema');
        $view->vars['thema'] = $this->getPostPlainText('thema');
        $view->vars['text'] = $this->getPostPlainText('text');
        $view->vars['days'] = '';
        $view->vars['month'] = '';
        $view->vars['year'] = '';
        $view->vars['hour'] = '';
        $view->vars['minute'] = '';
        $config = Config::getInstance();
        $finish = TimezoneHelper::strtotime(
            TimezoneHelper::date(
                "Y-m-d H:i:s",
                $config->game->start_time + $config->game->round_length * 86400
            ),
            'Y-m-d H:i:s'
        );
        $cur_year = TimezoneHelper::date("Y");
        $finishYear = TimezoneHelper::date("Y", $finish);
        for ($i = $cur_year; $i <= $finishYear; ++$i) {
            $year = $i[2] . $i[3];
            $view->vars['year'] .= '<option value="' . $year . '" ' . ($i == TimezoneHelper::date("Y") ? "selected" : "") . '>' . $year . '</option>';
        }
        $days = 31;
        for ($i = 1; $i <= $days; ++$i) {
            $day = $i;
            if ($day < 10) {
                $day = '0' . $day;
            }
            $view->vars['days'] .= '<option value="' . $day . '" ' . ($day == TimezoneHelper::date("d") ? "selected" : "") . '>' . $day . '</option>';
        }
        $mons = [
            1  => "Jan",
            2  => "Feb",
            3  => "Mar",
            4  => "Apr",
            5  => "May",
            6  => "Jun",
            7  => "Jul",
            8  => "Aug",
            9  => "Sep",
            10 => "Oct",
            11 => "Nov",
            12 => "Dec",
        ];
        $month = TimezoneHelper::date("m");
        if ($month[0] == '0') {
            $month = $month[1];
        }
        for ($i = 1; $i <= 12; ++$i) {
            $view->vars['month'] .= '<option value="' . $mons[$i] . '" ' . ($mons[$i] == $mons[$month] ? "selected" : "") . '>' . $mons[$i] . '</option>';
        }
        for ($i = 0; $i <= 23; ++$i) {
            if ($i < 10) {
                $i = '0' . $i;
            }
            $view->vars['hour'] .= '<option value="' . $i . '" ' . ($i == TimezoneHelper::date("H") ? "selected" : "") . '>' . $i . '</option>';
        }
        for ($i = 0; $i <= 59; ++$i) {
            if ($i < 10) {
                $i = '0' . $i;
            }
            $view->vars['minute'] .= '<option value="' . $i . '" ' . ($i == TimezoneHelper::date("i") ? "selected" : "") . '>' . $i . '</option>';
        }
        $this->view->vars['content'] .= $view->output();
        return false;
    }

    /**
     * @param mixed $isAdmin
     */
    private function showTopic($isAdmin): bool
    {
        $topic = $this->model->getTopic($_REQUEST['tid']);
        if ($topic === false) {
            return true;
        }
        $forum = $this->model->getForum(
            $this->selectedAllianceID,
            $this->session->getAllianceId(),
            $topic['forumId']
        );
        if ($forum === false) {
            return true;
        }
        $isAdmin = $isAdmin && $forum['aid'] == $this->session->getAllianceId();
        if ($topic['close']) {
            $topic['close'] = $forum['area'] == 1 ? !$this->model->isTopicOpenedForUser(
                $forum['id'],
                $this->session->getPlayerId()
            ) : $topic['close'];
        }
        if ($forum['aid'] <> $this->session->getAllianceId() && in_array($forum['area'], [2, 3,])) {
            return true;
        }
        $view = new PHPBatchView("alliance/showTopic");
        $view->vars['areaName'] = T(
            "Alliance",
            ['public_forum', 'conf_forum', 'alliance_forum', 'closed_forum',][$forum['area']]
        );
        $view->vars['aid'] = $this->selectedAllianceID;
        $view->vars['forumRoute'] = $this->forumRoute();
        $view->vars['forumName'] = $forum['name'];
        $view->vars['thread'] = $topic['thread'];
        $view->vars['fid'] = $forum['id'];
        $view->vars['tid'] = $topic['id'];
        $view->vars['showReplyButton'] = !$topic['close'] || $isAdmin;
        $view->vars['end_time'] = sprintf(
            T("Alliance", "This survey ends on x"),
            TimezoneHelper::autoDate($topic['end_time'], true)
        );
        $view->vars['ends'] = $topic['end_time'] > 0;
        $view->vars['close'] = $topic['close'];
        $view->vars['results'] = $topic['close'] || isset($_POST['vote_ergebnis']) && $_POST['vote_ergebnis'] == 1 || ($topic['end_time'] > 0 && time() > $topic['end_time']);
        $view->vars['finished'] = $topic['end_time'] > 0 && time() > $topic['end_time'];
        $view->vars['content'] = $view->vars['adminSwitchButton'] = '';
        $view->vars['nav'] = '';
        if ($this->session->hasAlliancePermission(AllianceModel::MANAGE_FORUM) && $forum['aid'] == $this->session->getAllianceId()) {
            $view->vars['adminSwitchButton'] = $this->getAdminSwitchButton($isAdmin, $forum['id'], $topic['id']);
        }
        $view->vars['showVotes'] = !empty($topic['Survey']);
        if ($view->vars['showVotes']) {
            $view->vars['voted'] = $this->model->haveIVoted($topic['id'], $this->session->getPlayerId());
            $view->vars['SurveyName'] = $topic['Survey'];
            $view->vars['SurveyDate'] = TimezoneHelper::autoDate($topic['SurveyStartTime'], true);
            $view->vars['options'] = $this->renderOptions(
                $topic['id'],
                $topic['close'] || $view->vars['voted'] || isset($_POST['vote_ergebnis']) && $_POST['vote_ergebnis'] == 1 || ($topic['end_time'] > 0 && time() > $topic['end_time'])
            );
        }
        $view->vars['content'] = $this->renderAnswers($topic['id'], $isAdmin, $view);
        $this->view->vars['content'] .= $view->output();
        return false;
    }

    /**
     * @param mixed $id
     * @param bool $isAdmin
     */
    private function renderAnswers($id, $isAdmin, PHPBatchView &$view): string
    {
        $total_posts = $this->model->getTotalPostsCount($id);
        $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
        if ($page == 'max') {
            $page = floor(($total_posts - 1) / 10) + 1;
        }
        $page = (int)filter_var($page, FILTER_SANITIZE_NUMBER_INT);
        if ($page > $page = floor(($total_posts - 1) / 10) + 1) {
            $page = $page = floor(($total_posts - 1) / 10) + 1;
        } elseif ($page < 1) {
            $page = 1;
        }
        $prefix = ['tid' => $id];
        $p = new PageNavigator($page, $total_posts, 10, $prefix, $this->forumRoute());
        $view->vars['nav'] = $p->get();
        $posts = $this->model->getPosts($id, $page);
        $HTML = '';
        while ($row = $posts->fetch_assoc()) {
            $HTML .= '<tr>';
            $HTML .= '<td class="pinfo">';
            $data = $this->model->getPlayerRow($row['uid'], "aid, name, race, total_pop, total_villages");
            $alliance = $this->model->getAllianceNameAndTag($data['aid']);
            $HTML .= '<a class="name" href="' . profileUrl((int) $row['uid']) . '">' . $data['name'] . '</a>';
            $HTML .= '<br /><img class="avatarHead" title="' . htmlspecialchars((string) $data['name'], ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars((string) $data['name'], ENT_QUOTES, 'UTF-8') . '" src="' . \Game\Hero\HeroImage::headUrl((int) $row['uid']) . '" border="0" />';
            $HTML .= '<br />' . ($data['aid'] > 0 ? '<a href="/alliance/' . (int) $data['aid'] . '">' : '') . $alliance['name'] . ($data['aid'] > 0 ? '</a>' : '') . '<br />';
            $HTML .= T("Alliance", "Posts:") . ' ' . $this->model->getPlayerPostsCount(
                $this->selectedAllianceID,
                $row['uid']
            ) . '<br />';
            $HTML .= '<br />';
            $HTML .= T("Alliance", "pop") . ': ' . $data['total_pop'] . '<br />';
            $HTML .= T("Alliance", "Villages") . ': ' . $data['total_villages'] . '<br />';
            $HTML .= T("Global", "races." . $data['race']);
            $HTML .= '</td>';
            $HTML .= '<td class="pcontent">';
            $HTML .= '<div class="posted">' . T("Alliance", "created") . ': ' . TimezoneHelper::autoDate(
                $row['time'],
                true
            ) . '</div>';
            $HTML .= '<div class="admin">';
            $options = [];
            if ($isAdmin) {
                $options[] = '<a class="edit" href="' . $this->forumRoute() . '?pid=' . $row['id'] . '&amp;ac=editpost&amp;seite=1"><img src="img/spacer.gif" title="' . T(
                    "Alliance",
                    "edit"
                ) . '" alt="' . T("Alliance", "edit") . '" /></a>';
                if ($total_posts > 1) {
                    $options[] = '<a class="fdel" href="' . $this->forumRoute() . '?tid=' . $id . '&amp;pid=' . $row['id'] . '&amp;delpost&amp;admin' . $this->csrfQuery() . '" onclick="return confirm(\'' . T(
                        "Alliance",
                        "Confirm deletion?"
                    ) . '\');"><img src="img/spacer.gif" title="' . T(
                        "Alliance",
                        "Delete"
                    ) . '" alt="' . T("Alliance", "Delete") . '"></a>';
                }
            } elseif ($this->session->getPlayerId() == $row['uid']) {
                $options[] = '<a class="edit" href="' . $this->forumRoute() . '?pid=' . $row['id'] . '&amp;ac=editpost&amp;seite=1"><img src="img/spacer.gif" title="' . T(
                    "Alliance",
                    "edit"
                ) . '" alt="' . T("Alliance", "edit") . '" /></a>';
            }
            $HTML .= implode(" ", $options);
            $HTML .= '</div>';
            $HTML .= '<br /><div class="clear dotted"></div><div class="text">' . BBCode::translateMessagesBBCode($row['post']) . '</div>';
            $edits = $this->model->getPostEdits($row['id']);
            if ($edits['count']) {
                $HTML .= '<div class="edited">' . sprintf(
                    T("Alliance", "x times edited, last edit by y"),
                    $edits['count'],
                    $this->model->getPlayerName($edits['uid']),
                    TimezoneHelper::autoDate($edits['time'], true)
                );
                $HTML .= '</div>';
            }
            $HTML .= '</td></tr>';
        }
        return $HTML;
    }

    /**
     * @param mixed $topicId
     * @param bool $show
     */
    private function renderOptions($topicId, $show = false): string
    {
        $options = $this->model->getOptions($topicId);
        $total_votes = 0;
        if ($show) {
            $total_votes = $this->model->getTotalVotes($topicId);
        }
        $HTML = '';
        while ($row = $options->fetch_assoc()) {
            $HTML .= '<tr>';
            $HTML .= '<td class="sel">' . $row['option_desc'] . '</td>';
            $HTML .= '<td class="stat"';
            if (!$show) {
                $HTML .= '>';
                $HTML .= '<input class="radio" type="radio" name="vote_option" value="' . $row['id'] . '" />';
                $HTML .= '<td class="count">?</td>';
            } else {
                $votes = $this->model->getTopicVotesCount($topicId, $row['id']);
                $percent = $total_votes == 0 ? 0 : round($votes / $total_votes * 100, 1);
                $HTML .= ' title="' . $percent . '%">';
                $HTML .= '<div class="bar-bg"><div style="width:' . $percent . '%;" class="bar"></div><div class="clear"></div></div>';
                $HTML .= '<td class="count">' . $votes . '</td>';
            }
            $HTML .= '</tr>';
        }
        return $HTML;
    }

    /**
     * @param bool $isAdmin
     */
    private function showForumRoot($isAdmin): void
    {
        $m = new ForumModel();
        if ($m->AllianceHasAnyForums($this->selectedAllianceID, $this->session->getAllianceId())) {
            //show forums.
            $public_form = $m->getPublicForum($this->selectedAllianceID);
            if ($public_form->num_rows) {
                $this->view->vars['content'] .= '<h4 class="round">' . T("Alliance", "public_forum") . '</h4>';
                $view = new PHPBatchView("alliance/rootForums");
                $view->vars['class'] = 'public';
                $view->vars['tbody'] = '';
                while ($row = $public_form->fetch_assoc()) {
                    $view->vars['tbody'] .= '<tr>';
                    $view->vars['tbody'] .= $this->renderForumAdminActions($row, $isAdmin);
                    $view->vars['tbody'] .= '<td class="tit"><a href="' . $this->forumRoute() . '?fid=' . $row['id'] . '" title="' . htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8') . '</a><br />' . $row['forum_desc'] . '</td>';
                    $view->vars['tbody'] .= '<td class="cou">' . $m->getForumTopicsCount($row['id']) . '</td>';
                    $view->vars['tbody'] .= '<td class="last">' . $this->renderForumLastPost($row['id']) . '</td>';
                    $view->vars['tbody'] .= '</tr>';
                }
                $this->view->vars['content'] .= $view->output();
            }
            $conf_forum = $m->getConfederaciesForums(
                $this->selectedAllianceID,
                $this->session->getAllianceId()
            );
            if (sizeof($conf_forum)) {
                $this->view->vars['content'] .= '<h4 class="round">' . T("Alliance", "conf_forum") . '</h4>';
                $view = new PHPBatchView("alliance/rootForums");
                $view->vars['class'] = 'confederation';
                $view->vars['tbody'] = '';
                foreach ($conf_forum as $row) {
                    $view->vars['tbody'] .= '<tr>';
                    $view->vars['tbody'] .= $this->renderForumAdminActions($row, $isAdmin);
                    $view->vars['tbody'] .= '<td class="tit"><a href="' . $this->forumRoute() . '?fid=' . $row['id'] . '" title="' . htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8') . '</a><br />' . $row['forum_desc'] . '</td>';
                    $view->vars['tbody'] .= '<td class="cou">' . $m->getForumTopicsCount($row['id']) . '</td>';
                    $view->vars['tbody'] .= '<td class="last">' . $this->renderForumLastPost($row['id']) . '</td>';
                    $view->vars['tbody'] .= '</tr>';
                }
                $this->view->vars['content'] .= $view->output();
            }
            if ($this->session->getAllianceId() == $this->selectedAllianceID) {
                $alliance_forum = $m->getAllianceForum($this->selectedAllianceID);
                if ($alliance_forum->num_rows) {
                    $this->view->vars['content'] .= '<h4 class="round">' . T("Alliance", "alliance_forum") . '</h4>';
                    $view = new PHPBatchView("alliance/rootForums");
                    $view->vars['class'] = 'alliance';
                    $view->vars['tbody'] = '';
                    while ($row = $alliance_forum->fetch_assoc()) {
                        $view->vars['tbody'] .= '<tr>';
                        $view->vars['tbody'] .= $this->renderForumAdminActions($row, $isAdmin);
                        $view->vars['tbody'] .= '<td class="tit"><a href="' . $this->forumRoute() . '?fid=' . $row['id'] . '" title="' . htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8') . '</a><br />' . $row['forum_desc'] . '</td>';
                        $view->vars['tbody'] .= '<td class="cou">' . $m->getForumTopicsCount($row['id']) . '</td>';
                        $view->vars['tbody'] .= '<td class="last">' . $this->renderForumLastPost($row['id']) . '</td>';
                        $view->vars['tbody'] .= '</tr>';
                    }
                    $this->view->vars['content'] .= $view->output();
                }
                $closed_forum = $m->getClosedForum($this->selectedAllianceID);
                if ($closed_forum->num_rows) {
                    $this->view->vars['content'] .= '<h4 class="round">' . T("Alliance", "closed_forum") . '</h4>';
                    $view = new PHPBatchView("alliance/rootForums");
                    $view->vars['class'] = 'closed';
                    $view->vars['tbody'] = '';
                    while ($row = $closed_forum->fetch_assoc()) {
                        $view->vars['tbody'] .= '<tr>';
                        $view->vars['tbody'] .= $this->renderForumAdminActions($row, $isAdmin);
                        $view->vars['tbody'] .= '<td class="tit"><a href="' . $this->forumRoute() . '?fid=' . $row['id'] . '" title="' . htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8') . '</a><br />' . $row['forum_desc'] . '</td>';
                        $view->vars['tbody'] .= '<td class="cou">' . $m->getForumTopicsCount($row['id']) . '</td>';
                        $view->vars['tbody'] .= '<td class="last">' . $this->renderForumLastPost($row['id']) . '</td>';
                        $view->vars['tbody'] .= '</tr>';
                    }
                    $this->view->vars['content'] .= $view->output();
                }
                if ($this->selectedAllianceID == $this->session->getAllianceId() && $this->session->hasAlliancePermission(AllianceModel::MANAGE_FORUM)) {
                    $this->view->vars['content'] .= '<div class="spacer"></div>' . $this->getNewForumButton();
                    $this->view->vars['content'] .= $this->getAdminSwitchButton($isAdmin);
                }
            }
        } else {
            $this->view->vars['content'] .= '<p class="error">' . T("Alliance", "noForum") . '</p>';
            if ($this->selectedAllianceID == $this->session->getAllianceId() && $this->session->hasAlliancePermission(AllianceModel::MANAGE_FORUM)) {
                $this->view->vars['content'] .= $this->getNewForumButton();
            }
            $this->view->vars['content'] .= '<div class="clear"></div>';
        }
    }

    private function getNewForumButton(): string
    {
        return '<div class="buttonBox">' . getButton(
            [
                "type"    => "button",
                "value"   => T("Alliance", "NewForum"),
                "class"   => "textButtonV1 green ",
                "version" => "textButtonV1",
                'onclick' => "this.disabled = true; window.location.href = '" . $this->forumRoute() . "?admin&newforum'; return false;",
            ],
            ["data" => ["type" => "button", "class" => "textButtonV1 green ", "version" => "textButtonV1",],],
            T("Alliance", "NewForum")
        ) . '</div>';
    }

    /**
     * @param mixed $fid
     */
    private function getNewTopicButton($fid): string
    {
        return '<div class="buttonBox">' . getButton(
            [
                "type"    => "button",
                "class"   => "textButtonV1 green ",
                "version" => "textButtonV1",
                'onclick' => "window.location.href = '" . $this->forumRoute() . "?fid=$fid&ac=newtopic'",
            ],
            ["data" => ["type" => "button", "class" => "textButtonV1 green ", "version" => "textButtonV1",],],
            T("Alliance", "New Thread")
        ) . '</div>';
    }

    /**
     * @param mixed $isAdmin
     * @param mixed $fid
     * @param mixed $tid
     */
    private function getAdminSwitchButton($isAdmin, $fid = 0, $tid = 0): string
    {
        return '<div class="buttonBox"><a href="' . $this->forumRoute() . '?admin&switch_admin' . $this->csrfQuery() . ($fid > 0 ? "&fid=" . $fid : '') . ($tid > 0 ? "&tid=" . $tid : '') . '"><img class="switch_admin dynamic_img " src="img/spacer.gif" alt="' . T(
            "Alliance",
            $isAdmin ? "switch non admin" : "switch admin"
        ) . '"></a></div>';
    }

    private function newForum(): bool
    {
        $view = new PHPBatchView("alliance/newForum");
        $view->vars['forumRoute'] = $this->forumRoute();
        if (WebService::isPost()) {
            //new forum post!
            $forumName = $this->getPostPlainText('u1');
            if ($forumName !== '') {
                $bidValue = $this->getPostInt('bid');
                $bid = $bidValue === 1 ? 0 : ($bidValue === 2 ? 1 : ($bidValue === 0 ? 2 : 3));
                $this->model->addForum(
                    $this->session->getAllianceId(),
                    $forumName,
                    $this->getPostPlainText('u2'),
                    $bid,
                    $this->getPostInt('for_sitters') === 1 ? 1 : 0,
                    isset($_POST['allys_by_id']) ? $_POST['allys_by_id'] : null,
                    isset($_POST['allys_by_name']) ? $_POST['allys_by_name'] : null,
                    isset($_POST['users_by_id']) ? $_POST['users_by_id'] : null,
                    isset($_POST['users_by_name']) ? $_POST['users_by_name'] : null
                );
                $this->redirect($this->forumRoute());
            }
        }
        $this->view->vars['content'] .= $view->output();
        return false;
    }

    private function editForum(): bool
    {
        $forum = $this->model->getForum(
            $this->selectedAllianceID,
            $this->session->getAllianceId(),
            $this->getRequestInt('fid')
        );
        if ($forum === false) {
            return true;
        }
        if ($forum['aid'] <> $this->session->getAllianceId()) {
            return true;
        }
        if (WebService::isPost()) {
            $forumName = $this->getPostPlainText('u1');
            if ($forumName !== '') {
                $this->model->editForum(
                    $forum['id'],
                    $forumName,
                    $this->getPostPlainText('u2'),
                    $this->getPostInt('for_sitters') === 1 ? 1 : 0,
                    isset($_POST['bnds']) ? $_POST['bnds'] : null,
                    isset($_POST['allys_by_id']) ? $_POST['allys_by_id'] : null,
                    isset($_POST['allys_by_name']) ? $_POST['allys_by_name'] : null,
                    isset($_POST['users']) ? $_POST['users'] : null,
                    isset($_POST['users_by_id']) ? $_POST['users_by_id'] : null,
                    isset($_POST['users_by_name']) ? $_POST['users_by_name'] : null
                );
                $this->redirect($this->forumRoute());
            }
        }
        $view = new PHPBatchView("alliance/forum_edit");
        $view->vars['forumRoute'] = $this->forumRoute();
        $view->vars['name'] = $forum['name'];
        $view->vars['desc'] = $forum['forum_desc'];
        $view->vars['for_sitters'] = $forum['sitter'];
        $view->vars['fid'] = $forum['id'];
        $view->vars['showMoreAlliances'] = $forum['area'] == 1;
        $view->vars['showMorePlayers'] = $forum['area'] == 3;
        if ($view->vars['showMoreAlliances']) {
            $allowed = $this->model->getAllowedAlliancesForForum($forum['id']);
            $view->vars['hasAlliances'] = $allowed->num_rows;
            if ($view->vars['hasAlliances']) {
                $view->vars['alliances'] = '';
                while ($row = $allowed->fetch_assoc()) {
                    $alliance = $this->model->getAllianceNameAndTag($row['aid']);
                    if ($alliance === false) {
                        $view->vars['hasAlliances']--;
                        continue;
                    }
                    $view->vars['alliances'] .= '<tr>';
                    $view->vars['alliances'] .= '<td class="sel"><input class="check" type="checkbox" name="bnds[]" value="' . (int) $row['aid'] . '" checked /></td>';
                    $view->vars['alliances'] .= '<td class="tag">' . htmlspecialchars($alliance['name'], ENT_QUOTES, 'UTF-8') . '</td>';
                    $view->vars['alliances'] .= '<td class="ally">' . htmlspecialchars($alliance['tag'], ENT_QUOTES, 'UTF-8') . '</td>';
                    $view->vars['alliances'] .= '</tr>';
                }
            }
        } elseif ($view->vars['showMorePlayers']) {
            $allowed = $this->model->getAllowedPlayersForForum($forum['id']);
            $view->vars['hasPlayers'] = $allowed->num_rows;
            if ($view->vars['hasPlayers']) {
                $view->vars['players'] = '';
                $i = 0;
                while ($row = $allowed->fetch_assoc()) {
                    $player = $this->model->getPlayerName($row['uid'], true);
                    if ($player === null) {
                        $view->vars['hasPlayers']--;
                        continue;
                    }
                    $view->vars['players'] .= '<tr>';
                    $view->vars['players'] .= '<td class="sel"><input class="check" type="checkbox" name="users[' . $i . ']" value="' . (int) $row['uid'] . '" checked /></td>';
                    $view->vars['players'] .= '<td class="pla">' . htmlspecialchars($player, ENT_QUOTES, 'UTF-8') . '</td>';
                    $view->vars['players'] .= '</tr>';
                    ++$i;
                }
            }
        }
        $view->vars['bid'] = $forum['area'] == 0 ? 1 : ($forum['area'] == 1 ? 2 : ($forum['area'] == 2 ? 0 : 3));
        $this->view->vars['content'] .= $view->output();
        return false;
    }

    /**
     * @param mixed $isAdmin
     */
    private function showForum($isAdmin): bool
    {
        $m = new ForumModel();
        $forum = $m->getForum(
            $this->selectedAllianceID,
            $this->session->getAllianceId(),
            (int)$_REQUEST['fid']
        );
        if ($forum === false) {
            return true;
        }
        if ($forum['aid'] <> $this->session->getAllianceId() && in_array($forum['area'], [2, 3,])) {
            return true;
        }
        $view = new PHPBatchView("alliance/forum_topic");
        $view->vars['aid'] = $this->selectedAllianceID;
        $view->vars['forumRoute'] = $this->forumRoute();
        $view->vars['fid'] = $forum['id'];
        $view->vars['forumName'] = $forum['name'];
        $view->vars['areaName'] = T(
            "Alliance",
            ['public_forum', 'conf_forum', 'alliance_forum', 'closed_forum',][$forum['area']]
        );
        $view->vars['content'] = '';
        $topics = $m->getForumTopics($forum['id']);
        while ($row = $topics->fetch_assoc()) {
            $view->vars['content'] .= '<tr>';
            $view->vars['content'] .= $this->renderTopicAdminActions($forum, $row, $isAdmin);
            $view->vars['content'] .= '<td class="tit"><a href="' . $this->forumRoute() . '?tid=' . $row['id'] . '">' . $row['thread'] . '</a><br /></td>';
            $view->vars['content'] .= '<td class="cou">' . $m->getTopicAnswersCount($row['id']) . '</td>';
            $view->vars['content'] .= '<td class="last">';
            $view->vars['content'] .= $this->renderTopicLastPost($row['id']);
            $view->vars['content'] .= '</td>';
            $view->vars['content'] .= '</tr>';
        }
        if (!$topics->num_rows) {
            $view->vars['content'] .= '<tr><td colspan="4" class="noData">' . T(
                "Alliance",
                "no thread created"
            ) . '</td></tr>';
        }
        $this->view->vars['content'] .= $view->output();
        if ($this->session->hasAlliancePermission(AllianceModel::MANAGE_FORUM) && $forum['aid'] == $this->session->getAllianceId()) {
            $this->view->vars['content'] .= '<div class="spacer"></div>' . $this->getNewTopicButton($forum['id']);
            $this->view->vars['content'] .= $this->getAdminSwitchButton($isAdmin, $forum['id']);
        }
        return false;
    }

    /**
     * @param float|int|string|null $topicId
     * @return string|null
     */
    private function renderTopicLastPost($topicId)
    {
        $m = new ForumModel();
        $last = $m->getTopicLastPost($topicId);
        if ($last === false) {
            return null;
        }
        $HTML = TimezoneHelper::autoDate($last['time'], true) . '<br />';
        $HTML .= ' <a href="' . profileUrl((int) $last['uid']) . '">' . $m->getPlayerName($last['uid']) . '</a>';
        $HTML .= '<a href="' . $this->forumRoute() . '?tid=' . $last['topicId'] . '&amp;page=max"><img class="latest_reply" src="img/spacer.gif" alt="' . T(
            "Alliance",
            "show last post"
        ) . '" title="' . T("Alliance", "show last post") . '" /></a>';
        return $HTML;
    }

    /**
     * @param mixed $forumId
     * @return string|null
     */
    private function renderForumLastPost($forumId)
    {
        $m = new ForumModel();
        $last = $m->getForumLastPost($forumId);
        if ($last === false) {
            return null;
        }
        $HTML = TimezoneHelper::autoDate($last['time'], true) . '<br />';
        $HTML .= ' <a href="' . profileUrl((int) $last['uid']) . '">' . $m->getPlayerName($last['uid']) . '</a>';
        $HTML .= '<a href="' . $this->forumRoute() . '?tid=' . $last['topicId'] . '&amp;page=max"><img class="latest_reply" src="img/spacer.gif" alt="' . T(
            "Alliance",
            "show last post"
        ) . '" title="' . T("Alliance", "show last post") . '" /></a>';
        return $HTML;
    }

    /**
     * @param array<string, mixed> $forum
     * @param array<string, float|int|string|null> $row
     * @param mixed $isAdmin
     */
    private function renderTopicAdminActions($forum, $row, $isAdmin): string
    {
        $options = '<td class="ico">';
        if (!$isAdmin || $forum['aid'] != $this->session->getAllianceId()) {
            if ($row['close']) {
                $row['close'] = $forum['area'] == 1 ? !$this->model->isTopicOpenedForUser(
                    $forum['id'],
                    $this->session->getPlayerId()
                ) : $row['close'];
            }
            $entries = $this->model->hasForumNewEntries($row['id']);
            $folder = [];
            $folder[] = 'folder';
            if ($entries) {
                $folder[] = 'new';
            }
            if ($row['stick']) {
                $folder[] = 'sticky';
            }
            if ($row['close']) {
                $folder[] = 'lock';
            }
            $options .= '<img class="' . implode("_", $folder) . '" src="img/spacer.gif">';
        } else {
            if ($row['close']) {
                $options .= '<a class="unlock" href="' . $this->forumRoute() . '?tid=' . $row['id'] . '&amp;admin&amp;unlock' . $this->csrfQuery() . '"><img src="img/spacer.gif" alt="' . T(
                    "Alliance",
                    "open_topic"
                ) . '" title="' . T("Alliance", "open_topic") . '"></a>';
            } else {
                $options .= '<a class="lock" href="' . $this->forumRoute() . '?tid=' . $row['id'] . '&amp;admin&amp;lock' . $this->csrfQuery() . '"><img src="img/spacer.gif" alt="' . T(
                    "Alliance",
                    "close_topic"
                ) . '" title="' . T("Alliance", "close_topic") . '"></a>';
            }
            $options .= '<a class="edit" href="' . $this->forumRoute() . '?tid=' . $row['id'] . '&amp;admin&amp;edittopic" title="' . T(
                "Alliance",
                "edit"
            ) . '"><img src="img/spacer.gif" alt="' . T("Alliance", "edit") . '" /></a><br />';
            if ($row['stick']) {
                $options .= '<a class="unpin" href="' . $this->forumRoute() . '?tid=' . $row['id'] . '&amp;admin&amp;unpin' . $this->csrfQuery() . '"><img src="img/spacer.gif" alt="' . T(
                    "Alliance",
                    "open_topic"
                ) . '" title="' . T("Alliance", "unstick_topic") . '"></a>';
            } else {
                $options .= '<a class="pin" href="' . $this->forumRoute() . '?tid=' . $row['id'] . '&amp;admin&amp;pin' . $this->csrfQuery() . '"><img src="img/spacer.gif" alt="' . T(
                    "Alliance",
                    "close_topic"
                ) . '" title="' . T("Alliance", "stick_topic") . '"></a>';
            }
            $options .= '<a class="fdel" href="' . $this->forumRoute() . '?tid=' . $row['id'] . '&amp;admin&amp;deltopic' . $this->csrfQuery() . '"><img src="img/spacer.gif" alt="' . T(
                "Alliance",
                "Delete"
            ) . '" onclick="return confirm(\'' . T("Alliance", "Confirm deletion?") . '\');"></a>';
        }
        $options .= '</td>';
        return $options;
    }

    /**
     * @param array<string, float|int|string|null>|array<string, mixed> $row
     * @param mixed $isAdmin
     */
    private function renderForumAdminActions($row, $isAdmin): string
    {
        $options = '<td class="ico">';
        if (!$isAdmin || $row['aid'] != $this->session->getAllianceId()) {
            $m = new ForumModel();
            $entries = $m->hasForumNewEntries($row['id']);
            if (!$entries) {
                $options .= '<img class="folder" src="img/spacer.gif" alt="' . T(
                    "Alliance",
                    "thread without new entries"
                ) . '" title="' . T(
                    "Alliance",
                    "thread without new entries"
                ) . '">';
            } else {
                $options .= '<img class="folder_new" src="img/spacer.gif" alt="' . T(
                    "Alliance",
                    "thread with new entries"
                ) . '" title="' . T("Alliance", "thread with new entries") . '">';
            }
        } else {
            $bid = $row['area'] == 0 ? 1 : ($row['area'] == 1 ? 2 : ($row['area'] == 2 ? 0 : 3));
            if ($row['area'] == 1) {
                //Conf forums act different
                $options .= '<a class="edit" href="' . $this->forumRoute() . '?fid=' . $row['id'] . '&amp;admin&amp;editforum" title="' . T(
                    "Alliance",
                    "edit"
                ) . '"><img src="img/spacer.gif" alt="' . T("Alliance", "edit") . '" /></a>';
                $options .= '<br /><a class="fdel" href="' . $this->forumRoute() . '?fid=' . $row['id'] . '&amp;admin&amp;delforum' . $this->csrfQuery() . '" onclick="return confirm(\'' . T(
                    "Alliance",
                    "Confirm deletion?"
                ) . '\');" title="' . T(
                    "Alliance",
                    "Delete"
                ) . '"><img src="img/spacer.gif" alt="' . T("Alliance", "Delete") . '" /></a>';
            } else {
                $options .= '<a class="up_arr" href="' . $this->forumRoute() . '?fid=' . $row['id'] . '&amp;bid=' . $bid . '&amp;admin&amp;pos=-1' . $this->csrfQuery() . '" title="' . T(
                    "Alliance",
                    "to the top"
                ) . '"><img src="img/spacer.gif" alt="' . T("Alliance", "to the top") . '" /></a>';
                $options .= '<a class="edit" href="' . $this->forumRoute() . '?fid=' . $row['id'] . '&amp;admin&amp;editforum" title="' . T(
                    "Alliance",
                    "edit"
                ) . '"><img src="img/spacer.gif" alt="' . T("Alliance", "edit") . '" /></a>';
                $options .= '<br /><a class="down_arr" href="' . $this->forumRoute() . '?fid=' . $row['id'] . '&amp;bid=' . $bid . '&amp;admin&amp;pos=1' . $this->csrfQuery() . '" title="' . T(
                    "Alliance",
                    "to the bottom"
                ) . '"><img src="img/spacer.gif" alt="' . T("Alliance", "to the bottom") . '" /></a>';
                $options .= '<a class="fdel" href="' . $this->forumRoute() . '?fid=' . $row['id'] . '&amp;admin&amp;delforum' . $this->csrfQuery() . '" onclick="return confirm(\'' . T(
                    "Alliance",
                    "Confirm deletion?"
                ) . '\');" title="' . T(
                    "Alliance",
                    "Delete"
                ) . '"><img src="img/spacer.gif" alt="' . T("Alliance", "Delete") . '" /></a>';
            }
        }
        $options .= '</td>';
        return $options;
    }
}
