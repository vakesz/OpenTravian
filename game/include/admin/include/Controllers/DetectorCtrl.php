<?php

declare(strict_types=1);

use Core\Database\DB;
use Game\AdminUi;
use Model\InfoBoxModel;

/**
 * Multi-account / silver-movement heuristics inside the control panel shell
 * (replaces the standalone detect.php page).
 */
class DetectorCtrl
{
    public function __construct()
    {
        $db = DB::getInstance();
        $notice = $this->handleActions($db);

        $body = ($notice !== '' ? $notice : '')
            . '<div class="adminActions">'
            . AdminUi::button('Refresh', 'green', ['href' => 'admin.php?action=detector'])
            . '</div>'
            . AdminUi::box('Silver moved', $this->silverMovedTable($db))
            . AdminUi::box('Multi accounts by Pop', $this->multiByPopTable($db))
            . AdminUi::box('Banned players', $this->bannedTable($db));

        Dispatcher::getInstance()->appendContent(
            AdminUi::page(
                'Detector',
                'Heuristics for silver transfers, same-population accounts and the current ban queue.',
                $body
            )
        );
    }

    private function handleActions(DB $db): string
    {
        if (isset($_GET['banPlayer'])) {
            $uid = (int) $_GET['banPlayer'];
            $reason = (isset($_GET['reason']) && in_array((string) $_GET['reason'], ['Multiaccount'], true))
                ? (string) $_GET['reason']
                : 'Other';
            $this->banPlayer($db, $uid, $reason);
            AdminLog::getInstance()->addLog('Detector banned uid ' . $uid . ' (' . $reason . ').');

            return AdminUi::notice('Player ' . $uid . ' has been banned.', 'success');
        }
        if (isset($_GET['releaseBannedPlayer'])) {
            $uid = (int) $_GET['releaseBannedPlayer'];
            $this->releaseBannedPlayer($db, $uid);
            AdminLog::getInstance()->addLog('Detector unbanned uid ' . $uid . '.');

            return AdminUi::notice('Player ' . $uid . ' has been released.', 'success');
        }
        if (isset($_GET['deletePlayer'])) {
            $uid = (int) $_GET['deletePlayer'];
            $db->query('INSERT IGNORE INTO deleting (uid, time) VALUES (' . $uid . ', ' . (time() + 600) . ')');
            AdminLog::getInstance()->addLog('Detector queued deletion for uid ' . $uid . '.');

            return AdminUi::notice('Player ' . $uid . ' will be deleted in 10 minutes.', 'warning');
        }
        if (isset($_GET['clearDeletion'])) {
            $uid = (int) $_GET['clearDeletion'];
            $db->run('DELETE FROM deleting WHERE uid=?', [$uid]);
            AdminLog::getInstance()->addLog('Detector cancelled deletion for uid ' . $uid . '.');

            return AdminUi::notice('Deletion of player ' . $uid . ' was cancelled.', 'info');
        }

        return '';
    }

    private function banPlayer(DB $db, int $uid, string $reason, int $time = 0): void
    {
        $exists = 0 < (int) $db->fetchScalar('SELECT COUNT(id) FROM banQueue WHERE uid=?', [$uid]);
        if ($exists) {
            return;
        }
        $end = $time === 0 ? 0 : $time + time();
        $db->run('UPDATE users SET access=0 WHERE id=?', [$uid]);
        if ($db->affectedRows()) {
            $db->run('INSERT INTO `banQueue`(`uid`, `reason`, `time`, `end`) VALUES (?, ?, ?, ?)', [$uid, $reason, time(), $end]);
            $db->run('INSERT INTO `banHistory`(`uid`, `reason`, `time`, `end`) VALUES (?, ?, ?, ?)', [$uid, $reason, time(), $end]);
            $db->run('DELETE FROM multiaccount_users WHERE uid=?', [$uid]);
            (new InfoBoxModel())->addInfo($uid, 0, 14, '', time(), time() + 365 * 86400);
        }
    }

    private function releaseBannedPlayer(DB $db, int $uid): void
    {
        $db->run('UPDATE users SET access=1 WHERE id=?', [$uid]);
        if ($db->affectedRows()) {
            (new InfoBoxModel())->deleteInfoByType($uid, 14);
        }
        $db->run('DELETE FROM banQueue WHERE uid=?', [$uid]);
    }

