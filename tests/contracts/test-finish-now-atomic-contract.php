<?php

declare(strict_types=1);

/**
 * Contract: finishNow / demolishNow / marketplace gold spends follow the shared
 * atomic purchase pattern — lock work inside one transaction, apply effects,
 * then decreaseGold(..., true), then commit and sync session gold. finishNow
 * must complete research and demolition in-request (no end_time=0 deferral).
 */

function failFinishNowAtomicContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$helper = file_get_contents($root . '/game/include/Game/GoldHelper.php');
$premium = file_get_contents($root . '/game/include/Controller/Ajax/premium.php');
$demolition = file_get_contents($root . '/game/include/Controller/Ajax/premium/instantDemolition.php');
if ($helper === false || $premium === false || $demolition === false) {
    failFinishNowAtomicContract('Unable to read finishNow / premium sources.');
}

foreach ([
    'function finishImmediatelyBuildings(): string',
    '$db->begin_transaction()',
    'FROM building_upgrade WHERE kid=$kid AND isMaster=0',
    'FOR UPDATE',
    'BuildingAction::upgrade($kid, $onload[\'building_field\'])',
    'BuildingAction::downgrade(',
    'UPDATE tdata SET u$nr=1 WHERE kid=$kid',
    'UPDATE smithy SET u$nr=IF(u$nr+1>20, 20, u$nr+1) WHERE kid=$kid',
    'self::decreaseGold($uid, $cost, true)',
    'self::syncSessionGold($uid)',
] as $needle) {
    if (!str_contains($helper, $needle)) {
        failFinishNowAtomicContract('finishImmediatelyBuildings must use the atomic in-request completion path: missing ' . $needle);
    }
}

if (str_contains($helper, 'UPDATE research SET end_time=0 WHERE kid=$kid')
    || str_contains($helper, 'UPDATE demolition SET end_time=0 WHERE kid=$kid')) {
    failFinishNowAtomicContract('finishNow must not defer research/demolition with end_time=0; complete them in the purchase transaction.');
}

$finishNowPos = strpos($helper, 'function finishImmediatelyBuildings(): string');
$decreasePos = $finishNowPos === false ? false : strpos($helper, 'self::decreaseGold($uid, $cost, true)', $finishNowPos);
$upgradePos = $finishNowPos === false ? false : strpos($helper, 'BuildingAction::upgrade($kid, $onload[\'building_field\'])', $finishNowPos);
if ($finishNowPos === false || $decreasePos === false || $upgradePos === false || $upgradePos > $decreasePos) {
    failFinishNowAtomicContract('finishNow must apply queue effects before decreaseGold(..., true).');
}

foreach ([
    'protected function perform(array $target)',
    '$db->begin_transaction()',
    'GoldHelper::decreaseGold(',
    'true)',
    'GoldHelper::syncSessionGold($uid)',
] as $needle) {
    if (!str_contains($demolition, $needle)) {
        failFinishNowAtomicContract('instant demolition must share the atomic purchase transaction pattern: missing ' . $needle);
    }
}

// The demolition (checkOnly=false) must precede the gold debit inside the transaction.
$demolitionPos = strpos($demolition, "demolishBuilding(\$target['slotId'], true)");
$demolitionGoldPos = strpos($demolition, 'GoldHelper::decreaseGold(');
if ($demolitionPos === false || $demolitionGoldPos === false || $demolitionPos > $demolitionGoldPos) {
    failFinishNowAtomicContract('instant demolition must apply demolishBuilding before decreaseGold inside the transaction.');
}

$marketplacePos = strpos($premium, 'private function marketplace(');
$marketplaceEnd = $marketplacePos === false
    ? false
    : strpos($premium, "\n    private function ", $marketplacePos + 1);
$marketplaceBody = ($marketplacePos !== false && $marketplaceEnd !== false)
    ? substr($premium, $marketplacePos, $marketplaceEnd - $marketplacePos)
    : '';
if ($marketplaceBody === ''
    || !str_contains($marketplaceBody, 'GoldHelper::decreaseGold($uid, $goldCost, true)')
    || !str_contains($marketplaceBody, 'GoldHelper::syncSessionGold($uid)')) {
    failFinishNowAtomicContract('marketplace resource exchange must keep decreaseGold(..., true) and sync session gold after commit.');
}

if (!str_contains($helper, 'public static function syncSessionGold(int $uid')) {
    failFinishNowAtomicContract('GoldHelper::syncSessionGold must remain available for nested purchase commits.');
}

echo "finishNow atomic purchase contract passed.\n";
