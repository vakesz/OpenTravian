<?php

use Core\Database\DB;
use Core\Helper\WebService;
use Core\Session;
use Model\InfoBoxModel;

class DeleteAllMessagesCtrl
{
    public function __construct()
    {
        $params['id'] = is_string($_POST['id'] ?? null) ? (int)$_POST['id'] : null;
        $params['result'] = false;
        if (WebService::isPost() && $params['id'] > 0 && Session::validateChecker()) {
            $db = DB::getInstance();
            $db->run('UPDATE mdata SET delete_receiver=1, delete_sender=1 WHERE uid=?', [$params['id']]);
            $db->run('DELETE FROM messages_report WHERE reported_uid=?', [$params['id']]);
            $this->banPlayer($params['id']);
            $params['result'] = true;
        }
        $dispatcher = Dispatcher::getInstance();
        $dispatcher->appendContent(Template::getInstance()->load($params, 'tpl/deleteAllMessages.tpl')->getAsString());
    }

    /**
     * @param int $uid
     */
    private function banPlayer($uid): void
    {
        $reason = 'Spam';
        $time = 7200;
        $db = DB::getInstance();
        $exists = 0 < $db->fetchScalar('SELECT COUNT(id) FROM banQueue WHERE uid=?', [$uid]);
        if (!$exists) {
            $db->run('UPDATE users SET access=0 WHERE id=?', [$uid]);
            if ($db->affectedRows()) {
                $db->run('INSERT INTO `banQueue`(`uid`, `reason`, `time`, `end`) VALUES (?, ?, ?, ?)', [$uid, $reason, time(), time() + $time]);
                $db->run('INSERT INTO `banHistory`(`uid`, `reason`, `time`, `end`) VALUES (?, ?, ?, ?)', [$uid, $reason, time(), time() + $time]);
                $db->run('DELETE FROM multiaccount_users WHERE uid=?', [$uid]);
                (new InfoBoxModel())->addInfo($uid, 0, 14, '', time(), time() + 365 * 86400);
            }
        }
    }
}