<?php

use Controller\Ajax\alliance\bonusOverview;
use Core\Config;
use Core\Database\DB;
use Core\Session;
use Core\Village;
use Game\Formulas;
use Model\AllianceBonusModel;

$config = Config::getInstance();
$session = Session::getInstance();
$village = Village::getInstance();
$m = new AllianceBonusModel();
if (!isset($minBonusLevel)) {
    $minBonusLevel = $m->getMinAllianceBonusLevel($session->getAllianceId());
}
if (!isset($limitPerDay)) {
    $limitPerDay = Formulas::getAllianceBonusDonationLimit($m->getMaxAllianceBonusLevel($session->getAllianceId()));
}
?>
<div id="allianceBonusWrapper" class="alliance-bonuses-overview alliance-bonuses-details">
    <?php if ($vars['newlyJoined']): ?>
        <div class="alliance_bonus_locked">
            <div><img class="lock_big" src="/img/spacer.gif"></div>
            <div class="alliance_bonus_locked_details">
                <?= T("AllianceBonus", "You joined the alliance less than 24:00 hours ago"); ?>
                <?= T("AllianceBonus", "You can still donate, but you may have to wait for the bonuses to unlock"); ?>
            </div>
        </div>
        <div class="clear"></div>
    <?php endif; ?>
    <div class="header">
        <div class="description">
            <?= T("AllianceBonus", "AllianceBonusDescription"); ?>
        </div>
    </div>
    <?php if ($minBonusLevel < 5): ?>
        <?php $reached = $session->getAllianceContribution() >= $limitPerDay; ?>
        <div id="contributionBox">
            <div class="contributionIconLarge"></div>
            <h4><?= T("AllianceBonus", "Contribute resources"); ?></h4>
            <div id="contributionForm">
                <?php require __DIR__ . "/contributionForm.php"; ?>
            </div>
        </div>
        <?php if (!$reached): ?>
            <div id="myDailyContributionLimit">
                <?php require __DIR__ . "/myDailyContributionLimit.php"; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <h4 class="round"><?= T("AllianceBonus", "Alliance bonus overview"); ?></h4>
    <div id="allianceBonusOverview">
        <?= bonusOverview::render(); ?>
    </div>
</div>

<div id="bonusLevelUpRewardTemplate" class="hide">
    <div class="bonusLevelUpReward">
        <div class="banner">
            <div class="description">
                <p></p>
                <p></p>
            </div>
        </div>
        <div class="stoneDisplayHeader">
            <div class="level1">
                <div class="star"></div>
                <div class="glow"></div>
            </div>
            <div class="level2">
                <div class="star"></div>
                <div class="glow"></div>
            </div>
            <div class="level5">
                <div class="star"></div>
                <div class="glow"></div>
            </div>
            <div class="level3">
                <div class="star"></div>
                <div class="glow"></div>
            </div>
            <div class="level4">
                <div class="star"></div>
                <div class="glow"></div>
            </div>
        </div>
        <div class="swords">
            <div class="sword1"></div>
            <div class="sword2"></div>
        </div>
        <div class="stoneDisplay">
            <div class="bonusRepresentation">
                <div>
                    <div class="stage1"></div>
                    <div class="glow"></div>
                    <div class="stage2"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    jQuery(function() {
        Travian.Translation.add({
            'allianz.allianceBonus_contributionResponse_success': <?= json_encode((string)T("AllianceBonus", "Contribution successful"), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        });
        Travian.Game.AllianceBonus.init(<?= (int)$session->getAllianceContribution(); ?>, <?= (int)$limitPerDay; ?>);
        Travian.Game.AllianceBonus.initBonusIcons();
        Travian.Game.AllianceBonus.initBonusOverview();
        <?php
        /**
         * @param int $type
         */
        function run_unlock_bonus_animation($type): string
        {
            $m = new AllianceBonusModel();
            $session = Session::getInstance();
            $session->set(AllianceBonusModel::USER_UNLOCK_PENDING_ANIMATION[$type], 0);

            $m->setAnimationAsFinished($session->getPlayerId(), $type);

            $level = $m->getAllianceBonusTypeParams($session->getAllianceId(), $type)['level'];
            $animation_classes = [
                AllianceBonusModel::TYPE_TRAINING => 'TroopProductionSpeed',
                AllianceBonusModel::TYPE_CP => 'CPProduction',
                AllianceBonusModel::TYPE_ARMOR => 'SmithyPower',
                AllianceBonusModel::TYPE_TRADE => 'MerchantCapacity',
            ];
            $animation_names = [
                AllianceBonusModel::TYPE_TRAINING => 'Recruitment',
                AllianceBonusModel::TYPE_CP => 'Philosophy',
                AllianceBonusModel::TYPE_ARMOR => 'Metallurgy',
                AllianceBonusModel::TYPE_TRADE => 'Commerce',
            ];
            $percent = $level * match ($type) {
                AllianceBonusModel::TYPE_TRADE => 30,
                AllianceBonusModel::TYPE_CP => 4,
                default => 2,
            };
            return 'Travian.Game.AllianceBonus.playLevelUpRewardAnimation("' . $animation_classes[$type] . '", ' . $level . ', "' . T("AllianceBonus", $animation_names[$type]) . '", "' . sprintf('+%s%%', $percent) . '");';
        }
        foreach(AllianceBonusModel::USER_UNLOCK_PENDING_ANIMATION as $key => $value){
            if ($session->get(AllianceBonusModel::USER_UNLOCK_PENDING_ANIMATION[$key]) == 1) {
                echo run_unlock_bonus_animation($key);
                break;
            }
        }
        ?>
    });
</script>
