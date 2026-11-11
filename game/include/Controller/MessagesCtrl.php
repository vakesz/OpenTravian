<?php

namespace Controller;

use Core\Config;
use Core\Database\DB;
use Core\Helper\BBCode;
use Core\Helper\Notification;
use Core\Helper\PageNavigator;
use Core\Helper\StringChecker;
use Core\Helper\TimezoneHelper;
use Core\Helper\WebService;
use Core\Session;
use Model\AllianceModel;
use Model\InfoBoxModel;
use Model\MessageModel;
use Model\Quest;
use resources\View\GameView;
use resources\View\PHPBatchView;
use Core\Caching\Caching;

use function getDisplay;

use const PHP_EOL;

class MessagesCtrl extends GameCtrl
{
    /**
     * @param mixed $autoType
     * @param mixed $subject
     * @return mixed
     */
    private function getSubject($autoType, $subject)
    {
        if ($autoType) {
            switch ($autoType) {
                case 1:
                case 2:
                    $subject = T(
                        'Messages',
                        $autoType == 1 ? 'Alliance Invitation received' : 'Alliance Invitation revoked'
                    );
                    break;
                case 3:
                    return T('inGame', 'QUEST_MESSAGE_SUBJECT');
                case 4:
                    return '<span style="color: darkgoldenrod">' . T('Global', 'FreeGoldTitle') . '</span>';
                case 5:
                    $translation = [
                        'topAttacker' => T('Statistics', 'top10.attackers of the week'),
                        'topDefender' => T('Statistics', 'top10.defenders of the week'),
                        'topClimber' => T('Statistics', 'top10.climbers of the week'),
                        'topRaider' => T('Statistics', 'top10.robbers of the week'),
                    ];
                    return '<span style="color: darkgoldenrod">' . $translation[$subject] . '</span>';
                case MessageModel::AUTO_TYPE_WELCOME:
                    return T('Messages', 'Useful tips & information');
            }
        }

        return $subject;
    }

    /**
     * @param mixed $autoType
     * @param mixed $subject
     * @param mixed $message
     * @return mixed
     */
    private function getMessageText($autoType, $subject, $message)
    {
        if ($autoType) {
            switch ($autoType) {
                case 1:
                case 2:
                    $data = unserialize($message, ['allowed_classes' => false]);
                    return BBCode::translateMessagesBBCode(
                        vsprintf(
                            T(
                                'Messages',
                                $autoType == 1 ? 'AllianceInvitationReceiveText' : 'AllianceInvitationRevokeText'
                            ),
                            $data
                        )
                    );
                case 3:
                    return sprintf(T('inGame', 'QUEST_MESSAGE'), Session::getInstance()->getName());
                case 4:
                case 5:
                    return sprintf(nl2br(T('Global', 'FreeGold')), $message);
                case MessageModel::AUTO_TYPE_WELCOME:
                    return sprintf(T('Messages', 'WelcomeText'), htmlspecialchars((string) $message, ENT_QUOTES));
            }
        }
        return nl2br(BBCode::translateMessagesBBCode($message));
    }

