<?php

declare(strict_types=1);

/**
 * Contract: every premium action must distinguish a completed purchase from a
 * stale action or a failed debit. The JSON client treats HTTP 400 as the error
 * path, so a response must never reload or announce a product after its debit
 * has failed.
 */

function failPremiumPurchaseFailureContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$premium = file_get_contents($root . '/game/include/Controller/Ajax/premium.php');
$extraModules = file_get_contents($root . '/game/include/Game/ExtraModules.php');
$ajaxController = file_get_contents($root . '/game/include/Controller/AjaxCtrl.php');

if ($premium === false || $extraModules === false || $ajaxController === false) {
    failPremiumPurchaseFailureContract('Premium purchase sources must be readable.');
}

foreach ([
    "unset(\$response['functionToCall'], \$response['redirectTo']);",
    "\$response['reload'] = false;",
    "\$response['reloadOnClose'] = false;",
] as $contract) {
    if (!str_contains($ajaxController, $contract)) {
        failPremiumPurchaseFailureContract('An error response must discard stale success navigation: ' . $contract);
    }
}

foreach ([
    'private const DIRECT_ACTIONS',
    'private function spendGold(',
    'private function completeExtraModuleAction(',
    'private function unavailablePremiumAction(): void',
    "T('Auction', 'notEnoughGold')",
    "T('PaymentWizard', 'This feature is disabled')",
] as $contract) {
    if (!str_contains($premium, $contract)) {
        failPremiumPurchaseFailureContract('Premium controller is missing failed-purchase recovery: ' . $contract);
    }
}

if (str_contains($premium, 'method_exists($this, $methodKey)')
    || !str_contains($premium, 'isset(self::DIRECT_ACTIONS[$featureKey])')
    || !str_contains($premium, '$method = self::DIRECT_ACTIONS[$featureKey];')) {
    failPremiumPurchaseFailureContract('Premium actions must use the explicit current-action map, not dynamic method dispatch.');
}

if (!str_contains($premium, 'GoldHelper::decreaseGold($uid, $goldCost, true)')) {
    failPremiumPurchaseFailureContract('Marketplace must keep its resource and gold writes in one transaction.');
}

foreach ([
    'if (!$this->spendGold($coins, true))',
    'if (!$this->spendGold($coins))',
] as $contract) {
    if (!str_contains($premium, $contract)) {
        failPremiumPurchaseFailureContract('Premium debit paths must use the shared failure-aware spender: ' . $contract);
    }
}

foreach ([
    "public const ACTION_COMPLETED = 'completed';",
    "public const ACTION_NOT_ENOUGH_GOLD = 'notEnoughGold';",
    "public const ACTION_UNAVAILABLE = 'unavailable';",
    'public static function runAction($action, &$response, $params): string',
    'private static function reduceGold($action, $times = 1, bool $transactionAlreadyOpen = false): bool',
    'GoldHelper::decreaseGold(Session::getInstance()->getPlayerId(), $coins, $transactionAlreadyOpen)',
] as $contract) {
    if (!str_contains($extraModules, $contract)) {
        failPremiumPurchaseFailureContract('ExtraModules must report a completed, unavailable, or failed-debit outcome: ' . $contract);
    }
}

foreach ([
    'public static function addFarms(&$response): string',
    'public static function finishTraining(&$response): string',
    'public static function academyResearchAll(&$response): string',
    'public static function smithyUpgradeAllToMax(&$response): string',
    'public static function smithyMaxLevel(&$response, $additionalData): string',
    'public static function upgradeToMaxLevel(&$response, $additionalData): string',
    'public static function buyAdventure(&$response): string',
    'public static function increaseStorage(&$response, $additionalData = null): string',
] as $contract) {
    if (!str_contains($extraModules, $contract)) {
        failPremiumPurchaseFailureContract('ExtraModules action must return an explicit outcome: ' . $contract);
    }
}

echo "Premium purchase failure contract passed.\n";
