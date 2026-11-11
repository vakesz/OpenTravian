<?php

declare(strict_types=1);

function failPremiumPurchaseAtomicContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$helper = file_get_contents(dirname(__DIR__, 2) . '/game/include/Game/GoldHelper.php');
$autoExtend = file_get_contents(dirname(__DIR__, 2) . '/game/include/Model/AutoExtendModel.php');
if ($helper === false || $autoExtend === false) {
    failPremiumPurchaseAtomicContract('Premium purchase sources must be readable.');
}

foreach ([
    'private function purchasePremiumFeature(',
    'SELECT $column FROM users WHERE id=$uid FOR UPDATE',
    'self::decreaseGold($uid, $cost, true)',
    'UPDATE users SET $column=$showTo WHERE id=$uid',
    'if ($uid <= 0 || !$db->begin_transaction())',
    '|| !$db->commit())',
    '$db->rollback()',
] as $required) {
    if (!str_contains($helper, $required)) {
        failPremiumPurchaseAtomicContract("Premium feature purchases must atomically debit and grant the expiry: missing $required");
    }
}

if (!str_contains($autoExtend, 'public function setAutoExtendState(int $uid, int $type, bool|int $result, int $endTime): bool')
    || !str_contains($autoExtend, 'return $this->set($uid, $type, $endTime, (bool) $result);')) {
    failPremiumPurchaseAtomicContract('Auto-extend persistence must report failures to the surrounding premium purchase transaction.');
}

echo "Premium purchase atomicity contract passed.\n";
