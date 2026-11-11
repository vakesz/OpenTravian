<?php

use Core\Helper\TimezoneHelper;
use Game\Admin\AuditLog;
use Game\AdminUi;

/**
 * The administrator audit trail: every state-changing panel request and every
 * administrator sign-in and two-factor event (who, when, action, target, IP),
 * newest first, with filters. Read-only: viewing it is not logged.
 */
class AdminLogCtrl
{
    // The action filter is named "event": "action" already selects the panel page.
    private const FILTERS = ['admin', 'event', 'target', 'ip', 'from', 'to'];

    public function __construct()
    {
        $filters = [];
        foreach (self::FILTERS as $key) {
            $filters[$key] = is_string($_GET[$key] ?? null) ? trim($_GET[$key]) : '';
        }
        $page = is_string($_GET['page'] ?? null) ? (int) $_GET['page'] : 1;
        $result = AuditLog::search($filters, $page);

        $rows = '';
        foreach ($result['rows'] as $row) {
            $who = (int) $row['admin_uid'] < 0 ? '' : AdminUi::esc($row['admin_name']) . ' (' . (int) $row['admin_uid'] . ')';
            $rows .= '<tr><td>' . (int) $row['id'] . '</td><td>' . AdminUi::esc(TimezoneHelper::autoDateString((int) $row['time'], true))
                . '</td><td>' . $who . '</td><td>' . AdminUi::esc($row['action']) . '</td><td>' . AdminUi::esc($row['target'])
                . '</td><td>' . AdminUi::esc($row['details']) . '</td><td>' . AdminUi::esc($row['ip']) . '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="7" class="center"><span class="adminMuted">No entries match.</span></td></tr>';
        }

        Dispatcher::getInstance()->appendContent(
            '<h1>Audit log</h1><p class="adminLead">Every state-changing request in the control panel and every administrator sign-in and two-factor event: who, when, action, target and IP. '
            . 'Field values are never stored.</p>'
            . $this->filterForm($filters)
            . '<table class="adminTable"><thead><tr><th>ID</th><th>Date</th><th>Account</th><th>Action</th><th>Target</th><th>Details</th><th>IP address</th></tr></thead><tbody>'
            . $rows . '</tbody></table>'
            . $this->pager($filters, $result['page'], $result['pages'], $result['total'])
        );
    }

    /** @param array<string, string> $filters */
    private function filterForm(array $filters): string
    {
        $actions = ['' => 'Any action'];
        foreach (AuditLog::actions() as $action) {
            $actions[$action] = $action;
        }

        return '<form method="get" action="admin.php" class="adminInline"><input type="hidden" name="action" value="adminLog">'
            . AdminUi::input('admin', 'Account (name or id)', $filters['admin'])
            . AdminUi::select('event', 'Action', $actions, $filters['event'])
            . AdminUi::input('target', 'Target contains', $filters['target'])
            . AdminUi::input('ip', 'IP starts with', $filters['ip'])
            . AdminUi::input('from', 'From (YYYY-MM-DD)', $filters['from'], ['placeholder' => 'YYYY-MM-DD'])
            . AdminUi::input('to', 'To (YYYY-MM-DD)', $filters['to'], ['placeholder' => 'YYYY-MM-DD'])
            . AdminUi::button('Filter', 'green') . '</form>';
    }

    /** @param array<string, string> $filters */
    private function pager(array $filters, int $page, int $pages, int $total): string
    {
        $link = static function (int $target, string $label) use ($filters): string {
            $query = http_build_query(array_filter($filters, static fn (string $value): bool => $value !== '') + ['action' => 'adminLog', 'page' => $target]);

            return '<a href="admin.php?' . AdminUi::esc($query) . '">' . AdminUi::esc($label) . '</a>';
        };

        return '<p class="adminMuted">' . $total . ' entries, page ' . $page . ' of ' . $pages . ' '
            . ($page > 1 ? $link($page - 1, 'Newer') . ' ' : '')
            . ($page < $pages ? $link($page + 1, 'Older') : '') . '</p>';
    }
}
