<?php

declare(strict_types=1);

namespace Game\SocialAuth;

use Core\Database\DBI;
use Core\Helper\TimezoneHelper;
use Game\Admin\AuditLog;
use Game\AdminUi;

/** The control panel's view of linked social sign-ins: the identities, an unlink button per row, and the social.* audit rows. */
final class AdminView
{
    public const PAGE_SIZE = 50;

    private function __construct()
    {
    }

    /**
     * One page of identities, most recent sign-in first, with the player's name.
     *
     * @param array<string, mixed> $filters player (name or account id), provider, email
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public static function search(array $filters, int $page): array
    {
        $clauses = [];
        $params = [];
        $text = static fn (string $key): string => is_string($filters[$key] ?? null) ? trim($filters[$key]) : '';
        $like = static fn (string $value): string => addcslashes($value, '%_\\');
        if ($text('player') !== '') {
            if (ctype_digit($text('player'))) {
                $clauses[] = 'i.uid = ?';
                $params[] = (int) $text('player');
            } else {
                $clauses[] = 'u.name LIKE ?';
                $params[] = '%' . $like($text('player')) . '%';
            }
        }
        if (Provider::tryFrom($text('provider')) !== null) {
            $clauses[] = 'i.provider = ?';
            $params[] = $text('provider');
        }
        if ($text('email') !== '') {
            $clauses[] = 'i.email LIKE ?';
            $params[] = '%' . $like($text('email')) . '%';
        }
        $where = $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses);
        $from = ' FROM account_oauth_identity i INNER JOIN users u ON u.id = i.uid' . $where;
        $db = DBI::getInstance();
        $total = (int) $db->fetchScalar('SELECT COUNT(i.id)' . $from, $params === [] ? null : $params, 0);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min(max(1, $page), $pages);
        $rows = $db->run(
            'SELECT i.uid, u.name, i.provider, i.email, i.email_verified, i.created, i.last_login' . $from
            . ' ORDER BY i.last_login DESC, i.id DESC LIMIT ' . self::PAGE_SIZE . ' OFFSET ' . (($page - 1) * self::PAGE_SIZE),
            $params
        )->fetchAll(\PDO::FETCH_ASSOC);

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param bool $withPlayer whether to show the player column (the all-players page)
     */
    public static function identitiesTable(array $rows, bool $withPlayer): string
    {
        $body = '';
        foreach ($rows as $row) {
            $uid = (int) $row['uid'];
            $provider = Provider::tryFrom((string) $row['provider']);
            $label = $provider === null ? (string) $row['provider'] : $provider->label();
            $body .= '<tr>'
                . ($withPlayer ? '<td><a href="admin.php?action=editPlayer&amp;uid=' . $uid . '">' . AdminUi::esc($row['name']) . '</a> (' . $uid . ')</td>' : '')
                . '<td>' . AdminUi::esc($label) . '</td><td>' . AdminUi::esc($row['email']) . '</td><td>' . ((int) $row['email_verified'] === 1 ? 'Yes' : 'No')
                . '</td><td>' . AdminUi::esc(TimezoneHelper::autoDateString((int) $row['created'], true))
                . '</td><td>' . AdminUi::esc(TimezoneHelper::autoDateString((int) $row['last_login'], true))
                . '</td><td><form method="post" action="/admin.php?action=socialSignIns" class="adminInline"'
                . ' onsubmit="return confirm(' . AdminUi::esc(json_encode('Unlink this ' . $label . ' account from ' . $row['name'] . '?')) . ')">'
                . '<input type="hidden" name="uid" value="' . $uid . '"><input type="hidden" name="provider" value="' . AdminUi::esc($row['provider']) . '">'
                . AdminUi::button('Unlink', 'grey', ['type' => 'submit']) . '</form></td></tr>';
        }
        if ($body === '') {
            $body = '<tr><td colspan="' . ($withPlayer ? 7 : 6) . '" class="center"><span class="adminMuted">No linked social accounts.</span></td></tr>';
        }

        return '<table class="adminTable"><thead><tr>' . ($withPlayer ? '<th>Player</th>' : '') . '<th>Provider</th><th>Email</th><th>Verified</th><th>Linked</th><th>Last sign-in</th><th></th></tr></thead><tbody>'
            . $body . '</tbody></table>';
    }

    /** The social.* audit rows, newest first: for one player, or for everybody. */
    public static function eventsTable(?int $uid, int $limit): string
    {
        if ($uid === null) {
            $rows = array_slice(AuditLog::search(['eventPrefix' => 'social.'], 1)['rows'], 0, $limit);
        } else {
            // The target is "uid=<id> provider=<name>"; the space keeps uid=7 from matching uid=70.
            $rows = DBI::getInstance()->run(
                "SELECT time, admin_uid, admin_name, action, target, details, ip FROM admin_log WHERE action LIKE 'social.%' AND (target = ? OR target LIKE ?) ORDER BY id DESC LIMIT " . $limit,
                ['uid=' . $uid, 'uid=' . $uid . ' %']
            )->fetchAll(\PDO::FETCH_ASSOC);
        }
        $body = '';
        foreach ($rows as $row) {
            $body .= '<tr><td>' . AdminUi::esc(TimezoneHelper::autoDateString((int) $row['time'], true)) . '</td><td>' . AdminUi::esc($row['action'])
                . '</td><td>' . AdminUi::esc($row['target']) . '</td><td>' . AdminUi::esc($row['details'])
                . '</td><td>' . AdminUi::esc($row['admin_name']) . ' (' . (int) $row['admin_uid'] . ')</td><td>' . AdminUi::esc($row['ip']) . '</td></tr>';
        }
        if ($body === '') {
            $body = '<tr><td colspan="6" class="center"><span class="adminMuted">No social sign-in events yet.</span></td></tr>';
        }

        return '<table class="adminTable"><thead><tr><th>Date</th><th>Event</th><th>Target</th><th>Details</th><th>Account</th><th>IP address</th></tr></thead><tbody>'
            . $body . '</tbody></table>';
    }
}
