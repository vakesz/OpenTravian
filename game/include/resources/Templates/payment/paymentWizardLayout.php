<?php

$activeTab = $vars['class'];
$gold = htmlspecialchars((string)number_format_x($vars['gold']), ENT_QUOTES, 'UTF-8');
$currencyCoinKind = 'gold';
$currencyCoinInteractive = false;
ob_start();
require __DIR__ . '/../layout/currencyCoin.php';
$goldCoin = ob_get_clean();
?>
<div id="paymentWizard" class="<?=htmlspecialchars($activeTab, ENT_QUOTES, 'UTF-8');?>">
	<div class="accountBalance" aria-label="<?=htmlspecialchars(T('PaymentWizard', 'Gold'), ENT_QUOTES, 'UTF-8');?>">
		<div class="inlineIcon">
			<?=$goldCoin; ?>
			<span class="value ajaxReplaceableGoldAmount">‭<?=$gold;?>‬</span>
		</div>
	</div>
	<div class="header">
		<div class="contentNavi subNavi">
			<div class="scrollingContainer">
				<?php foreach (['buyGold' => 'Buy Gold', 'specialOffers' => 'Specials', 'vouchers' => T('PaymentWizard', 'Vouchers'), 'advantages' => T('PaymentWizard', 'Advantages')] as $tab => $label): ?>
					<div class="content <?=$activeTab === $tab ? 'active' : '';?>"><a href="#" class="tabItem <?=$activeTab === $tab ? 'active' : '';?>" data-tabname="<?=$tab;?>"><span><?=htmlspecialchars($label, ENT_QUOTES, 'UTF-8');?></span></a></div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
	<div id="paymentWizardContent" class="contentWrapper">
		<?php if ($activeTab === 'buyGold' && empty($vars['purchaseAvailable'])): ?>
			<div class="shopEmptyState" role="status"><?=htmlspecialchars(T('PaymentWizard', 'paymentUnAvailable'), ENT_QUOTES, 'UTF-8');?></div>
		<?php else: ?>
			<?=$vars['content']; ?>
		<?php endif; ?>
	</div>
</div>
