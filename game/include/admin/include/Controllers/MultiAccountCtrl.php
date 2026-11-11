<?php

use Core\Database\DB;
use Model\InfoBoxModel;
use Model\MultiAccount;

class MultiAccountCtrl
{
    public function __construct()
    {
        $multiAccount = new MultiAccount();
        if (is_string($_GET['id'] ?? null)) {
            $id = (int)$_GET['id'];
            $result = $multiAccount->getMultiAccountById($id);
            if($result->num_rows){
                $result = $result->fetch_assoc();
                $this->banPlayer($result['uid']);
                if (($_GET['main'] ?? null) !== 'true') {
                    $players = explode("|", $result['data']);
                    foreach($players as $data){
                        $rData = explode(",", $data);
                        $this->banPlayer((int)$rData[0]);
                    }
                }
            }
        }
        $page = max(1, (int)($_GET['p'] ?? 1));
        $params['content'] = null;
        $result = $multiAccount->getMultiAccountUsers($page, $this->pageSize);
        while ($row = $result->fetch_assoc()) {
            $params['content'] .= '<tr>';
            $params['content'] .= '<td class="head">' . (int)$row['id'] . '</td>';
            $params['content'] .= '<td class="head"><a href="admin.php?action=editPlayer&uid=' . (int)$row['uid'] . '">' . \Game\AdminUi::esc($this->getPlayerName($row['uid'])) . '</a></td>';
            $params['content'] .= '<td>';
            $params['content'] .= '<table class="adminTable"><thead><tr><td class="head">Name</td><td class="head">Attacks</td><td class="head">Reinforcements</td><td class="head">Trades</td></tr></thead><tbody>';
            $players = explode("|", $row['data']);
            foreach($players as $data){
                $rData = explode(",", $data);
                $name = $this->getPlayerName((int)$rData[0]);
                if($name == FALSE) continue;
                $params['content'] .= '<tr>';
                $params['content'] .= '<td class="head"><a href="admin.php?action=editPlayer&uid='.(int)$rData[0].'">'.\Game\AdminUi::esc($name).'</a></td>';
                $params['content'] .= '<td class="head">'.(int)($rData[1] ?? 0).'</td>';
                $params['content'] .= '<td class="head">'.(int)($rData[2] ?? 0).'</td>';
                $params['content'] .= '<td class="head">'.(int)($rData[3] ?? 0).'</td>';
                $params['content'] .= '</tr>';
            }
            $params['content'] .= '</tbody></table>';
            $params['content'] .= '</td>';
            $params['content'] .= '<td class="head">' . $row['priority'] . '</td>';
            $params['content'] .= '<td>';
            $params['content'] .= '<a href="admin.php?action=multiAccount&id='.$row['id'].'">Ban all players</a>';
            $params['content'] .= '<br /><a href="admin.php?action=multiAccount&id='.$row['id'].'&main=true">Ban main user</a>';
            $params['content'] .= '</td>';
            $params['content'] .= '</tr>';
        }
        if (!$result->num_rows) {
            $params['content'] .= '<tr><td colspan="5" class="center adminMuted">No multi-account matches.</td></tr>';
        }
        $params['navigator'] = $this->getNavigator($page, $multiAccount->getMultiAccountUsersTotal(), ['action' => 'multiAccount']);
        Dispatcher::getInstance()->appendContent(Template::getInstance()->load($params, 'tpl/multiAccountUsers.tpl')->getAsString());
    }
    /**
     * @param float|int|string|null $uid
     * @param string $reason
     * @param int $time
     */
    private function banPlayer($uid, $reason = 'Multiaccount', $time = 0): void{
        $db = DB::getInstance();
        $uid = (int)$uid;
        $end = $time == 0 ? 0 : $time + time();
        $exists = 0 < $db->fetchScalar('SELECT COUNT(id) FROM banQueue WHERE uid=?', [$uid]);
        if (!$exists) {
            $db->run('UPDATE users SET access=0 WHERE id=?', [$uid]);
            if ($db->affectedRows()) {
                $db->run('INSERT INTO `banQueue`(`uid`, `reason`, `time`, `end`) VALUES (?, ?, ?, ?)', [$uid, $reason, time(), $end]);
                $db->run('INSERT INTO `banHistory`(`uid`, `reason`, `time`, `end`) VALUES (?, ?, ?, ?)', [$uid, $reason, time(), $end]);
                $db->run('DELETE FROM multiaccount_users WHERE uid=?', [$uid]);
                (new InfoBoxModel())->addInfo($uid, 0, 14, '', time(), time() + 365 * 86400);
                AdminLog::getInstance()->addLog("Banned ".$this->getPlayerName($uid).".");
            }
        }
    }
    /**
     * @param float|int|string|null $uid
     * @return mixed
     */
    private function getPlayerName($uid)
    {
        $db = DB::getInstance();
        $find = $db->fetchScalar('SELECT name FROM users WHERE id=?', [(int)$uid]);
        if($find)
            return $find;
        return FALSE;
    }
    const NEXT_SHAPE = '»';
    const PREVIOUS_SHAPE = '«';
    /** @var int */
    private $pageSize = 5;
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
     * @param int $num_rows
     * @param array<mixed>|array<string, string> $prefix
     */
    private function getNavigator($page, $num_rows, $prefix = []): string
    {
        return $this->previousPageNavigator($page, $num_rows, $prefix) . ' | ' . $this->nextPageNavigator($page, $num_rows, $prefix);
    }
}