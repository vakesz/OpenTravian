<?php

use Core\Helper\TimezoneHelper;
use Core\Session;
use Game\Admin\AdminAccounts;
use Game\Admin\AdminRoles;
use Game\AdminUi;

/**
 * Owners manage who can use the control panel: create an administrator with a temporary password, change a
 * role, disable, enable or remove. Support and Multihunter are built in. Every change is a POST with the CSRF
 * token and a confirmation, and is audit-logged (Dispatcher records the request, AdminLog the outcome).
 */
class AdminAccountsCtrl
{
    private ?string $error = null;
    private ?string $saved = null;
    private ?string $temporaryPassword = null;

    public function __construct()
    {
        if (\Core\Helper\WebService::isPost() && Session::validateChecker()) {
            $this->handlePost((int) Session::getInstance()->getAdminUid());
        }
        Dispatcher::getInstance()->appendContent(AdminUi::page(
            'Admin accounts',
            'Who can use this control panel. An owner can do everything; a moderator can do everything except manage these accounts, payments, gold codes and the round reset. A new account gets a temporary password that its owner must replace at the first sign-in; two-factor sign-in follows the global setting.',
            ($this->error !== null ? AdminUi::notice($this->error, 'error') : '')
            . ($this->saved !== null ? AdminUi::notice($this->saved, 'success') : '')
            . ($this->temporaryPassword !== null ? AdminUi::notice('Temporary password: ' . $this->temporaryPassword . ' (shown once; hand it over now, it must be replaced at the first sign-in)', 'warning') : '')
            . $this->table((int) Session::getInstance()->getAdminUid())
            . $this->createForm()
        ));
    }

    private function handlePost(int $actor): void
    {
        $action = is_string($_POST['do'] ?? null) ? $_POST['do'] : '';
        if ($action === 'create') {
            $created = AdminAccounts::create((string) ($_POST['name'] ?? ''), (string) ($_POST['role'] ?? ''), (string) ($_POST['email'] ?? ''), $actor);
            if ($created['error'] !== null) {
                $this->error = $created['error'];

                return;
            }
            \AdminLog::getInstance()->addLog('Created the ' . (string) ($_POST['role'] ?? '') . ' account ' . trim((string) $_POST['name']) . ' (' . $created['uid'] . ').');
            $this->saved = 'Account created.';
            $this->temporaryPassword = $created['password'];

            return;
        }
        $uid = is_string($_POST['uid'] ?? null) && ctype_digit($_POST['uid']) ? (int) $_POST['uid'] : -1;
        $change = match ($action) {
            'disable', 'enable' => 'disable',
            'remove' => 'remove',
            'role' => 'role',
            default => null,
        };
        if ($change === null) {
            $this->error = 'Unknown action.';

            return;
        }
        // Enabling is always allowed for an extra account; the other changes have to pass the guards.
        $refusal = $action === 'enable' ? (AdminAccounts::record($uid) === null || AdminAccounts::record($uid)['builtIn'] ? 'That is not an extra administrator account.' : null)
            : AdminAccounts::refusal($uid, $actor, $change);
        if ($refusal !== null) {
            $this->error = $refusal;

            return;
        }
        $name = (string) (\Core\Database\DB::getInstance()->fetchScalar('SELECT name FROM users WHERE id=?', [$uid]) ?: $uid);
        switch ($action) {
            case 'disable':
                AdminAccounts::setDisabled($uid, true);
                $this->saved = $name . ' is disabled and signed out.';
                break;
            case 'enable':
                AdminAccounts::setDisabled($uid, false);
                $this->saved = $name . ' is enabled.';
                break;
            case 'remove':
                AdminAccounts::remove($uid);
                $this->saved = $name . ' was removed.';
                break;
            case 'role':
                $role = (string) ($_POST['role'] ?? '');
                if (!AdminRoles::valid($role)) {
                    $this->error = 'Choose a role.';

                    return;
                }
                AdminAccounts::setRole($uid, $role);
                $this->saved = $name . ' is now ' . AdminRoles::label($role) . '.';
                break;
        }
        \AdminLog::getInstance()->addLog($this->saved . ' (' . $uid . ')');
    }

    private function table(int $actor): string
    {
        $rows = '';
        foreach (AdminAccounts::all() as $admin) {
            $status = $admin['builtIn'] ? 'Built in' : ($admin['disabled'] ? 'Disabled' : ($admin['mustChange'] ? 'Must set a password' : 'Active'));
            $controls = '';
            if (!$admin['builtIn']) {
                $options = '';
                foreach (AdminRoles::ALL as $role) {
                    $options .= '<option value="' . $role . '"' . ($role === $admin['role'] ? ' selected' : '') . '>' . AdminRoles::label($role) . '</option>';
                }
                $controls = $this->form($admin['uid'], 'role', '<select name="role" aria-label="Role of ' . AdminUi::esc($admin['name']) . '">' . $options . '</select> ' . AdminUi::confirmButton('Set role', 'Change the role of ' . $admin['name'] . '?', 'grey'))
                    . $this->form($admin['uid'], $admin['disabled'] ? 'enable' : 'disable', AdminUi::confirmButton($admin['disabled'] ? 'Enable' : 'Disable', ($admin['disabled'] ? 'Enable ' : 'Disable and sign out ') . $admin['name'] . '?', 'grey'))
                    . $this->form($admin['uid'], 'remove', AdminUi::confirmButton('Remove', 'Remove ' . $admin['name'] . ' for good? This deletes the account.', 'grey'));
                if ($admin['uid'] === $actor) {
                    $controls = '<span class="adminMuted">This is you.</span>';
                }
            }
            $rows .= '<tr><td>' . AdminUi::esc($admin['name']) . ' (' . $admin['uid'] . ')</td><td>' . AdminRoles::label($admin['role']) . '</td><td>' . $status . '</td><td>'
                . ($admin['lastLogin'] > 0 ? AdminUi::esc(TimezoneHelper::autoDateString($admin['lastLogin'], true)) : '<span class="adminMuted">never</span>')
                . '</td><td>' . $controls . '</td></tr>';
        }

        return AdminUi::box('Administrators', '<table class="adminTable"><thead><tr><th>Account</th><th>Role</th><th>Status</th><th>Last sign-in</th><th></th></tr></thead><tbody>' . $rows . '</tbody></table>');
    }

    private function form(int $uid, string $do, string $inner): string
    {
        return '<form method="post" action="admin.php?action=adminAccounts" class="adminInline">' . Session::getCheckerInput()
            . '<input type="hidden" name="uid" value="' . $uid . '"><input type="hidden" name="do" value="' . $do . '">' . $inner . '</form>';
    }

    private function createForm(): string
    {
        return '<form method="post" action="admin.php?action=adminAccounts">' . Session::getCheckerInput() . '<input type="hidden" name="do" value="create">'
            . AdminUi::box(
                'Add an administrator',
                AdminUi::input('name', 'Name', '', ['maxlength' => 15, 'required' => true, 'autocomplete' => 'off'], '3 to 15 characters. This is the name they sign in with, on the normal game login.')
                . AdminUi::select('role', 'Role', ['moderator' => 'Moderator', 'owner' => 'Owner'], 'moderator')
                . AdminUi::input('email', 'Email (optional)', '', ['type' => 'email', 'maxlength' => 99, 'autocomplete' => 'off'])
            )
            . AdminUi::confirmButton('Create account', 'Create this administrator account?', 'green') . '</form>';
    }
}
