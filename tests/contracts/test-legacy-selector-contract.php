<?php

declare(strict_types=1);

function failLegacySelectorContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$sources = [
    'alliance description' => $root . '/game/include/resources/Templates/alliance/optionChangeDesc.php',
    'alliance internal page' => $root . '/game/include/resources/Templates/alliance/changeInternalPage.php',
    'profile editor' => $root . '/game/include/resources/Templates/profile/EditProfile.php',
    'academy' => $root . '/game/include/resources/Templates/build/building/Academy.php',
    'map outline' => $root . '/game/include/resources/Templates/map/normal.php',
];

foreach ($sources as $name => $path) {
    $source = file_get_contents($path);
    if ($source === false) {
        failLegacySelectorContract("The {$name} source must be readable.");
    }
    if (str_contains($source, '$(')) {
        failLegacySelectorContract("The {$name} control must not depend on the removed Prototype selector helper.");
    }
}

$academy = file_get_contents($sources['academy']);
if (!str_contains($academy, "jQuery('#researchFutureLink')")
    || !str_contains($academy, 'Travian.toggleSwitch(future, switchLink)')
    || !str_contains($academy, "switchLink.text(future.hasClass('hide')")) {
    failLegacySelectorContract('The Academy future-research toggle must update its visibility and label together.');
}

$map = file_get_contents($sources['map outline']);
if (!str_contains($map, 'class="entry player selected"')
    || !str_contains($map, 'class="entry alliance"')
    || substr_count($map, "return outlineContainerTabClick('") !== 2) {
    failLegacySelectorContract('Map outline tabs must use the shared state-aware click handler.');
}

echo "Legacy selector contract passed.\n";
