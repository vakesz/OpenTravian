<?php

declare(strict_types=1);

/**
 * Contract: ExtraModules::finishTraining must credit training rows inside the
 * gold purchase transaction (TrainingModel::completeAllTrainingInOpenTransaction)
 * instead of deferring with end_time=0 / commence=0 for Automation.
 */

function failFinishTrainingAtomicContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$extra = file_get_contents($root . '/game/include/Game/ExtraModules.php');
$training = file_get_contents($root . '/game/include/Model/TrainingModel.php');
if ($extra === false || $training === false) {
    failFinishTrainingAtomicContract('Unable to read finishTraining sources.');
}

foreach ([
    'public function completeAllTrainingInOpenTransaction(int $kid): int',
    'FROM training WHERE kid=? ORDER BY id ASC FOR UPDATE',
    "\$row['commence'] = 0",
    "\$row['end_time'] = 0",
    '$this->creditTrainingResult($row)',
] as $needle) {
    if (!str_contains($training, $needle)) {
        failFinishTrainingAtomicContract('TrainingModel must expose in-transaction full-queue completion: missing ' . $needle);
    }
}

foreach ([
    'completeAllTrainingInOpenTransaction($kid)',
    'self::reduceGold(__FUNCTION__, 1, true)',
    'GoldHelper::syncSessionGold($uid)',
] as $needle) {
    if (!str_contains($extra, $needle)) {
        failFinishTrainingAtomicContract('finishTraining must complete training then debit gold in one txn: missing ' . $needle);
    }
}

if (str_contains($extra, 'UPDATE training SET end_time=0, commence=0')) {
    failFinishTrainingAtomicContract('finishTraining must not defer completion with end_time=0 / commence=0.');
}

$finishPos = strpos($extra, 'public static function finishTraining(&$response): string');
$completePos = $finishPos === false ? false : strpos($extra, 'completeAllTrainingInOpenTransaction($kid)', $finishPos);
$goldPos = $finishPos === false ? false : strpos($extra, 'self::reduceGold(__FUNCTION__, 1, true)', $finishPos);
if ($finishPos === false || $completePos === false || $goldPos === false || $completePos > $goldPos) {
    failFinishTrainingAtomicContract('finishTraining must credit training before reduceGold(..., true).');
}

echo "finishTraining atomic purchase contract passed.\n";
