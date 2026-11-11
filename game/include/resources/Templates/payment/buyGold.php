<?php

declare(strict_types=1);

/**
 * Buy gold: the package grid of the reference shop, followed by the payment
 * methods the operator configured. $vars: packages, providers (id => label), currency.
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$currency = (string) $vars['currency'];
$imageUrl = static fn (string $file): string => get_gpack_cdn_mainPage_url() . gpack_image_path('legacy/views/shop/' . $file);
$currencyCoinKind = 'gold';
$currencyCoinInteractive = false;
ob_start();
require __DIR__ . '/../layout/currencyCoin.php';
$goldCoin = ob_get_clean();
?>
<div class="contentV2 shopWrapper selectPackage">
	<h1 class="titleInHeader countrySelection "><svg viewBox="0 0 20 20" preserveAspectRatio="none" class="inlineIcon arrowDown"><path d="M1 1L10 19L19 1"></path></svg><div class="inlineIcon"><i class="flag international"></i><span class="value"><?=$escape(sprintf(T('PaymentWizard', 'International'), $currency));?></span></div></h1>
	<div class="buyProgress">
		<?php foreach (['selectPackage' => ['Select package', 'done'], 'selectPaymentMethod' => ['Payment method', 'locked'], 'insertBillingInformation' => ['Billing information', 'locked'], 'confirmed' => ['Confirmed', 'locked']] as $step => [$label, $state]): ?>
			<?php if ($step !== 'selectPackage'): ?><div class="divider"></div><?php endif; ?>
			<div class="inlineIcon step <?=$step;?> <?=$state;?>"><svg viewBox="0 0 19.58 20"><path class="shield" d="M19.58 3.79S16.73 3 13.77 1.85C12.37 1.32 11 0 9.79 0s-2.7 1.35-4.15 1.91C2.74 3 0 3.79 0 3.79S-.38 15.42 9.79 20C20 15.42 19.58 3.79 19.58 3.79z"></path><path class="check" d="M15.37 6.18a.26.26 0 00-.25-.13 1.08 1.08 0 00-.58.22 5.55 5.55 0 00-.78.65l-3.45 3.42a6.57 6.57 0 01-.53.48.73.73 0 01-.3.18c-.13 0-1.74-2.51-2.1-2.51a1.42 1.42 0 00-1 .48 1.45 1.45 0 00-.46 1 13.56 13.56 0 001.25 3.06 1.57 1.57 0 00.63.63 1.66 1.66 0 00.81.31 2.33 2.33 0 001.06-.62 20.4 20.4 0 001.82-1.73l3.35-3.54.06-.06a1.76 1.76 0 00.58-1.23v-.15a1 1 0 00-.11-.46z"></path></svg><span class="value"><?=$escape(T('PaymentWizard', $label));?></span></div>
		<?php endforeach; ?>
	</div>
	<div class="purchaseStepWrapper">
		<div class="packages">
			<?php foreach ($vars['packages'] as $index => $package): ?>
				<?php $number = $index + 1; $badge = $package['bestSeller'] ? 'bestSeller' : ($package['bestValue'] ? 'bestValue' : ''); ?>
				<input id="package<?=$number;?>" type="radio" value="<?=$package['id'];?>" name="package">
				<label for="package<?=$number;?>" class="package<?=$badge !== '' ? ' bestPackage' : '';?>">
					<?php if ($badge !== ''): ?>
						<div class="best <?=$badge;?>"><div class="text"><?=$escape(T('PaymentWizard', $badge === 'bestSeller' ? 'Best seller' : 'Best value'));?></div></div>
					<?php endif; ?>
					<div class="inlineIcon productAmount"><?=$goldCoin;?><span class="value"><?=$package['gold'];?></span></div>
					<div class="packageImage"><img alt="" src="<?=$escape($imageUrl($package['image']));?>"></div>
					<div class="productPrice"><?=$escape(\Game\Payment\Money::format($package['priceMinor'], $currency));?><span class="asterisk">*</span></div>
					<div class="changePackage"><?=$escape(T('Auction', 'Change'));?></div>
				</label>
			<?php endforeach; ?>
		</div>
	</div>
	<div class="paymentWrapper">
		<div class="paymentMethods" hidden>
			<?php foreach ($vars['providers'] as $id => $label): ?>
				<label><input type="radio" name="paymentProvider" value="<?=$escape($id);?>"<?=count($vars['providers']) === 1 ? ' checked' : '';?>> <span><?=$escape($label);?></span></label>
			<?php endforeach; ?>
		</div>
		<span class="choosePayment"><?=$escape(T('PaymentWizard', 'Choose your payment method'));?></span>
		<a href="#" class="transactionHistory"><?=$escape(T('PaymentWizard', 'Order history'));?></a>
		<span class="paymentError" role="alert" hidden></span>
		<button class="textButtonV2 buttonFramed buyButton rectangle withText green" type="button" disabled id="continueBuy"><div><?=$escape(T('PaymentWizard', 'Continue'));?></div></button>
		<button class="textButtonV2 buttonFramed buyButton rectangle withText green" type="button" disabled id="confirmBuy"><div><?=$escape(T('PaymentWizard', 'Buy now'));?></div></button>
		<button class="textButtonV2 buttonFramed rectangle withText green" type="button" id="backToGame"><div><?=$escape(T('Logout', 'back_to_the_game'));?></div></button>
	</div>
</div>
<script type="text/javascript" id="buyGold_script">
	jQuery(function () {
		var wizard = jQuery('#paymentWizard'), shop = wizard.find('.shopWrapper'), methods = wizard.find('.paymentMethods');
		var next = wizard.find('#continueBuy'), buy = wizard.find('#confirmBuy'), error = wizard.find('.paymentError');
		var update = function () {
			next.prop('disabled', !wizard.find('input[name=package]:checked').length);
			buy.prop('disabled', !(wizard.find('input[name=package]:checked').length && wizard.find('input[name=paymentProvider]:checked').length));
		};
		var showError = function (response) {
			error.text(response.message).prop('hidden', false);
			update();
		};
		wizard.find('input[name=package], input[name=paymentProvider]').on('change', update);
		next.on('click', function () {
			shop.removeClass('selectPackage').addClass('selectPaymentMethod');
			shop.find('.step.selectPaymentMethod').removeClass('locked').addClass('done');
			methods.prop('hidden', false);
			return false;
		});
		buy.on('click', function () {
			buy.prop('disabled', true);
			error.prop('hidden', true);
			Travian.api('payment-checkout', {
				data: {
					goldProductId: wizard.find('input[name=package]:checked').val(),
					provider: wizard.find('input[name=paymentProvider]:checked').val()
				},
				error: showError
			});
			return false;
		});
		wizard.find('#backToGame').on('click', function () {
			Travian.WindowManager.closeByContext('paymentWizard');
			return false;
		});
		wizard.find('a.transactionHistory').on('click', function () {
			Travian.api('payment-history', {
				success: function (response) {
					var dialog = new Travian.Dialog.Dialog(response.options.dialogOptions);
					dialog.setContent(response.options.html);
					dialog.show();
				},
				error: showError
			});
			return false;
		});
		update();
	});
</script>
