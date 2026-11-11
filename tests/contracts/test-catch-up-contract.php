#!/usr/bin/env php
<?php

declare(strict_types=1);

// The catch-up pass: stored beat, wiring in the engine, and that it reuses the workers' processors.

function expectCatchUp(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__, 2);
$read = static fn (string $path): string => (string) file_get_contents($root . '/' . $path);
$engine = $read('game/include/AutomationEngine.php');
$catchUp = $read('game/include/Core/Jobs/CatchUp.php');
$automation = $read('game/include/Core/Automation.php');

expectCatchUp(str_contains($read('database/world.sql'), '`engineBeat` INT(10) UNSIGNED'), 'world.sql must store the last engine beat.');
expectCatchUp(str_contains($read('game/include/Core/Jobs/EngineHeartbeat.php'), 'UPDATE config SET engineBeat=?'), 'Every beat must also be stored in the world database.');

// The previous beat is read before the first beat overwrites it, and the pass runs before any worker exists.
$read1 = strpos($engine, "\$previousBeat = (int)(Config::getInstance()->dynamic->engineBeat ?? 0);");
$firstBeat = strpos($engine, "\$engineBeat();\n\$nextBeat");
$pass = strpos($engine, 'CatchUp::run(');
$launch = strpos($engine, 'Jobs\\Launcher::lunchJobs();');
expectCatchUp($read1 !== false && $firstBeat !== false && $read1 < $firstBeat, 'The engine must read the previous beat before its first beat.');
expectCatchUp($pass !== false && $launch !== false && $pass < $launch, 'The catch-up pass must run before the workers start.');
expectCatchUp(str_contains($engine, 'if ($loop && !EngineHeartbeat::pauseRequested()) {') && str_contains($engine, 'EngineHeartbeat::pauseRequested()'), 'A stop or pause during the pass must keep the workers from starting.');
expectCatchUp(str_contains($engine, "\$engineState = 'catching-up';") && str_contains($engine, "\$engineState = 'running';"), 'The beat must say the engine is catching up, then running.');

// One implementation of every task: the pass calls the workers' processors and holds no effect code of its own.
foreach (['processBuildingRow', 'processDemolitionTask', 'processResearchTask', 'processMarketTask', 'processMovementTask', 'trainingComplete'] as $method) {
    expectCatchUp(str_contains($catchUp, '->' . $method . '('), "The pass must call Automation::$method.");
}
expectCatchUp(!str_contains($catchUp, 'TransactionalTask') && !str_contains($catchUp, 'BuildingAction') && !str_contains($catchUp, 'DELETE FROM'), 'The pass must not carry effect or claiming code of its own.');
expectCatchUp(str_contains($automation, '$this->processBuildingRow((int)$row[\'id\'], (bool)$row[\'isMaster\'], $m);') && str_contains($automation, '$this->processMarketTask((int)$row[\'id\'], $processor);'), 'The workers must use the same row processors as the pass.');

// The knobs are documented and passed to the engine.
foreach (['TRAVIAN_CATCHUP_AFTER_SECONDS', 'TRAVIAN_CATCHUP_RATE_PER_SECOND', 'TRAVIAN_CATCHUP_MAX_SECONDS'] as $variable) {
    expectCatchUp(str_contains($read('compose.yaml'), $variable) && str_contains($read('docs/configuration.md'), $variable) && str_contains($read('.env.example'), $variable), "$variable must be in compose.yaml, configuration.md and .env.example.");
}

echo "Catch-up contract passed.\n";
