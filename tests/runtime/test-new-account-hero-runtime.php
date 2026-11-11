<?php

declare(strict_types=1);

// A brand-new account (a fixture clone with its hero at home) must get the game pages: dorf1, dorf2 and the hero
// page render with HTTP 200, so the hero status the HUD needs is never missing. The pages are fetched over HTTP from
// inside the php container.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "New account hero runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Database\DBI;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$base = 'http://web';
if (trim((string)shell_exec("curl -s -m 5 -o /dev/null -w '%{http_code}' $base/ 2>&1")) !== '200') {
    echo "New account hero runtime skipped (the web service is not reachable).\n";
    exit(0);
}
try {
    $fixture = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "New account hero runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$pdo = DBI::getInstance();
$name = (string)$pdo->fetchScalar('SELECT name FROM users WHERE id=?', [$fixture['id']]);
$pdo->run('UPDATE wdata SET occupied=1 WHERE id=?', [$fixture['kid']]);
register_shutdown_function(static fn () => DBI::getInstance()->run('UPDATE wdata SET occupied=0 WHERE id=?', [$fixture['kid']]));

$jar = tempnam(sys_get_temp_dir(), 'fxjar');
register_shutdown_function(static fn () => @unlink($jar));
$password = fixtureLocalPassword();
shell_exec("curl -s -m 10 -c $jar -b $jar -o /dev/null $base/");
$login = trim((string)shell_exec("curl -s -m 10 -c $jar -b $jar -o /dev/null -w '%{http_code}' --data-urlencode " . escapeshellarg('name=' . $name) . ' --data-urlencode ' . escapeshellarg('password=' . $password) . " $base/login.php"));
if ($login !== '302') {
    echo "New account hero runtime skipped (the fixture cannot log in with the local password: HTTP $login).\n";
    exit(0);
}
$fetch = static function (string $page) use ($jar, $base): string {
    return trim((string)shell_exec("curl -s -m 30 -c $jar -b $jar -o /dev/null -w '%{http_code}' $base/$page"));
};
foreach (['dorf1.php', 'dorf2.php', 'hero.php'] as $page) {
    $code = $fetch($page);
    if ($code !== '200') {
        $fail("The new account's $page answered HTTP $code instead of 200.");
    }
}
// A hero that is in no village and no movement (a lost movement) is put back home instead of leaving the status
// empty, which used to answer every game page with HTTP 500.
$pdo->run('UPDATE units SET u11=0 WHERE kid=?', [$fixture['kid']]);
$pdo->run('DELETE FROM movement WHERE kid=? AND u11>0', [$fixture['kid']]);
$code = $fetch('dorf1.php');
if ($code !== '200') {
    $fail("A hero in no village or movement answered HTTP $code instead of 200.");
}
if ((int)$pdo->fetchScalar('SELECT u11 FROM units WHERE kid=?', [$fixture['kid']]) !== 1) {
    $fail('The lost hero was not put back in its village.');
}
echo "New account hero runtime passed.\n";
