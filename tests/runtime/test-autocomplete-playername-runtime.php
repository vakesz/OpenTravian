<?php

declare(strict_types=1);

// Player-name autocomplete: LIKE wildcards are literal, an empty term returns nothing, hidden and
// system accounts are never suggested, and the result is capped.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Autocomplete runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/ajax.php';
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use Controller\Ajax\autocomplete\playername;
use Core\Database\DB;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$search = static function (string $term): array {
    $_POST = ['context' => 'username', 'search' => $term];
    $controller = (new ReflectionClass(playername::class))->newInstanceWithoutConstructor();
    $controller->dispatch();
    $response = new ReflectionProperty($controller, 'response');

    return $response->getValue($controller);
};

$db = DB::getInstance();
$name = (string)$db->fetchScalar("SELECT name FROM users WHERE id>2 AND hidden=0 AND access>0 AND CHAR_LENGTH(name)>=4 ORDER BY id LIMIT 1");
if ($name === '') {
    echo "Autocomplete runtime passed (no public player to search for).\n";
    exit(0);
}
$names = static fn (string $term): array => array_column($search($term), 'name');
$needle = mb_substr($name, 0, 3);
if (!in_array($name, $names($needle), true)) {
    $fail("A prefix search for '{$needle}' must find {$name}.");
}
if (in_array($name, $names(mb_substr($name, 1, 3)), true) && !str_starts_with($name, mb_substr($name, 1, 3))) {
    $fail('Player suggestions match the start of a name, not any part of it.');
}
$first = $search($needle)[0] ?? [];
if (!isset($first['uid'], $first['name'])) {
    $fail('Player suggestions carry uid and name.');
}
foreach (['%', '_', ''] as $short) {
    if ($search($short) !== []) {
        $fail("The term '{$short}' is empty or a wildcard and must return nothing.");
    }
}
foreach (['%%', '__', '\\\\'] as $wild) {
    if ($search($wild) !== []) {
        $fail("The wildcard term '{$wild}' must be matched literally and find no player.");
    }
}
foreach ((array)$db->query("SELECT name FROM users WHERE id<=2 OR hidden=1 OR access=0")->fetch_all(MYSQLI_NUM) as $row) {
    if (mb_strlen($row[0]) >= 2 && in_array($row[0], $names($row[0]), true)) {
        $fail("The hidden or system account {$row[0]} must never be suggested.");
    }
}
if (count($search('e')) > playername::AUTO_COMPLETE_LIMIT || count($search('ee')) > playername::AUTO_COMPLETE_LIMIT) {
    $fail('Results are capped at the autocomplete limit.');
}

echo "Autocomplete runtime passed.\n";
