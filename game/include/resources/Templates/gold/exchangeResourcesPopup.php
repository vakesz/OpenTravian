<?php

declare(strict_types=1);

/**
 * NPC merchant dialog: `#build.exchangeResources` + `#npc` table.
 *
 * @var array{
 *     currentResources: list<int|float>,
 *     desiredResources: list<int>,
 *     total: int|float,
 *     goldCost: int,
 *     villageId: int
 * } $vars
 */
$villageId = (int)$vars['villageId'];
$resources = array_map(static fn ($v): int => (int)floor((float)$v), $vars['currentResources']);
$total = (int)floor((float)$vars['total']);
$desired = array_map(static fn ($v): int => (int)$v, $vars['desiredResources']);
$goldCost = (int)$vars['goldCost'];
$resourceAlts = [
    T('inGame', 'resources.r1'),
    T('inGame', 'resources.r2'),
    T('inGame', 'resources.r3'),
    T('inGame', 'resources.r4'),
];
?>
<div id="build" class="exchangeResources">
    <p class="npc_desc"><?= T('npc', 'npc_desc'); ?></p>
    <input type="hidden" name="villageId" value="<?= $villageId ?>">
    <table id="npc" cellpadding="1" cellspacing="1">
        <thead>
            <tr>
                <?php for ($i = 0; $i < 4; $i++): ?>
                <td class="all">
                    <a href="#" onclick="exchangeResources.fillup(<?= $i ?>); return false;">
                        <i class="r<?= $i + 1 ?>" src="/img/spacer.gif" alt="<?= htmlspecialchars((string)$resourceAlts[$i], ENT_QUOTES, 'UTF-8'); ?>" title="<?= htmlspecialchars((string)$resourceAlts[$i], ENT_QUOTES, 'UTF-8'); ?>"></i>
                    </a>
                    <span id="org<?= $i ?>"><?= $resources[$i] ?></span>
                </td>
                <?php endfor; ?>
                <td class="deco"></td>
                <td class="sum"><?= htmlspecialchars((string)T('npc', 'sum'), ENT_QUOTES, 'UTF-8') ?>:&nbsp;<span id="sum"><?= $total ?></span></td>
            </tr>
        </thead>
        <tbody>
            <tr>
                <?php for ($i = 0; $i < 4; $i++): ?>
                <td class="sel">
                    <input type="text" inputmode="numeric" class="text" onkeyup="exchangeResources.calculateRest();" name="desired<?= $i ?>" size="5" maxlength="7" value="<?= $desired[$i] ?>">
                </td>
                <?php endfor; ?>
                <td class="deco"></td>
                <td class="sum"><?= htmlspecialchars((string)T('npc', 'sum'), ENT_QUOTES, 'UTF-8') ?>:&nbsp;<span id="newsum"><?= array_sum($desired) ?></span></td>
            </tr>
            <tr>
                <?php for ($i = 0; $i < 4; $i++): ?>
                <td class="rem">
                    <span id="diff<?= $i ?>"><?= $desired[$i] - $resources[$i] ?></span>
                </td>
                <?php endfor; ?>
                <td class="deco"></td>
                <td class="sum">Rest:&nbsp;<span id="remain"><?= $total - array_sum($desired) ?></span></td>
            </tr>
        </tbody>
    </table>
    <p id="submitButton" class="disableButtonHandler" style="display: none;">
        <?= getButton(
            [
                'type' => 'submit',
                'class' => 'textButtonV1 gold ',
                'coins' => $goldCost,
                'id' => 'npc_market_button',
                'onclick' => 'Travian.Game.PremiumFeature.NpcTrader(returnInputValues()); return false;',
                'title' => T('npc', 'redeem_now') . '.',
            ],
            [
                'data' => [
                    'type' => 'submit',
                ],
            ],
            T('npc', 'redeem')
        ); ?>
    </p>
    <p id="submitText" style="display: block;">
        <?= getButton(
            [
                'type' => 'button',
                'class' => 'textButtonV1 gold ',
                'onclick' => 'exchangeResources.distribute(' . $villageId . ');',
                'title' => T('npc', 'distribute_remaining_resources'),
            ],
            [
                'data' => [
                    'type' => 'button',
                ],
            ],
            T('npc', 'distribute_remaining_resources')
        ); ?>
    </p>
    <script>
        var exchangeResources = new Travian.Game.ExchangeResources(jQuery('table#npc'));
        // used as callback in paymentfeature to get data for the action
        function returnInputValues() {
            return {
                'villageId': parseInt(jQuery('div#build.exchangeResources input[name=villageId]').val(), 10),
                'desired': {
                    'lumber': parseInt(jQuery('div#build.exchangeResources input[name=desired0]').val(), 10),
                    'clay': parseInt(jQuery('div#build.exchangeResources input[name=desired1]').val(), 10),
                    'iron': parseInt(jQuery('div#build.exchangeResources input[name=desired2]').val(), 10),
                    'crop': parseInt(jQuery('div#build.exchangeResources input[name=desired3]').val(), 10),
                }
            };
        }
    </script>
</div>
