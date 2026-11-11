<?php

declare(strict_types=1);

function assertSchemaSeed(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function containsSqlFragment(string $sql, string $fragment): bool
{
    $normalize = static fn (string $value): string => strtolower((string)preg_replace('/\s+/', ' ', trim($value)));

    return str_contains($normalize($sql), $normalize($fragment));
}

$root = dirname(__DIR__, 2);
$account = file_get_contents($root . '/database/account.sql');
$world = file_get_contents($root . '/database/world.sql');

assertSchemaSeed(is_string($account), 'The account seed must be readable.');
assertSchemaSeed(is_string($world), 'The world seed must be readable.');

foreach (['activation', 'users'] as $table) {
    preg_match('/CREATE TABLE (?:IF NOT EXISTS )?`' . $table . '` \((.*?)\n\)/s', $world, $tableSql);
    assertSchemaSeed(
        isset($tableSql[1]) && containsSqlFragment($tableSql[1], '`password` VARCHAR(255) NOT NULL'),
        "World {$table}.password must support modern hashes (VARCHAR(255) NOT NULL)."
    );
}
assertSchemaSeed(!str_contains(strtolower($account), 'bannershop'), 'The account seed must not include advertising tables.');
assertSchemaSeed(str_contains($account, '`isActive` tinyint(4) NOT NULL DEFAULT 0'), 'External payment defaults must be disabled.');
assertSchemaSeed(str_contains($account, '`active` tinyint(1) UNSIGNED NOT NULL DEFAULT 0'), 'Payment configuration must default to disabled.');

preg_match('/INSERT INTO `goldProducts`.*?VALUES\n(.*?);/s', $account, $goldProducts);
assertSchemaSeed(isset($goldProducts[1]), 'The account seed must contain gold-product rows.');
preg_match_all('/^\(\d+,.+, ([01])\)[,;]?$/m', $goldProducts[1], $productStates);
assertSchemaSeed(count($productStates[1]) === 6, 'The seed must hold exactly one six-package tier set.');
assertSchemaSeed(!in_array('1', $productStates[1], true), 'Seeded external gold products must be disabled.');
assertSchemaSeed(!str_contains($goldProducts[1], 'IRR') && !str_contains($account, 'goldProductMoneyUnit'), 'Gold products are priced in the single operator currency, not per row.');
foreach (['paymentOrder', 'paymentEvent', 'paymentFlag'] as $table) {
    assertSchemaSeed(str_contains($account, 'CREATE TABLE `' . $table . '`'), "The account seed must define $table.");
}
assertSchemaSeed(str_contains($account, 'UNIQUE KEY `provider_eventId` (`provider`,`eventId`)'), 'Provider events must be unique per provider and event id.');
assertSchemaSeed(containsSqlFragment($world, 'UNIQUE KEY `creditKey` (`creditKey`)'), 'World credits must be unique per order and direction.');
foreach (['paymentProviders', 'paymentLog', 'connectInfo', 'isSMS', 'loginToken', 'mailerLock', 'notificationGroupId', '`locations`'] as $retired) {
    assertSchemaSeed(!str_contains($account, $retired), "The retired payment schema element $retired must be gone.");
}

foreach ([
    '`ajax_token`                                       VARCHAR(64)           NULL     DEFAULT NULL',
    '`token`    VARCHAR(64)      NOT NULL',
    '`quarantined_at`  INT UNSIGNED NULL DEFAULT NULL',
    'KEY `idx_commence_id` (`commence`, `id`)',
    'KEY `idx_enabled_time` (`enabled`, `time`)',
    'KEY `idx_mode_end_time` (`mode`, `end_time`)',
    'KEY `idx_access_adventure` (`access`, `last_adventure_time`)',
    'KEY `idx_access_heroexp` (`access`, `lastHeroExpCheck`)',
    '`sortIndex` SMALLINT(5) UNSIGNED NOT NULL DEFAULT \'0\'',
    '`isExpanded` TINYINT(1) UNSIGNED NOT NULL DEFAULT \'0\'',
    'KEY `idx_owner_kid_sort` (`owner`, `kid`, `sortIndex`)',
    '`isActive` TINYINT(1) UNSIGNED NOT NULL DEFAULT \'1\'',
    'KEY `idx_lid_active` (`lid`, `isActive`)',
] as $fragment) {
    assertSchemaSeed(containsSqlFragment($world, $fragment), "World seed is missing: {$fragment}");
}

assertSchemaSeed(
    glob($root . '/database/migrations/{account,world}/*.sql', GLOB_BRACE) === [],
    'Schema migrations must be folded into the canonical seeds.'
);
assertSchemaSeed(!file_exists($root . '/tools/apply-migrations.php'), 'The retired migration runner must not remain.');

echo "Schema seed checks passed.\n";
