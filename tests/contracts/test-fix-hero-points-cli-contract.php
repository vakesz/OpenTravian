<?php

declare(strict_types=1);

function failFixHeroPointsCliContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$source = file_get_contents(dirname(__DIR__, 2) . '/game/include/fixes/fixHeroPoints.php');
if ($source === false) {
    failFixHeroPointsCliContract('Could not read the hero-points repair script.');
}

if (!str_contains($source, 'dirname(__DIR__, 2)')
    || !str_contains($source, 'deployment')
    || !str_contains($source, 'env.php')
    || !str_contains($source, "dirname(__DIR__) . DIRECTORY_SEPARATOR . \"bootstrap.php\"")) {
    failFixHeroPointsCliContract('The hero-points repair must bootstrap correctly when run as a CLI script.');
}

echo "Hero-points CLI repair contract passed.\n";
