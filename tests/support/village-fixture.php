<?php

declare(strict_types=1);

// A throwaway player and village for tests that must spend, build or queue. It is cloned
// from an existing capital's one-row-per-village tables, never touches that player, and is
// deleted again at shutdown, so exit() or a failed assertion cannot leak it.

use Core\Database\DBI;

/**
 * @param int|null $race the tribe of the fixture player and their troops (the clone keeps the source player's tribe when null)
 * @return array{id: int, kid: int, password: string}
 */
function fixtureVillageCreate(?int $race = null): array
{
    $pdo = DBI::getInstance();
    $source = $pdo->fetchSingleRow(
        'SELECT u.id, u.kid FROM users u JOIN vdata v ON v.kid=u.kid JOIN fdata f ON f.kid=v.kid WHERE u.id>2 AND v.capital=1 ORDER BY u.id LIMIT 1'
    );
    if (!$source) {
        throw new RuntimeException('No capital to clone a fixture village from.');
    }
    $kid = (int)$pdo->fetchScalar(
        'SELECT w.id FROM wdata w LEFT JOIN vdata v ON v.kid=w.id WHERE v.kid IS NULL AND w.fieldtype>0 ORDER BY w.id DESC LIMIT 1'
    );
    if ($kid === 0) {
        throw new RuntimeException('No free map slot for the fixture village.');
    }
    $cloned = [];
    $clone = static function (string $table, string $keyColumn, int $sourceKey, array $overrides) use ($pdo, &$cloned): ?int {
        $columns = $pdo->fetchPluck(
            "SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND extra NOT LIKE '%auto_increment%' ORDER BY ordinal_position",
            [$table]
        );
        $names = [];
        $values = [];
        foreach ($columns as $column) {
            $names[] = "`$column`";
            $values[] = array_key_exists($column, $overrides) ? $overrides[$column] : "`$column`";
        }
        $pdo->run(
            "INSERT INTO `$table` (" . implode(',', $names) . ') SELECT ' . implode(',', $values) . " FROM `$table` WHERE `$keyColumn`=?",
            [$sourceKey]
        );
        $cloned[] = $table;

        return (int)$pdo->fetchScalar('SELECT LAST_INSERT_ID()');
    };

    // users.name is unique, so a test that makes several fixtures gets a distinct name for each.
    static $created = 0;
    $name = 'zzfx' . getmypid() . ($created++ > 0 ? 'n' . $created : '');
    $uid = $clone('users', 'id', (int)$source['id'], [
        'name' => $pdo->quote($name),
        'uuid' => 'UUID()',
        'kid' => (string)$kid,
        'email' => "''",
        'aid' => '0',
        'alliance_role' => '0',
    ]);
    if ($uid <= 0) {
        throw new RuntimeException('The fixture player could not be created.');
    }
    $village = ['kid' => (string)$kid];
    $clone('vdata', 'kid', (int)$source['kid'], ['kid' => (string)$kid, 'owner' => (string)$uid, 'expandedfrom' => '0']);
    foreach (['fdata', 'units', 'tdata', 'smithy'] as $table) {
        $clone($table, 'kid', (int)$source['kid'], $village);
    }
    // The cloned hero lives in the fixture village, so the hero is at home even when the source player's hero is out
    // on an adventure (a hero that is nowhere has no status and the game pages cannot render).
    $pdo->run('UPDATE units SET u11=1 WHERE kid=?', [$kid]);
    if ($race !== null) {
        $pdo->run('UPDATE users SET race=? WHERE id=?', [$race, $uid]);
        $pdo->run('UPDATE units SET race=? WHERE kid=?', [$race, $kid]);
    }
    $uidTables = $pdo->fetchPluck(
        "SELECT c.table_name FROM information_schema.columns c WHERE c.table_schema=DATABASE() AND c.column_name='uid' AND c.column_key='PRI' AND c.table_name NOT IN ('users')"
    );
    foreach ($uidTables as $table) {
        $hasKid = (bool)$pdo->fetchScalar(
            "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name='kid'",
            [$table]
        );
        $exists = (int)$pdo->fetchScalar("SELECT COUNT(*) FROM `$table` WHERE uid=?", [(int)$source['id']]);
        if ($exists === 1) {
            $clone($table, 'uid', (int)$source['id'], ['uid' => (string)$uid] + ($hasKid ? $village : []));
        }
    }
    register_shutdown_function(static fn () => fixtureVillageRemove($uid, $kid));

    $password = (string)$pdo->fetchScalar('SELECT password FROM users WHERE id=?', [$uid]);

    return ['id' => $uid, 'kid' => $kid, 'password' => $password];
}

/** Deletes every row the fixture player or village owns. Safe to call twice. */
function fixtureVillageRemove(int $uid, int $kid): void
{
    if ($uid <= 2 || $kid <= 0) {
        return;
    }
    $pdo = DBI::getInstance();
    $guard = (string)$pdo->fetchScalar('SELECT name FROM users WHERE id=?', [$uid]);
    if (!str_starts_with($guard, 'zzfx')) {
        return;
    }
    foreach ($pdo->fetchPluck("SELECT table_name FROM information_schema.columns WHERE table_schema=DATABASE() AND column_name='kid'") as $table) {
        if ($table !== 'available_villages') {
            $pdo->run("DELETE FROM `$table` WHERE kid=?", [$kid]);
        }
    }
    // Troops on their way to the village (or back from an oasis it attacked) must not outlive it.
    foreach ($pdo->fetchPluck("SELECT table_name FROM information_schema.columns WHERE table_schema=DATABASE() AND column_name='to_kid'") as $table) {
        $pdo->run("DELETE FROM `$table` WHERE to_kid=?", [$kid]);
    }
    foreach ($pdo->fetchPluck("SELECT table_name FROM information_schema.columns WHERE table_schema=DATABASE() AND column_name='uid'") as $table) {
        $pdo->run("DELETE FROM `$table` WHERE uid=?", [$uid]);
    }
    $pdo->run('DELETE FROM users WHERE id=?', [$uid]);
}

/** The starter player's password that dev/install-world.sh generates; empty when that file is missing. */
function fixtureLocalPassword(): string
{
    $file = dirname(__DIR__, 2) . '/dev/client-tests/.password.local';

    return is_file($file) ? trim((string)file_get_contents($file)) : '';
}
