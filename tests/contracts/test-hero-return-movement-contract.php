<?php

declare(strict_types=1);

function failHeroReturnMovementContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$adventure = file_get_contents(dirname(__DIR__, 2) . '/game/include/Model/Movements/AdventureProcessor.php');
$battle = file_get_contents(dirname(__DIR__, 2) . '/game/include/Model/BattleModel.php');
$return = file_get_contents(dirname(__DIR__, 2) . '/game/include/Model/Movements/ReturnProcessor.php');
if ($adventure === false || $battle === false || $return === false) {
    failHeroReturnMovementContract('Hero return sources must be readable.');
}

foreach ([
    '$calc->setFrom($row[\'to_kid\']);',
    '$calc->setTo($row[\'kid\']);',
    '$calc->isReturn();',
    '$timeNeeded = $calc->calc();',
    '$row[\'end_time\'] + 1000 * $timeNeeded',
    '1,',
] as $required) {
    if (!str_contains($adventure, $required)) {
        failHeroReturnMovementContract("Adventure completion must create a timed mode-1 hero return: missing $required");
    }
}

if (!str_contains($battle, '2 * $this->row[\'end_time\'] - $this->row[\'start_time\']')
    || !str_contains($battle, '$this->row[\'to_kid\'], $this->row[\'kid\']')) {
    failHeroReturnMovementContract('Normal battle return must retain the outbound travel duration and destination.');
}

if (!str_contains($return, 'if ($row[\'mode\'] == 1)')
    && !str_contains($return, 'UPDATE units SET')) {
    failHeroReturnMovementContract('Only the return processor may restore returned hero units to their home village.');
}

echo "Hero adventure and battle returns are timed movement contracts.\n";
