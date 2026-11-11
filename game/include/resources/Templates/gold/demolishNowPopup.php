<?php

declare(strict_types=1);

/**
 * `#demolishNowDialog`: short confirm copy + gold Use [coins].
 *
 * @var array{goldCost: int, title: string} $vars
 */
?>
<div id="demolishNowDialog">
    <h1><?= htmlspecialchars((string)$vars['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
    <p><?= htmlspecialchars((string)T('demolishNowPopup', 'desc'), ENT_QUOTES, 'UTF-8'); ?></p>
    <div class="buttonWrapper buttons">
        <?= getButton(
            [
                'type' => 'submit',
                'class' => 'textButtonV1 gold ',
                'coins' => (int)$vars['goldCost'],
                'onclick' => 'Travian.Game.PremiumFeature.InstantDemolition.confirm(); return false;',
                'title' => T('Buildings', 'mainBuilding.complete_demolish_title'),
            ],
            [
                'data' => [
                    'type' => 'submit',
                ],
            ],
            T('demolishNowPopup', 'Use')
        ); ?>
    </div>
</div>
