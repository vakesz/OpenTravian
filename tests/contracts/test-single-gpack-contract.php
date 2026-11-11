#!/usr/bin/env php
<?php

declare(strict_types=1);

function failSingleGpackContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$registry = require $root . '/assets/gpack/registry.php';
$functions = file_get_contents($root . '/game/include/functions.general.php');

if (!is_array($registry) || $functions === false) {
    failSingleGpackContract('Unable to read the maintained Gpack configuration.');
}

$packs = $registry['list'] ?? null;
if (($registry['default'] ?? null) !== 'travian'
    || !is_array($packs)
    || array_keys($packs) !== ['travian']) {
    failSingleGpackContract('travian must remain the only selectable game Gpack.');
}

if (str_contains($functions, 'travian_gpack_hash')
    || str_contains($functions, 'function set_gpack_version(')) {
    failSingleGpackContract('The retired game-Gpack cookie selector must not remain reachable.');
}

// The pack id comes from the shipped registry, never from an installed world's generated config,
// so renaming the pack cannot strand a world that was installed under the old name.
if (str_contains($functions, "['gpacks']") || !str_contains($functions, 'function gpack_registry(')) {
    failSingleGpackContract('The game code must read the Gpack registry, not the world config.');
}
$installer = file_get_contents($root . '/game/include/Game/Installer/WorldInstaller.php');
if ($installer === false || str_contains($installer, "'gpacks'")) {
    failSingleGpackContract('The installer must not copy the Gpack registry into the world config.');
}

echo "Single Gpack contract passed.\n";
