<?php

use Core\Config;
use Core\Session;
use Game\GoldHelper;
use Model\AutoExtendModel;

$session = Session::getInstance();
$config = Config::getInstance();
$autoExtend = new AutoExtendModel();
$helper = new GoldHelper();
$boosts = [
    ['id' => 'wood', 'resourceId' => 1, 'title' => 'woodProductionBoost'],
    ['id' => 'clay', 'resourceId' => 2, 'title' => 'clayProductionBoost'],
    ['id' => 'iron', 'resourceId' => 3, 'title' => 'ironProductionBoost'],
    ['id' => 'crop', 'resourceId' => 4, 'title' => 'cropProductionBoost'],
];
?>
<div class="paymentPopupDialogWrapper premiumFeaturePackage premiumFeatureProductionBoost">
    <h1 class="headline">
        <?=T('productionBoostPopup', 'Production bonus'); ?>
        <span class="goldWrapper"><i class="gold"></i><?=number_format_x($config->gold->productionBoostGold); ?></span>
    </h1>
    <h3 class="subHeadLine"><?=T('productionBoostPopup', 'Select which resources production you would like to increase:'); ?></h3>
    <div class="featureCollection" id="featureCollectionWrapper">
        <?php foreach ($boosts as $boost): ?>
            <?php
            $resourceId = $boost['resourceId'];
            $active = $session->hasProductionBoost($resourceId);
            $autoExtendType = $resourceId + 1;
            $autoExtendActive = $autoExtend->hasAutoExtend($session->getPlayerId(), $autoExtendType);
            $remainingClass = $autoExtendActive ? 'renewalActive' : 'bonusEndsSoon';
            ?>
            <article class="feature featureBooking premiumFeatureProductionBoost">
                <div class="featureImage <?=$boost['id']; ?>"></div>
                <div class="featureContent">
                    <h3 class="featureTitle productionBoostTitle"><?=T('inGame', 'productionBoost.' . $boost['title']); ?></h3>
                    <div class="featureRemainingTime productionBoostSubtitle subtitle <?=$active ? $remainingClass : ''; ?>">
                        <?php if ($active): ?>
                            <?=sprintf(T('inGame', 'productionBoost.remainingTime2'), appendTimer($session->productionBoostTill($resourceId) - time())); ?>
                        <?php else: ?>
                            <?=T('PaymentWizard', 'Bonus duration'); ?>: <?=$duration; ?>
                        <?php endif; ?>
                    </div>
                    <div class="featureButton productionBoostButtonPos">
                        <?=$helper->getProductionBoostButton($resourceId, false, false, true); ?>
                    </div>
                    <?php if ($active): ?>
                        <div class="featureDuration featureRenewal productionBoostButtonSubtitle subtitle">
                            <input type="checkbox" id="productionboost-<?=$boost['id']; ?>" class="checkbox prolongProductionboost<?=ucfirst($boost['id']); ?>" <?=$autoExtendActive ? 'checked="checked"' : ''; ?>>
                            <label for="productionboost-<?=$boost['id']; ?>"><?=T('PaymentWizard', 'Extend automatically'); ?></label>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="clear"></div>
            </article>
        <?php endforeach; ?>
    </div>
    <p class="furtherInfos"><?=sprintf(T('productionBoostPopup', 'furtherInfos'), $duration); ?></p>
</div>
