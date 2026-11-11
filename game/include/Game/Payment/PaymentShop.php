<?php

declare(strict_types=1);

namespace Game\Payment;

use Core\Config;
use Core\Database\DB;

/** Whether the buy-gold shop is open: the operator switched it on, a provider is configured and packages exist. */
final class PaymentShop
{
    /** @return array<string,Provider> */
    public static function providers(): array
    {
        return PaymentConfig::fromEnvironment()->providers(new CurlTransport());
    }

    public static function isOpen(DB $account, int $now): bool
    {
        return (int) $account->fetchScalar('SELECT active FROM paymentConfig LIMIT 1', [], 0) === 1
            && !Config::getInstance()->dynamic->serverFinished
            && self::providers() !== []
            && GoldPackages::active($account, $now) !== [];
    }
}
