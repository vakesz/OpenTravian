<?php

use Core\Database\DB;
use Core\Helper\TimezoneHelper;

class DeletingCtrl
{
    public function __construct()
    {
        $db = DB::getInstance();
        $section = $_REQUEST['section'] ?? null;
        $uid = is_string($_REQUEST['uid'] ?? null) ? (int)$_REQUEST['uid'] : 0;
        if (!isServerFinished() && $section === 'del' && $uid > 0) {
            $db->run('DELETE FROM deleting WHERE uid=?', [$uid]);
        } else if (!isServerFinished() && $section === 'deleteNow' && $uid > 0) {
            $db->run('UPDATE deleting SET time=0 WHERE uid=?', [$uid]);
        }
        $page = max(1, (int)($_GET['p'] ?? 1));
        $result = $db->query("SELECT u.id, d.time, u.name FROM deleting d, users u WHERE d.uid=u.id ORDER BY id DESC LIMIT " . (($page - 1) * $this->pageSize) . ", {$this->pageSize}");
        $params['content'] = null;
        while($row = $result->fetch_assoc()){
            $remaining = $row['time'] - time();
            $params['content'] .= '<tr>';
            $params['content'] .= '<td class="center"><a href="admin.php?action=deleting&uid='.(int)$row['id'].'&section=del">Delete</a></td>';
            $params['content'] .= '<td class="center"><a href="admin.php?action=editPlayer&uid='.(int)$row['id'].'">'.\Game\AdminUi::esc($row['name']).'</a></td>';
            $params['content'] .= '<td class="center">'.secondsToString($remaining).'</td>';
            $params['content'] .= '<td class="center">'.TimezoneHelper::autoDateString($row['time'], true).'</td>';
            $params['content'] .= '<td class="center"><a href="admin.php?action=deleting&uid='.(int)$row['id'].'&section=deleteNow">» Delete now</a></td>';
            $params['content'] .= '</tr>';
        }
        if(!$result->num_rows){
            $params['content'] .= '<tr><td colspan="5" class="center adminMuted">No deletions queued.</td></tr>';
        }
        $params['navigator'] = $this->getNavigator($page, $db->fetchScalar("SELECT COUNT(uid) FROM deleting"), ['action' => 'activationList']);
        \Dispatcher::getInstance()->appendContent(\Template::getInstance()->load($params, 'tpl/deleting.tpl')->getAsString());
    }
    const NEXT_SHAPE = '»';
    const PREVIOUS_SHAPE = '«';
    /** @var int */
    private $pageSize = 50;
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