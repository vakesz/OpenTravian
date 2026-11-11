<?php

declare(strict_types=1);

/**
 * Contract: NPC merchant dialog matches #build.exchangeResources + #npc,
 * not the retired .npcExchange custom shell / npc-exchange.js path.
 */
function failNpcExchangeDialogContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$popup = file_get_contents($root . '/game/include/resources/Templates/gold/exchangeResourcesPopup.php');
$helper = file_get_contents($root . '/game/include/Game/GoldHelper.php');
$head = file_get_contents($root . '/game/include/resources/Templates/layout/head.php');
$crypt = file_get_contents($root . '/game/deployment/public/game-client.js');

foreach ([$popup, $helper, $head, $crypt] as $source) {
    if ($source === false) {
        failNpcExchangeDialogContract('Unable to read NPC exchange sources.');
    }
}

if (!str_contains($popup, 'id="build" class="exchangeResources"')
    || !str_contains($popup, 'id="npc"')
    || !str_contains($popup, 'exchangeResources.fillup')
    || !str_contains($popup, 'NpcTrader(returnInputValues())')
    || !str_contains($popup, 'distribute_remaining_resources')
    || str_contains($popup, 'npcExchange')
    || str_contains($popup, 'getNpcExchangeValues')) {
    failNpcExchangeDialogContract('exchangeResourcesPopup must emit #npc table markup.');
}

if (substr_count($popup, "T('npc', 'sum')") !== 2 || str_contains($popup, 'Sum:&nbsp;')) {
    failNpcExchangeDialogContract('NPC totals must use the canonical localized Sum label.');
}

if (!str_contains($helper, '"did"')
    || !str_contains($helper, 'exchangeResourcesClick')
    || !str_contains($helper, 'exchange-resources-popup')) {
    failNpcExchangeDialogContract('Exchange resources button must carry did + click title.');
}

if (str_contains($head, 'npc-exchange.js')
    || is_file($root . '/game/deployment/public/npc-exchange.js')) {
    failNpcExchangeDialogContract('Remove obsolete npc-exchange.js legacy path.');
}

if (!str_contains($crypt, 'Travian.Game.ExchangeResources = Travian.Game.Marketplace.ExchangeResources')
    || !str_contains($crypt, 'NpcTrader: function')) {
    failNpcExchangeDialogContract('game-client.js must alias ExchangeResources and expose NpcTrader.');
}

echo "NPC exchange dialog contract passed.\n";
