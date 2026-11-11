<?php

declare(strict_types=1);

$battle = file_get_contents(dirname(__DIR__, 2) . '/game/include/Model/BattleModel.php');

if ($battle === false
    || !str_contains($battle, '$returnSpeeds = [];')
    || !str_contains($battle, '$returnUnitIds = [];')
    || !str_contains($battle, '$calculator->setMinSpeed($returnSpeeds);')
    || !str_contains($battle, '$calculator->isCavalryOnly($returnUnitIds)')) {
    fwrite(STDERR, "Caged-attack returns must derive their speed only from the returning army.\n");
    exit(1);
}

echo "Caged-attack return-speed contract passed.\n";
