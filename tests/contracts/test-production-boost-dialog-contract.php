<?php

declare(strict_types=1);

$controller = file_get_contents(dirname(__DIR__, 2) . '/game/include/Controller/Ajax/premium/productionBoost.php');
$template = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/ajax/productionBoost.php');
$client = file_get_contents(dirname(__DIR__, 2) . '/game/deployment/public/game-client.js');

if ($controller === false
    || $template === false
    || $client === false
    || !str_contains($controller, "PHPBatchView('ajax/productionBoost')")
    || !str_contains($controller, "['html']")
    || !str_contains($template, 'getProductionBoostButton($resourceId, false, false, true)')
    || !str_contains($template, 'prolongProductionboost<?=ucfirst($boost')
    || !str_contains($template, "'productionBoost.remainingTime2'")
    || !str_contains($client, 'Travian.Dialog.Ajax.call(this, "premium/production-boost"')) {
    fwrite(STDERR, "The production-boost dialog must render all purchasable boosts and bind the client endpoint.\n");
    exit(1);
}

echo "Production-boost dialog contract passed.\n";
