<?php

declare(strict_types=1);

use Game\Installer\AppDatabaseGrants;

$root = dirname(__DIR__, 2);
require_once $root . '/game/include/Game/Installer/AppDatabaseGrants.php';

function expectAppDatabaseGrants(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$databases = ['travian_main', 'travian_world'];
$own = [
    "GRANT USAGE ON *.* TO `travian`@`%` IDENTIFIED BY PASSWORD '*8B0D29B9DB29FB6A60F85C08CF1B4BF94EFFD31B'",
    'GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, LOCK TABLES, SHOW VIEW, TRIGGER ON `travian\_main`.* TO `travian`@`%`',
    'GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, LOCK TABLES, SHOW VIEW, TRIGGER ON `travian\_world`.* TO `travian`@`%`',
];
expectAppDatabaseGrants(AppDatabaseGrants::excess($own, $databases) === [], 'The installer grants must be exactly what the check allows.');

$root_ = ["GRANT ALL PRIVILEGES ON *.* TO `root`@`%` IDENTIFIED BY PASSWORD '*SECRETHASH' WITH GRANT OPTION"];
$excess = AppDatabaseGrants::excess($root_, $databases);
expectAppDatabaseGrants($excess === ['ALL PRIVILEGES on *.*', 'GRANT OPTION on *.*'], 'A root-like account must be reported: ' . implode(', ', $excess));
expectAppDatabaseGrants(!str_contains(implode(' ', $excess), 'SECRETHASH'), 'The report must never carry the password hash.');

$more = array_merge($own, [
    'GRANT FILE, PROCESS ON *.* TO `travian`@`%`',
    'GRANT SELECT ON `mysql`.* TO `travian`@`%`',
    'GRANT DELETE HISTORY ON `travian\_world`.* TO `travian`@`%`',
    'GRANT SELECT ON `travian%`.* TO `travian`@`%`',
    'GRANT SELECT ON `travian_world`.`users` TO `travian`@`%`',
]);
$excess = AppDatabaseGrants::excess($more, $databases);
foreach (['FILE on *.*', 'PROCESS on *.*', 'SELECT on mysql.*', 'DELETE HISTORY on travian_world.*', 'SELECT on travian%.*', 'SELECT on travian_world.users'] as $expected) {
    expectAppDatabaseGrants(in_array($expected, $excess, true), 'Missing from the report: ' . $expected . ' (got ' . implode(', ', $excess) . ')');
}
expectAppDatabaseGrants(AppDatabaseGrants::excess(['GRANT `somerole` TO `travian`@`%`'], $databases) === [], 'A role line has no privileges to judge.');

$statement = AppDatabaseGrants::statement('travian_world', "'travian'@'%'");
expectAppDatabaseGrants($statement === 'GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, LOCK TABLES, SHOW VIEW, TRIGGER ON `travian\_world`.* TO \'travian\'@\'%\'', 'The statement must grant the listed privileges on the escaped database: ' . $statement);
expectAppDatabaseGrants(AppDatabaseGrants::excess([$statement], ['travian_world']) === [], 'What the installer grants must pass its own check.');

// One grant list: the installer builds its GRANT through the class, so no second list can drift.
foreach (['game/include/Game/Installer/WorldInstaller.php'] as $file) {
    $source = (string) file_get_contents($root . '/' . $file);
    expectAppDatabaseGrants(str_contains($source, 'AppDatabaseGrants::statement('), $file . ' must build its GRANT through AppDatabaseGrants.');
    expectAppDatabaseGrants(!preg_match('/GRANT\s+SELECT/i', $source), $file . ' must not carry its own privilege list.');
}
expectAppDatabaseGrants(AppDatabaseGrants::PRIVILEGES === ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'ALTER', 'DROP', 'INDEX', 'LOCK TABLES', 'SHOW VIEW', 'TRIGGER'], 'The privilege list is a deliberate decision: change it knowingly and update docs/configuration.md.');
expectAppDatabaseGrants(!in_array('GRANT OPTION', AppDatabaseGrants::PRIVILEGES, true) && !in_array('ALL PRIVILEGES', AppDatabaseGrants::PRIVILEGES, true) && !in_array('FILE', AppDatabaseGrants::PRIVILEGES, true), 'No administrative privilege may enter the list.');
expectAppDatabaseGrants(str_contains((string) file_get_contents($root . '/docs/configuration.md'), '## Application database user'), 'docs/configuration.md must explain why the user holds DDL and a % host.');

echo "App database grants contract passed.\n";
