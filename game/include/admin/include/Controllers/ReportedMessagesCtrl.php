<?php

use Core\Database\DB;
use Core\Helper\BBCode;
use Core\Helper\TimezoneHelper;
use Model\MessageModel;

class ReportedMessagesCtrl
{
    public function __construct()
    {
        if (isset($_REQUEST['see'])) {
            $this->processAnswer();
        } else if (isset($_REQUEST['delete'])) {
            $this->deleteTicket(1);
        } else if (isset($_REQUEST['deleteAll'])) {
            $this->deleteTicket(2);
        } else {
            $this->showTickets();
        }
    }

    /**
     * @param int $type
     */
    private function deleteTicket($type): void
    {
        if($type == 2){
            $answerId = (int)$_REQUEST['deleteAll'];
            $db = DB::getInstance();
            $db->run('UPDATE mdata SET delete_receiver=1, delete_sender=1 WHERE uid=?', [$answerId]);
            $db->run('DELETE FROM messages_report WHERE reported_uid=?', [$answerId]);
        } else {
            $db = DB::getInstance();
            $answerId = (int)$_REQUEST['delete'];
            $db->run('DELETE FROM messages_report WHERE id=?', [$answerId]);
        }
        $this->showTickets();
    }

    private function processAnswer(): void
    {
        $answerId = (int)$_REQUEST['see'];
        $ticket = DB::getInstance()->run('SELECT * FROM messages_report WHERE id=?', [$answerId])->get_result();
        if (!$ticket->num_rows) {
            $this->showTickets();
            return;
        }
        $msgModel = new MessageModel();
        $ticket = $ticket->fetch_assoc();
        $params['error'] = '';
        $params['answerId'] = $answerId;
        $params['player'] = '<a href="admin.php?action=editPlayer&uid=' . (int)$ticket['reported_uid'] . '">' . \Game\AdminUi::esc($msgModel->getPlayerName($ticket['reported_uid'])) . '</a>';
        $params['reason'] = (string) $ticket['type'];
        $params['time'] = (int) $ticket['time'];
        $msgR = $msgModel->getMessageAdmin($ticket['message_id']);
        $params['message'] = BBCode::translateMessagesBBCode($msgR['message'] ?? '');
        $dispatcher = Dispatcher::getInstance();
        $dispatcher->appendContent(Template::getInstance()->load($params, 'tpl/reportShow.tpl')->getAsString());
    }
    /**
     * @param mixed $uid
     * @return mixed
     */
    private function getPlayerName($uid)
    {
        $db = DB::getInstance();
        $find = $db->fetchScalar('SELECT name FROM users WHERE id=?', [(int)$uid]);
        if ($find)
            return $find;
        return 'n/a';
    }
    private function showTickets(): void
    {
        $params['content'] = '';
        $params['error'] = '';
        $messages = new MessageModel();
        $db = DB::getInstance();
        $result = $db->query("SELECT * FROM messages_report ORDER BY id ASC LIMIT 20");
        while ($row = $result->fetch_assoc()) {
            $params['content'] .= '<tr>';
            $params['content'] .= '<td>' . (int)$row['id'] . '.</td>';
            $params['content'] .= '<td><a href="admin.php?action=editPlayer&uid=' . (int)$row['reported_uid'] . '">' . \Game\AdminUi::esc($messages->getPlayerName($row['reported_uid'])) . '</a></td>';
            $params['content'] .= '<td><a href="admin.php?action=reportedMessages&see=' . (int)$row['id'] . '">' . \Game\AdminUi::esc($row['type']) . '</a></td>';
            $params['content'] .= '<td>' . TimezoneHelper::autoDateString($row['time'], true) . '</td>';
            $params['content'] .= '<td class="center"><a href="admin.php?action=reportedMessages&delete=' . (int)$row['id'] . '">Delete report</a> · <a href="admin.php?action=reportedMessages&deleteAll=' . (int)$row['reported_uid'] . '">Delete all messages</a></td>';
            $params['content'] .= '</tr>';
        }
        if (!$result->num_rows) {
            $params['content'] = '<tr><td colspan="5" class="center adminMuted">No reported messages.</td></tr>';
        }
        $dispatcher = Dispatcher::getInstance();
        $dispatcher->appendContent(Template::getInstance()->load($params, 'tpl/reportedMessages.tpl')->getAsString());
    }
}