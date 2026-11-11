<?php

use Core\Database\DB;
use Core\Helper\TimezoneHelper;
use Core\Helper\WebService;
use Model\InfoBoxModel;
use Model\VillageModel;

class BannedListCtrl
{
    public function __construct()
    {
        $db = DB::getInstance();
        $params['uid'] = null;
        if (is_string($_REQUEST['uid'] ?? null)) {
            $params['uid'] = (int)$_REQUEST['uid'];
        }
        if (($_REQUEST['section'] ?? null) === 'unban' && $params['uid'] !== null) {
            $uid = $params['uid'];
            $db->run('UPDATE users SET access=1 WHERE id=?', [$uid]);
            if ($db->affectedRows()) {
                (new InfoBoxModel())->deleteInfoByType($uid, 14);
            }
            $db->run('DELETE FROM banQueue WHERE uid=?', [$uid]);
            AdminLog::getInstance()->addLog("UnBanned " . $this->getPlayerName($uid) . ".");
        }
        if (WebService::isPost()) {
            $uid = (int)($_POST['uid'] ?? 0);
            $reason = is_string($_POST['reason'] ?? null) ? $_POST['reason'] : '';
            $time = max(0, (int)($_POST['time'] ?? 0));
            $end = $time === 0 ? 0 : $time + time();
            $exists = 0 < $db->fetchScalar('SELECT COUNT(id) FROM banQueue WHERE uid=?', [$uid]);
            if (!$exists) {
                $db->run('UPDATE users SET access=0 WHERE id=?', [$uid]);
                if ($db->affectedRows()) {
                    $db->run('INSERT INTO `banQueue`(`uid`, `reason`, `time`, `end`) VALUES (?, ?, ?, ?)', [$uid, $reason, time(), $end]);
                    $db->run('INSERT INTO `banHistory`(`uid`, `reason`, `time`, `end`) VALUES (?, ?, ?, ?)', [$uid, $reason, time(), $end]);
                    $db->run('DELETE FROM multiaccount_users WHERE uid=?', [$uid]);
                    (new InfoBoxModel())->addInfo($uid, 0, 14, '', time(), time() + 365 * 86400);
                    AdminLog::getInstance()->addLog("Banned " . $this->getPlayerName($uid) . ".");
                }
            }
            $params['reason'] = $reason;
            $params['time'] = $time;
        }
        $page = max(1, (int)($_GET['p'] ?? 1));
        $result = $db->query("SELECT * FROM banQueue ORDER BY end DESC, id ASC LIMIT " . (($page - 1) * $this->pageSize) . ", {$this->pageSize}");
        $banned_arr = [];
        $params['content'] = null;
        while ($row = $result->fetch_assoc()) {
            $banned_arr[] = $row['uid'];
            $params['content'] .= '<tr>';
            $params['content'] .= '<td class="head"><a href="?action=editPlayer&uid=' . (int)$row['uid'] . '">' . \Game\AdminUi::esc($name = $this->getPlayerName($row['uid'])) . '</a></td>';
            if ($row['end'] == 0) {
                $params['content'] .= '<td class="head"><span class="f7">' . TimezoneHelper::autoDateString($row['time'],
                        TRUE) . ' - Forever</td>';
            } else {
                $params['content'] .= '<td class="head"><span class="f7">' . TimezoneHelper::autoDateString($row['time'],
                        TRUE) . ' - ' . TimezoneHelper::autoDateString($row['end'], TRUE) . '</td>';
            }
            $params['content'] .= '<td class="head">' . \Game\AdminUi::esc($row['reason']) . '</td>';
            $params['content'] .= '<td><a href="?action=bannedList&section=unban&uid=' . (int)$row['uid'] . '&id=' . (int)$row['id'] . '" onClick="return del(\'unban\',' . \Game\AdminUi::esc(json_encode($name)) . ')">Delete</a></td>';
            $params['content'] .= '<td class="head"><a href="?action=editPlayer&section=punishPlayer&uid=' . (int)$row['uid'] . '&ref=bannedList" onClick="return del(\'unban\',' . \Game\AdminUi::esc(json_encode($name)) . ')">Punish</a></td>';
            $params['content'] .= '</tr>';
        }
        if (!$result->num_rows) {
            $params['content'] .= '<tr><td colspan="5" class="center adminMuted">No players are banned.</td></tr>';
        }
        $params['navigator'] = $this->getNavigator($page,
            $db->fetchScalar("SELECT COUNT(id) FROM banQueue"),
            ['action' => 'bannedList']);
        $params['total'] = $db->fetchScalar("SELECT COUNT(id) FROM banQueue");
        Dispatcher::getInstance()->appendContent(Template::getInstance()->load($params, 'tpl/ban.tpl')->getAsString());
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
        return $this->previousPageNavigator($page, $num_rows, $prefix) . ' | ' . $this->nextPageNavigator($page,
                $num_rows,
                $prefix);
    }
}