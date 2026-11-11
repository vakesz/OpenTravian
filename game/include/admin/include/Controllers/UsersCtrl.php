<?php

declare(strict_types=1);

use Core\Database\DB;
use Core\Helper\TimezoneHelper;
use Game\AdminUi;
use Game\Tribes;

/**
 * The Players → Users list: search and open a player for editing inside the control panel
 * (replacing the old menu link that left the panel for the public statistics page).
 */
class UsersCtrl
{
    private const PAGE_SIZE = 50;

    public function __construct()
    {
        $db = DB::getInstance();
        $q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
        $page = max(1, (int) ($_GET['p'] ?? 1));
        $where = 'u.id > 2';
        $args = [];
        if ($q !== '') {
            if (ctype_digit($q)) {
                $where .= ' AND u.id = ?';
                $args[] = (int) $q;
            } else {
                $where .= ' AND u.name LIKE ?';
                $args[] = '%' . $q . '%';
            }
        }

        $total = (int) $db->fetchScalar('SELECT COUNT(*) FROM users u WHERE ' . $where, $args);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($page, $pages);
        $offset = ($page - 1) * self::PAGE_SIZE;

        $statement = $db->run(
            'SELECT u.id, u.name, u.race, u.access, u.total_pop, u.total_villages, u.gift_gold, u.bought_gold, u.silver, u.last_login_time, u.hidden
             FROM users u WHERE ' . $where . ' ORDER BY u.total_pop DESC, u.id ASC LIMIT ' . self::PAGE_SIZE . ' OFFSET ' . $offset,
            $args
        );
        $rows = $statement === false ? [] : ($statement->get_result()->fetch_all(MYSQLI_ASSOC) ?: []);

        $body = $this->filterForm($q)
            . $this->table($rows)
            . $this->pager($q, $page, $pages, $total);

        Dispatcher::getInstance()->appendContent(
            AdminUi::page('Users', 'Search and open a player to edit their account, villages and gold.', $body)
        );
    }

    private function filterForm(string $q): string
    {
        return '<form method="get" action="admin.php" class="adminInline">'
            . '<input type="hidden" name="action" value="users">'
            . AdminUi::input('q', 'Name or id', $q)
            . AdminUi::button('Search', 'green')
            . '</form>';
    }

    /** @param list<array<string, mixed>> $rows */
    private function table(array $rows): string
    {
        $html = '<table class="adminTable"><thead><tr>'
            . '<th>Id</th><th>Name</th><th>Tribe</th><th>Pop</th><th>Villages</th>'
            . '<th>Gold</th><th>Silver</th><th>Status</th><th>Last login</th><th></th>'
            . '</tr></thead><tbody>';
        if ($rows === []) {
            return $html . '<tr><td colspan="10" class="center adminMuted">No players found.</td></tr></tbody></table>';
        }
        foreach ($rows as $row) {
            $uid = (int) $row['id'];
            $html .= '<tr>'
                . '<td class="center">' . $uid . '</td>'
                . '<td><a href="admin.php?action=editPlayer&uid=' . $uid . '">' . AdminUi::esc((string) $row['name']) . '</a></td>'
                . '<td>' . AdminUi::esc(Tribes::NAMES[(int) $row['race']] ?? ('#' . (int) $row['race'])) . '</td>'
                . '<td class="center">' . (int) $row['total_pop'] . '</td>'
                . '<td class="center">' . (int) $row['total_villages'] . '</td>'
                . '<td class="center">' . (int) $row['gift_gold'] . ' / ' . (int) $row['bought_gold'] . '</td>'
                . '<td class="center">' . (int) $row['silver'] . '</td>'
                . '<td>' . AdminUi::esc($this->statusLabel((int) $row['access'], (int) $row['hidden'] === 1)) . '</td>'
                . '<td>' . ($row['last_login_time'] > 0 ? TimezoneHelper::autoDateString((int) $row['last_login_time'], true) : '—') . '</td>'
                . '<td class="center"><a href="admin.php?action=editPlayer&uid=' . $uid . '">Edit</a></td>'
                . '</tr>';
        }

        return $html . '</tbody></table>';
    }

    private function statusLabel(int $access, bool $hidden): string
    {
        $label = match ($access) {
            0 => 'Banned',
            1 => 'Player',
            2 => 'Administrator',
            3 => 'Fake user',
            default => 'Access ' . $access,
        };

        return $hidden ? $label . ' (hidden)' : $label;
    }

    private function pager(string $q, int $page, int $pages, int $total): string
    {
        $link = static function (int $target, string $label) use ($q): string {
            $query = http_build_query(array_filter(['action' => 'users', 'q' => $q, 'p' => $target], static fn ($v) => $v !== '' && $v !== null));

            return '<a href="admin.php?' . AdminUi::esc($query) . '">' . AdminUi::esc($label) . '</a>';
        };

        return '<p class="adminMuted">' . $total . ' players, page ' . $page . ' of ' . $pages . ' '
            . ($page > 1 ? $link($page - 1, 'Previous') . ' ' : '')
            . ($page < $pages ? $link($page + 1, 'Next') : '') . '</p>';
    }
}
