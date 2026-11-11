<?php

use Core\Config;
use Core\Database\DB;
use Core\Helper\WebService;

class SendPrivateMessageCtrl
{
    public function __construct()
    {
        $dispatcher = Dispatcher::getInstance();
        if (WebService::isPost() && is_string($_POST['message'] ?? null) && $_POST['message'] !== '') {
            $db = DB::getInstance();
            $db->run('UPDATE config SET message=?', [$_POST['message']]);
            $db->run('UPDATE users SET ok=1');
            AdminLog::getInstance()->addLog("Sent a private message!");
            Config::getInstance()->dynamic->message = $_POST['message'];
        }
        $dispatcher->appendContent(Template::getInstance()->load(['message' => Config::getInstance()->dynamic->message], 'tpl/privateMessage.tpl')->getAsString());
    }
}