    public function __construct()
    {
        parent::__construct();
        $this->view = new GameView();
        $this->view->vars['titleInHeader'] = T('Messages', 'Messages');
        // body.messages (meta page); village perspective falls back to buildings via Layout.
        $this->view->vars['bodyCssClass'] = 'messages';
        $this->view->vars['showHeaderBar'] = true;
        $this->view->vars['showCloseButton'] = true;
        $selectedTab =
            isset($_REQUEST['t']) && $_REQUEST['t'] <= 5 && $_REQUEST['t'] >= 0
                ? (int) $_REQUEST['t']
                : (Session::getInstance()->getStoredFavoriteTab('messages') ?? 0);
        $openMessageId = isset($_REQUEST['id']) && is_scalar($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
        $markUnreadPosted =
            (isset($_POST['toggleState']) && isset($_POST['id'])) ||
            ((string) ($_POST['mark'] ?? '') === 'unread' && isset($_POST['id']));
        if ($markUnreadPosted) {
            $hasPermission = Session::getInstance()->checkSitterPermission(
                Session::SITTER_CAN_REMOVE_ARCHIVE_MESSAGES_OR_REPORTS
            );
            if ($hasPermission) {
                $m = new MessageModel();
                $playerId = Session::getInstance()->getPlayerId();
                foreach ($this->postedMessageIds() as $messageId) {
                    $msg = $m->getMessage($messageId, $playerId);
                    if (is_array($msg)) {
                        $m->setMessageAsUnViewed($messageId, $playerId);
                    }
                }
                $selectedTab = 0;
            }
        }
        $contentTabToken = match ($selectedTab) {
            1 => 'messagesWrite',
            2 => 'messagesSent',
            3 => 'messagesArchive',
            4 => 'messagesNotes',
            5 => 'messagesIgnore',
            default => 'messagesInbox',
        };
        $this->view->vars['contentCssClass'] = 'messages ' . $contentTabToken;
        $view = new PHPBatchView('messages/menu');
        $view->vars['favorText'] = sprintf(
            T('Global', 'Select tab %s as favourite'),
            T('Messages', ['Inbox', 'Write', 'Sent', 'Archive', 'Notes', 'Ignored players'][$selectedTab])
        );
        //$view->vars['Inbox'] = get_button_id();
        //$view->vars['Write'] = get_button_id();
        //$view->vars['Sent'] = get_button_id();
        $view->vars['selectedTab'] = $selectedTab;
        $view->vars['favorTabId'] = Session::getInstance()->getStoredFavoriteTab('messages');
        //$view->vars['Archive'] = get_button_id();
        //$view->vars['Notes'] = get_button_id();
        $view->vars['goldClub'] = Session::getInstance()->hasGoldClub();
        $view->vars['ArchiveGoldClubTitle'] = T('Reports', 'Archive function for reports and messages||For this feature you need the Gold club activated');
        //$view->vars['ignoredPlayers'] = get_button_id();
        $this->view->vars['content'] .= $view->output();
        if (
            !isset($_REQUEST['toggleState']) &&
            !isset($_REQUEST['mark']) &&
            ($selectedTab == 0 || $selectedTab == 2 || $selectedTab == 3) &&
            $openMessageId > 0 &&
            Session::getInstance()->checkSitterPermission(Session::SITTER_CAN_SEND_OR_ANSWER_MESSAGES)
        ) {
            $m = new MessageModel();
            $message = $m->getMessage($openMessageId, Session::getInstance()->getPlayerId());
            if ($message !== false) {
                $view = new PHPBatchView('messages/showMsg');
                $view->vars['answerActive'] = true;
                if ($message['autoType']) {
                    $view->vars['answerActive'] = false;
                }
                if ($message['reported']) {
                    $view->vars['reportedText'] = sprintf(
                        T('Messages', 'Current message has already been reported as: x'),
                        [
                            T('Messages', 'Advertisement'),
                            T('Messages', 'harassment'),
                            T('Messages', 'gold'),
                            T('Messages', 'Other'),
                        ][$message['reported'] - 1]
                    );
                }
                $view->vars['id'] = $message['id'];
                $view->vars['t'] = $selectedTab;
                $view->vars['time'] = TimezoneHelper::reportDateString((int) $message['time']);
                $view->vars['subject'] = $this->getSubject($message['autoType'], $message['topic']);
                $view->vars['send'] = $selectedTab == 2 || $message['to_uid'] != Session::getInstance()->getPlayerId();
                $view->vars['uid'] = $view->vars['send'] ? $message['to_uid'] : $message['uid'];
                $view->vars['isAdmin'] = Session::getInstance()->isAdmin();
                if ($view->vars['isAdmin']) {
                    if ($message['autoType'] && $message['autoType'] <= 2) {
                        $name = T('Messages', 'Ambassador');
                    } else {
                        $name = $m->getPlayerName($message['uid']);
                    }
                    $view->vars['info'] = [
                        'name' => $name,
                        'uid' => $message['uid'],
                        'to_uid' => $message['to_uid'],
                        'to_name' => $m->getPlayerName($message['to_uid']),
                    ];
                }
                BBCode::setFromPlayer($message['uid']);
                if (Session::getInstance()->getPlayerId() == $message['to_uid']) {
                    BBCode::setToPlayer($message['to_uid']);
                }
                $view->vars['message'] = $this->getMessageText(
                    $message['autoType'],
                    $message['topic'],
                    $message['message']
                );
                $view->vars['reportable'] =
                    $view->vars['uid'] != Session::getInstance()->getPlayerId() &&
                    !$message['autoType'] &&
                    $view->vars['uid'] > 2;
                $view->vars['canReportSpam'] = Session::getInstance()->checkSitterPermission(
                    Session::SITTER_CAN_REMOVE_ARCHIVE_MESSAGES_OR_REPORTS
                );
                if ($message['autoType'] && $message['autoType'] <= 2) {
                    $view->vars['name'] = T('Messages', 'Ambassador');
                } else {
                    $view->vars['name'] = $m->getPlayerName($view->vars['uid']);
                }
                $view->vars['isAllianceAutoType'] = $message['autoType'] && $message['autoType'] <= 2;
                $view->vars['reported'] = $message['reported'];
                $view->vars['goldClub'] = Session::getInstance()->hasGoldClub();
                if (!$view->vars['isAllianceAutoType']) {
                    if ($view->vars['uid'] == 0) {
                        $view->vars['name'] = T('Messages', 'TG Support');
                    } elseif ($view->vars['uid'] == 2) {
                        $view->vars['name'] = '<u>' . $view->vars['name'] . '</u>';
                    }
                }
                if (
                    !$message['viewed'] &&
                    $message['to_uid'] == Session::getInstance()->getPlayerId() &&
                    !Session::getInstance()->isAdminInAnotherAccount()
                ) {
                    if ($message['autoType'] == 3) {
                        Quest::getInstance()->setQuestBitwise('world', 6, 1);
                    }
                    $m->setMessageAsViewed($message['id'], $message['to_uid']);
                }
                $this->view->vars['content'] .= $view->output();
            }
        } elseif ($selectedTab == 0) {
            $this->Inbox();
        } elseif ($selectedTab == 1) {
            $this->write();
        } elseif ($selectedTab == 2) {
            $this->sendBox();
        } elseif ($selectedTab == 3) {
            $this->Archive();
        } elseif ($selectedTab == 4) {
            $view = new PHPBatchView('messages/note');
            $view->vars['note'] = Session::getInstance()->getNote();
            $view->vars['info'] = '';
            if (WebService::isPost() && isset($_POST['notepad']) && Session::validateChecker()) {
                $note = is_string($_POST['notepad']) ? strip_tags($_POST['notepad']) : '';
                $m = new MessageModel();
                if ($m->setNote(Session::getInstance()->getPlayerId(), $note)) {
                    $view->vars['note'] = $note;
                    $view->vars['info'] = T('Messages', '- saved -');
                }
            }
            $this->view->vars['content'] .= $view->output();
        } elseif ($selectedTab == 5) {
            $view = new PHPBatchView('messages/ignoreList');
            $this->view->vars['content'] .= $view->output();
        }
        Caching::getInstance()->delete("newMessages{$this->session->getPlayerId()}");
    }

    /**
     * @param mixed $fromPlayerId
     * @param int $toPlayerId
     * @return bool|int
     */
    public function checkMultiUnread($fromPlayerId, $toPlayerId)
    {
        if ($fromPlayerId <= 2 || empty($toPlayerId)) {
            return 0;
        }
        $db = DB::getInstance();
        $limit = 2;
        $count = $db->fetchScalar(
            'SELECT COUNT(id) FROM mdata WHERE viewed=0 AND time >= ' .
                (time() - 2 * 3600) .
                " AND to_uid=$toPlayerId AND uid=$fromPlayerId AND isAlliance=0"
        );
        if ($toPlayerId == 2 || $toPlayerId == 0) {
            $limit = 3;
        }
        return $count >= $limit;
    }

    /**
     * @param mixed $fromPlayerId
     */
    public function checkForUnreadSpam($fromPlayerId): void
    {
        if ($fromPlayerId <= 2 || empty($fromPlayerId)) {
            return;
        }
        $db = DB::getInstance();
        $count = $db->fetchScalar(
            'SELECT COUNT(id) FROM mdata WHERE viewed=0 AND time >= ' .
                (time() - 2 * 3600) .
                " AND uid=$fromPlayerId AND isAlliance=0"
        );
        if ($count >= mt_rand(6, 12)) {
            $this->redirect('/messages?spamDetected=true');
            $uid = Session::getInstance()->getPlayerId();
            $reason = 'Spam';
            $time = 10 * 60;
            $db = DB::getInstance();
            $exists = 0 < $db->fetchScalar("SELECT COUNT(id) FROM banQueue WHERE uid=$uid");
            if (!$exists) {
                $db->query("UPDATE users SET access=0 WHERE id=$uid");
                if ($db->affectedRows()) {
                    $db->query(
                        "INSERT INTO `banQueue`(`uid`, `reason`, `time`, `end`) VALUES ($uid, '$reason', " .
                            time() .
                            ', ' .
                            ($time == 0 ? $time : $time + time()) .
                            ')'
                    );
                    $db->query(
                        "INSERT INTO `banHistory`(`uid`, `reason`, `time`, `end`) VALUES ($uid, '$reason', " .
                            time() .
                            ', ' .
                            ($time == 0 ? $time : $time + time()) .
                            ')'
                    );
                    $db->query("DELETE FROM multiaccount_users WHERE uid=$uid");
                    (new InfoBoxModel())->addInfo($uid, 0, 14, '', time(), time() + 365 * 86400);
                    Notification::notify('Spam blocked', 'Lots of unread messages for a player!');
                }
            }
        }
    }

    /**
     * Shared helpers for the message lists (inbox, sent, archive).
     */
    private function listOrderAscending(): bool
    {
        return ($_GET['order'] ?? '') === 'ASC';
    }

    private function listPage(): int
    {
        return isset($_REQUEST['page']) && is_scalar($_REQUEST['page']) ? max(1, abs((int) $_REQUEST['page'])) : 1;
    }

    /**
     * Message ids from the detail form or list checkbox (`id[]`) posts.
     *
     * @return list<int>
     */
    private function postedMessageIds(): array
    {
        $ids = [];
        if (isset($_POST['id'])) {
            foreach ((array) $_POST['id'] as $id) {
                if (is_scalar($id) && (int) $id > 0) {
                    $ids[] = (int) $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /** Marks the ids named by `mark=read|unread&id[]=` (status-icon link / detail form). */
    private function applyMarkRequest(MessageModel $m): void
    {
        $mark = (string) ($_REQUEST['mark'] ?? '');
        if ($mark !== 'read' && $mark !== 'unread') {
            return;
        }
        $ids = [];
        if (isset($_REQUEST['id'])) {
            foreach ((array) $_REQUEST['id'] as $id) {
                if (is_scalar($id) && (int) $id > 0) {
                    $ids[] = (int) $id;
                }
            }
        }
        $playerId = Session::getInstance()->getPlayerId();
        foreach ($ids as $messageId) {
            if ($mark === 'read') {
                $m->setMessageAsViewed($messageId, $playerId);
            } else {
                $m->setMessageAsUnViewed($messageId, $playerId);
            }
        }
    }

    /**
     * @param array<mixed> $row
     */
    private function renderListRow(
        MessageModel $m,
        array $row,
        bool $hasPermission,
        string $basePath,
        int $page,
        bool $sent
    ): string {
        $counterpart = (int) ($sent ? $row['to_uid'] : $row['uid']);
        $isSpecial = $counterpart === 0 || $counterpart === 2;
        $topic = $this->getSubject($row['autoType'], $row['topic']);
        $statusLabel = T('Messages', $row['viewed'] == 1 ? 'Read' : 'Unread');
        $statusImg =
            '<img src="/img/spacer.gif" class="messageStatus messageStatus' .
            ($row['viewed'] == 1 ? 'Read' : 'Unread') .
            '" title="' . $statusLabel . '" alt="' . $statusLabel . '">';
        $query = '?page=' . $page;
        $html = '<tr class="' . ($counterpart === 0 ? 'support' : ($counterpart === 2 ? 'multihunter' : '')) . '">';
        $html .= '<td class="sel">';
        if ($hasPermission) {
            $html .= '<input class="check" type="checkbox" name="id[]" value="' . $row['id'] . '">';
        }
        $html .= '</td><td class="subject"><div class="subjectWrapper">';
        if ($hasPermission && !$sent) {
            $html .=
                '<a href="' . $basePath . $query . '&amp;id%5B%5D=' . $row['id'] . '&amp;mark=' .
                ($row['viewed'] == 0 ? 'read' : 'unread') . '">' . $statusImg . '</a>';
        } else {
            $html .= $statusImg;
        }
        $html .= '<a href="' . $basePath . $query . '&amp;id=' . $row['id'] . '">' . $topic . '</a></div></td>';
        if ($row['autoType'] && $row['autoType'] <= 2) {
            $html .=
                '<td class="send"><a href="' . getSupportUrl() . '" target="_blank">' .
                T('Messages', 'Ambassador') . '</a></td>';
        } else {
            $isSupport = $counterpart === 0;
            $html .=
                '<td class="send"><a href="' . ($isSupport ? getSupportUrl() : profileUrl($counterpart)) . '">' .
                ($isSpecial && !$isSupport ? '<u>' : '') . ($isSupport ? T('Messages', 'TG Support') : $m->getPlayerName($counterpart)) .
                ($isSpecial && !$isSupport ? '</u>' : '') . '</a></td>';
        }
        return $html . '<td class="dat">' . TimezoneHelper::reportDateString((int) $row['time']) . '</td></tr>';
    }

    /**
     * @param list<array{id: string, name: string, label: string}> $buttons
     * @param \mysqli_result|bool $rows
     */
    private function renderMessageList(
        MessageModel $m,
        int $tab,
        string $basePath,
        string $dateLabel,
        string $emptyText,
        $rows,
        int $total,
        array $buttons,
        bool $hasPermission,
        int $page
    ): void {
        $ascending = $this->listOrderAscending();
        $content = '';
        $i = 0;
        while ($row = $rows->fetch_assoc()) {
            ++$i;
            $content .= $this->renderListRow($m, $row, $hasPermission, $basePath, $page, $tab === 2);
        }
        if ($i === 0) {
            $content = '<tr><td colspan="4" class="noData">' . $emptyText . '</td></tr>';
        }
        $prefix = $ascending ? ['order' => 'ASC'] : [];
        $navigator = new PageNavigator(
            $page,
            max(1, $total),
            Session::getInstance()->getReportsRecordsPerPage(),
            $prefix,
            $basePath
        );
        $view = new PHPBatchView('messages/list');
        $view->vars['tab'] = $tab;
        $view->vars['basePath'] = $basePath;
        $view->vars['dateLabel'] = $dateLabel;
        $view->vars['recursive'] = $ascending;
        $view->vars['hasPermission'] = $hasPermission;
        $view->vars['buttons'] = $buttons;
        $view->vars['content'] = $content;
        $view->vars['nav'] = $i === 0 ? '' : $navigator->get();
        $this->view->vars['content'] .= $view->output();
    }

    private function Inbox(): void
    {
        $m = new MessageModel();
        $playerId = Session::getInstance()->getPlayerId();
        $hasPermission = Session::getInstance()->checkSitterPermission(
            Session::SITTER_CAN_REMOVE_ARCHIVE_MESSAGES_OR_REPORTS
        );
        $goldClub = Session::getInstance()->hasGoldClub();
        if ($hasPermission) {
            $this->applyMarkRequest($m);
            $postedIds = $this->postedMessageIds();
            if ($postedIds !== [] && !isset($_REQUEST['mark'])) {
                foreach ($postedIds as $msgId) {
                    if (isset($_REQUEST['archive'])) {
                        if ($goldClub) {
                            $m->archiveMessage($msgId, $playerId);
                        }
                    } elseif (isset($_REQUEST['delete'])) {
                        $m->deleteInboxMessage($msgId, $playerId);
                    } else {
                        $m->setMessageAsViewed($msgId, $playerId);
                    }
                }
            }
        }
        $page = $this->listPage();
        $buttons = [
            ['id' => 'mark', 'name' => 'mark', 'value' => 'read', 'label' => T('Messages', 'Mark as read')],
            ['id' => 'delete', 'name' => 'delete', 'label' => T('Messages', 'Delete')],
        ];
        if ($goldClub) {
            $buttons[] = ['id' => 'archive', 'name' => 'archive', 'label' => T('Messages', 'Archive')];
        }
        $this->renderMessageList(
            $m,
            0,
            '/messages/inbox',
            T('Messages', 'Received'),
            T('Messages', 'There are no messages available in the inbox'),
            $m->getInbox($page, $playerId, $this->listOrderAscending()),
            $m->getInboxCount($playerId),
            $buttons,
            $hasPermission,
            $page
        );
    }

    private function write(): void
    {
        $m = new MessageModel();
        $perm = Session::getInstance()->checkSitterPermission(Session::SITTER_CAN_SEND_OR_ANSWER_MESSAGES);
        if (!$perm) {
            return;
        }
        $pop = Session::getInstance()->get('total_pop');
        $min_pop = Config::getAdvancedProperty('mimMessagePop');
        if (Session::getInstance()->isAdmin()) {
            $min_pop = -1;
        }
        $maxRecipient = $InadmissibleMessage = $spam = $banned = $longSubject = $morePopRequired = $spam2 = false;
        if (WebService::isPost() && Session::claimAction($_REQUEST['c'] ?? null)) {
            $_REQUEST['an'] = strip_tags(is_string($_REQUEST['an'] ?? null) ? $_REQUEST['an'] : '');
            $_REQUEST['be'] = strip_tags(is_string($_REQUEST['be'] ?? null) ? $_REQUEST['be'] : '');
            $_REQUEST['be'] = mb_substr($_REQUEST['be'], 0, min(45, strlen($_REQUEST['be'])));
            if (empty($_REQUEST['be'])) {
                $_REQUEST['be'] = T('Messages', 'no subject');
            }
            $_REQUEST['message'] = strip_tags(is_string($_REQUEST['message'] ?? null) ? $_REQUEST['message'] : '');
            if (Session::getInstance()->isSitter()) {
                $playerName = $m->getPlayerName(Session::getInstance()->getSitterUID());
                $_REQUEST['message'] =
                    sprintf(T('Messages', 'WrittenBySitter%s'), $playerName) . PHP_EOL . $_REQUEST['message'];
            }
            $longSubject = strlen($_REQUEST['be']) > 45;
            if ($longSubject) {
                goto process;
            }
            $recipients = array_unique(array_map('trim', explode(';', $_REQUEST['an'])));
            $maxRecipient = sizeof($recipients) > getDisplay('maximumMultiMessageSendNum');
            if ($maxRecipient) {
                goto process;
            }
            $InadmissibleMessage =
                Session::getInstance()->getPlayerId() > 2 &&
                (!is_string($_REQUEST['be'] ?? null) || !is_string($_REQUEST['message'] ?? null)
                    || !StringChecker::isValidName($_REQUEST['be']) || !StringChecker::isValidMessage($_REQUEST['message']));
            if ($InadmissibleMessage) {
                goto process;
            }
            $banned = Session::getInstance()->data['access'] == 0 && Session::getInstance()->getPlayerId() > 2;
            $db = DB::getInstance();
            $redirect = false;
            foreach ($recipients as $recipient) {
                if (empty($recipient)) {
                    continue;
                }
                switch ($recipient) {
                    case '[ally]':
                        if ($banned) {
                            goto process;
                        }
                        if (
                            Session::getInstance()->getAllianceId() &&
                            Session::getInstance()->hasAlliancePermission(AllianceModel::IGM_MESSAGE)
                        ) {
                            $lastMsg = $m->checkLastMessage(
                                Session::getInstance()->getPlayerId(),
                                Session::getInstance()->banned(),
                                false,
                                $_REQUEST['be'],
                                $_REQUEST['message']
                            );
                            if ($lastMsg !== false) {
                                $users = $db->query(
                                    'SELECT id FROM users WHERE aid=' .
                                        Session::getInstance()->getAllianceId() .
                                        ' AND id!=' .
                                        Session::getInstance()->getPlayerId()
                                );
                                while ($row = $users->fetch_assoc()) {
                                    $m->sendMessage(
                                        Session::getInstance()->getPlayerId(),
                                        $row['id'],
                                        $_REQUEST['be'],
                                        $_REQUEST['message'],
                                        0,
                                        true
                                    );
                                }
                                $redirect = true;
                            } else {
                                $spam = true;
                            }
                        }
                        break;
                    case '[all]':
                        if (Session::getInstance()->isAdmin() && !Session::getInstance()->isAdminInAnotherAccount()) {
                            $db = DB::getInstance();
                            $users = $db->query('SELECT id FROM users WHERE id > 2 AND access<>2');
                            while ($row = $users->fetch_assoc()) {
                                $m->sendMessage(
                                    Session::getInstance()->getPlayerId(),
                                    $row['id'],
                                    $_REQUEST['be'],
                                    $_REQUEST['message']
                                );
                            }
                            $redirect = true;
                        }
                        break;
                    default:
                        $recipientUID = $m->getPlayerIdByName($recipient);
                        if ($recipientUID === false) {
                            goto process;
                        }
                        if ($recipientUID > 2 && $pop < $min_pop) {
                            $morePopRequired = true;
                            goto process;
                        }
                        if ($banned && $recipientUID > 2) {
                            goto process;
                        }
                        $lastMsg =
                            $recipientUID == Session::getInstance()->getPlayerId()
                                ? true
                                : $m->checkLastMessage(
                                    Session::getInstance()->getPlayerId(),
                                    Session::getInstance()->banned(),
                                    false,
                                    $_REQUEST['be'],
                                    $_REQUEST['message']
                                );
                        $spam2 =
                            $recipientUID == Session::getInstance()->getPlayerId()
                                ? false
                                : $this->checkMultiUnread(Session::getInstance()->getPlayerId(), $recipientUID);
                        if ($spam2) {
                            goto process;
                        }
                        if ($lastMsg == false) {
                            $spam = true;
                            goto process;
                        }
                        $this->checkForUnreadSpam(Session::getInstance()->getPlayerId());
                        $m->sendMessage(
                            Session::getInstance()->getPlayerId(),
                            $recipientUID,
                            $_REQUEST['be'],
                            $_REQUEST['message']
                        );
                        $redirect = true;
                        break;
                }
            }
            if ($redirect) {
                $this->redirect('/messages/inbox');
            }
        }
        process:
        $view = new PHPBatchView('messages/write');
        if ($longSubject) {
            $view->vars['error'] = T('Messages', 'Error: Very long subject!');
        } elseif ($banned) {
            $view->vars['error'] = T('Messages', 'You dont have permission here');
        } elseif ($spam) {
            $view->vars['error'] = T('Messages', 'Spam protection: Please wait for 10 minutes and try again');
        } elseif ($spam2 > 0) {
            $view->vars['error'] = sprintf(T('Messages', 'spam_unread_protection'), $spam2);
        } elseif ($morePopRequired) {
            $view->vars['error'] = sprintf(
                T('Messages', 'Spam protection: You must have at least %s pop to be able to send messages'),
                $min_pop
            );
        } elseif ($maxRecipient) {
            $view->vars['error'] = sprintf(
                T('Messages', 'You cannot send a message to more than %s users'),
                getDisplay('maximumMultiMessageSendNum')
            );
        }
        $view->vars['InadmissibleMessage'] = $InadmissibleMessage;
        $view->vars['checker'] = Session::getInstance()->getChecker();
        $view->vars['subject'] = is_string($_REQUEST['be'] ?? null) ? strip_tags($_REQUEST['be']) : null;
        $view->vars['receiver'] = is_string($_REQUEST['an'] ?? null) ? strip_tags($_REQUEST['an']) : null;
        $view->vars['message'] = is_string($_REQUEST['message'] ?? null) ? strip_tags($_REQUEST['message']) : null;
        $recipientParam = $_GET['to'] ?? null;
        if ($recipientParam !== null) {
            if (Session::getInstance()->isAdmin() && $recipientParam == '[all]') {
                $view->vars['receiver'] = '[all]';
            } else {
                $view->vars['receiver'] = $m->getPlayerName((int) $recipientParam);
            }
        }
        if (isset($_REQUEST['id'])) {
            $message = $m->getMessage((int) $_REQUEST['id'], Session::getInstance()->getPlayerId());
            if ($message !== false) {
                $message['message'] = strip_tags($message['message']);
                if ($message['autoType']) {
                } else {
                    $view->vars['subject'] = $message['topic'];
                    $view->vars['subject'] = MessageModel::answerSubject((string) $view->vars['subject']);
                    $view->vars['receiver'] = $m->getPlayerName($message['uid']);
                    if ($message['autoType']) {
                        if ($message['autoType'] <= 2) {
                            $data = [Session::getInstance()->getName()];
                            $data = array_merge($data, unserialize($message['message'], ['allowed_classes' => false]));
                            $message['message'] = vsprintf(
                                T(
                                    'Messages',
                                    $message['autoType'] == 1
                                        ? 'AllianceInvitationReceiveText'
                                        : 'AllianceInvitationRevokeText'
                                ),
                                $data
                            );
                            $message['message'] = str_replace('<br />', "\n", $message['message']);
                        }
                    }
                    $view->vars['message'] =
                        "\n\n" .
                        '____________' .
                        "\n" .
                        sprintf(T('Messages', 'xxx wrote:'), $view->vars['receiver']) .
                        "\n\n" .
                        $message['message'];
                }
            }
        }
        $this->view->vars['content'] .= $view->output();
    }

    private function sendBox(): void
    {
        $m = new MessageModel();
        $playerId = Session::getInstance()->getPlayerId();
        $hasPermission = Session::getInstance()->checkSitterPermission(
            Session::SITTER_CAN_REMOVE_ARCHIVE_MESSAGES_OR_REPORTS
        );
        if ($hasPermission && isset($_REQUEST['delete'])) {
            foreach ($this->postedMessageIds() as $messageId) {
                $m->deleteSendBoxMessage($messageId, $playerId);
            }
        }
        $page = $this->listPage();
        $this->renderMessageList(
            $m,
            2,
            '/messages/sent',
            T('Messages', 'Sent at'),
            T('Messages', 'There are no messages available in the sentbox'),
            $m->getSentBox($page, $playerId, $this->listOrderAscending()),
            $m->getSentBoxCount($playerId),
            [['id' => 'delete', 'name' => 'delete', 'label' => T('Messages', 'Delete')]],
            $hasPermission,
            $page
        );
    }

    private function Archive(): void
    {
        $m = new MessageModel();
        $playerId = Session::getInstance()->getPlayerId();
        $hasPermission = Session::getInstance()->checkSitterPermission(
            Session::SITTER_CAN_REMOVE_ARCHIVE_MESSAGES_OR_REPORTS
        );
        if ($hasPermission) {
            $this->applyMarkRequest($m);
            $postedIds = $this->postedMessageIds();
            if ($postedIds !== [] && !isset($_REQUEST['mark'])) {
                foreach ($postedIds as $msgId) {
                    if (isset($_REQUEST['delete'])) {
                        $m->deleteInboxMessage($msgId, $playerId);
                    } elseif (isset($_REQUEST['recover'])) {
                        $m->recoverMessage($msgId, $playerId);
                    } else {
                        $m->setMessageAsViewed($msgId, $playerId);
                    }
                }
            }
        }
        $page = $this->listPage();
        $buttons = [['id' => 'delete', 'name' => 'delete', 'label' => T('Messages', 'Delete')]];
        if (Session::getInstance()->hasGoldClub()) {
            $buttons[] = ['id' => 'recover', 'name' => 'recover', 'label' => T('Messages', 'Recover')];
        }
        $this->renderMessageList(
            $m,
            3,
            '/messages/archive',
            T('Messages', 'Received'),
            T('Messages', 'There are no messages available in the archive'),
            $m->getArchive($page, $playerId, $this->listOrderAscending()),
            $m->getArchiveCount($playerId),
            $buttons,
            $hasPermission,
            $page
        );
    }
}
