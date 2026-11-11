#!/usr/bin/env php
<?php

declare(strict_types=1);

function failWorldSchemaVersionContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$version = file_get_contents($root . '/game/include/Game/WorldSchemaVersion.php');
$update = file_get_contents($root . '/game/include/update.php');
$setup = file_get_contents($root . '/game/include/Game/Installer/WorldInstaller.php');
$installer = file_get_contents($root . '/game/include/Model/InstallerModel.php');

if ($version === false || $update === false || $setup === false || $installer === false
    || preg_match('/public const CURRENT = (\d+);/', $version, $match) !== 1
    || (int)$match[1] < 1) {
    failWorldSchemaVersionContract('The current world-schema version must be a positive, readable constant.');
}

if (!str_contains($update, 'define("PATCH_VERSION", WorldSchemaVersion::CURRENT);')) {
    failWorldSchemaVersionContract('Incremental migrations must use the canonical world-schema version.');
}
if (!str_contains($setup, 'use Game\\WorldSchemaVersion;')
    || !str_contains($setup, "VALUES (1, ?, ?, 1, ?, 0, 1, ?, ?, ?, ?, ?, '')")
    || !str_contains($setup, '$patchVersion = WorldSchemaVersion::CURRENT;')
    || !str_contains($setup, "bind_param('iiiisssi'")) {
    failWorldSchemaVersionContract('The web installer must seed the imported world schema at its current version.');
}
if (!str_contains($installer, 'patchVersion=" . \\Game\\WorldSchemaVersion::CURRENT')) {
    failWorldSchemaVersionContract('The CLI installer must mark a finalized world with its imported schema version.');
}

echo "World schema-version contract passed.\n";
