<?php

declare(strict_types=1);

function failAutoExtendAtomicContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$model = file_get_contents(dirname(__DIR__, 2) . '/game/include/Model/AutoExtendModel.php');
if ($model === false) {
    failAutoExtendAtomicContract('Auto-extend model must be readable.');
}

if (!str_contains($model, 'private function renew(')
    || !str_contains($model, 'SELECT $column FROM users WHERE id=$uid FOR UPDATE')
    || !str_contains($model, '(int)$user[$column] !== $expectedExpiry')
    || !str_contains($model, 'GoldHelper::decreaseGold($uid, $coins, true)')
    || !str_contains($model, 'if (!$db->begin_transaction())')
    || !str_contains($model, 'if (!$db->commit())')
    || !str_contains($model, '$db->rollback()')) {
    failAutoExtendAtomicContract('An auto-extension must debit gold, grant the expiry, and persist its renewal row in one database transaction.');
}

if (!str_contains($model, 'ON DUPLICATE KEY UPDATE lastChecked=0')
    || !str_contains($model, 'INSERT INTO autoExtend (uid, type, commence, enabled, finished)')) {
    failAutoExtendAtomicContract('Auto-extension state must be upserted so a concurrent toggle cannot create a duplicate renewal row.');
}

$schema = file_get_contents(dirname(__DIR__, 2) . '/database/world.sql');
$migration = file_get_contents(dirname(__DIR__, 2) . '/game/include/update.php');
if ($schema === false || $migration === false
    || !str_contains($schema, 'UNIQUE KEY `uid_type` (`uid`, `type`)')
    || !str_contains($migration, 'ALTER TABLE autoExtend ADD UNIQUE KEY uid_type (uid, type)')) {
    failAutoExtendAtomicContract('The auto-extension uniqueness invariant must apply to both new and existing worlds.');
}

echo "Auto-extend atomic renewal contract passed.\n";
