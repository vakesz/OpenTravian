<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/game/include/Controller/Ajax/marketplace/prepare.php');
$template = file_get_contents($root . '/game/include/resources/Templates/build/building/MarketplaceSendResources.php');
$marketController = file_get_contents($root . '/game/include/Controller/Build/MarketPlaceCtrl.php');
$routes = file_get_contents($root . '/game/deployment/include/api_legacy_routes.php');
$progressBarStart = $template === false
    ? false
    : strpos($template, '<div class="progressBar preventMobileSwipeNavigation"');
$fillupStart = $progressBarStart === false
    ? false
    : strpos($template, '<button type="button" class="textButtonV2 buttonFramed fillup', $progressBarStart);
$firstResourceControl = $progressBarStart === false || $fillupStart === false
    ? ''
    : substr($template, $progressBarStart, $fillupStart - $progressBarStart);
$resourceControlOrderMatches = preg_match(
    '/<div class="bar">.*?<button[^>]*\bplus\b.*?<button[^>]*\bminus\b.*?<div class="slider"/s',
    $firstResourceControl
) === 1;

if ($controller === false
    || $template === false
    || $marketController === false
    || $routes === false
    || !str_contains($controller, 'final class prepare extends AjaxBase')
    || !str_contains($controller, 'begin_transaction')
    || !str_contains($controller, 'FOR UPDATE')
    || !str_contains($controller, 'INSERT INTO send')
    || !str_contains($controller, '(int)floor(Formulas::merchantCAP(')
    || !str_contains($controller, 'Session::claimAction($checker)')
    || !str_contains($controller, "['confirmation']")
    || !str_contains($controller, "['checker']")
    || str_contains($controller, "['formular']")
    || !str_contains($marketController, "' buildMarketSendResources'")
    || !str_contains($template, 'id="marketplaceSendResources"')
    || !str_contains($template, 'contentV2 sendResourcesView formV2')
    || !str_contains($template, 'targetSelection')
    || !str_contains($template, 'resourceSelector')
    || !str_contains($template, "Travian.api('marketplace/prepare'")
    || !str_contains($template, "Travian.api('marketplace/reload'")
    || !str_contains($template, 'setTarget(response.confirmation)')
    || !str_contains($template, 'sendConfirmedShipment(response.confirmation)')
    || !str_contains($template, 'renderDeliveryGroups(reload.deliveries)')
    || !str_contains($template, 'function reloadMarketplace()')
    || !str_contains($template, 'window.setInterval(reloadMarketplace, 15000)')
    || !str_contains($template, 'pointerdown')
    || !str_contains($template, "input[name=\"deliveries\"]")
    || !str_contains($template, 'state.repeat = selectedRepeat;')
    || !str_contains($template, "attr('data-repeat')")
    || !str_contains($template, 'resourcePayload()')
    || str_contains($template, "root.find('#x2')")
    || str_contains($template, 'name="r1"')
    || str_contains($template, 'name="dname"')
    || !str_contains($template, "\$resourceFieldNames = ['lumber', 'clay', 'iron', 'crop']")
    || !str_contains($controller, "['lumber', 'clay', 'iron', 'crop']")
    || substr_count($template, "input[name=\"deliveries\"]") !== 1
    || !str_contains($template, 'state.canSend = false')
    || !str_contains($template, ".attr('class', 'textButtonV2 buttonFramed send' + (allowed ? '' : ' disabled') + ' rectangle withText green')")
    || !str_contains($template, "resource.find('button.minus').prop('disabled', state.amounts[index] === 0 || state.pending)")
    || !str_contains($template, 'if (!state.canSend)')
    || !str_contains($template, 'state.refreshAfterPending = true;')
    || !str_contains($template, 'state.pending === 0 && state.refreshAfterPending')
    || !str_contains($template, "resource.find('.slider .handle').css('margin-inline-start', percentage);")
    || str_contains($template, 'send rectangle withText green disabled" disabled="disabled"')
    || str_contains($template, 'tabindex="9"')
    || !str_contains($marketController, 'getDeliveryGroups(')
    || !str_contains($template, "response.message || 'Unable to send resources.'")
    || str_contains($template, 'window.marketPlace.')
    || !str_contains($routes, "'marketplace/prepare' => ['marketplace', 'prepare']")
    || str_contains($routes, "'marketplace/back'")
    || !$resourceControlOrderMatches) {
    fwrite(STDERR, "Marketplace send resources must use the native V2 composer and preserve atomic server-side shipment validation.\n");
    exit(1);
}

// The reference handle is the Gpack's hidden `.handle.blue` moved by an inline margin; the old absolute-handle override is gone.
$fixes = file_get_contents($root . '/assets/gpack/travian/mainPage/lang/overrides.css');
if ($fixes === false
    || str_contains($fixes, '--marketplace-slider-progress')
    || !str_contains($template, '<div class="handle blue" style="margin-inline-start: 0%; transition-duration: 250ms;">')) {
    fwrite(STDERR, "Marketplace amount sliders must use the reference handle markup.\n");
    exit(1);
}

echo "Marketplace prepare API contract passed.\n";
