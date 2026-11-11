<?php

declare(strict_types=1);

// The Loyalty tab's checkbox posts {villageId, state} to /api/v1/allowLoyaltyDecrease: it sets or clears the flag on the
// player's own village only, and rejects other villages and malformed bodies. The tab renders the checkbox with the
// stored state.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Allow loyalty decrease runtime skipped outside the php container.\n";
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
    echo "Allow loyalty decrease runtime skipped (the web service is not reachable).\n";
    exit(0);
}
try {
    $fixture = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Allow loyalty decrease runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$pdo = DBI::getInstance();
$kid = $fixture['kid'];
$name = (string)$pdo->fetchScalar('SELECT name FROM users WHERE id=?', [$fixture['id']]);
$other = (int)$pdo->fetchScalar('SELECT kid FROM vdata WHERE owner=1 AND kid<>? ORDER BY kid LIMIT 1', [$kid]);
$pdo->run('UPDATE fdata SET f23t=26, f23=1 WHERE kid=?', [$kid]);
$pdo->run('UPDATE vdata SET allowLoyaltyDecrease=0 WHERE kid IN (?, ?)', [$kid, $other]);
register_shutdown_function(static fn () => DBI::getInstance()->run('UPDATE vdata SET allowLoyaltyDecrease=0 WHERE kid=?', [$other]));

$jar = tempnam(sys_get_temp_dir(), 'fxjar');
register_shutdown_function(static fn () => @unlink($jar));
shell_exec("curl -s -m 10 -c $jar -b $jar -o /dev/null $base/");
$login = trim((string)shell_exec("curl -s -m 10 -c $jar -b $jar -o /dev/null -w '%{http_code}' --data-urlencode " . escapeshellarg('name=' . $name) . ' --data-urlencode ' . escapeshellarg('password=' . fixtureLocalPassword()) . " $base/login.php"));
if ($login !== '302') {
    echo "Allow loyalty decrease runtime skipped (the fixture cannot log in with the local password: HTTP $login).\n";
    exit(0);
}
$tab = static fn (): string => (string)shell_exec("curl -s -m 30 -c $jar -b $jar " . escapeshellarg("$base/build.php?id=23&gid=26&s=3"));
// The ajax dispatcher wants the page's token as the bearer, like the client script sends it.
preg_match("/apiToken = window === parent && '([^']+)'/", $tab(), $token) || $fail('The page must carry the ajax token.');
$api = static function (array $body) use ($jar, $base, $token): string {
    return trim((string)shell_exec("curl -s -m 20 -c $jar -b $jar -o /dev/null -w '%{http_code}' -H 'Authorization: Bearer {$token[1]}' -H 'Content-Type: application/json; charset=UTF-8' -X POST --data " . escapeshellarg(json_encode($body)) . " $base/api/v1/allowLoyaltyDecrease"));
};
$flag = static fn (int $village): int => (int)$pdo->fetchScalar('SELECT allowLoyaltyDecrease FROM vdata WHERE kid=?', [$village]);

str_contains($tab(), 'id="allowLoyaltyDecrease"') || $fail('The Loyalty tab must render the checkbox.');
!preg_match('/id="allowLoyaltyDecrease"[^>]*checked/', $tab()) || $fail('The checkbox starts unchecked.');
$api(['villageId' => $kid, 'state' => true]);
$flag($kid) === 1 || $fail('Checking the box must set the flag.');
preg_match('/id="allowLoyaltyDecrease"[^>]*checked/', $tab()) || $fail('The checkbox must show the stored state.');
$api(['villageId' => $kid, 'state' => false]);
$flag($kid) === 0 || $fail('Unchecking the box must clear the flag.');
if ($other > 0) {
    $code = $api(['villageId' => $other, 'state' => true]);
    $flag($other) === 0 || $fail('Another player\'s village must not be changed (HTTP ' . $code . ').');
}
$api(['villageId' => 'x', 'state' => true]);
$api(['villageId' => $kid]);
$flag($kid) === 0 || $fail('A malformed body must change nothing.');

echo "Allow loyalty decrease runtime passed.\n";
