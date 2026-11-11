<?php

declare(strict_types=1);

function failSidebarVillageAttackCacheContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$source = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/View/GameView.php');
if ($source === false) {
    failSidebarVillageAttackCacheContract('Village sidebar renderer must be readable.');
}

foreach ([
    '$attacksList = $memcache->get("attacksList:{$this->session->getPlayerId()}");',
    'if (!is_array($attacksList)) {',
    '$attacksList = [];',
] as $needle) {
    if (!str_contains($source, $needle)) {
        failSidebarVillageAttackCacheContract('A missing attack-count cache entry must normalize to an array before mutation.');
    }
}

if (str_contains($source, 'if (!($attacksList = $memcache->get(')) {
    failSidebarVillageAttackCacheContract('The attack-count cache miss must not overwrite the array with false.');
}

echo "Sidebar village attack-cache contract passed.\n";
