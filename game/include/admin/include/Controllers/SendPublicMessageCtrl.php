<?php

use Core\Config;
use Core\Database\DB;
use Core\Database\GlobalDB;
use Core\Database\ServerDB;
use Core\Helper\WebService;

class SendPublicMessageCtrl
{
    public function __construct()
    {
        $dispatcher = Dispatcher::getInstance();
        if (!isServerFinished() && WebService::isPost() && is_string($_POST['message'] ?? null) && $_POST['message'] !== '') {
            $globalDB = GlobalDB::getInstance();
            $servers = $globalDB->run('SELECT id, configFileLocation FROM gameServers WHERE finished=0 AND startTime <= ?', [time()])->get_result();
            while ($server = $servers->fetch_assoc()) {
                try {
                    if ($server['id'] == Config::getProperty("settings", "worldUniqueId")) {
                        $db = DB::getInstance();
                    } else {
                        $db = ServerDB::getInstance($server['configFileLocation']);
                    }
                    $db->run('UPDATE config SET message=?', [$_POST['message']]);
                    $db->run('UPDATE users SET ok=1');
                } catch (\Exception $e) {
                    continue;
                }
            }
            AdminLog::getInstance()->addLog("Sent a public message!");
            Config::getInstance()->dynamic->message = $_POST['message'];
        }
        $dispatcher->appendContent(Template::getInstance()->load(['message' => Config::getInstance()->dynamic->message], 'tpl/publicMessage.tpl')->getAsString());
    }
}