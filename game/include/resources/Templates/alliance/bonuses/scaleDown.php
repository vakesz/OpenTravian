<?php

/** @var array<mixed> $vars Injected by resources\View\PHPBatchView::display(). */
$gold = !empty($vars['gold']);
$sum = array_sum($vars['resources']);
$names = ['scale1', 'scale2', 'scale3', 'scale4'];
$confirm = $gold
    ? getButton(
        [
            'id' => 'donate_gold_confirm',
            'type' => 'button',
            'class' => 'textButtonV1 gold ',
            'version' => 'textButtonV1',
            'title' => sprintf(T("AllianceBonus", "Contribute with %s gold"), (int)\Core\Config::getProperty('gold', 'allianceBonus3xGold')),
            'coins' => (int)\Core\Config::getProperty('gold', 'allianceBonus3xGold'),
        ],
        ['data' => ['type' => 'button', 'value' => T("AllianceBonus", "Contribute"), 'confirm' => '', 'onclick' => 'Travian.Game.PremiumFeature.AllianceBonus(1); return false;']],
        T("AllianceBonus", "Contribute")
    )
    : getButton(
        [
            'id' => 'donate_green',
            'type' => 'button',
            'class' => 'textButtonV1 green  disabled',
            'version' => 'textButtonV1',
            'title' => T("AllianceBonus", "Contribute"),
        ],
        ['data' => [
            'type' => 'button',
            'value' => T("AllianceBonus", "Contribute"),
            'onclick' => 'if(jQuery(this).hasClass(\'disabled\')){event.stopPropagation(); return false;} else {Travian.Game.AllianceBonus.contribute(true)}',
            'onfocus' => 'jQuery(\'button\', \'input[type!=hidden]\', \'select\').trigger(\'focus\'); event.stopPropagation(); return false;',
        ]],
        T("AllianceBonus", "Contribute")
    );
?>
<div class="bonus-scaleDown<?= $gold ? '' : ' normal'; ?>">
    <img class="res_small" src="/img/spacer.gif">
    <div class="bonus-scaleDown-text">
        <div class="bonus-scaleDown-text-error hide">
            <?= T("AllianceBonus", "Your contribution sum is higher than your daily limit"); ?>
        </div>
        <?= sprintf(T("AllianceBonus", "You can still contribute: %s resources."), '<span class="bold warning">' . $vars['untilLimit'] . '</span>'); ?>
    </div>
    <div class="bonus-donation">
        <table cellspacing="0" cellpadding="1">
            <thead>
            <tr>
                <?php foreach ($names as $index => $name): ?>
                <td>
                    <div class="inlineIconList resourceWrapper"><div class="inlineIcon resources" title=""><a onclick="Travian.Game.AllianceBonus.fillUp(document.getElementById('<?= $name; ?>'), <?= $vars['available'][$index]; ?>, 'scale')"><i class="r<?= $index + 1; ?>"></i></a><span class="value "><?= $vars['available'][$index]; ?></span></div></div>
                </td>
                <?php endforeach; ?>
                <td class="deco"></td>
                <td class="sum">
                    <?= T("AllianceBonus", "Sum:"); ?>
                    &nbsp;
                    <span>
                        <?= array_sum($vars['available']); ?>
                    </span>
                </td>
            </tr>
            </thead>
            <tbody>
            <tr>
                <?php foreach ($names as $index => $name): ?>
                <td class="don">
                    <input type="text" id="<?= $name; ?>" class="text" inputmode="numeric" maxlength="7" size="5"
                           value="<?= $vars['resources'][$index]; ?>"
                           onkeyup="Travian.Game.AllianceBonus.checkAndChange(this, <?= $vars['available'][$index]; ?>, 'scale')">
                </td>
                <?php endforeach; ?>
                <td class="deco"></td>
                <td class="sum">
                    <?= T("AllianceBonus", "Sum:"); ?>&nbsp;
                    <span id="scaleSum"><?= $sum; ?></span>
                </td>
            </tr>
            <?php if ($gold): ?>
            <tr>
                <td colspan="4" class="no_borders"></td>
                <td class="deco no_borders"></td>
                <td class="sum multiplied">
                    x3:&nbsp;
                    <span id="scaleSumMultiplied" data-multiplicator="3">
                        <?= 3 * $sum; ?>
                    </span>
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="bonus-donation-buttons-popup">
        <input type="hidden" id="multiplicationFactor" value="<?= $gold ? 3 : 1; ?>" />
        <span id="buttonScale"><?= getButton(
            [
                'id' => 'scale',
                'type' => 'button',
                'class' => 'textButtonV1 ' . ($gold ? 'gold' : 'green') . ' ',
                'version' => 'textButtonV1',
                'title' => T("AllianceBonus", "Scale down resources"),
            ],
            ['data' => ['type' => 'button', 'value' => T("AllianceBonus", "Scale down resources"), 'onclick' => 'Travian.Game.AllianceBonus.scaleDown()']],
            T("AllianceBonus", "Scale down resources")
        ); ?></span>
        <span id="buttonDonate" class="hidden"><?= $confirm; ?></span>
    </div>
</div>
