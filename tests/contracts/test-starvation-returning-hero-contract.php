<?php

declare(strict_types=1);

function failStarvationReturningHeroContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$source = file_get_contents(dirname(__DIR__, 2) . '/game/include/Game/Starvation.php');
if ($source === false) {
    failStarvationReturningHeroContract('Starvation source must be readable.');
}

if (!str_contains($source, "\$heroKid = \$isMovement && (int)\$row['mode'] === 1 ? \$row['to_kid'] : \$row['kid'];")
    || !str_contains($source, 'UPDATE hero SET health=0 WHERE kid=$heroKid')
    || str_contains($source, 'UPDATE hero SET health=0 WHERE kid={$row[\'kid\']}')) {
    failStarvationReturningHeroContract('Starving returning heroes must be killed at their home/destination village, not the stale movement origin.');
}

echo "Returning-hero starvation contract passed.\n";
