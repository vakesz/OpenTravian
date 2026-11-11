<?php

declare(strict_types=1);

function failDeadHeroCropContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$resources = file_get_contents(dirname(__DIR__, 2) . '/game/include/Game/ResourcesHelper.php');
$production = file_get_contents(dirname(__DIR__, 2) . '/game/include/Controller/ProductionCtrl.php');
if ($resources === false || $production === false) {
    failDeadHeroCropContract('Hero production sources must be readable.');
}

if (str_contains($resources, 'FROM hero WHERE health!=0')
    || !str_contains($resources, 'SELECT production, productionType, health FROM hero WHERE kid=')
    || !str_contains($resources, '$production[3] += 6;')
    || !str_contains($resources, "if ((int)\$heroData['health'] > 0)")) {
    failDeadHeroCropContract('Resource updates must retain the fixed crop output from a dead home hero while omitting allocated production.');
}

if (!str_contains($production, '$this->heroProd[3] += 6;')
    || !str_contains($production, 'if ($hero->getHeroHealth() > 0)')) {
    failDeadHeroCropContract('The production overview must mirror dead-hero fixed crop output and live-only allocated production.');
}

echo "Dead home hero crop production contract passed.\n";
