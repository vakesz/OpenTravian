<?php

use Core\Database\DBI;
use Core\Helper\WebService;
use Game\Admin\AuditLog;
use Game\AdminUi;
use Game\SocialAuth\AdminView;
use Game\SocialAuth\LinkedIdentities;
use Game\SocialAuth\Provider;

/**
 * Linked social sign-ins of all players (Google, Facebook, Apple), with an unlink button per row and the
 * social.* events of the audit trail. Unlinking is a POST (CSRF-guarded like every panel action) and is written
 * to the audit trail by the dispatcher and again as social.unlink.
 */
class SocialSignInsCtrl
{
    private const FILTERS = ['player', 'provider', 'email'];

    public function __construct()
    {
        $dispatcher = Dispatcher::getInstance();
        if (WebService::isPost()) {
            $dispatcher->appendContent($this->unlink());
        }
        $filters = [];
        foreach (self::FILTERS as $key) {
            $filters[$key] = is_string($_GET[$key] ?? null) ? trim($_GET[$key]) : '';
        }
        $page = is_string($_GET['page'] ?? null) ? (int) $_GET['page'] : 1;
        $result = AdminView::search($filters, $page);

        $dispatcher->appendContent(
            '<h1>Social sign-ins</h1><p class="adminLead">Social accounts players have linked to their game account. An account is never linked by its email address: the player links it in Options.</p>'
            . $this->filterForm($filters)
            . AdminView::identitiesTable($result['rows'], true)
            . $this->pager($filters, $result['page'], $result['pages'], $result['total'])
            . '<h2>Recent events</h2>' . AdminView::eventsTable(null, 50)
            . '<p class="adminMuted"><a href="admin.php?action=adminLog">The full audit log</a> filters these by their social.* action.</p>'
        );
    }

    private function unlink(): string
    {
        $uid = is_string($_POST['uid'] ?? null) && ctype_digit($_POST['uid']) ? (int) $_POST['uid'] : 0;
        $provider = Provider::tryFrom(is_string($_POST['provider'] ?? null) ? $_POST['provider'] : '');
        if ($uid < 1 || $provider === null) {
            return AdminUi::notice('Choose a linked account to unlink.', 'error');
        }
        if (!LinkedIdentities::unlink($uid, $provider)) {
            return AdminUi::notice('That account is not linked any more.', 'info');
        }
        $name = (string) DBI::getInstance()->fetchScalar('SELECT name FROM users WHERE id=?', [$uid], '');
        AuditLog::record('social.unlink', 'uid=' . $uid . ' provider=' . $provider->value, 'by an administrator');

        return AdminUi::notice('The ' . $provider->label() . ' account of ' . $name . ' was unlinked.', 'success');
    }

    /** @param array<string, string> $filters */
    private function filterForm(array $filters): string
    {
        $providers = ['' => 'Any provider'];
        foreach (Provider::cases() as $provider) {
            $providers[$provider->value] = $provider->label();
        }

        return '<form method="get" action="admin.php" class="adminInline"><input type="hidden" name="action" value="socialSignIns">'
            . AdminUi::input('player', 'Player (name or id)', $filters['player'])
            . AdminUi::select('provider', 'Provider', $providers, $filters['provider'])
            . AdminUi::input('email', 'Email contains', $filters['email'])
            . AdminUi::button('Filter', 'green') . '</form>';
    }

    /** @param array<string, string> $filters */
    private function pager(array $filters, int $page, int $pages, int $total): string
    {
        $link = static function (int $target, string $label) use ($filters): string {
            $query = http_build_query(array_filter($filters, static fn (string $value): bool => $value !== '') + ['action' => 'socialSignIns', 'page' => $target]);

            return '<a href="admin.php?' . AdminUi::esc($query) . '">' . AdminUi::esc($label) . '</a>';
        };

        return '<p class="adminMuted">' . $total . ' linked accounts, page ' . $page . ' of ' . $pages . ' '
            . ($page > 1 ? $link($page - 1, 'Previous') . ' ' : '')
            . ($page < $pages ? $link($page + 1, 'Next') : '') . '</p>';
    }
}
