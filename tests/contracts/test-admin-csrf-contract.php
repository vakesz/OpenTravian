<?php

declare(strict_types=1);

namespace {
    use Core\Session;

    function failAdminCsrf(string $message): void
    {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    function expectAdminCsrf(bool $condition, string $message): void
    {
        if (!$condition) {
            failAdminCsrf($message);
        }
    }

    // The guard runs against the real Session (a guest session keeps its token in $_SESSION).
    if (!is_file('/travian/game/deployment/include/env.php')) {
        echo "Admin CSRF contract skipped outside the php container.\n";
        exit(0);
    }
    $_SERVER['REQUEST_URI'] = '/admin.php';
    $_SERVER['QUERY_STRING'] = '';
    $_SERVER['PHP_SELF'] = '/admin.php';
    chdir('/travian/game/deployment/public');
    require '/travian/game/deployment/include/env.php';
    require '/travian/game/include/bootstrap.php';

    $root = dirname(__DIR__, 2);
    require_once $root . '/game/include/Game/AdminUi.php';
    require_once $root . '/game/include/admin/include/Core/RequestGuard.php';
    $token = Session::getInstance()->getChecker();

    // Truth table: state-changing GETs are recognised, plain views are not.
    foreach ([
        ['editPlayer', ['section' => 'killHero', 'uid' => '5'], true],
        ['editPlayer', ['section' => 'punishPlayer', 'uid' => '5'], false],
        ['editPlayer', ['uid' => '5'], false],
        ['EDITVILLAGE', ['r' => 'fillResources', 'kid' => '1'], true],
        ['editVillage', ['kid' => '1'], false],
        ['deleting', ['section' => 'deleteNow', 'uid' => '3'], true],
        ['deleting', ['p' => '2'], false],
        ['IPBan', ['del' => '10.0.0.1'], true],
        ['badWords', ['del' => '2'], true],
        ['fixes', ['fixKid' => '12'], true],
        ['cleanup', ['do' => 'deleteReportsXDays'], true],
        ['multiAccount', ['id' => '4'], true],
        ['multiAccount', ['p' => '2'], false],
        ['bannedList', ['section' => 'unban', 'uid' => '9'], true],
        ['reportedMessages', ['see' => '1'], false],
        ['reportedMessages', ['deleteAll' => '1'], true],
        ['playerAccusations', ['handle' => '3'], true],
        ['playerAccusations', [], false],
        ['configurationDetails', [], false],
    ] as [$action, $query, $expected]) {
        expectAdminCsrf(
            AdminRequestGuard::isStateChangingGet($action, $query) === $expected,
            "State-changing GET classification is wrong for $action " . json_encode($query)
        );
    }

    // A state-changing GET is answered with a confirmation form, never executed.
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = ['action' => 'editPlayer', 'section' => 'killHero', 'uid' => '5', 'mpvt_token' => 'stale-from-url'];
    $page = AdminRequestGuard::intercept('editPlayer');
    expectAdminCsrf(is_string($page) && str_contains($page, '<form method="post"'), 'A mutating GET must show a POST confirmation form.');
    expectAdminCsrf(str_contains($page, 'name="section" value="killHero"') && str_contains($page, 'name="uid" value="5"'), 'The confirmation must carry the original parameters.');
    expectAdminCsrf(!str_contains($page, 'stale-from-url') && str_contains($page, 'value="' . $token . '"'), 'The confirmation must use the current token, not the one from the URL.');
    $_GET = ['action' => 'editPlayer', 'uid' => '5'];
    expectAdminCsrf(AdminRequestGuard::intercept('editPlayer') === null, 'A plain view must proceed.');

    // POST needs the token; a confirmed POST is replayed into $_GET without the token.
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['action' => 'editPlayer', 'section' => 'killHero', 'uid' => '5', 'mpvt_token' => 'wrong', AdminRequestGuard::CONFIRM_FIELD => '1'];
    $_GET = [];
    $rejected = AdminRequestGuard::intercept('editPlayer');
    expectAdminCsrf(is_string($rejected) && http_response_code() === 400 && $_GET === [], 'A POST with a wrong token must be rejected and not replayed.');
    http_response_code(200);
    $_POST['mpvt_token'] = $token;
    expectAdminCsrf(AdminRequestGuard::intercept('editPlayer') === null, 'A POST with the right token must proceed.');
    expectAdminCsrf(($_GET['section'] ?? null) === 'killHero' && ($_GET['uid'] ?? null) === '5' && !isset($_GET['mpvt_token']), 'A confirmed POST must be replayed into $_GET without the token.');
    $_POST = ['name' => 'x'];
    $missing = AdminRequestGuard::intercept('anything');
    expectAdminCsrf(is_string($missing), 'A POST without a token must be rejected.');
    http_response_code(200);

    // Older forms receive a token; GET forms and forms that already carry one do not.
    $html = '<form method="get" action="a"><input name="q"></form>'
        . '<form action="b" method=\'POST\'><input name="x"></form>'
        . '<form method="post" action="c">' . \Core\Session::getCheckerInput() . '<input name="y"></form>'
        . '<form METHOD=post action="d"><input name="z"></form>';
    $out = AdminRequestGuard::injectTokens($html);
    expectAdminCsrf(substr_count($out, 'name="mpvt_token"') === 3, 'Exactly the two forms without a token must receive one.');
    expectAdminCsrf(str_contains($out, '<form method="get" action="a"><input name="q">'), 'GET forms must stay untouched.');

    // Source-level guarantees.
    $session = (string)file_get_contents($root . '/game/include/Core/Session.php');
    expectAdminCsrf(str_contains($session, 'hash_equals(self::sessionToken(), trim($submitted))') && !str_contains($session, 'strcmp('), 'The checker must be compared with hash_equals.');
    $config = (string)file_get_contents($root . '/game/include/admin/include/Controllers/ConfigurationDetailsCtrl.php');
    expectAdminCsrf(!preg_match('/\$_GET\[.(registerClosed|activation|maintenance|needPreregistrationCode|fakeAccountProcess|savePlayableTribes|playableTribes)/', $config), 'The settings toggles must not read $_GET.');
    expectAdminCsrf(str_contains($config, 'isPost() || !\Core\Session::validateChecker()') && substr_count($config, 'method="post"') >= 2, 'The settings toggles must be POST forms behind the session checker.');
    $dispatcher = (string)file_get_contents($root . '/game/include/admin/include/Core/Dispatcher.php');
    expectAdminCsrf(!str_contains($dispatcher, "\$_GET['toggleInterruption']") && str_contains($dispatcher, 'AdminRequestGuard::intercept('), 'The dispatcher must gate every request and must not toggle live-world changes by GET.');
    $webService = (string)file_get_contents($root . '/game/include/Core/Helper/WebService.php');
    expectAdminCsrf(!str_contains($webService, 'Authorization'), 'Redirects must not send a hard-coded Authorization header.');
    foreach (array_keys(AdminRequestGuard::mutatingGetRules()) as $action) {
        $found = false;
        foreach (glob($root . '/game/include/admin/include/Controllers/*Ctrl.php') ?: [] as $file) {
            $found = $found || strtolower(basename($file, 'Ctrl.php')) === $action;
        }
        expectAdminCsrf($found, "The guard lists $action but no such admin controller exists.");
    }

    echo "Admin CSRF contract passed.\n";
}
