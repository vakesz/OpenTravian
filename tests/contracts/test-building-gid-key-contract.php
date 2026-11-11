#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: the building table is indexed by gid - 1 and every stored building
 * (fdata item_id, queued constructions) is a gid. Gid 49 is the Viking Harbor and
 * gid 50 is the Barricade (Knowledge Base building data, 2026-10-31), so the
 * Barricade sits at key 49 and key 48 is reserved for the Harbor.
 */

use Game\Formulas;

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

function assertGidKey(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$buildings = Formulas::$data['buildings'];
foreach ($buildings as $key => $building) {
    if (isset($building['gid'])) {
        assertGidKey((int)$building['gid'] === $key + 1, "Building key $key must carry gid " . ($key + 1) . ', found ' . $building['gid'] . '.');
    }
}
assertGidKey(($buildings[49]['name'] ?? '') === 'Barricade' && (int)$buildings[49]['gid'] === 50, 'Barricade is gid 50 at key 49.');
assertGidKey(in_array(50, Formulas::TRIBE_SPECIFIC_BUILDINGS, true) && in_array(50, Formulas::WALL_BUILDINGS, true), 'Gid 50 is a tribe-specific wall building.');
assertGidKey(($buildings[48]['name'] ?? 'Harbor') === 'Harbor' && (int)($buildings[48]['gid'] ?? 49) === 49, 'Key 48 is the Harbor (gid 49) or still unused, never another building.');
assertGidKey(!in_array(49, Formulas::WALL_BUILDINGS, true), 'The Harbor is not a wall building.');

$lang = file_get_contents($root . '/game/include/resources/Translation/en-US.php');
assertGidKey(str_contains($lang, "\$Definition['Buildings'][50]['title'] = 'Barricade';"), 'Translation gid 50 is the Barricade.');
assertGidKey(!str_contains($lang, "\$Definition['Buildings'][49]['title'] = 'Barricade';"), 'Translation gid 49 is not the Barricade.');

echo "Building gid/key contract passed.\n";
