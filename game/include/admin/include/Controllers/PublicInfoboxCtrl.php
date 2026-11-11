<?php

use Core\Database\DB;
use Core\Database\GlobalDB;
use Core\Helper\TimezoneHelper;
use Core\Helper\WebService;
use Core\Session;
use Model\InfoBoxModel;

class PublicInfoboxCtrl
{
    public function __construct()
    {
        $params['action'] = 'publicInfobox';
        $params['expire'] = is_string($_POST['expire'] ?? null) && TimezoneHelper::strtotime($_POST['expire']) ? $_POST['expire'] : TimezoneHelper::date("Y-m-d H:i", time() + 86400);
        $params['html'] = is_string($_POST['html'] ?? null) ? $_POST['html'] : '';
        $globalDB = GlobalDB::getInstance();
        if(!isServerFinished() && ($_REQUEST['section'] ?? null) === 'delete' && is_string($_REQUEST['iid'] ?? null)) {
            $globalDB->run('DELETE FROM infobox WHERE id=? AND autoType=0', [(int)$_REQUEST['iid']]);
            InfoBoxModel::invalidatePublicInfoBox();
            AdminLog::getInstance()->addLog("deleted {$params['action']}");
        }
        if(!isServerFinished() && WebService::isPost() && Session::validateChecker() && TimezoneHelper::strtotime($params['expire'])){
            $this->addInfo($globalDB, nl2br($params['html']), time(), TimezoneHelper::strtotime($params['expire']));
            InfoBoxModel::invalidatePublicInfoBox();
            AdminLog::getInstance()->addLog("added {$params['action']}");
        }
        $result = $globalDB->query("SELECT * FROM infobox WHERE autoType=0 AND showTo>=" . time());
        $params['content'] = null;
        while($row = $result->fetch_assoc()){
            $params['content'] .= '<tr>';
            $params['content'] .= '<td class="on center">'.$row['params'].'</td>';
            $params['content'] .= '<td class="on center">'.\Core\Helper\TimezoneHelper::autoDateString($row['showTo']).'</td>';
            $params['content'] .= '<td  class="center"><a href="?action='.$params['action'].'&section=delete&iid='.(int)$row['id'].'">Delete</a></td>';
            $params['content'] .= '</tr>';
        }
        if(!$result->num_rows){
            $params['content'] .= '<tr><td colspan="3" class="center adminMuted">No info boxes.</td></tr>';
        }
        Dispatcher::getInstance()->appendContent(Template::getInstance()->load($params, 'tpl/infobox.tpl')->getAsString());
    }
    /**
     * @param string $params
     * @param int $showFrom
     * @param int $showTo
     */
    public function addInfo(DB $db, $params, $showFrom, $showTo): void
    {
        $db->run('INSERT INTO infobox (params, showFrom, showTo) VALUES (?, ?, ?)', [(string)$params, (int)$showFrom, (int)$showTo]);
    }
}