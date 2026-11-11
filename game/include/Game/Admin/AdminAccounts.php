<?php

declare(strict_types=1);

namespace Game\Admin;

use Core\Database\DB;
use Core\Security\Password;
use Model\OptionModel;
use Model\RegisterModel;

/**
 * Who may use the control panel. Support (users.id 0) and Multihunter (2) are built in and always owners; any
 * other administrator is an ordinary users row (no village, hidden from the statistics) with a row in
 * admin_accounts that holds the role. Every check goes through isAdmin()/role(), so Session, the two-factor
 * gate and the panel share one answer; a world installed before the table existed simply has no extra admins.
 */
final class AdminAccounts
{
    public const BUILT_IN = [0, 2];

    /** @var array<int, array{role: string, disabled: bool, mustChange: bool, builtIn: bool}|null> */
    private static array $cache = [];
    /** @var (callable(int): (array{role: string, disabled: bool, mustChange: bool}|null))|null */
    private static $resolver = null;

    private function __construct()
    {
    }

    /** Replaces the database lookup (tests); null restores it. @param (callable(int): (array{role: string, disabled: bool, mustChange: bool}|null))|null $resolver */
    public static function useResolver(?callable $resolver): void
    {
        self::$resolver = $resolver;
        self::$cache = [];
    }

    public static function forget(): void
    {
        self::$cache = [];
    }

    /** @return array{role: string, disabled: bool, mustChange: bool, builtIn: bool}|null */
    public static function record(int $uid): ?array
    {
        if (in_array($uid, self::BUILT_IN, true)) {
            return ['role' => AdminRoles::OWNER, 'disabled' => false, 'mustChange' => false, 'builtIn' => true];
        }
        if ($uid < 3) {
            return null;
        }
        if (!array_key_exists($uid, self::$cache)) {
            $found = self::$resolver !== null ? (self::$resolver)($uid) : self::lookup($uid);
            self::$cache[$uid] = $found === null ? null : $found + ['builtIn' => false];
        }

        return self::$cache[$uid];
    }

    /** Whether this user may use the panel now: a built-in account, or an extra one that is not disabled. */
    public static function isAdmin(int $uid): bool
    {
        $record = self::record($uid);

        return $record !== null && !$record['disabled'];
    }

    /** A disabled extra administrator: the account exists but may not sign in. */
    public static function isDisabled(int $uid): bool
    {
        $record = self::record($uid);

        return $record !== null && $record['disabled'];
    }

    /** owner or moderator for an administrator who may use the panel, otherwise null. */
    public static function role(int $uid): ?string
    {
        return self::isAdmin($uid) ? self::record($uid)['role'] : null;
    }

    /** An account that exists only for the panel and has no village: Support and every extra administrator. */
    public static function isPanelOnly(int $uid): bool
    {
        return $uid === 0 || (self::record($uid) !== null && !in_array($uid, self::BUILT_IN, true));
    }

    public static function mustChangePassword(int $uid): bool
    {
        $record = self::record($uid);

        return $record !== null && !$record['disabled'] && $record['mustChange'];
    }

    public static function clearMustChangePassword(int $uid): void
    {
        DB::getInstance()->run('UPDATE admin_accounts SET must_change_password=0 WHERE uid=?', [$uid]);
        self::forget();
    }

    /** @return array{role: string, disabled: bool, mustChange: bool}|null */
    private static function lookup(int $uid): ?array
    {
        if (!class_exists(DB::class, false)) {
            return null;
        }
        try {
            $statement = DB::getInstance()->run('SELECT role, disabled_at, must_change_password FROM admin_accounts WHERE uid=?', [$uid]);
            $row = $statement ? $statement->get_result()->fetch_assoc() : null;
        } catch (\Throwable $exception) {
            return null;
        }
        if (!is_array($row) || !AdminRoles::valid((string) $row['role'])) {
            return null;
        }

        return ['role' => (string) $row['role'], 'disabled' => $row['disabled_at'] !== null, 'mustChange' => (int) $row['must_change_password'] === 1];
    }

