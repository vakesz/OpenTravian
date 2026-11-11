<?php

use Core\Config;
use Core\Database\GlobalDB;
use Core\Helper\IPTracker;
use Core\Helper\TimezoneHelper;
use Core\Helper\WebService;
use Core\Session;

class IPBanCtrl
{
    /** @var mixed */
    private $countryCodes = [];
    public function __construct()
    {
        $this->countryCodes = Config::getInstance()->countryCodes;
        $params = [
            'ip' => is_string($_REQUEST['ip'] ?? null) ? trim($_REQUEST['ip']) : null,
            'time' => is_string($_POST['time'] ?? null) ? max(0, (int)$_POST['time']) : 7200,
            'reason' => is_string($_POST['reason'] ?? null) ? $_POST['reason'] : 'Pushing',
        ];
        if(!isServerFinished() && WebService::isPost() && Session::validateChecker()){
            ;
            $ip = IPTracker::normalize($params['ip']);
            if($ip !== null){
                if($ip === IPTracker::normalize(WebService::ipAddress())){
                    $params['error'] = 'You cannot block your ip address.';
                } else {
                    IPTracker::blockIP($ip, $params['reason'], $params['time']);
                    $params['error'] = 'IP blocked.';
                }
            } else {
                $params['error'] = 'No IPs to block.';
            }
        } else {
            $del = is_string($_GET['del'] ?? null) ? IPTracker::normalize($_GET['del']) : null;
            if(!isServerFinished() && $del !== null){
                IPTracker::unblockIP($del);
            }
        }
        $page = max(1, (int)($_GET['p'] ?? 1));
        $db = GlobalDB::getInstance();
        $result = $db->query("SELECT * FROM banIP WHERE (blockTill>".time()." OR blockTill=0) ORDER BY id DESC LIMIT " . (($page - 1) * $this->pageSize) . ", {$this->pageSize}");
        $params['content'] = null;
        while($row = $result->fetch_assoc()){
            $params['content'] .= '<tr>';
            $ip = \Game\AdminUi::esc($row['ip']);
            $params['content'] .= '<td class="center"><a href="admin.php?action=IPBan&del='.rawurlencode($row['ip']).'">Delete</a></td>';
            $params['content'] .= '<td class="center">'.$ip.'</td>';
            $countryFlag = '-';
            if(function_exists("geoip_country_code_by_name")){
                $countryFlag = strtoupper(geoip_country_code_by_name($row['ip']));
            }
            $params['content'] .= '<td class="center">'.(isset($this->countryCodes[$countryFlag]) ? $this->countryCodes[$countryFlag] : 'Unknown location').'</td>';
            $params['content'] .= '<td class="center">'.\Game\AdminUi::esc($row['reason']).'</td>';
            if($row['blockTill'] == 0){
                $params['content'] .= '<td class="head"><span class="f7">'.TimezoneHelper::autoDateString($row['time'], TRUE).' - Forever</td>';
            } else {
                $params['content'] .= '<td class="head"><span class="f7">'.TimezoneHelper::autoDateString($row['time'], TRUE).' - '.TimezoneHelper::autoDateString($row['blockTill'], TRUE).'</td>';
            }
            $params['content'] .= '</tr>';
        }
        if(!$result->num_rows){
            $params['content'] .= '<tr><td colspan="5" class="center adminMuted">No IP bans.</td></tr>';
        }
        $params['navigator'] = $this->getNavigator($page, $db->fetchScalar("SELECT COUNT(id) FROM banIP WHERE (blockTill>".time()." OR blockTill=0)"), ['action' => 'IPBan']);
        \Dispatcher::getInstance()->appendContent(\Template::getInstance()->load($params, 'tpl/banIP.tpl')->getAsString());
    }
    const NEXT_SHAPE = '»';
    const PREVIOUS_SHAPE = '«';
    /** @var int */
    private $pageSize = 30;
    /**
     * @param mixed $page
     * @param mixed $num_rows
     * @param mixed $prefix
     */
    private function previousPageNavigator($page, $num_rows, $prefix = []): string
    {
        $text = self::PREVIOUS_SHAPE;
        $total_pages = ceil($num_rows / $this->pageSize);
        if (($page - 1) >= 1 && ($page - 1) <= $total_pages) {
            $prefix['p'] = --$page;
            $query = http_build_query($prefix);
            return '<a href="?' . $query . '">' . $text . '</a>';
        }
        return $text;
    }

    /**
     * @param mixed $page
     * @param mixed $num_rows
     * @param mixed $prefix
     */
    private function nextPageNavigator($page, $num_rows, $prefix = []): string
    {
        $text = self::NEXT_SHAPE;
        $total_pages = ceil($num_rows / $this->pageSize);
        if ((1 + $page) <= $total_pages) {
            $prefix['p'] = ++$page;
            $query = http_build_query($prefix);
            return '<a href="?' . $query . '">' . $text . '</a>';
        }
        return $text;
    }

    /**
     * @param int $page
     * @param mixed $num_rows
     * @param array<mixed>|array<string, string> $prefix
     */
    private function getNavigator($page, $num_rows, $prefix = []): string
    {
        return $this->previousPageNavigator($page, $num_rows, $prefix) . ' | ' . $this->nextPageNavigator($page, $num_rows, $prefix);
    }
}