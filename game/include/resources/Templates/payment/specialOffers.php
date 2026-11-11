<?php

$shieldPath = 'M19.58 3.79S16.73 3 13.77 1.85C12.37 1.32 11 0 9.79 0s-2.7 1.35-4.15 1.91C2.74 3 0 3.79 0 3.79S-.38 15.42 9.79 20C20 15.42 19.58 3.79 19.58 3.79z';
$checkPath = 'M15.37 6.18a.26.26 0 0 0-.25-.13 1.08 1.08 0 0 0-.58.22 5.55 5.55 0 0 0-.78.65l-3.45 3.42a6.57 6.57 0 0 1-.53.48.73.73 0 0 1-.3.18c-.13 0-1.74-2.51-2.1-2.51a1.42 1.42 0 0 0-1 .48 1.45 1.45 0 0 0-.46 1 13.56 13.56 0 0 0 1.25 3.06 1.57 1.57 0 0 0 .63.63 1.66 1.66 0 0 0 .81.31 2.33 2.33 0 0 0 1.06-.62 20.4 20.4 0 0 0 1.82-1.73l3.35-3.54.06-.06a1.76 1.76 0 0 0 .58-1.23v-.15a1 1 0 0 0-.11-.46z';
$steps = [
    ['class' => 'selectPackage done', 'label' => 'Select package'],
    ['class' => 'selectPaymentMethod locked', 'label' => 'Payment method'],
    ['class' => 'insertBillingInformation locked', 'label' => 'Billing information'],
    ['class' => 'confirmed locked', 'label' => 'Confirmed'],
];
?>
<div class="contentV2 shopWrapper selectPackage">
    <h1 class="titleInHeader countrySelection ">
        <svg viewBox="0 0 20 20" preserveAspectRatio="none" class="inlineIcon arrowDown" aria-hidden="true"><path d="M1 1L10 19L19 1"></path></svg>
        <div class="inlineIcon"><i class="flag international"></i><span class="value">International EUR</span></div>
    </h1>
    <div class="buyProgress">
        <?php foreach ($steps as $index => $step): ?>
            <?php if ($index > 0): ?><div class="divider"></div><?php endif; ?>
            <div class="inlineIcon step <?=htmlspecialchars($step['class'], ENT_QUOTES, 'UTF-8'); ?>">
                <svg viewBox="0 0 19.58 20" aria-hidden="true">
                    <path class="shield" d="<?=$shieldPath; ?>"></path>
                    <path class="check" d="<?=$checkPath; ?>"></path>
                </svg>
                <span class="value"><?=htmlspecialchars($step['label'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="purchaseStepWrapper">
        <div class="packages specialOffersPackages">
            <label for="package0" class="package specialOffer typeOneTimeOffer noOffer emptyOffer">
                <div class="emptyOfferInformation">There are no specials on offer currently.</div>
            </label>
        </div>
    </div>
    <div class="paymentWrapper">
        <div class="paymentMethods"></div>
        <span class="choosePayment">Choose your payment method</span>
        <a href="#" class="transactionHistory">Order history</a>
        <button class="textButtonV2 buttonFramed buyButton rectangle withText green" type="button" disabled id="continueBuy"><div>Continue</div></button>
        <button class="textButtonV2 buttonFramed buyButton rectangle withText green" type="button" disabled id="confirmBuy"><div><?=T('PaymentWizard', 'Buy now'); ?></div></button>
        <button class="textButtonV2 buttonFramed rectangle withText green" type="button" id="backToGame" onclick="Travian.WindowManager.closeByContext('paymentWizard'); return false;"><div><?=T('Logout', 'back_to_the_game'); ?></div></button>
    </div>
</div>