    /**
     * Every administrator, built-in accounts first.
     *
     * @return list<array{uid: int, name: string, role: string, disabled: bool, mustChange: bool, builtIn: bool, createdBy: int, createdAt: int, lastLogin: int}>
     */
    public static function all(): array
    {
        $db = DB::getInstance();
        $statement = $db->run(
            'SELECT u.id, u.name, u.last_login_time, a.role, a.created_by, a.created_at, a.disabled_at, a.must_change_password
             FROM users u LEFT JOIN admin_accounts a ON a.uid=u.id WHERE u.id IN (0, 2) OR a.uid IS NOT NULL ORDER BY u.id'
        );
        $result = $statement ? $statement->get_result() : false;
        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) {
            $builtIn = in_array((int) $row['id'], self::BUILT_IN, true);
            $rows[] = [
                'uid' => (int) $row['id'],
                'name' => (string) $row['name'],
                'role' => $builtIn ? AdminRoles::OWNER : (string) $row['role'],
                'disabled' => !$builtIn && $row['disabled_at'] !== null,
                'mustChange' => !$builtIn && (int) $row['must_change_password'] === 1,
                'builtIn' => $builtIn,
                'createdBy' => (int) $row['created_by'],
                'createdAt' => (int) $row['created_at'],
                'lastLogin' => (int) $row['last_login_time'],
            ];
        }

        return $rows;
    }

    /** Enabled owners, the built-in ones included. */
    public static function enabledOwners(): int
    {
        $extra = (int) DB::getInstance()->fetchScalar("SELECT COUNT(uid) FROM admin_accounts WHERE role='owner' AND disabled_at IS NULL");

        return $extra + count(self::BUILT_IN);
    }

    /**
     * Why an administrator may not be changed this way, or null. Nobody changes a built-in account, themselves,
     * or the last enabled owner.
     *
     * @param 'disable'|'remove'|'role' $change
     */
    public static function refusal(int $target, int $actor, string $change): ?string
    {
        $record = self::record($target);
        if ($record === null) {
            return 'That is not an administrator account.';
        }
        if ($record['builtIn']) {
            return 'Support and Multihunter are built in and cannot be changed here.';
        }
        if ($target === $actor) {
            return 'You cannot do that to your own account.';
        }
        if ($record['role'] === AdminRoles::OWNER && !$record['disabled'] && self::enabledOwners() <= 1) {
            return 'That is the last enabled owner.';
        }

        return null;
    }

    /**
     * Creates a panel-only account with a temporary password the creator hands over once.
     *
     * @return array{uid: int|null, password: string|null, error: string|null}
     */
    public static function create(string $name, string $role, string $email, int $createdBy): array
    {
        $name = trim($name);
        $email = trim($email);
        if (!AdminRoles::valid($role)) {
            return ['uid' => null, 'password' => null, 'error' => 'Choose a role.'];
        }
        if (preg_match('/^[A-Za-z0-9 _.-]{3,15}$/', $name) !== 1) {
            return ['uid' => null, 'password' => null, 'error' => 'The name must be 3 to 15 letters, numbers, spaces, dots, underscores or hyphens.'];
        }
        if ($email !== '' && (strlen($email) > 99 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            return ['uid' => null, 'password' => null, 'error' => 'Enter a valid email address or leave it empty.'];
        }
        $options = new OptionModel();
        if ($options->nameExists($name) || $options->isNameBlackListed($name)) {
            return ['uid' => null, 'password' => null, 'error' => 'That name is taken or not allowed.'];
        }
        $password = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
        $uid = (new RegisterModel())->addUser($name, Password::hash($password), $email, 0, 0, 1, false, true);
        if ($uid === false || $uid < 3) {
            return ['uid' => null, 'password' => null, 'error' => 'That name is taken.'];
        }
        $db = DB::getInstance();
        $db->run('UPDATE users SET hidden=1, email_verified=1 WHERE id=?', [$uid]);
        $db->run('INSERT INTO admin_accounts (uid, role, created_by, created_at) VALUES (?, ?, ?, ?)', [$uid, $role, $createdBy, time()]);
        self::forget();

        return ['uid' => $uid, 'password' => $password, 'error' => null];
    }

    public static function setDisabled(int $uid, bool $disabled): void
    {
        DB::getInstance()->run('UPDATE admin_accounts SET disabled_at=? WHERE uid=?', [$disabled ? time() : null, $uid]);
        self::forget();
    }

    public static function setRole(int $uid, string $role): void
    {
        DB::getInstance()->run('UPDATE admin_accounts SET role=? WHERE uid=?', [$role, $uid]);
        self::forget();
    }

    /** Deletes the administrator and their (village-less) user row; the audit log keeps the name it recorded. */
    public static function remove(int $uid): void
    {
        $db = DB::getInstance();
        $db->run('DELETE FROM admin_accounts WHERE uid=?', [$uid]);
        $db->run('DELETE FROM users WHERE id=? AND id > 2', [$uid]);
        self::forget();
    }
}
