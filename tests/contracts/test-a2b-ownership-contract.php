<?php

declare(strict_types=1);

// A pending troop-send row (a2b) belongs to the player who prepared it: it is stored with their
// id and only ever read or deleted by that player.

$root = dirname(__DIR__, 2);
$fail = static function (string $message): void {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$schema = (string)file_get_contents($root . '/database/world.sql');
$table = substr($schema, (int)strpos($schema, 'CREATE TABLE `a2b`'), 1500);
if (!str_contains(substr($table, 0, (int)strpos($table, 'ENGINE')), '`uid` INT(11) UNSIGNED NOT NULL')) {
    $fail('a2b must have an owner column.');
}
foreach (['RallyPoint.php', 'SendTroops.php'] as $file) {
    $source = (string)file_get_contents($root . '/game/include/Controller/RallyPoint/' . $file);
    foreach (['FROM a2b WHERE', 'DELETE FROM a2b WHERE'] as $needle) {
        $offset = 0;
        while (($at = strpos($source, $needle, $offset)) !== false) {
            $statement = substr($source, $at, 260);
            if (!str_contains($statement, 'uid=')) {
                $fail("{$file}: every a2b read and delete must be scoped to the owner: " . substr($statement, 0, 80));
            }
            $offset = $at + 10;
        }
    }
    if (!str_contains($source, 'INSERT INTO a2b (`uid`,')) {
        $fail("{$file}: a2b rows must be inserted with their owner.");
    }
}

echo "a2b ownership contract passed.\n";
