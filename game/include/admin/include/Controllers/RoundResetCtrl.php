<?php

use Core\Config;
use Core\Database\DB;
use Core\Helper\TimezoneHelper;
use Core\Helper\WebService;
use Core\Session;
use Game\Admin\AuditLog;
use Game\AdminUi;
use Game\Round\RoundReset;
use Game\Round\RoundResetFailed;
use Game\Round\RoundResetRefused;

/**
 * Ends the finished round and starts the next one on a fresh map without a reinstall. Panel access is
 * the only gate (the dispatcher admits the Multihunter and Support sessions); the reset runs on a POST that
 * passed the panel's CSRF guard and carries the world title typed by the administrator.
 */
class RoundResetCtrl
{
    private const RESULTS = [1 => 'Won by a player', 2 => 'Won by the Natars'];

    public function __construct()
    {
        $dispatcher = Dispatcher::getInstance();
        $session = Session::getInstance();
        $html = '<h1>Round reset</h1><p class="adminLead">Archives the finished round and starts a new round on a fresh map without a reinstall.</p>';
        if (WebService::isPost()) {
            $html .= $this->reset((string) $session->getName());
        }
        $html .= RoundReset::finished() ? $this->form() : AdminUi::notice('The round has not finished yet. A round can be reset only after it has ended.', 'info');
        $html .= $this->archive();
        $dispatcher->appendContent($html);
    }

    private function reset(string $adminName): string
    {
        $mode = is_string($_POST['mode'] ?? null) ? $_POST['mode'] : '';
        $title = is_string($_POST['title'] ?? null) ? trim($_POST['title']) : '';
        if (!hash_equals((string) Config::getInstance()->settings->serverName, $title)) {
            return AdminUi::notice('The world title does not match.', 'error');
        }
        ignore_user_abort(true);
        try {
            $roundId = RoundReset::run($mode, $adminName);
        } catch (RoundResetRefused $refused) {
            return AdminUi::notice($refused->getMessage(), 'error');
        } catch (RoundResetFailed $failed) {
            AuditLog::record('round.reset', 'mode=' . $mode, 'failed');

            return AdminUi::notice($failed->getMessage(), 'error');
        }
        AuditLog::record('round.archive', 'round=' . $roundId, 'mode=' . $mode);
        AuditLog::record('round.reset', 'round=' . $roundId, 'mode=' . $mode);

        return AdminUi::notice('Round ' . $roundId . ' was archived and the new round has started.', 'success');
    }

    private function form(): string
    {
        $body = '<form method="post" action="admin.php?action=roundReset">'
            . '<div role="radiogroup"><label><input type="radio" name="mode" value="' . RoundReset::MODE_KEEP . '" checked> Keep accounts</label>'
            . '<p class="adminMuted">Players keep their name, password, email and settings. Gold, hero and medals are reset and every player gets a fresh start village.</p>'
            . '<label><input type="radio" name="mode" value="' . RoundReset::MODE_REMOVE . '"> Remove accounts</label>'
            . '<p class="adminMuted">Every player account is deleted. Players register again.</p></div>'
            . AdminUi::input('title', 'Type the world title to confirm', '', ['autocomplete' => 'off', 'required' => 'required'])
            . '<div class="adminActions">' . AdminUi::button('Reset round', 'green') . '</div></form>';

        return AdminUi::box('Round reset', $body);
    }

    private function archive(): string
    {
        $result = DB::getInstance()->query('SELECT id, startTime, finishTime, resultCode, winnerName, playerCount FROM rounds ORDER BY id DESC');
        $rows = '';
        while ($result && ($row = $result->fetch_assoc())) {
            $rows .= '<tr><td>' . (int) $row['id'] . '</td>'
                . '<td>' . AdminUi::esc(TimezoneHelper::autoDateString((int) $row['startTime'], true)) . '</td>'
                . '<td>' . AdminUi::esc(TimezoneHelper::autoDateString((int) $row['finishTime'], true)) . '</td>'
                . '<td>' . AdminUi::esc(self::RESULTS[(int) $row['resultCode']] ?? '') . '</td>'
                . '<td>' . AdminUi::esc((string) $row['winnerName']) . '</td>'
                . '<td>' . (int) $row['playerCount'] . '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="6" class="noData center">No rounds archived yet.</td></tr>';
        }

        return AdminUi::box('Archived rounds', '<table class="adminTable"><thead><tr><th>Round</th><th>Started</th><th>Finished</th><th>Result</th><th>Winner</th><th>Players</th></tr></thead><tbody>' . $rows . '</tbody></table>');
    }
}
