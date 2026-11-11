<?php use Game\GoldHelper;

$helper = new GoldHelper(); ?>
<div class="productionContainer">
    <div class="productionPerHour">
        <h4><?= T("productionOverview", "production_per_hour"); ?>:</h4>
        <table cellspacing="1" cellpadding="1" class="row_table_data">
            <thead>
            <tr>
                <td><?= T("productionOverview", "production_field"); ?></td>
                <td><?= T("productionOverview", "production"); ?></td>
                <td><?= T("productionOverview", "bonus"); ?></td>
            </tr>
            </thead>
            <tbody>
            <?php
            $totalProd = 0;
            $totalBonus = 0;
            $boostTotalBonus = 0;
            foreach ($vars['boost'][$vars['tabIndex']] as $boost) {
                $level = $vars['bonusBuilding'][$boost];
                if ($boost == 45) {
                    $boostTotalBonus += ($level * 0.05) * $vars['oasesBonus']['bonus'][$vars['tabIndex'] - 1];
                } else {
                    $boostTotalBonus += $level * 5;
                }
            }
            foreach ($vars['productionFields'] as $field) {
                $bonus = round5($field['value'] * ($boostTotalBonus + $vars['oasesBonus']['bonus'][$field['item_id'] - 1]) / 100);
                echo '<tr>
                <td>', T("Buildings", $field['item_id'] . '.title'), ' ', T("Buildings", "level"), ' ', $field['level'], '</td>
                <td class="numberCell">&#x202d;', number_format_x($field['value']), '&#x202c;</td>
                <td class="numberCell">&#x202d;', number_format_x($bonus), '&#x202c;</td>
            </tr>';
                $totalProd += $field['value'];
                $totalBonus += $bonus;
            }
            ?>
            <tr class="productionSum">
                <td><?= T("productionOverview", "sum"); ?></td>
                <td class="numberCell">&#x202d;<?= number_format_x($totalProd); ?>&#x202c;</td>
                <td class="numberCell">&#x202d;<?= number_format_x($totalBonus); ?>&#x202c;</td>
            </tr>
            </tbody>
        </table>
    </div>
    <div class="productionBoostSpeechBubble">
        <div class="fluidSpeechBubble-container">
            <div class="fluidSpeechBubble">
                <div class="fluidSpeechBubble-tl"></div>
                <div class="fluidSpeechBubble-tr"></div>
                <div class="fluidSpeechBubble-tc"></div>
                <div class="fluidSpeechBubble-ml"></div>
                <div class="fluidSpeechBubble-mr"></div>
                <div class="fluidSpeechBubble-mc"></div>
                <div class="fluidSpeechBubble-bl"></div>
                <div class="fluidSpeechBubble-br"></div>
                <div class="fluidSpeechBubble-bc"></div>
                <div class="speechArrowBack"></div>
                <div class="fluidSpeechBubble-contents cf">
                    <h5><?= T("productionOverview", "production_bonus"); ?>:</h5>
                    <table cellspacing="0" cellpadding="0"
                           class="row_table_data">
                        <tbody>
                        <?php
                        $waterWorksBoostPercent = -1;
                        $waterWorksLevel = 0;
                        foreach ($vars['boost'][$vars['tabIndex']] as $boost) {
                            $level = $vars['bonusBuilding'][$boost];
                            if ($boost == 45) {
                                $waterWorksLevel = $level;
                                $waterWorksBoostPercent = ($level * 0.05) * $vars['oasesBonus']['bonus'][$vars['tabIndex'] - 1];
                                continue;
                            } else {
                                $boostPercent = $level * 5;
                            }
                            if ($level <= 0) {
                                continue;
                            }
                            echo '<tr class="', ($boostPercent == 0 ? 'inactive' : ''), '">
                            <td>', T("Buildings", $boost . '.title'), ' ', T("Buildings", "level"), ' ', $level, ':</td>
                            <td class="numberCell">&#x202d;&#x202d;', $boostPercent, '&#x202c;%&#x202c;</td>
                        </tr>';
                        }
                        ?>
                        <tr class="<?= $vars['oasesBonus']['count'][$vars['tabIndex'] - 1] ? '' : 'inactive'; ?>">
                            <td><?= T("productionOverview", "Oases"); ?>
                                (&times;<?= $vars['oasesBonus']['count'][$vars['tabIndex'] - 1]; ?>)
                            </td>
                            <td class="numberCell">&#x202d;&#x202d;<?= $vars['oasesBonus']['bonus'][$vars['tabIndex'] - 1]; ?>&#x202c;%&#x202c;</td>
                        </tr>
                        <?php
                        if ($waterWorksLevel > 0) {
                            echo '<tr class="', ($waterWorksBoostPercent == 0 ? 'inactive' : ''), '">
                            <td>', T("Buildings", '45.title'), ' ', T("Buildings", "level"), ' ', $waterWorksLevel, ':</td>
                            <td class="numberCell">&#x202d;&#x202d;', $waterWorksBoostPercent, '&#x202c;%&#x202c;</td>
                        </tr>';
                        }
                        ?>
                        <tr>
                            <td class="bold"><?= T("productionOverview", "total_bonus"); ?></td>
                            <td class="bold numberCell">&#x202d;&#x202d;<?= $vars['oasesBonus']['bonus'][$vars['tabIndex'] - 1] + $boostTotalBonus; ?>&#x202c;%&#x202c;</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="total productionContainer">
    <div class="productionPerHourTotal">
        <h4><?= T("productionOverview", "total_production_per_hour"); ?>:</h4>
        <table cellspacing="0" cellpadding="0" class="row_table_data">
            <tbody>
            <tr>
                <td><?= T("productionOverview", "production"); ?></td>
                <td class="numberCell">&#x202d;<?= number_format_x($totalProd); ?>&#x202c;</td>
            </tr>
            <tr>
                <td><?= T("productionOverview", "bonus"); ?></td>
                <td class="numberCell">&#x202d;<?= number_format_x($totalBonus); ?>&#x202c;</td>
            </tr>
            <tr>
                <td><?= T("productionOverview", "hero_production"); ?></td>
                <td class="numberCell">&#x202d;<?= number_format_x($vars['heroProd'][$vars['tabIndex'] - 1]); ?>&#x202c;</td>
            </tr>
            <tr class="subtotal">
                <td class="bold"><?= T("productionOverview", "interim_balance"); ?>
                    =
                </td>
                <?php
                $total_net_prod = $totalProd + $totalBonus + $vars['heroProd'][$vars['tabIndex'] - 1];
                ?>
                <td class="numberCell bold">&#x202d;<?= number_format_x($total_net_prod); ?>&#x202c;</td>
            </tr>

            <tr class="<?= !$vars['bonusPlus'][$vars['tabIndex'] - 1] ? 'inactive' : ''; ?>">
                <td>&#x202d;&#x202d;25&#x202c;%&#x202c; <?= lcfirst((string)T("productionOverview", "production")); ?><?= !$vars['bonusPlus'][$vars['tabIndex'] - 1] ? ' (' . T("productionOverview", "inactive") . ')' : ''; ?></td>
                <td class="numberCell">&#x202d;<?= number_format_x($total_net_prod * 25 / 100); ?>&#x202c;</td>
            </tr>
            <?php
            // Reference always exposes the compensation-bonus row between the
            // production boost and the total. Local worlds currently grant 0%.
            $compensationPercent = (int)($vars['compensationBonusPercent'] ?? 0);
            $compensationAmount = (int)round($total_net_prod * $compensationPercent / 100);
            ?>
            <tr class="bold">
                <td class="bold">&#x202d;&#x202d;<?= $compensationPercent; ?>&#x202c;%&#x202c; <?= T("productionOverview", "compensation_bonus"); ?></td>
                <td class="numberCell">&#x202d;<?= number_format_x($compensationAmount); ?>&#x202c;</td>
            </tr>
            <tr class="total bold">
                <td class="bold"><?= T("productionOverview", "total"); ?></td>
                <td class="numberCell bold">&#x202d;<?= number_format_x($total_net_prod + ($vars['bonusPlus'][$vars['tabIndex'] - 1] ? round($total_net_prod / 4) : 0) + $compensationAmount); ?>&#x202c;</td>
            </tr>
            </tbody>
        </table>
    </div>

    <div class="productionBoostSpeechBubble">
    <div class="productionBoostResourceSpeechBubble">
        <div class="fluidSpeechBubble-container">
            <div class="fluidSpeechBubble">
                <div class="fluidSpeechBubble-tl"></div>
                <div class="fluidSpeechBubble-tr"></div>
                <div class="fluidSpeechBubble-tc"></div>
                <div class="fluidSpeechBubble-ml"></div>
                <div class="fluidSpeechBubble-mr"></div>
                <div class="fluidSpeechBubble-mc"></div>
                <div class="fluidSpeechBubble-bl"></div>
                <div class="fluidSpeechBubble-br"></div>
                <div class="fluidSpeechBubble-bc"></div>
                <div class="speechArrowBack"></div>
                <div class="fluidSpeechBubble-contents cf">
                    <form id="fluidSpeechBubble" method="post" action="">
                        <p><?= sprintf(T("productionOverview", "productionWithBoost"), '&#x202d;&#x202d;25&#x202c;%&#x202c;'); ?>:
                            <span class="bold">&#x202d;<?= number_format_x($totalProd + $totalBonus + $vars['heroProd'][$vars['tabIndex'] - 1] + round(($totalProd + $totalBonus + $vars['heroProd'][$vars['tabIndex'] - 1]) / 4)); ?>&#x202c;</span></p>
                        <?= $helper->getProductionBoostButton($vars['tabIndex'], false, false, true); ?>
                        <div class="productionBoostSpeechBubbleFurtherInfo">
                            <p><?= T("productionOverview", "productionBoostSpeechBubbleFurtherInfo"); ?></p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>
