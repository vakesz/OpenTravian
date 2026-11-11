<?php

declare(strict_types=1);

/**
 * Contract: finishNow confirmation matches #finishNowDialog —
 * orders wording, per-building name + Level, gold Use [coins] button.
 */

function failFinishNowPopupContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$template = file_get_contents($root . '/game/include/resources/Templates/gold/finishNowPopup.php');
$ctrl = file_get_contents($root . '/game/include/Controller/Ajax/premium/instantCompletion.php');
$en = file_get_contents($root . '/game/include/resources/Translation/en-US.php');
$client = file_get_contents($root . '/game/deployment/public/game-client.js');
$ajaxController = file_get_contents($root . '/game/include/Controller/AjaxCtrl.php');
$premium = file_get_contents($root . '/game/include/Controller/Ajax/premium.php');
$goldHelper = file_get_contents($root . '/game/include/Game/GoldHelper.php');

foreach ([$template, $ctrl, $en, $client, $ajaxController, $premium, $goldHelper] as $source) {
    if ($source === false) {
        failFinishNowPopupContract('Unable to read finishNow popup sources.');
    }
}

foreach ([
    'id="finishNowDialog"',
    'span class="lvl"',
    'buttonWrapper',
    "T('finishNowPopup', 'Use')",
    "'type' => 'submit'",
    "'class' => 'textButtonV1 gold '",
    "T('finishNowPopup', 'useTitle')",
    'InstantCompletion.confirm()',
    "'coins' => (int)\$vars['goldCost']",
    '<h5>',
] as $needle) {
    if (!str_contains($template, $needle)) {
        failFinishNowPopupContract('finishNowPopup template missing: ' . $needle);
    }
}

if (str_contains($template, '<h1>') || str_contains($template, 'dialogButtonOk') || str_contains($template, 'finishNowSection')) {
    failFinishNowPopupContract('The finish-now dialog has no h1 title, h5 headings only, and no dialogButtonOk class (#finishNowDialog).');
}

// The queue-box button carries no gold price and passes no village id; the price shows in the confirmation.
if (!str_contains($goldHelper, 'InstantCompletion.confirmDialog(); return false;')
    || str_contains($goldHelper, 'InstantCompletion.confirmDialog({villageId')) {
    failFinishNowPopupContract('The Complete construction immediately button must call confirmDialog() without arguments.');
}

if (str_contains($template, 'Building instructions')
    || str_contains($template, "T('finishNowPopup', 'Redeem')")
    || str_contains($template, 'goldFeatureConfirmationCost')
    || str_contains($template, 'buildingCount')) {
    failFinishNowPopupContract('Remove the legacy count-only Redeem finishNow popup path.');
}

if (!str_contains($ctrl, 'eligibleBuildingOrders')
    || !str_contains($ctrl, "'name'")
    || !str_contains($ctrl, "'level'")) {
    failFinishNowPopupContract('finishNowPopup controller must build per-order name/level rows.');
}

if (!str_contains($client, 'buyWithConfirmation("instant-completion")')
    || !str_contains($client, 'Travian.Game.TwoStepAction = function')
    || str_contains($client, 'finish-now-popup')) {
    failFinishNowPopupContract('Finish-now must be the two-step instant-completion purchase (PUT signs, POST with X-Nonce executes).');
}

if (!str_contains($en, "orders will be completed instantly")
    || !str_contains($en, "Building orders:")
    || !str_contains($en, "\$Definition['finishNowPopup']['Use'] = 'Use'")) {
    failFinishNowPopupContract('en-US finishNowPopup copy must match orders wording + Use.');
}

if (str_contains($en, 'The following commands will be instantly completed')
    || str_contains($en, 'Building instructions:')) {
    failFinishNowPopupContract('Remove obsolete commands/instructions finishNowPopup copy.');
}

foreach (["'completed'", "'noOrders'", "'worldWonder'", "'notEnoughGold'"] as $result) {
    if (!str_contains($goldHelper, $result)) {
        failFinishNowPopupContract('GoldHelper must classify finish-now outcome: ' . $result);
    }
}
if (!str_contains($ctrl, '(new GoldHelper())->finishImmediatelyBuildings()')
    || !str_contains($ctrl, "'completed' => null")
    || !str_contains($ctrl, "'notEnoughGold' => 'notEnoughGold'")
    || !str_contains($ctrl, "T('finishNowPopup', 'No construction orders or research that could be completed instantly')")) {
    failFinishNowPopupContract('A stale finish-now confirmation must return an error instead of a successful no-op reload.');
}

echo "Finish-now popup contract passed.\n";
