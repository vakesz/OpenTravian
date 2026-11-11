<?php

use Core\Config;
use Game\ExtraModules;

$durationContent = '<span class="value">' . htmlspecialchars((string)$vars['timeInString'], ENT_QUOTES, 'UTF-8') . '</span>';
$benefitTitle = buildingBenefitTitleAttribute(...);
$trainingTimeSign = static function (int $reduction): string {
    return $reduction > 0 ? '&#8237;−&#8237;' : '&#8237;+&#8237;';
};

?>
<?php if ($vars['isNew']): ?>
    <div class="build_desc">
        <a class="build_logo"
           onclick="return Travian.Game.iPopup(<?= $vars['itemId']; ?>, 4, 'gid');"
           href="#">
            <img class="build_logo big white g<?= $vars['itemId']; ?>"
                 alt="<?= T("Buildings", $vars['itemId'] . '.title'); ?>"
                 src="/img/spacer.gif"/>
        </a>
        <?= T("Buildings", $vars['itemId'] . '.desc'); ?>
    </div>
    <?php if ($vars['showValuesTable']): ?>
        <table cellpadding="1" cellspacing="1" id="build_value">
            <?= $vars['valueTable']; ?>
        </table>
    <?php endif; ?>
    <?php if (ExtraModules::isEnabled('upgradeToMaxLevel')): ?>
        <script type="text/javascript">
            function getGidX() {
                return {additionalData: {gid: <?= (int)$vars['building_field']; ?>}};
            }
        </script>
    <?php endif; ?>
    <div id="contract" class="contractWrapper">
        <?php if ($vars['showCosts']): ?>
            <div class="contractText">
                <?= $vars['contractText']; ?>:
            </div>
            <?= \Game\Hero\CostChips::render($vars['cost'], $vars['freeCrop']); ?>
        <div class="lineWrapper">
            <div class="inlineIcon duration"><i class="clock_medium"></i><?= $durationContent; ?></div>
        </div>
        <?= $vars['npcButton']; ?>
        <?php endif; ?>
        <div class="contractLink">
            <?php echo($vars['contractLink']); ?>
        </div>
    </div>
    <?php if (ExtraModules::isEnabled('increaseStorage')): ?>
        <?php if ($vars['itemId'] == 10 || $vars['itemId'] == 11 || $vars['itemId'] == 38 || $vars['itemId'] == 39): ?>
            <div style="margin-<?= getDirection() == 'RTL' ? 'right' : 'left'; ?>: 385px; color: blue; font-weight: bold;">
                <br/>
                <br/>
                <br/>
                <br/>
                <br/>
                <?php if (true/*$vars['itemId'] == 10 || $vars['itemId'] == 38*/): ?>
                    <?= T("Buildings", "increase warehouse storage by level 20 storage value"); ?>
                <?php else: ?>
                    <?= T("Buildings", "increase granny storage by level 20 storage value"); ?>
                <?php endif; ?>
                <br/>
                <?= T("Global", "Times"); ?>: <input
                        min="1"
                        onchange="onChangeTimes()"
                        onkeydown="onChangeTimes()"
                        onkeyup="onChangeTimes()"
                        type="number" class="text" id="times" value="1" style="width: 45px;">
                <?= ExtraModules::showButton("increaseStorage", 'getGidAndTimes'); ?>
                <script type="text/javascript">
                    function getGidAndTimes() {
                        return {
                            additionalData: {
                                gid: <?=$vars['building_field'];?>,
                                times: jQuery('#times').val()
                            }
                        };
                    }

                    var coins = jQuery("button.gold.increaseStorage").attr("coins");

                    function onChangeTimes() {
                        var times = jQuery('#times').val();
                        jQuery("button.gold.increaseStorage .goldValue").html(times * coins);
                    }
                </script>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    <div class="clear"></div>
<?php else: ?>
    <?php $hasValueTable = trim((string)$vars['valueTable']) !== ''; ?>
    <div class="upgradeHeader<?= $hasValueTable ? ' levelSpecificInfo' : ' '; ?>">
	   <a onclick="Travian.Game.Manual.open('building', <?= (int)$vars['itemId']; ?>);"><img class="buildingIllustration g<?= $vars['itemId']; ?> <?= $vars['raceName']; ?> " alt="<?= T("Buildings", $vars['itemId'] . '.title'); ?>" src="/img/spacer.gif"></a>
