<?php

use Core\Config;
use Core\Session;
use Game\GoldHelper;
use Model\AutoExtendModel;

$session = Session::getInstance();
$config = Config::getInstance();
$autoExtend = new AutoExtendModel();
$helper = new GoldHelper();
$resourceBoosts = [
    ['id' => 'lumber', 'resourceId' => 1, 'title' => 'Increase lumber production', 'autoExtendType' => 2],
    ['id' => 'clay', 'resourceId' => 2, 'title' => 'Increase clay production', 'autoExtendType' => 3],
    ['id' => 'iron', 'resourceId' => 3, 'title' => 'Increase iron production', 'autoExtendType' => 4],
    ['id' => 'crop', 'resourceId' => 4, 'title' => 'Increase crop production', 'autoExtendType' => 5],
];

$duration = static function (int $until, bool $renewing): string {
    if ($until <= time()) {
        return '';
    }

    return '<span class="timerLabel">Still active for:</span><span class="timerReact'
        . ($renewing ? ' renewalActive' : '') . '">' . appendTimer($until - time()) . '</span>';
};
?>
	<div class="advantagesBonusBox goldClub <?=$session->hasGoldClub() ? 'active' : '';?>">
		<div class="titleDecoration"></div><div class="bonusIcon"></div>
		<div class="bonusTitle"><h3><?=T('PaymentWizard', 'goldClub'); ?></h3><div>Higher efficiency with automated merchants, farm lists, and other advantages.</div></div>
		<div class="bonusButton"><?=$helper->getGoldClubButton();?></div>
		<div class="bonusDuration"><span><?=T('PaymentWizard', 'Bonus duration'); ?>: <strong><?=T('PaymentWizard', 'ToTheEndOfTheGame'); ?></strong></span></div>
		<?=\Game\ShopHelpTooltip::icon(\Game\ShopHelpTooltip::goldClub());?>
	</div>
	<div class="advantagesBonusBox travianPlus <?=$session->hasPlus() ? 'active' : '';?>">
		<div class="titleDecoration"></div><div class="bonusIcon"></div>
		<div class="bonusTitle"><h3>OpenTravian Plus</h3><div>Optimize your empire with more buildings in construction, attack warnings, and much more.</div></div>
		<div class="bonusButton"><?=$helper->getPlusButton(false, true);?></div>
		<div class="bonusDuration">
			<?php if ($session->hasPlus()): ?>
				<label class="checkbox"><input type="checkbox" class="prolongPlus" <?=$autoExtend->hasAutoExtend($session->getPlayerId(), 1) ? 'checked' : '';?>><span><?=T('PaymentWizard', 'Extend automatically'); ?></span></label><?=$duration($session->plusTill(), $autoExtend->hasAutoExtend($session->getPlayerId(), 1));?>
			<?php else: ?>
				<span><?=T('PaymentWizard', 'Bonus duration'); ?>: <strong><?=$helper->getDurationText($config->gold->plusAccountDurationSeconds);?></strong></span>
			<?php endif; ?>
		</div>
		<?=\Game\ShopHelpTooltip::icon(\Game\ShopHelpTooltip::plus());?>
	</div>
	<?php foreach ($resourceBoosts as $boost): ?>
		<?php $active = $session->hasProductionBoost($boost['resourceId']); $renewing = $autoExtend->hasAutoExtend($session->getPlayerId(), $boost['autoExtendType']); $autoClass = $boost['id'] === 'lumber' ? 'Wood' : ucfirst($boost['id']); ?>
		<div class="advantagesBonusBox <?=$boost['id'];?>ProductionBonus <?=$active ? 'active' : '';?>">
			<div class="titleDecoration"></div><div class="bonusIcon"></div>
			<div class="bonusTitle"><h3><?=$boost['title'];?></h3></div>
			<div class="bonusInfo bonusText"><?=\Game\ProductionBoostArrow::svg('advantageBonusArrow premiumFeature', 'svg-' . substr(bin2hex(random_bytes(4)), 0, 6));?><span><strong><?="\u{202D}+\u{202D}25\u{202C}%\u{202C}";?></strong> for <strong><?=$helper->getDurationText($config->gold->productionBoostDurationSeconds);?></strong></span></div>
			<div class="bonusButton"><?=$helper->getProductionBoostButton($boost['resourceId'], false, true);?></div>
			<div class="bonusDuration">
				<?php if ($active): ?>
					<label class="checkbox"><input type="checkbox" class="prolongProductionboost<?=$autoClass;?>" <?=$renewing ? 'checked' : '';?>><span><?=T('PaymentWizard', 'Extend automatically'); ?></span></label><?=$duration($session->productionBoostTill($boost['resourceId']), $renewing);?>
				<?php else: ?>
					<span><?=T('PaymentWizard', 'Bonus duration'); ?>: <strong><?=$helper->getDurationText($config->gold->productionBoostDurationSeconds);?></strong></span>
				<?php endif; ?>
			</div>
			<?=\Game\ShopHelpTooltip::icon(\Game\ShopHelpTooltip::productionBoost($boost['id'], $boost['title'], $helper->getDurationText($config->gold->productionBoostDurationSeconds)));?>
		</div>
	<?php endforeach; ?>
