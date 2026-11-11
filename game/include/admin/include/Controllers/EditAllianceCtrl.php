<?php

use Core\Database\DB;
use Core\Helper\WebService;
use Core\Session;
use Model\AllianceModel;
use Model\StatisticsModel;

class EditAllianceCtrl
{
    private DB $db;

    public function __construct()
    {
        $this->db = DB::getInstance();
        $section = is_string($_REQUEST['section'] ?? null) ? $_REQUEST['section'] : null;
        $aid = is_string($_REQUEST['aid'] ?? null) ? (int)$_REQUEST['aid'] : 0;
        if (!isServerFinished() && $section === 'deleteAlliance' && $aid > 0) {
            $m = new AllianceModel();
            $result = $this->db->run('SELECT id FROM users WHERE aid=?', [$aid])->get_result();
            while ($row = $result->fetch_assoc()) {
                $m->leaveAlliance($row['id'], $aid);
            }
            AdminLog::getInstance()->addLog("Deleted an alliance.");
        } else if (!isServerFinished() && $section === 'editAll' && $aid > 0 && WebService::isPost() && Session::validateChecker()) {
            $text = static fn (string $key): string => is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
            $this->db->run('UPDATE alidata SET name=?, tag=?, desc1=?, desc2=? WHERE id=?', [
                $text('name'), $text('tag'), $text('desc1'), $text('desc2'), $aid,
            ]);
            $dispatcher = Dispatcher::getInstance();
            $dispatcher->appendContent(\Game\AdminUi::notice('Changes made.', 'success'));
            AdminLog::getInstance()->addLog("Edited alliance(" . $aid . ")");
        }
        $this->showEditAlliance($aid);
    }

    /**
     * @param int $aid
     */
    private function showEditAlliance($aid): void
    {
        $dispatcher = Dispatcher::getInstance();
        $aliData = $this->db->run('SELECT * FROM alidata WHERE id=?', [$aid])->get_result();
        if (!$aliData->num_rows) {
            $dispatcher->appendContent(\Game\AdminUi::notice('Alliance does not exists!', 'error'));
            return;
        }
        $aliData = $aliData->fetch_assoc();
        $statistics = new StatisticsModel();
        $params['allianceId'] = $aliData['id'];
        $params['tag'] = $aliData['tag'];
        $params['desc1'] = $aliData['desc1'];
        $params['desc2'] = $aliData['desc2'];
        $params['name'] = $aliData['name'];
        $params['allianceRank'] = $statistics->getAllianceRankById($aid);
        $params['points'] = 0;
        $params['position'] = null;
        $params['hasPosition'] = false;
        $params['MembersHTML'] = null;
        $members = $this->db->query("SELECT users.id, users.name, users.last_login_time, users.alliance_role, users.alliance_role_name,
        (SELECT COUNT(vdata.kid) FROM vdata WHERE vdata.owner=users.id) as `total_villages`,
        (SELECT SUM(vdata.pop) FROM vdata WHERE vdata.owner=users.id) as `total_pop`
        FROM users WHERE users.aid=" . (int)$aid . " ORDER BY `total_pop` DESC");
        $rank = 0;
        $isMyAlliance = true;
        while ($row = $members->fetch_assoc()) {
            ++$rank;
            $params['points'] += $row['total_pop'];
            if ($row['alliance_role_name'] != '') {
                $params['hasPosition'] = TRUE;
                $params['position'] .= '<tr><th>' . \Game\AdminUi::esc($row['alliance_role_name']) . '</th><td><a href="admin.php?action=editPlayer&uid=' . (int)$row['id'] . '">' . \Game\AdminUi::esc($row['name']) . '</a></td></tr>';
                //has role!
            }
            $login_stat = '';
            if ($isMyAlliance) {
                if ((time() - 600) < $row['last_login_time']) { // 0 Min - 10 Min
                    $login_stat = T("Alliance", "online now");
                } else if ((time() - 86400) < $row['last_login_time'] && (time() - 600) > $row['last_login_time']) { // 10 Min - 1 Days
                    $login_stat = T("Alliance", "active players");
                } else if ((time() - 259200) < $row['last_login_time'] && (time() - 86400) > $row['last_login_time']) { // 1-3 Days
                    $login_stat = T("Alliance", "active 3days");
                } else if ((time() - 604800) < $row['last_login_time'] && (time() - 259200) > $row['last_login_time']) { // 3-7 Days
                    $login_stat = T("Alliance", "active 7days");
                } else {
                    $login_stat = T("Alliance", "inactive");
                }
            }
            $params['MembersHTML'] .= '<tr><td>' . $rank . '</td><td><a href="admin.php?action=editPlayer&uid=' . (int)$row['id'] . '">' . \Game\AdminUi::esc($row['name']) . '</a></td><td class="head">' . $row['total_pop'] . '</td><td>' . $row['total_villages'] . '</td><td class="head">' . $login_stat . '</td></tr>';
        }
        $params['Members'] = $rank;


        $dispatcher->appendContent(Template::getInstance()->load($params, 'tpl/editAlliance.tpl')->getAsString());
    }
}
