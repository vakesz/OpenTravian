<?php

declare(strict_types=1);

use Game\Admin\AuditLog;

$root = dirname(__DIR__, 2);

function failAdminAuditLogContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function expectAdminAuditLog(bool $condition, string $message): void
{
    if (!$condition) {
        failAdminAuditLogContract($message);
    }
}

require_once dirname(__DIR__, 2) . '/game/include/Game/Admin/AuditLog.php';

// buildWhere: every filter is bound, never concatenated.
[$where, $params] = AuditLog::buildWhere([]);
expectAdminAuditLog($where === '' && $params === [], 'No filters must give no WHERE clause.');

[$where, $params] = AuditLog::buildWhere(['admin' => '2', 'event' => 'editPlayer', 'target' => 'uid=5', 'ip' => '192.168', 'from' => '2026-11-01', 'to' => '2026-11-07']);
expectAdminAuditLog(
    $where === ' WHERE admin_uid = ? AND action = ? AND target LIKE ? AND ip LIKE ? AND time >= ? AND time <= ?'
    && $params[0] === 2 && $params[1] === 'editPlayer' && $params[2] === '%uid=5%' && $params[3] === '192.168%'
    && $params[4] === strtotime('2026-11-01 00:00:00') && $params[5] === strtotime('2026-11-07 23:59:59'),
    'Filters must become bound clauses: ' . $where
);
[$where, $params] = AuditLog::buildWhere(['admin' => 'Multi_hunter', 'target' => "100%' OR 1=1 --"]);
expectAdminAuditLog(
    $where === ' WHERE admin_name LIKE ? AND target LIKE ?'
    && $params[0] === '%Multi\_hunter%' && $params[1] === "%100\\%' OR 1=1 --%" && !str_contains($where, 'OR 1=1'),
    'A name is a LIKE search with wildcards escaped, and values never reach the SQL text.'
);
[$where, $params] = AuditLog::buildWhere(['from' => 'yesterday', 'to' => '2026-13-45x', 'event' => '  ', 'admin' => ['x']]);
expectAdminAuditLog($where === '' && $params === [], 'Malformed dates, blanks and non-string values must be ignored.');

// targetFromRequest and fieldNames: identifying parameters and field names, never values.
$target = AuditLog::targetFromRequest(['section' => 'killHero', 'uid' => '5', 'secret' => 'x', 'page' => '3'], ['kid' => '123', 'name' => 'Some Player', 'password' => 'hunter2']);
expectAdminAuditLog($target === 'uid=5 kid=123 name=Some Player section=killHero', 'The target must hold whitelisted parameters only, got: ' . $target);
expectAdminAuditLog(AuditLog::targetFromRequest(['uid' => str_repeat('9', 60), 'id' => "1\n2"], []) === '', 'Long or multi-line values must be dropped.');
expectAdminAuditLog(AuditLog::targetFromRequest(['uid' => '5'], ['uid' => '7']) === 'uid=7', 'A posted value must win over the query string.');
$fields = AuditLog::fieldNames(['mode' => 'required', 'mpvt_token' => 'abc', 'adminConfirmed' => '1', 'code' => '123456']);
expectAdminAuditLog($fields === 'fields: mode, code' && !str_contains($fields, 'required') && !str_contains($fields, '123456'), 'Only field names, minus the CSRF token, may be logged: ' . $fields);
expectAdminAuditLog(AuditLog::fieldNames([]) === '', 'No fields must give an empty detail.');

expectAdminAuditLog(AuditLog::targetFromRequest([], ['table' => 'movement', 'id' => '12']) === 'id=12 table=movement', 'A requeued task is identified by its table and id.');

// The audit trail survives the round reset that empties the other world tables.
$roundReset = (string) file_get_contents($root . '/game/include/Game/Round/RoundReset.php');
expectAdminAuditLog(preg_match("/WIPED_TABLES = \\[(.*?)\\];/s", $roundReset, $wiped) === 1 && !str_contains($wiped[1], "'admin_log'"), 'The round reset must not empty the audit log.');

// Wiring and schema.
$schema = (string) file_get_contents($root . '/database/world.sql');
expectAdminAuditLog(
    preg_match('/CREATE TABLE `admin_log` \((.*?)\) ENGINE/s', $schema, $table) === 1
    && array_filter(['time', 'admin_uid', 'admin_name', 'action', 'target', 'details', 'ip'], static fn (string $column): bool => !str_contains($table[1], '`' . $column . '`')) === []
    && str_contains($table[1], 'KEY (`admin_uid`, `time`)') && str_contains($table[1], 'KEY (`action`, `time`)'),
    'world.sql must define admin_log with who, when, action, target, details and IP, indexed by time, admin and action.'
);
$dispatcher = (string) file_get_contents($root . '/game/include/admin/include/Core/Dispatcher.php');
expectAdminAuditLog(
    str_contains($dispatcher, 'if (WebService::isPost()) {')
    && str_contains($dispatcher, 'AuditLog::record($handlerName, AuditLog::targetFromRequest($_GET, $_POST), AuditLog::fieldNames($_POST, Session::getCheckerName()));')
    && strpos($dispatcher, 'AuditLog::record($handlerName') < strpos($dispatcher, 'require($this->getHandleFileLocation($handler));'),
    'Every state-changing panel request must be recorded before its controller runs.'
);
$session = (string) file_get_contents($root . '/game/include/Core/Session.php');
$login = (string) file_get_contents($root . '/game/include/Controller/LoginCtrl.php');
foreach (["AuditLog::record('sign-in'", "AuditLog::record('sign-in-failed'", "AuditLog::record('sign-in-refused'", "'two-factor-passed' : 'two-factor-failed'", "AuditLog::record('two-factor-throttled'", "AuditLog::record('two-factor-enrolled'"] as $needle) {
    expectAdminAuditLog(str_contains($session . $login, $needle), "Sign-in and two-factor events must be audited: missing {$needle}");
}
$controller = (string) file_get_contents($root . '/game/include/admin/include/Controllers/AdminLogCtrl.php');
expectAdminAuditLog(
    str_contains($controller, "'event'") && !str_contains($controller, "'action', 'target'") && str_contains($controller, 'AuditLog::search($filters, $page)'),
    'The viewer must filter through AuditLog::search with the event key (action selects the panel page).'
);
expectAdminAuditLog(!str_contains($controller, 'AuditLog::record') && !str_contains($controller, 'addLog('), 'Viewing the log must not be logged.');

echo "Admin audit log contract passed.\n";
