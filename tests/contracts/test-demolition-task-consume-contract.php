<?php

declare(strict_types=1);

/**
 * Contract: demolition completion must not fail when BuildingAction::downgrade
 * already deletes the demolition row inside the same TransactionalTask
 * transaction (historically quarantined as "disappeared before completion").
 */

function failDemolitionTaskConsumeContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$task = file_get_contents($root . '/game/include/Core/Jobs/TransactionalTask.php');
$action = file_get_contents($root . '/game/include/Game/Buildings/BuildingAction.php');
$automation = file_get_contents($root . '/game/include/Core/Automation.php');

foreach ([$task, $action, $automation] as $source) {
    if ($source === false) {
        failDemolitionTaskConsumeContract('Unable to read demolition completion sources.');
    }
}

if (!str_contains($automation, 'TransactionalTask::consume(\'demolition\'')) {
    failDemolitionTaskConsumeContract('processDemolitionTask must still consume demolition rows.');
}

if (!str_contains($action, 'DELETE FROM demolition WHERE kid=$kid AND building_field=$building_field')) {
    failDemolitionTaskConsumeContract('BuildingAction::downgrade must keep cleaning demolition by field for non-engine callers.');
}

if (!str_contains($task, 'The effect may already remove the hot row')
    || !str_contains($task, 'SELECT id FROM $table WHERE id=$id FOR UPDATE')) {
    failDemolitionTaskConsumeContract('TransactionalTask::consume must tolerate an effect that already deleted the hot row.');
}

if (preg_match(
    '/if \(\$consume\) \{\s*\$db->query\("DELETE FROM \$table WHERE id=\$id"\);\s*if \(\$db->affectedRows\(\) !== 1\)/',
    $task
) === 1) {
    failDemolitionTaskConsumeContract('Do not unconditionally require DELETE affectedRows===1 after the effect.');
}

echo "Demolition task consume contract passed.\n";
