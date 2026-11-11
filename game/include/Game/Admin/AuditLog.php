<?php

declare(strict_types=1);

namespace Game\Admin;

use Core\Database\DB;
use Core\Helper\WebService;
use Core\Session;

/**
 * The administrator audit trail: who (account and name), when, which panel
 * action or sign-in event, which target and from which IP. Every state-changing
 * panel request and every administrator sign-in and two-factor event writes one
 * row to admin_log. Request values are never stored (only the names of the
 * submitted fields and a whitelist of identifying parameters), so passwords and
 * codes cannot reach the log.
 */
final class AuditLog
{
    public const PAGE_SIZE = 50;

    /** Request parameters that identify what an action was applied to. */
    private const TARGET_KEYS = ['uid', 'id', 'kid', 'aid', 'did', 'name', 'player', 'tag', 'section', 'r', 'see', 'delete', 'deleteAll', 'del', 'handle', 'table', 'do', 'fix', 'fixKid', 'unban', 'provider'];

    private function __construct()
    {
    }

    /**
     * Writes one row. The actor defaults to the signed-in administrator; sign-in
     * events pass the account explicitly. A failure here never interrupts the
     * request (the table is world data and may be missing in an old world).
     */
    public static function record(string $action, string $target = '', string $details = '', ?int $adminUid = null, ?string $adminName = null): void
    {
        try {
            if ($adminUid === null) {
                $session = Session::getInstance();
                $known = $session->isValid() && $session->isAdmin();
                $adminUid = $known ? (int) $session->getAdminUid() : -1;
                $adminName = $adminName ?? ($known ? (string) $session->getName() : '');
            }
            DB::getInstance()->run(
                'INSERT INTO admin_log (time, admin_uid, admin_name, action, target, details, ip) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    time(),
                    $adminUid,
                    mb_substr((string) $adminName, 0, 100),
                    mb_substr($action, 0, 64),
                    mb_substr($target, 0, 255),
                    mb_substr($details, 0, 2000),
                    mb_substr((string) WebService::ipAddress(), 0, 100),
                ]
            );
        } catch (\Throwable $exception) {
            error_log('admin audit log unavailable: ' . $exception->getMessage());
        }
    }

    /**
     * What a panel request was applied to, as "key=value" pairs of whitelisted,
     * short, plain parameters.
     *
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     */
    public static function targetFromRequest(array $query, array $post): string
    {
        $pairs = [];
        foreach (self::TARGET_KEYS as $key) {
            $value = $post[$key] ?? $query[$key] ?? null;
            if (is_scalar($value) && preg_match('/^[\w .\-@|]{1,40}$/u', (string) $value) === 1) {
                $pairs[] = $key . '=' . $value;
            }
        }

        return implode(' ', $pairs);
    }

    /**
     * The names (never the values) of the submitted fields, minus the CSRF token.
     *
     * @param array<string, mixed> $post
     * @param string $checkerName the CSRF field (Session::getCheckerName())
     */
    public static function fieldNames(array $post, string $checkerName = 'mpvt_token'): string
    {
        $names = array_values(array_filter(
            array_map('strval', array_keys($post)),
            static fn (string $name): bool => $name !== $checkerName && $name !== 'adminConfirmed'
        ));

        return $names === [] ? '' : 'fields: ' . implode(', ', $names);
    }

    /**
     * WHERE clause and bound parameters for the filter form. Every value is bound.
     *
     * @param array<string, mixed> $filters admin, event (the action column), eventPrefix (actions starting with it), target, ip, from, to (Y-m-d)
     * @return array{0: string, 1: list<int|string>}
     */
    public static function buildWhere(array $filters): array
    {
        $clauses = [];
        $params = [];
        $text = static fn (string $key): string => is_string($filters[$key] ?? null) ? trim($filters[$key]) : '';
        $like = static fn (string $value): string => addcslashes($value, '%_\\');

        $admin = $text('admin');
        if ($admin !== '') {
            if (ctype_digit($admin)) {
                $clauses[] = 'admin_uid = ?';
                $params[] = (int) $admin;
            } else {
                $clauses[] = 'admin_name LIKE ?';
                $params[] = '%' . $like($admin) . '%';
            }
        }
        if ($text('event') !== '') {
            $clauses[] = 'action = ?';
            $params[] = $text('event');
        }
        if ($text('eventPrefix') !== '') {
            $clauses[] = 'action LIKE ?';
            $params[] = $like($text('eventPrefix')) . '%';
        }
        if ($text('target') !== '') {
            $clauses[] = 'target LIKE ?';
            $params[] = '%' . $like($text('target')) . '%';
        }
        if ($text('ip') !== '') {
            $clauses[] = 'ip LIKE ?';
            $params[] = $like($text('ip')) . '%';
        }
        foreach (['from' => ['time >= ?', ' 00:00:00'], 'to' => ['time <= ?', ' 23:59:59']] as $key => [$clause, $suffix]) {
            $date = $text($key);
            $stamp = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? strtotime($date . $suffix) : false;
            if ($stamp !== false) {
                $clauses[] = $clause;
                $params[] = $stamp;
            }
        }

        return [$clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses), $params];
    }

    /**
     * One page of rows, newest first, and the total that matches.
     *
     * @param array<string, mixed> $filters
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public static function search(array $filters, int $page): array
    {
        [$where, $params] = self::buildWhere($filters);
        $db = DB::getInstance();
        $total = (int) $db->fetchScalar('SELECT COUNT(id) FROM admin_log' . $where, $params === [] ? null : $params);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min(max(1, $page), $pages);
        $statement = $db->run(
            'SELECT id, time, admin_uid, admin_name, action, target, details, ip FROM admin_log' . $where
            . ' ORDER BY id DESC LIMIT ' . self::PAGE_SIZE . ' OFFSET ' . (($page - 1) * self::PAGE_SIZE),
            $params
        );
        $rows = $statement ? $statement->get_result()->fetch_all(MYSQLI_ASSOC) : [];

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** @return list<string> the distinct actions seen so far, for the filter menu */
    public static function actions(): array
    {
        $result = DB::getInstance()->query('SELECT DISTINCT action FROM admin_log ORDER BY action');

        return $result ? array_map('strval', array_column($result->fetch_all(MYSQLI_ASSOC), 'action')) : [];
    }

    /** Deletes entries older than the retention setting (days; 0 keeps everything) and returns how many went. */
    public static function prune(int $retentionDays, ?int $now = null): int
    {
        if ($retentionDays <= 0) {
            return 0;
        }
        $statement = DB::getInstance()->run('DELETE FROM admin_log WHERE time < ?', [($now ?? time()) - $retentionDays * 86400]);

        return $statement ? max(0, (int) $statement->affected_rows) : 0;
    }
}
