<?php

declare(strict_types=1);

$processor = file_get_contents(dirname(__DIR__, 2) . '/game/include/Model/Movements/ReinforcementProcessor.php');

if ($processor === false
    || !str_contains($processor, '$units_id[] = nrToUnitId($i, $race);')
    || !str_contains($processor, '$calculator->isCavalryOnly($units_id)')
    || !str_contains($processor, 'setHunnicMountedArmySpeedBonus')) {
    fwrite(STDERR, "Returning reinforcements must retain a Hun hero's cavalry-only speed bonus.\n");
    exit(1);
}

echo "Reinforcement return-speed contract passed.\n";
