<?php

use Core\Config;
use Core\Helper\TimezoneHelper;
use Core\Helper\WebService;
use Core\Session;
use Game\Admin\Preflight;
use Game\Admin\AdminAccounts;
use Game\Admin\AdminRoles;
use Game\Admin\SetupChecklist;
use Game\AdminUi;

class MainCtrl
{
    public function __construct()
    {
        $session = Session::getInstance();
        $adminUid = (int) $session->getAdminUid();
        $role = match (true) {
            $adminUid === 0 => 'Administrator',
            $adminUid === 2 => 'Multihunter',
            default => AdminRoles::label((string) AdminAccounts::role($adminUid)),
        };
        $rows = [
            'Signed in as' => $session->getName() . ' (' . $role . ')',
            'World' => Config::getProperty('settings', 'serverName') . ' (' . getWorldId() . ')',
            'Speed' => getGameSpeed() . 'x',
            'Map size' => (2 * MAP_SIZE + 1) . ' x ' . (2 * MAP_SIZE + 1),
            'Today' => TimezoneHelper::date('l j F Y'),
            'Your IP address' => WebService::ipAddress(),
        ];
        $table = '<table class="adminTable"><tbody>';
        foreach ($rows as $label => $value) {
            $table .= '<tr><td class="head">' . AdminUi::esc($label) . '</td><td>' . AdminUi::esc($value) . '</td></tr>';
        }
        $table .= '</tbody></table>';
        $html = AdminUi::page('Control panel', 'Manage the world, its players and its content.', AdminUi::box('Overview', $table));
        $html .= AdminUi::box('Setup checklist', $this->checklist());
        $html .= AdminUi::box(
            'Quick links',
            '<div class="adminActions">'
            . AdminUi::button('Info and settings', 'grey', ['href' => 'admin.php?action=configurationDetails'])
            . AdminUi::button('Backups', 'grey', ['href' => 'admin.php?action=backups'])
            . AdminUi::button('Engine health', 'grey', ['href' => 'admin.php?action=engineHealth'])
            . AdminUi::button('Audit log', 'grey', ['href' => 'admin.php?action=adminLog'])
            . '</div>'
        );
        Dispatcher::getInstance()->appendContent($html);
    }

    /**
     * What needs attention first; the Advanced view also lists the checks that are fine.
     */
    private function checklist(): string
    {
        $tone = [Preflight::FAIL => 'error', Preflight::WARN => 'warning', Preflight::INFO => 'info', Preflight::OK => 'success'];
        $attention = '';
        $fine = '';
        foreach (SetupChecklist::items(Preflight::evaluate(Preflight::collect())) as $item) {
            $link = $item['href'] !== null ? ' <a href="' . AdminUi::esc($item['href']) . '">' . AdminUi::esc((string) $item['linkLabel']) . '</a>' : '';
            $row = '<li class="adminChecklist ' . AdminUi::esc($tone[$item['level']]) . '">' . AdminUi::esc($item['message']) . $link . '</li>';
            if (SetupChecklist::needsAttention($item['id'], $item['level'])) {
                $attention .= $row;
            } else {
                $fine .= $row;
            }
        }
        $html = $attention !== '' ? '<ul class="adminChecklistList">' . $attention . '</ul>' : AdminUi::empty('Nothing needs attention.');
        if ($fine !== '') {
            $html .= AdminUi::advanced('<ul class="adminChecklistList">' . $fine . '</ul>');
        }

        return $html . '<p class="adminMuted">The same checks run from <code>tools/preflight.php</code>; <a href="admin.php?action=engineHealth">Engine health</a> adds the engine and the queues.</p>';
    }
}
