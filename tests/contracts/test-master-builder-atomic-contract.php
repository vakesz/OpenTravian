<?php

declare(strict_types=1);

function failMasterBuilderAtomicContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$source = file_get_contents(dirname(__DIR__, 2) . '/game/include/Model/MasterBuilder.php');
if ($source === false) {
    failMasterBuilderAtomicContract('Master Builder source must be readable.');
}

foreach ([
    'if (!$db->begin_transaction())',
    'GoldHelper::decreaseGold($village[\'owner\'], $gold, true)',
    'DELETE FROM building_upgrade WHERE id={$row[\'id\']} AND isMaster=1',
    'INSERT INTO building_upgrade (`kid`, `building_field`, `isMaster`, start_time, `commence`)',
    'WHERE kid={$row[\'kid\']} AND wood>=',
    'if (!$db->commit())',
    '$db->rollback()',
] as $required) {
    if (!str_contains($source, $required)) {
        failMasterBuilderAtomicContract("Master Builder processing must atomically debit Gold, move the queue entry, and deduct resources: missing $required");
    }
}

echo "Master Builder atomicity contract passed.\n";
