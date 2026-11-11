<?php

use Core\Config;

/** @var array<mixed> $vars Injected by resources\View\PHPBatchView::display(). */
$sum = array_sum($vars['resources']);
$cost = (int)Config::getProperty('gold', 'allianceBonus3xGold');
?>
<div class="bonus-donation-gold">
    <img class="res_big" src="/img/spacer.gif">
    <div class="bonus-donation-gold-container">
        <div class="bonus-donation-gold-title">
            <?= T("AllianceBonus", "Your contribution will be tripled"); ?>
        </div>
        <div class="bonus-donation-gold-start-value">
            <?= sprintf(T("AllianceBonus", "Contribution: %s resources"), $sum); ?>
            <div class="bonus-donation-gold-arrowMultiply">
                x 3
            </div>
        </div>
        <div class="bonus-donation-gold-outcome-value">
            <?= sprintf(T("AllianceBonus", "Tripled: %s resources"), '<strong>' . (3 * $sum) . '</strong>'); ?>
        </div>
        <div class="bonus-donation-buttons-popup">
            <span id="buttonDonate"><?= getButton(
                [
                    'id' => 'donate_gold_confirm',
                    'type' => 'button',
                    'class' => 'textButtonV1 gold ',
                    'version' => 'textButtonV1',
                    'title' => sprintf(T("AllianceBonus", "Contribute with %s gold"), $cost),
                    'coins' => $cost,
                ],
                ['data' => ['type' => 'button', 'value' => T("AllianceBonus", "Contribute"), 'confirm' => '', 'onclick' => 'Travian.Game.PremiumFeature.AllianceBonus(); return false;']],
                T("AllianceBonus", "Contribute")
            ); ?></span>
        </div>
        <span id="donateGoldConfirmSum" class="hide"><?= 3 * $sum; ?></span>
    </div>
</div>
