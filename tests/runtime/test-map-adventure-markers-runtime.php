<?php

declare(strict_types=1);

// The map draws the player's adventures like the reference: one marker element per adventure (type adventure, the
// reference's difficulty scale with 0 for hard, the title "Adventure" and the text "Adventure <n><br />{a.ad} {a.ad<d>}"),
// the labels behind that text, and no start link in the tile popup (the reference's popup of an adventure tile only
// offers "Center map").

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Map adventure markers runtime skipped outside the php container.\n";
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
    echo "Map adventure markers runtime skipped (the web service is not reachable).\n";
    exit(0);
}
try {
    $fixture = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Map adventure markers runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$pdo = DBI::getInstance();
$uid = $fixture['id'];
$name = (string)$pdo->fetchScalar('SELECT name FROM users WHERE id=?', [$uid]);
$tiles = $pdo->fetchPluck('SELECT id FROM wdata WHERE occupied=0 AND id<>? ORDER BY id LIMIT 2 OFFSET 40', [$fixture['kid']]);
$pdo->run('DELETE FROM adventure WHERE uid=?', [$uid]);
foreach ($tiles as $index => $tile) {
    $pdo->run('INSERT INTO adventure (uid, kid, dif, created) VALUES (?, ?, ?, ?)', [$uid, $tile, $index, time()]);
}
$jar = tempnam(sys_get_temp_dir(), 'fxjar');
register_shutdown_function(static fn () => @unlink($jar));
shell_exec("curl -s -m 10 -c $jar -b $jar -o /dev/null $base/");
$login = trim((string)shell_exec("curl -s -m 10 -c $jar -b $jar -o /dev/null -w '%{http_code}' --data-urlencode " . escapeshellarg('name=' . $name) . ' --data-urlencode ' . escapeshellarg('password=' . fixtureLocalPassword()) . " $base/login.php"));
if ($login !== '302') {
    echo "Map adventure markers runtime skipped (the fixture cannot log in with the local password: HTTP $login).\n";
    exit(0);
}
$page = (string)shell_exec("curl -s -m 30 -c $jar -b $jar $base/karte.php");
preg_match_all('/\{"dataId":"adventure(\d+)","x":(-?\d+),"y":(-?\d+),"type":"adventure","parameters":\{"difficulty":(\d)\},"title":"Adventure","text":"Adventure (\d+)<br \\\\\/>\{a\.ad\} \{a\.ad(\d)\}"\}/', $page, $markers, PREG_SET_ORDER);
count($markers) === 2 || $fail('The map must carry one marker per adventure, found ' . count($markers) . ' near: ' . (preg_match('/.{80}adventure.{300}/s', $page, $near) ? $near[0] : 'no adventure text; ' . strlen($page) . ' bytes, elements at ' . (int)strpos($page, '"elements"')));
foreach ($markers as $marker) {
    $marker[4] === $marker[6] || $fail('The label must follow the difficulty.');
}
$difficulties = array_map(static fn (array $marker): string => $marker[4], $markers);
sort($difficulties);
$difficulties === ['0', '1'] || $fail('The hard adventure uses difficulty 0 and the normal one 1, got ' . implode(',', $difficulties));
foreach (["'a.ad0': 'hard'", "'a.ad1': 'normal'", "'a.ad': 'Difficulty:'"] as $label) {
    str_contains($page, $label) || $fail("The map must define the label $label.");
}
foreach ($tiles as $tile) {
    $xy = \Game\Formulas::kid2xy((int)$tile);
    $popup = (string)shell_exec("curl -s -m 30 -c $jar -b $jar -X POST -H 'Content-Type: application/json' --data " . escapeshellarg(json_encode(['x' => (string)$xy['x'], 'y' => (string)$xy['y']])) . " $base/api/v1/map/tile-details");
    !str_contains($popup, 'start_adventure') && !str_contains($popup, 'startAdventure') || $fail('The tile popup must not offer to start the adventure.');
}

echo "Map adventure markers runtime passed.\n";
