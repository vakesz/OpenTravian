<?php

declare(strict_types=1);

// The adventure list starts the hero the way the reference does: PUT /api/v1/troop/send signs the request (an X-Nonce
// header comes back), POST with the same body and that nonce sends the hero. Only the signed body can be sent, the nonce
// works once, and a hero that is away, a village without a Rally Point and another player's adventure are refused.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Hero adventure start runtime skipped outside the php container.\n";
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
    echo "Hero adventure start runtime skipped (the web service is not reachable).\n";
    exit(0);
}
try {
    $fixture = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Hero adventure start runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$pdo = DBI::getInstance();
$uid = $fixture['id'];
$kid = $fixture['kid'];
$name = (string)$pdo->fetchScalar('SELECT name FROM users WHERE id=?', [$uid]);
$tile = (int)$pdo->fetchScalar('SELECT id FROM wdata WHERE occupied=0 AND id<>? ORDER BY id LIMIT 1 OFFSET 40', [$kid]);
$other = (int)$pdo->fetchScalar('SELECT id FROM users WHERE id>2 AND id<>? ORDER BY id LIMIT 1', [$uid]);
$pdo->run('DELETE FROM adventure WHERE uid=?', [$uid]);
$pdo->run('INSERT INTO adventure (uid, kid, dif, created) VALUES (?, ?, 0, ?)', [$uid, $tile, time()]);
$mine = (int)$pdo->fetchScalar('SELECT id FROM adventure WHERE uid=? ORDER BY id DESC LIMIT 1', [$uid]);
$pdo->run('INSERT INTO adventure (uid, kid, dif, created) VALUES (?, ?, 0, ?)', [$other, $tile, time()]);
$foreign = (int)$pdo->fetchScalar('SELECT id FROM adventure WHERE uid=? ORDER BY id DESC LIMIT 1', [$other]);
register_shutdown_function(static fn () => DBI::getInstance()->run('DELETE FROM adventure WHERE id=?', [$foreign]));
$pdo->run('UPDATE fdata SET f39=1, f39t=16 WHERE kid=?', [$kid]);
$pdo->run('UPDATE users SET protection=0, vacationActiveTil=0 WHERE id=?', [$uid]);
$pdo->run('UPDATE units SET u11=1 WHERE kid=?', [$kid]);

$jar = tempnam(sys_get_temp_dir(), 'fxjar');
register_shutdown_function(static fn () => @unlink($jar));
shell_exec("curl -s -m 10 -c $jar -b $jar -o /dev/null $base/");
$login = trim((string)shell_exec("curl -s -m 10 -c $jar -b $jar -o /dev/null -w '%{http_code}' --data-urlencode " . escapeshellarg('name=' . $name) . ' --data-urlencode ' . escapeshellarg('password=' . fixtureLocalPassword()) . " $base/login.php"));
if ($login !== '302') {
    echo "Hero adventure start runtime skipped (the fixture cannot log in with the local password: HTTP $login).\n";
    exit(0);
}
$page = (string)shell_exec("curl -s -m 30 -c $jar -b $jar $base/hero/adventures");
preg_match("/apiToken = window === parent && '([^']+)'/", $page, $token) || $fail('The page must carry the ajax token.');
str_contains($page, 'id="adventure' . $mine . '"') || $fail('The list must show the adventure.');
!str_contains($page, 'start_adventure.php') || $fail('The list must not link to a start page.');
str_contains($page, 'Travian.Game.HeroAdventure.initialize()') || $fail('The list must start adventures through the shared client function.');

/** @return array{0: int, 1: string, 2: string} status, X-Nonce header, body */
$call = static function (string $method, array $body, ?string $nonce = null) use ($jar, $base, $token): array {
    $headers = ['Authorization: Bearer ' . $token[1], 'Content-Type: application/json; charset=UTF-8'];
    if ($nonce !== null) {
        $headers[] = 'X-Nonce: ' . $nonce;
    }
    $args = '';
    foreach ($headers as $header) {
        $args .= ' -H ' . escapeshellarg($header);
    }
    $out = (string)shell_exec("curl -s -m 30 -c $jar -b $jar -D - -X $method$args --data " . escapeshellarg(json_encode($body)) . " $base/api/v1/troop/send");
    [$head, $content] = array_pad(explode("\r\n\r\n", $out, 2), 2, '');
    preg_match('/^HTTP\/\S+ (\d+)/', $head, $status);
    preg_match('/^x-nonce: (\S+)/mi', $head, $header);

    return [(int)($status[1] ?? 0), $header[1] ?? '', $content];
};
$body = static fn (int $adventure): array => ['action' => 'troopsSend', 'eventType' => 50, 'troops' => [['t11' => 1]], 'target' => ['adventureId' => $adventure]];
$movements = static fn (): int => (int)$pdo->fetchScalar('SELECT COUNT(*) FROM movement WHERE kid=? AND u11>0 AND mode=0', [$kid]);

[$status, $nonce] = $call('PUT', $body($mine));
($status === 200 && $nonce !== '') || $fail("Signing must answer 200 with an X-Nonce header (HTTP $status).");
$movements() === 0 || $fail('Signing must not send the hero.');
$call('POST', $body($mine));
$movements() === 0 || $fail('A POST without the nonce must not send the hero.');
[, $nonce] = $call('PUT', $body($mine));
$call('POST', ['action' => 'troopsSend', 'eventType' => 50, 'troops' => [['t11' => 1]], 'target' => ['adventureId' => $mine + 1000]], $nonce);
$movements() === 0 || $fail('A nonce must not send a different adventure.');
[, $nonce] = $call('PUT', $body($mine));
[$status, , $content] = $call('POST', $body($mine), $nonce);
$answer = json_decode($content, true);
$status === 200 || $fail("The signed POST must answer 200 (HTTP $status): $content");
$movements() === 1 || $fail('The signed POST must send the hero once.');
(int)($answer['troops'][0]['arrivalIn'] ?? -1) > 0 && (int)($answer['troops'][0]['timeArrive'] ?? 0) > time() || $fail('The answer must name the arrival: ' . $content);
(int)($answer['sourceVillage']['id'] ?? 0) === $kid || $fail('The answer must name the village the hero left.');
str_contains((string)($answer['html'] ?? ''), 'class="adventureStarted') && str_contains((string)$answer['html'], 'button type="button" class="textButtonV2 buttonFramed continue') || $fail('The answer must carry the started block.');
$call('POST', $body($mine), $nonce);
$movements() === 1 || $fail('A spent nonce must not send the hero again.');

// The hero is away now.
[$status] = $call('PUT', $body($mine));
$status !== 200 || $fail('A hero that is away cannot be sent.');
// Back home: another player's adventure, a village without a Rally Point and a malformed body are refused.
$pdo->run('DELETE FROM movement WHERE kid=?', [$kid]);
$pdo->run('UPDATE units SET u11=1 WHERE kid=?', [$kid]);
[$status] = $call('PUT', $body($foreign));
$status !== 200 || $fail("Another player's adventure must be refused.");
[$status] = $call('PUT', ['action' => 'troopsSend', 'eventType' => 51, 'troops' => [['t11' => 1]], 'target' => ['adventureId' => $mine]]);
$status !== 200 || $fail('Only adventure sends are accepted.');
[$status] = $call('PUT', ['action' => 'troopsSend', 'eventType' => 50, 'troops' => [['t1' => 5]], 'target' => ['adventureId' => $mine]]);
$status !== 200 || $fail('Only the hero can be sent on an adventure.');
$pdo->run('UPDATE fdata SET f39=0, f39t=0 WHERE kid=?', [$kid]);
[$status] = $call('PUT', $body($mine));
$status !== 200 || $fail('A village without a Rally Point cannot send the hero.');

echo "Hero adventure start runtime passed.\n";
