<?php

declare(strict_types=1);

// Creates, changes and removes a throwaway administrator on the real world database: the account is a
// village-less, hidden user row; the role, disable and remove paths work; the guards refuse. The fixture
// is removed at the end (and at shutdown if the test dies).

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Admin accounts runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use Core\Database\DB;
use Game\Admin\AdminAccounts;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$db = DB::getInstance();
$name = 'ZzAdm' . random_int(1000, 9999);
$uid = 0;
register_shutdown_function(static function () use (&$uid, $db): void {
    if ($uid > 2) {
        $db->run('DELETE FROM admin_accounts WHERE uid=?', [$uid]);
        $db->run('DELETE FROM users WHERE id=? AND id > 2', [$uid]);
    }
});

$bad = AdminAccounts::create('x', 'moderator', '', 2);
if ($bad['error'] === null || AdminAccounts::create($name, 'superuser', '', 2)['error'] === null || AdminAccounts::create($name, 'moderator', 'not-an-email', 2)['error'] === null) {
    $fail('A bad name, role or email must be refused.');
}
$created = AdminAccounts::create($name, 'moderator', '', 2);
if ($created['error'] !== null || $created['uid'] === null || strlen((string) $created['password']) < 20) {
    $fail('Creating an administrator failed: ' . json_encode($created));
}
$uid = $created['uid'];
if (AdminAccounts::create($name, 'owner', '', 2)['error'] === null) {
    $fail('A second account with the same name must be refused.');
}
$row = $db->run('SELECT hidden, kid, access, email_verified, password FROM users WHERE id=?', [$uid])->get_result()->fetch_assoc();
if ($row === null || (int) $row['hidden'] !== 1 || (int) $row['kid'] !== 0 || !password_verify((string) $created['password'], (string) $row['password'])) {
    $fail('The account must be hidden, have no village and sign in with the temporary password.');
}
if ((int) $db->fetchScalar('SELECT COUNT(kid) FROM vdata WHERE owner=?', [$uid]) !== 0) {
    $fail('An administrator account must own no village.');
}
if (!AdminAccounts::isAdmin($uid) || AdminAccounts::role($uid) !== 'moderator' || !AdminAccounts::mustChangePassword($uid) || !AdminAccounts::isPanelOnly($uid)) {
    $fail('The new account must be a moderator that has to set a password.');
}
if ((int) $db->fetchScalar('SELECT COUNT(id) FROM users WHERE id=? AND id > 1 AND hidden=0 AND access > 0', [$uid]) !== 0) {
    $fail('The statistics filter must not list the administrator.');
}
AdminAccounts::clearMustChangePassword($uid);
AdminAccounts::setRole($uid, 'owner');
if (AdminAccounts::role($uid) !== 'owner' || AdminAccounts::mustChangePassword($uid)) {
    $fail('Role and password-change flag must update.');
}
if (AdminAccounts::refusal($uid, 2, 'disable') !== null || AdminAccounts::refusal($uid, $uid, 'disable') === null || AdminAccounts::refusal(2, $uid, 'remove') === null) {
    $fail('Another administrator may disable the account; nobody may act on themselves or a built-in account.');
}
AdminAccounts::setDisabled($uid, true);
if (AdminAccounts::isAdmin($uid) || !AdminAccounts::isDisabled($uid) || AdminAccounts::role($uid) !== null) {
    $fail('A disabled account must not be an administrator.');
}
AdminAccounts::setDisabled($uid, false);
if (!AdminAccounts::isAdmin($uid)) {
    $fail('Enabling must restore the account.');
}
$listed = array_column(AdminAccounts::all(), 'uid');
if (!in_array(0, $listed, true) || !in_array(2, $listed, true) || !in_array($uid, $listed, true)) {
    $fail('The list shows the built-in accounts and the new one.');
}
AdminAccounts::remove($uid);
if (AdminAccounts::isAdmin($uid) || (int) $db->fetchScalar('SELECT COUNT(id) FROM users WHERE id=?', [$uid]) !== 0 || (int) $db->fetchScalar('SELECT COUNT(uid) FROM admin_accounts WHERE uid=?', [$uid]) !== 0) {
    $fail('Removing must delete the account and its row.');
}
$uid = 0;
echo "Admin accounts runtime passed.\n";
