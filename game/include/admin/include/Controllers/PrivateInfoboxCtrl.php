<?php

use Core\Database\DB;
use Core\Helper\TimezoneHelper;
use Core\Helper\WebService;
use Core\Session;
use Model\InfoBoxModel;

class PrivateInfoboxCtrl
{
    public function __construct()
    {
        $params['action'] = 'privateInfobox';
        $params['expire'] = is_string($_POST['expire'] ?? null) && TimezoneHelper::strtotime($_POST['expire']) ? $_POST['expire'] : TimezoneHelper::date("Y-m-d H:i", time() + 86400);
        $params['html'] = is_string($_POST['html'] ?? null) ? $_POST['html'] : '';
        $db = DB::getInstance();
        if (($_REQUEST['section'] ?? null) === 'delete' && is_string($_REQUEST['iid'] ?? null)) {
            $db->run('DELETE FROM infobox WHERE id=? AND forAll=1 AND (type < 7 OR type > 10)', [(int)$_REQUEST['iid']]);
            InfoBoxModel::invalidateAllUsersPrivateInfoBox();
            AdminLog::getInstance()->addLog("deleted {$params['action']}");
        }
        if(WebService::isPost() && Session::validateChecker() && TimezoneHelper::strtotime($params['expire'])){
            $this->addInfo($db, 0, 1, 0, nl2br($params['html']), time(), TimezoneHelper::strtotime($params['expire']));
            InfoBoxModel::invalidateAllUsersPrivateInfoBox();
            AdminLog::getInstance()->addLog("added {$params['action']}");
        }
        $result = $db->query("SELECT * FROM infobox WHERE forAll=1 AND (type < 7 OR type > 10) AND showTo>=" . time());
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
     * @param int $uid
     * @param int $forAll
     * @param int $type
     * @param string $params
     * @param int $showFrom
     * @param int $showTo
     */
    public function addInfo(DB $db, $uid, $forAll, $type, $params, $showFrom, $showTo): void
    {
        $db->run('INSERT INTO infobox (uid, forAll, type, params, showFrom, showTo) VALUES (?, ?, ?, ?, ?, ?)', [(int)$uid, (int)$forAll, (int)$type, (string)$params, (int)$showFrom, (int)$showTo]);
    }
}