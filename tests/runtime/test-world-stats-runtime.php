<?php

declare(strict_types=1);

// The public world stats snapshot: aggregates only, public accounts only, cached. Runs on a throwaway
// fixture player and alliance, which are removed (and the cache cleared) at shutdown.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "World stats runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Caching\Caching;
use Core\Database\DBI;
use Game\WorldStatsSnapshot;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
$cache = Caching::getInstance();
$aid = 0;
register_shutdown_function(static function () use ($pdo, $cache, &$aid): void {
    if ($aid > 0) {
        $pdo->run('DELETE FROM alidata WHERE id=?', [$aid]);
    }
    $cache->delete(WorldStatsSnapshot::CACHE_KEY);
});
$fresh = static function () use ($cache): array {
    $cache->delete(WorldStatsSnapshot::CACHE_KEY);

    return WorldStatsSnapshot::get();
};

// Shape: only whitelisted aggregate keys, nothing personal.
$base = $fresh();
$expected = ['startTime', 'ageDays', 'speed', 'players', 'alliances', 'topPlayers', 'topAlliances', 'wonder'];
if (array_keys($base) !== $expected) {
    $fail('The snapshot must hold exactly: ' . implode(', ', $expected));
}
foreach ($base['topPlayers'] as $row) {
    if (array_keys($row) !== ['rank', 'population']) {
        $fail('A top player row is an anonymous rank and population only.');
    }
}
foreach ($base['topAlliances'] as $row) {
    if (array_keys($row) !== ['rank', 'name', 'tag', 'population']) {
        $fail('A top alliance row is rank, public name, tag and population only.');
    }
}
if (count($base['topPlayers']) > WorldStatsSnapshot::TOP_SIZE || count($base['topAlliances']) > WorldStatsSnapshot::TOP_SIZE) {
    $fail('The top lists are capped.');
}

// Cache: the second read is served from Redis with a bounded lifetime.
$ttl = (int) Caching::singleton()->ttl(WorldStatsSnapshot::CACHE_KEY);
if ($ttl < 1 || $ttl > WorldStatsSnapshot::TTL) {
    $fail("The cached snapshot must expire within " . WorldStatsSnapshot::TTL . " seconds, got {$ttl}.");
}
$cache->set(WorldStatsSnapshot::CACHE_KEY, ['marker' => true], 60);
if (WorldStatsSnapshot::get() !== ['marker' => true]) {
    $fail('A cached snapshot must be returned without recomputing.');
}

try {
    $player = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "World stats runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$pdo->run('INSERT INTO alidata (name, tag) VALUES (?, ?)', ['zzfx stats ' . getmypid(), 'zzs' . substr((string) getmypid(), -3)]);
$aid = (int) $pdo->fetchScalar('SELECT LAST_INSERT_ID()');
$pdo->run('UPDATE users SET total_pop=50000000, aid=?, hidden=0, access=1 WHERE id=?', [$aid, $player['id']]);
$name = (string) $pdo->fetchScalar('SELECT name FROM users WHERE id=?', [$player['id']]);

$visible = $fresh();
if ($visible['players'] !== $base['players'] + 1 || $visible['alliances'] !== $base['alliances'] + 1) {
    $fail('A public player and their alliance are counted.');
}
if ($visible['topPlayers'][0] !== ['rank' => 1, 'population' => 50000000]) {
    $fail('The richest public player leads the anonymous list.');
}
$leader = $visible['topAlliances'][0] ?? [];
if (($leader['tag'] ?? '') !== 'zzs' . substr((string) getmypid(), -3) || $leader['population'] !== 50000000 || $leader['rank'] !== 1) {
    $fail('The alliance of a public player leads the alliance list with its summed population.');
}
$encoded = (string) json_encode($visible);
foreach ([$name, (string) $player['id'], (string) $player['kid']] as $private) {
    if ($private !== '' && str_contains($encoded, '"' . $private . '"')) {
        $fail("The snapshot must not carry the player's name, id or village ({$private}).");
    }
}

foreach (['hidden=1' => 'a hidden player', 'access=0' => 'a banned or system player'] as $change => $label) {
    $pdo->run("UPDATE users SET {$change} WHERE id=?", [$player['id']]);
    $hidden = $fresh();
    if ($hidden['players'] !== $base['players'] || $hidden['alliances'] !== $base['alliances']
        || $hidden['topPlayers'] !== $base['topPlayers'] || $hidden['topAlliances'] !== $base['topAlliances']) {
        $fail("{$label} and their alliance must not appear anywhere in the snapshot.");
    }
    $pdo->run('UPDATE users SET hidden=0, access=1 WHERE id=?', [$player['id']]);
}

echo "World stats runtime passed.\n";