<?php if ($hasValueTable): ?>
	   <table cellpadding="1" cellspacing="1" id="build_value" class="transparent">
		  <?= $vars['valueTable']; ?>
	   </table>
<?php endif; ?>
	</div>
	<div class="buildingDescription ">
	   <div class="headline">
		  <div class="openedClosedSwitch switchOpened"></div>
		  <h4><?= T("Global", "General.description"); ?></h4>
	   </div>
	   <div class="description"><?= T("Buildings", $vars['itemId'] . '.desc'); ?></div>
	</div>
	<script type="text/javascript" data-cmp-info="6">
		jQuery(function () {
			Travian.Game.BuildingUpgradeView.initialize();
		})
	</script>
    <?php if (ExtraModules::isEnabled('upgradeToMaxLevel')): ?>
        <script type="text/javascript">
            function getGidX() {
                return {additionalData: {gid: <?= (int)$vars['building_field']; ?>}};
            }
        </script>
    <?php endif; ?>
	<div class="upgradeBuilding <?= !$vars['showCosts'] ? "completed" : ""; ?>">
    <?php if (!$vars['showCosts']): ?>
			<div class="completedMessage"><?= $vars['contractLink']['main']; ?></div>
			<div class="buildingBenefits completed">
				<div class="wrapper">
					<div class="unit"<?= $benefitTitle('culturePoints_medium'); ?>>
						<i class="culturePoints_medium"></i>
						<span class="value"><?=$vars['culturePointsAndPopulation']['cp'];?></span>
						<span class="delta">(&#8237;+&#8237;<?=$vars['nextLevelCpPop']['cp'];?>&#8236;&#8236;)</span>
					</div>
					<div class="unit"<?= $benefitTitle('population_medium'); ?>>
						<i class="population_medium"></i>
						<span class="value"><?=$vars['culturePointsAndPopulation']['pop'];?></span>
						<span class="delta">(&#8237;+&#8237;<?=$vars['nextLevelCpPop']['pop'];?>&#8236;&#8236;)</span>
					</div>
					<?php if ($vars['culturePointsAndPopulation']['infantryBonusTime']  !== null):?>
						<div class="unit"<?= $benefitTitle('infantryBonusTime_medium'); ?>>
							<i class="infantryBonusTime_medium"></i>
							<span class="value"><?=$trainingTimeSign($vars['culturePointsAndPopulation']['infantryBonusTime']);?><?=$vars['culturePointsAndPopulation']['infantryBonusTime'];?>&#8236;%&#8236;</span>
							<span class="delta">(<?=$trainingTimeSign($vars['nextLevelCpPop']['infantryBonusTime']);?><?=$vars['nextLevelCpPop']['infantryBonusTime'];?>&#8236;%&#8236;)</span>
						</div>
					<?php endif;?>

					<?php if ($vars['culturePointsAndPopulation']['cavalryBonusTime']  !== null):?>
						<div class="unit"<?= $benefitTitle('cavalryBonusTime_medium'); ?>>
							<i class="cavalryBonusTime_medium"></i>
							<span class="value"><?=$trainingTimeSign($vars['culturePointsAndPopulation']['cavalryBonusTime']);?><?=$vars['culturePointsAndPopulation']['cavalryBonusTime'];?>&#8236;%&#8236;</span>
							<span class="delta">(<?=$trainingTimeSign($vars['nextLevelCpPop']['cavalryBonusTime']);?><?=$vars['nextLevelCpPop']['cavalryBonusTime'];?>&#8236;%&#8236;)</span>
						</div>
					<?php endif;?>
					<?php if ($vars['culturePointsAndPopulation']['merchantCap']  !== null):?>
						<div class="unit"<?= $benefitTitle('merchantCap_small'); ?>>
							<i class="merchantCap_small"></i>
							<span class="value"><?=$vars['culturePointsAndPopulation']['merchantCap'];?>&#8236;&#8236;</span>
							<span class="delta">(+<?=$vars['nextLevelCpPop']['merchantCap'];?>&#8236;&#8236;)</span>
						</div>
					<?php endif;?>
					<?php if ($vars['culturePointsAndPopulation']['siegeTime'] !== null):?>
						<div class="unit"<?= $benefitTitle('siegeTime_small'); ?>>
							<i class="siegeTime_small"></i>
							<span class="value"><?=$trainingTimeSign($vars['culturePointsAndPopulation']['siegeTime']);?><?=$vars['culturePointsAndPopulation']['siegeTime'];?>&#8236;%&#8236;</span>
							<span class="delta">(<?=$trainingTimeSign($vars['nextLevelCpPop']['siegeTime']);?><?=$vars['nextLevelCpPop']['siegeTime'];?>&#8236;%&#8236;)</span>
						</div>
					<?php endif;?>
					<?php if ($vars['culturePointsAndPopulation']['maxTraps'] !== null):?>
						<div class="unit"<?= $benefitTitle('maxTraps_medium'); ?>>
							<i class="maxTraps_medium"></i>
							<span class="value">&#8237;&#8237;<?=$vars['culturePointsAndPopulation']['maxTraps'];?>&#8236;&#8236;</span>
							<span class="delta">(&#8237;+&#8237;<?=$vars['nextLevelCpPop']['maxTraps'];?>&#8236;&#8236;)</span>
						</div>
					<?php endif;?>
					<?php if ($vars['culturePointsAndPopulation']['warehouseCap']  !== null):?>
						<div class="unit"<?= $benefitTitle('warehouseCap_medium'); ?>>
							<i class="warehouseCap_medium"></i>
							<span class="value">&#8237;<?=$vars['culturePointsAndPopulation']['warehouseCap'];?>&#8236;</span>
							<span class="delta">(&#8237;+&#8237;<?=$vars['nextLevelCpPop']['warehouseCap'];?>&#8236;&#8236;)</span>
						</div>
					<?php endif;?>
					<?php if ($vars['culturePointsAndPopulation']['granaryCap']  !== null):?>
						<div class="unit"<?= $benefitTitle('granaryCap_medium'); ?>>
							<i class="granaryCap_medium"></i>
							<span class="value">&#8237;<?=$vars['culturePointsAndPopulation']['granaryCap'];?>&#8236;</span>
							<span class="delta">(&#8237;+&#8237;<?=$vars['nextLevelCpPop']['granaryCap'];?>&#8236;&#8236;)</span>
						</div>
					<?php endif;?>
					<?php if ($vars['culturePointsAndPopulation']['troopSpeed']  !== null):?>
						<div class="unit"<?= $benefitTitle('troopSpeed_medium'); ?>>
							<i class="troopSpeed_medium"></i>
							<span class="value"><?=($vars['culturePointsAndPopulation']['troopSpeed']);?>&#8236;%&#8236;</span>
							<span class="delta">(&#8237;+&#8237;<?=($vars['nextLevelCpPop']['troopSpeed']);?>&#8236;%&#8236;)</span>
						</div>
					<?php endif;?><?php if ($vars['culturePointsAndPopulation']['crannyCap']  !== null):?>
						<div class="unit"<?= $benefitTitle('crannyCap_small'); ?>>
							<i class="crannyCap_small"></i>
							<span class="value"><?=($vars['culturePointsAndPopulation']['crannyCap']);?>&#8236;&#8236;</span>
							<span class="delta">(&#8237;+&#8237;<?=($vars['nextLevelCpPop']['crannyCap']);?>&#8236;&#8236;)</span>
						</div>
					<?php endif;?>
					<?php if ($vars['culturePointsAndPopulation']['troopVisibility'] !== null):?>
						<div class="unit"<?= $benefitTitle('troopVisibility_small'); ?>>
							<i class="troopVisibility_small"></i>
							<span class="value">&#8237;<?=$vars['culturePointsAndPopulation']['troopVisibility'];?>&#8236;</span>
							<span class="delta">(&#8237;+&#8237;<?=$vars['nextLevelCpPop']['troopVisibility'];?>&#8236;&#8236;)</span>
						</div>
					<?php endif;?>
					<?php if ($vars['culturePointsAndPopulation']['defenceBonus'] !== null):?>
						<div class="unit"<?= $benefitTitle('defenceBonus_medium'); ?>>
							<i class="defenceBonus_medium"></i>
							<span class="value">&#8237;<?=$vars['culturePointsAndPopulation']['defenceBonus'];?>&#8236;</span>
							<span class="delta">(&#8237;+&#8237;<?=$vars['nextLevelCpPop']['defenceBonus'];?>&#8236;&#8236;)</span>
						</div>
					<?php endif; ?>
				</div>
			</div>
    <?php else: ?>
            <div id="contract" class="contractWrapper">
                <?= \Game\Hero\CostChips::render($vars['cost'], $vars['freeCrop']); ?>
                <div class="upgradeBlocked">
                <?php if (isset($vars['contractLink']['noResources']) && $vars['contractLink']['noResources']): ?>
                    <div class="errorMessage">
                        <?= $vars['contractLink']['main']; ?>
                       <span class="hide">
                       <?php
                       $time = \Core\Village::getInstance()->calcWhenResourcesAreAvailable($vars['cost']);
                echo appendTimer($time);
                ?>
                       </span>
                    </div>
                    <?= $vars['npcButton']; ?>
                <?php endif; ?>
                </div>
            </div>
            <div class="buildingBenefits ">
                <div class="wrapper">
                    <div class="unit"<?= $benefitTitle('culturePoints_medium'); ?>>
                        <i class="culturePoints_medium"></i>
                        <span class="value"><?=$vars['culturePointsAndPopulation']['cp'];?></span>
                        <span class="delta">(&#8237;+&#8237;<?=$vars['nextLevelCpPop']['cp'];?>&#8236;&#8236;)</span>
                    </div>
                    <div class="unit"<?= $benefitTitle('population_medium'); ?>>
                        <i class="population_medium"></i>
                        <span class="value"><?=$vars['culturePointsAndPopulation']['pop'];?></span>
                        <span class="delta">(&#8237;+&#8237;<?=$vars['nextLevelCpPop']['pop'];?>&#8236;&#8236;)</span>
                    </div>
                    <?php if ($vars['culturePointsAndPopulation']['constructionTime'] !== null):?>
                        <div class="unit"<?= $benefitTitle('constructionTime_medium'); ?>>
                            <i class="constructionTime_medium"></i>
                            <span class="value">&#8237;&#8237;<?=$vars['culturePointsAndPopulation']['constructionTime'];?>&#8236;%&#8236;</span>
                            <span class="delta">(&#8237;&minus;&#8237;<?=$vars['nextLevelCpPop']['constructionTime'];?>&#8236;%&#8236;)</span>
                        </div>
                    <?php endif;?>
                    <?php if ($vars['culturePointsAndPopulation']['infantryBonusTime']  !== null):?>
                        <div class="unit"<?= $benefitTitle('infantryBonusTime_medium'); ?>>
                            <i class="infantryBonusTime_medium"></i>
                            <span class="value"><?=$trainingTimeSign($vars['culturePointsAndPopulation']['infantryBonusTime']);?><?=$vars['culturePointsAndPopulation']['infantryBonusTime'];?>&#8236;%&#8236;</span>
                            <span class="delta">(<?=$trainingTimeSign($vars['nextLevelCpPop']['infantryBonusTime']);?><?=$vars['nextLevelCpPop']['infantryBonusTime'];?>&#8236;%&#8236;)</span>
                        </div>
                    <?php endif;?>
                    <?php if ($vars['culturePointsAndPopulation']['cavalryBonusTime']  !== null):?>
                    <div class="unit"<?= $benefitTitle('cavalryBonusTime_medium'); ?>>
                        <i class="cavalryBonusTime_medium"></i>
                        <span class="value"><?=$trainingTimeSign($vars['culturePointsAndPopulation']['cavalryBonusTime']);?><?=$vars['culturePointsAndPopulation']['cavalryBonusTime'];?>&#8236;%&#8236;</span>
                        <span class="delta">(<?=$trainingTimeSign($vars['nextLevelCpPop']['cavalryBonusTime']);?><?=$vars['nextLevelCpPop']['cavalryBonusTime'];?>&#8236;%&#8236;)</span>
                    </div>
                    <?php endif;?>
                    <?php if ($vars['culturePointsAndPopulation']['merchantCap']  !== null):?>
                    <div class="unit"<?= $benefitTitle('merchantCap_small'); ?>>
                        <i class="merchantCap_small"></i>
                        <span class="value"><?=$vars['culturePointsAndPopulation']['merchantCap'];?>&#8236;&#8236;</span>
                        <span class="delta">(+<?=$vars['nextLevelCpPop']['merchantCap'];?>&#8236;&#8236;)</span>
                    </div>
                    <?php endif;?>
                    <?php if ($vars['culturePointsAndPopulation']['siegeTime'] !== null):?>
                    <div class="unit"<?= $benefitTitle('siegeTime_small'); ?>>
                        <i class="siegeTime_small"></i>
                        <span class="value"><?=$trainingTimeSign($vars['culturePointsAndPopulation']['siegeTime']);?><?=$vars['culturePointsAndPopulation']['siegeTime'];?>&#8236;%&#8236;</span>
                        <span class="delta">(<?=$trainingTimeSign($vars['nextLevelCpPop']['siegeTime']);?><?=$vars['nextLevelCpPop']['siegeTime'];?>&#8236;%&#8236;)</span>
                    </div>
                    <?php endif;?>
                    <?php if ($vars['culturePointsAndPopulation']['maxTraps'] !== null):?>
                    <div class="unit"<?= $benefitTitle('maxTraps_medium'); ?>>
                        <i class="maxTraps_medium"></i>
                        <span class="value">&#8237;&#8237;<?=$vars['culturePointsAndPopulation']['maxTraps'];?>&#8236;&#8236;</span>
                        <span class="delta">(&#8237;+&#8237;<?=$vars['nextLevelCpPop']['maxTraps'];?>&#8236;&#8236;)</span>
                    </div>
                    <?php endif;?>
                    <?php if ($vars['culturePointsAndPopulation']['warehouseCap']  !== null):?>
                        <div class="unit"<?= $benefitTitle('warehouseCap_medium'); ?>>
                            <i class="warehouseCap_medium"></i>
                            <span class="value">&#8237;<?=$vars['culturePointsAndPopulation']['warehouseCap'];?>&#8236;</span>
                            <span class="delta">(&#8237;+&#8237;<?=$vars['nextLevelCpPop']['warehouseCap'];?>&#8236;&#8236;)</span>
                        </div>
                    <?php endif;?>
                    <?php if ($vars['culturePointsAndPopulation']['granaryCap']  !== null):?>
                        <div class="unit"<?= $benefitTitle('granaryCap_medium'); ?>>
                            <i class="granaryCap_medium"></i>
                            <span class="value">&#8237;<?=$vars['culturePointsAndPopulation']['granaryCap'];?>&#8236;</span>
                            <span class="delta">(&#8237;+&#8237;<?=$vars['nextLevelCpPop']['granaryCap'];?>&#8236;&#8236;)</span>
                        </div>
                    <?php endif;?>
                    <?php if ($vars['culturePointsAndPopulation']['troopSpeed']  !== null):?>
                        <div class="unit"<?= $benefitTitle('troopSpeed_medium'); ?>>
                            <i class="troopSpeed_medium"></i>
                            <span class="value"><?=($vars['culturePointsAndPopulation']['troopSpeed']);?>&#8236;%&#8236;</span>
                            <span class="delta">(&#8237;+&#8237;<?=($vars['nextLevelCpPop']['troopSpeed']);?>&#8236;%&#8236;)</span>
                        </div>
                    <?php endif;?>
                    <?php if ($vars['culturePointsAndPopulation']['crannyCap']  !== null):?>
                        <div class="unit"<?= $benefitTitle('crannyCap_small'); ?>>
                            <i class="crannyCap_small"></i>
                            <span class="value"><?=($vars['culturePointsAndPopulation']['crannyCap']);?>&#8236;&#8236;</span>
                            <span class="delta">(&#8237;+&#8237;<?=($vars['nextLevelCpPop']['crannyCap']);?>&#8236;&#8236;)</span>
                        </div>
                    <?php endif;?>
					<?php if ($vars['culturePointsAndPopulation']['troopVisibility'] !== null):?>
						<div class="unit"<?= $benefitTitle('troopVisibility_small'); ?>>
							<i class="troopVisibility_small"></i>
							<span class="value">&#8237;<?=$vars['culturePointsAndPopulation']['troopVisibility'];?>&#8236;</span>
							<span class="delta">(&#8237;+&#8237;<?=$vars['nextLevelCpPop']['troopVisibility'];?>&#8236;&#8236;)</span>
						</div>
					<?php endif;?>
					<?php if ($vars['culturePointsAndPopulation']['defenceBonus'] !== null):?>
						<div class="unit"<?= $benefitTitle('defenceBonus_medium'); ?>>
							<i class="defenceBonus_medium"></i>
							<span class="value">&#8237;<?=$vars['culturePointsAndPopulation']['defenceBonus'];?>&#8236;</span>
							<span class="delta">(&#8237;+&#8237;<?=$vars['nextLevelCpPop']['defenceBonus'];?>&#8236;&#8236;)</span>
						</div>
					<?php endif; ?>
                </div>
            </div>
            <?php
            $plusAdvertising = !empty($vars['contractLink']['plus']) && !empty($vars['contractLink']['master']);
$has = $plusAdvertising || !empty($vars['contractLink']['extraModuleButton']);
?>
            <div class="upgradeButtonsContainer<?= ($has ? ' section2Enabled' : ''); ?>">
                <div class="section1">
                    <?php if (!(isset($vars['contractLink']['noResources']) && $vars['contractLink']['noResources'])): ?>
                    <?php if (!is_null($vars['contractLink']['main'])): ?>
                            <?= $vars['contractLink']['main']; ?>
                    <?php endif; ?>
                    <?php endif; ?>
                    <?= $vars['contractLink']['master']; ?>
                    <div class="inlineIcon duration" title="<?= htmlspecialchars(T('Manual', 'duration'), ENT_QUOTES, 'UTF-8'); ?>"><div class="iconWrapper"><i class="clock_medium"></i></div><?= $durationContent; ?></div>
                    <?php if (isset($vars['contractLink']['waitLoop']) && $vars['contractLink']['waitLoop']): ?>
                        <span class="none">(<?= T("Buildings", "waitLoop"); ?>)</span>
                    <?php endif; ?>
                    </div>
                <div class="section2">
                    <?php if ($plusAdvertising): ?>
                        <div class="plusAdvertising">
                            <?= str_replace(['<br />', '<br>', '<br/>'], '', $vars['contractLink']['plus']); ?>
                        </div>
                    <?php endif; ?>
                    <?= str_replace(['<br />', '<br>', '<br/>'], '', $vars['contractLink']['extraModuleButton'] ?? ''); ?>
                </div>
                <div class="clear"></div>
            </div>
            <div class="clear"></div>
    <?php endif; ?>
	</div>
    <?php if (ExtraModules::isEnabled('increaseStorage') && ($vars['itemId'] == 10 || $vars['itemId'] == 11 || $vars['itemId'] == 38 || $vars['itemId'] == 39)): ?>
        <div class="roundedCornersBox big">
            <h4>
                <div class="statusMessage"
                     style="color: midnightblue"><?php if (true/*$vars['itemId'] == 10 || $vars['itemId'] == 38*/): ?>
                        <?= T("Buildings", "increase warehouse storage by level 20 storage value"); ?>
                    <?php else: ?>
                        <?= T("Buildings", "increase granny storage by level 20 storage value"); ?>
                    <?php endif; ?>
                </div>
            </h4>
            <div id="contract" class="contractWrapper">
                <div class="contractLink centeredText">
                    <?= T("Global", "Times"); ?>: <input
                            onchange="onChangeTimes()"
                            type="number" id="times" class="text" value="1" style="width: 45px;">
                    <?= ExtraModules::showButton("increaseStorage", 'getGidAndTimes'); ?>
                    <script type="text/javascript">
                        function getGidAndTimes() {
                            return {
                                additionalData: {
                                    gid: <?=$vars['building_field'];?>,
                                    times: jQuery('#times').val()
                                }
                            };
                        }

                        var coins = jQuery("button.gold.increaseStorage").attr("coins");

                        function onChangeTimes() {
                            var times = jQuery('#times').val();
                            jQuery("button.gold.increaseStorage .goldValue").html(times * coins);
                        }
                    </script>
                    <div class="clear"></div>
                </div>
                <div class="clear"></div>
            </div>
            <div class="clear"></div>
        </div>
    <?php endif; ?>
<?php endif; ?>
