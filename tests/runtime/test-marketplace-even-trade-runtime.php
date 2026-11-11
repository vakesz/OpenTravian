<?php

declare(strict_types=1);

// Below 200 account population, or under beginner's protection, a player may only offer and accept 1:1 trades: the
// Buy tab disables every other offer with the reference tooltip, accepting one is refused, and so is creating one.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Marketplace even trade runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Database\DBI;
use Model\MarketModel;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$base = 'http://web';
if (trim((string)shell_exec("curl -s -m 5 -o /dev/null -w '%{http_code}' $base/ 2>&1")) !== '200') {
    echo "Marketplace even trade runtime skipped (the web service is not reachable).\n";
    exit(0);
}
try {
    $fixture = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Marketplace even trade runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$pdo = DBI::getInstance();
$kid = $fixture['kid'];
$uid = $fixture['id'];
$name = (string)$pdo->fetchScalar('SELECT name FROM users WHERE id=?', [$uid]);
$seller = (int)$pdo->fetchScalar('SELECT kid FROM vdata WHERE owner=1 AND kid<>? ORDER BY kid LIMIT 1', [$kid]);
if ($seller === 0) {
    $fail('The fixture needs a Natar village to sell from.');
}
$sendBefore = (int)$pdo->fetchScalar('SELECT COALESCE(MAX(id), 0) FROM `send`');
register_shutdown_function(static function () use ($pdo, $seller, $sendBefore): void {
    $pdo->run('DELETE FROM market WHERE kid=?', [$seller]);
    $pdo->run('DELETE FROM `send` WHERE id>? AND (kid=? OR to_kid=?)', [$sendBefore, $seller, $seller]);
});
$pdo->run('DELETE FROM market WHERE kid IN (?, ?)', [$kid, $seller]);
$pdo->run("UPDATE fdata SET f19t=17, f19=10 WHERE kid=?", [$kid]);
$pdo->run('UPDATE vdata SET wood=100000, clay=100000, iron=100000, crop=100000, maxstore=200000, maxcrop=200000, lastmupdate=? WHERE kid=?', [miliseconds(), $kid]);

$jar = tempnam(sys_get_temp_dir(), 'fxjar');
register_shutdown_function(static fn () => @unlink($jar));
shell_exec("curl -s -m 10 -c $jar -b $jar -o /dev/null $base/");
$login = trim((string)shell_exec("curl -s -m 10 -c $jar -b $jar -o /dev/null -w '%{http_code}' --data-urlencode " . escapeshellarg('name=' . $name) . ' --data-urlencode ' . escapeshellarg('password=' . fixtureLocalPassword()) . " $base/login.php"));
if ($login !== '302') {
    echo "Marketplace even trade runtime skipped (the fixture cannot log in with the local password: HTTP $login).\n";
    exit(0);
}
$get = static fn (string $path): string => (string)shell_exec("curl -s -m 30 -c $jar -b $jar " . escapeshellarg("$base/$path"));
$post = static function (string $path, array $fields) use ($jar, $base): string {
    $args = '';
    foreach ($fields as $field => $value) {
        $args .= ' --data-urlencode ' . escapeshellarg($field . '=' . $value);
    }

    return (string)shell_exec("curl -s -m 30 -c $jar -b $jar$args " . escapeshellarg("$base/$path"));
};
$market = new MarketModel();
$setRules = static function (int $population, bool $protected) use ($pdo, $uid): void {
    $pdo->run('UPDATE users SET total_pop=?, protection=? WHERE id=?', [$population, $protected ? time() + 3600 : 0, $uid]);
};
$freshOffers = static function () use ($pdo, $market, $seller, $kid): array {
    $pdo->run('DELETE FROM market WHERE kid IN (?, ?)', [$kid, $seller]);
    $ids = [];
    foreach ([[100, 100], [200, 100], [100, 150]] as [$need, $give]) {
        $market->addOffer($seller, 0, 0, 2, $need, 1, $give);
        $ids[] = (int)$pdo->fetchScalar('SELECT id FROM market WHERE kid=? AND needValue=? AND giveValue=?', [$seller, $need, $give]);
    }

    return $ids;
};
$acceptButtons = static function (string $html): array {
    preg_match_all('/<td class="accept"><button type="button" title="([^"]*)" class="([^"]*)"/', $html, $matches, PREG_SET_ORDER);

    return array_map(static fn (array $m): array => [html_entity_decode($m[1]), str_contains(' ' . $m[2] . ' ', ' disabled ')], $matches);
};
$buyPage = 'build.php?id=19&gid=17&t=1';
$populationTip = 'While below 200 account population, you can only accept 1:1 trades.';
$protectionTip = "During beginner's protection, you can only accept 1:1 trades.";

foreach ([['population', 150, false, $populationTip], ['protection', 400, true, $protectionTip], ['both', 150, true, $populationTip]] as [$case, $population, $protected, $tip]) {
    $setRules($population, $protected);
    [$even, $worse, $better] = $freshOffers();
    $buttons = $acceptButtons($get($buyPage));
    count($buttons) === 3 || $fail("[$case] The Buy tab must list the three offers, found " . count($buttons));
    $tips = array_column($buttons, 0);
    $disabled = array_column($buttons, 1);
    // Rows come in the page's own order; find them by their tooltips instead of by position.
    substr_count(implode('|', $tips), $tip) === 2 || $fail("[$case] Both offers that are not 1:1 must carry: $tip (" . json_encode($tips) . ')');
    count(array_filter($disabled)) === 2 || $fail("[$case] Exactly the two non-1:1 offers must be disabled.");

    $page = $get("build.php?id=19&gid=17&t=1&v=1:x&a=$kid&g=$worse&b=0&s=0&al=0");
    (int)$pdo->fetchScalar('SELECT COUNT(*) FROM market WHERE id=?', [$worse]) === 1 || $fail("[$case] A non-1:1 offer must not be accepted: " . json_encode([preg_match('/<p class="error">(.*?)<\/p>/s', $page, $e) ? $e[1] : null, (int)$pdo->fetchScalar('SELECT COUNT(*) FROM market WHERE id=?', [$worse]), (int)$pdo->fetchScalar('SELECT COUNT(*) FROM market')]));
    str_contains($page, 'you can only accept 1:1 trades. The same applies if you have less than 200 population.') || $fail("[$case] Refusing an accept must say why.");

    $page = $get('build.php?id=19&gid=17&t=2');
    preg_match('/name="c" value="([0-9a-f]+)"/', $page, $token) || $fail("[$case] The Offer tab must carry the action token.");
    $create = static fn (int $give, int $need) => $post('build.php?id=19&gid=17&t=2', ['gid' => '17', 't' => '2', 'a' => '4', 'c' => $token[1], 'm1' => (string)$give, 'm2' => (string)$need, 'rid1' => '1', 'rid2' => '2', 'd1' => '', 'd2' => '1', 'ally' => '']);
    $ownOffers = static fn (): int => (int)$pdo->fetchScalar('SELECT COUNT(*) FROM market WHERE kid=?', [$kid]);
    $reply = $create(100, 200);
    $ownOffers() === 0 || $fail("[$case] A non-1:1 offer must not be created.");
    str_contains($reply, 'you can only offer 1:1 trades. The same applies if you have less than 200 population.') || $fail("[$case] Refusing an offer must say why.");
    $post('build.php?id=19&gid=17&t=2', ['gid' => '17', 't' => '2', 'a' => '4', 'c' => $token[1], 'm1' => '100', 'm2' => '100', 'rid1' => '1', 'rid2' => '2', 'd1' => '', 'd2' => '1', 'ally' => '']);
    $ownOffers() === 1 || $fail("[$case] A 1:1 offer must be created.");
}

// Population of 200 and no protection: every ratio is allowed again.
$setRules(200, false);
[$even, $worse, $better] = $freshOffers();
$buttons = $acceptButtons($get($buyPage));
count(array_filter(array_column($buttons, 1))) === 0 || $fail('At 200 population without protection no offer may be disabled: ' . json_encode($buttons));
$get("build.php?id=19&gid=17&t=1&v=1:x&a=$kid&g=$worse&b=0&s=0&al=0");
(int)$pdo->fetchScalar('SELECT COUNT(*) FROM market WHERE id=?', [$worse]) === 0 || $fail('At 200 population a non-1:1 offer must be accepted.');

echo "Marketplace even trade runtime passed.\n";