    private function silverMovedTable(DB $db): string
    {
        $html = '<table class="adminTable"><thead><tr>'
            . '<th>Player</th><th>Pop</th><th>Gold</th><th>Silver</th><th>Auction silver</th><th></th>'
            . '</tr></thead><tbody>';
        $auctions = $db->query(
            'SELECT DISTINCT uid FROM auction a WHERE a.bids > 0 AND a.uid > 2 AND (SELECT access FROM users WHERE id=a.uid)=1 '
            . 'ORDER BY (SELECT SUM(silver) FROM auction WHERE uid=a.uid) DESC, a.uid ASC'
        );
        $count = 0;
        while ($auc = $auctions->fetch_assoc()) {
            $uid = (int) $auc['uid'];
            $user = $db->query('SELECT * FROM users WHERE id=' . $uid)->fetch_assoc();
            if (!$user) {
                continue;
            }
            $sold = (int) $db->fetchScalar('SELECT SUM(silver) FROM auction WHERE bids > 0 AND uid=' . $uid);
            $bought = (int) $db->fetchScalar('SELECT SUM(silver) FROM auction WHERE uid!=0 AND activeUid=' . $uid);
            if ($sold < 10000 && $bought < 10000) {
                continue;
            }
            $count++;
            $html .= '<tr>'
                . '<td><a href="admin.php?action=editPlayer&uid=' . $uid . '">[' . $uid . '] ' . AdminUi::esc((string) $user['name']) . '</a></td>'
                . '<td class="center">' . (int) $user['total_pop'] . '</td>'
                . '<td class="center">' . (int) $user['gift_gold'] . ' / ' . (int) $user['bought_gold'] . '</td>'
                . '<td class="center">' . (int) $user['silver'] . '</td>'
                . '<td class="center">Sold: ' . $sold . ' Bought: ' . $bought . '</td>'
                . '<td class="center"><a href="admin.php?action=detector&banPlayer=' . $uid . '&reason=Multiaccount">Ban</a></td>'
                . '</tr>';
        }
        if ($count === 0) {
            $html .= '<tr><td colspan="6" class="center adminMuted">Nothing, Good!</td></tr>';
        }

        return $html . '</tbody></table>';
    }

    private function multiByPopTable(DB $db): string
    {
        $html = '<table class="adminTable"><thead><tr>'
            . '<th>Player</th><th>Pop</th><th>Gold</th><th></th>'
            . '</tr></thead><tbody>';
        $users = $db->query(
            'SELECT DISTINCT a.* FROM users a JOIN users b ON a.total_pop = b.total_pop AND a.id != b.id '
            . 'AND a.id > 2 AND a.access=1 AND a.total_pop > 100'
        );
        $count = 0;
        while ($row = $users->fetch_assoc()) {
            $count++;
            $uid = (int) $row['id'];
            $html .= '<tr>'
                . '<td><a href="admin.php?action=editPlayer&uid=' . $uid . '">[' . $uid . '] ' . AdminUi::esc((string) $row['name']) . '</a></td>'
                . '<td class="center">' . (int) $row['total_pop'] . '</td>'
                . '<td class="center">' . (int) $row['gift_gold'] . ' / ' . (int) $row['bought_gold'] . '</td>'
                . '<td class="center"><a href="admin.php?action=detector&banPlayer=' . $uid . '&reason=Multiaccount">Ban</a></td>'
                . '</tr>';
        }
        if ($count === 0) {
            $html .= '<tr><td colspan="4" class="center adminMuted">Nothing, Good!</td></tr>';
        }

        return $html . '</tbody></table>';
    }

    private function bannedTable(DB $db): string
    {
        $html = '<table class="adminTable"><thead><tr>'
            . '<th>Player</th><th>Pop</th><th>Banned</th><th></th>'
            . '</tr></thead><tbody>';
        $banned = $db->query('SELECT a.* FROM banQueue a ORDER BY time ASC, id ASC');
        $count = 0;
        while ($row = $banned->fetch_assoc()) {
            $user = $db->query('SELECT * FROM users WHERE id=' . (int) $row['uid'])->fetch_assoc();
            if (!$user) {
                continue;
            }
            $count++;
            $uid = (int) $user['id'];
            $deleting = 0 < (int) $db->fetchScalar('SELECT COUNT(uid) FROM deleting WHERE uid=?', [$uid]);
            $html .= '<tr>'
                . '<td><a href="admin.php?action=editPlayer&uid=' . $uid . '">[' . $uid . '] ' . AdminUi::esc((string) $user['name']) . '</a></td>'
                . '<td class="center">' . (int) $user['total_pop'] . '</td>'
                . '<td>Start: ' . AdminUi::esc($this->diff(time() - (int) $row['time']))
                . '<br>Ends: ' . AdminUi::esc($this->diff((int) $row['end'] - (int) $row['time'], (int) $row['end'])) . '</td>'
                . '<td class="center">'
                . '<a href="admin.php?action=detector&releaseBannedPlayer=' . $uid . '">Unban</a> · '
                . ($deleting
                    ? '<a href="admin.php?action=detector&clearDeletion=' . $uid . '">Cancel deletion</a>'
                    : '<a href="admin.php?action=detector&deletePlayer=' . $uid . '">Delete</a>')
                . '</td></tr>';
        }
        if ($count === 0) {
            $html .= '<tr><td colspan="4" class="center adminMuted">Nothing, Good!</td></tr>';
        }

        return $html . '</tbody></table>';
    }

    private function diff(int $diff, int $end = -1): string
    {
        if ($end === 0) {
            return 'Never';
        }
        if ($diff > 86400) {
            return round($diff / 86400, 1) . 'd';
        }
        if ($diff > 3600) {
            return round($diff / 3600, 1) . 'h';
        }

        return round($diff / 60, 1) . 'm';
    }
}
