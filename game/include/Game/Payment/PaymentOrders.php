<?php

declare(strict_types=1);

namespace Game\Payment;

use Core\Database\DB;

/** Persistence of checkout orders in the account database. */
final class PaymentOrders
{
    /** @param array{id:int,gold:int,priceMinor:int} $package */
    public static function create(DB $db, int $worldUniqueId, int $uid, array $package, string $provider, string $currency, ?int $now = null): ?PaymentOrder
    {
        $token = bin2hex(random_bytes(32));
        $now ??= time();
        $saved = $db->run(
            'INSERT INTO paymentOrder (token, worldUniqueId, uid, productId, gold, amountMinor, currency, provider, status, created, updated)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'pending\', ?, ?)',
            [$token, $worldUniqueId, $uid, $package['id'], $package['gold'], $package['priceMinor'], $currency, $provider, $now, $now]
        );
        if (!$saved || $db->lastInsertId() < 1) {
            return null;
        }

        return self::find($db, 'id', (string) $db->lastInsertId());
    }

    public static function findByToken(DB $db, string $token): ?PaymentOrder
    {
        return self::find($db, 'token', $token);
    }

    public static function findByPaymentRef(DB $db, string $provider, string $paymentRef): ?PaymentOrder
    {
        $statement = $db->run('SELECT * FROM paymentOrder WHERE provider=? AND paymentRef=? LIMIT 1', [$provider, $paymentRef]);
        $row = $statement ? $statement->get_result()->fetch_assoc() : null;

        return is_array($row) ? PaymentOrder::fromRow($row) : null;
    }

    /** The order of this player the provider knows as `$providerRef` (used by the return route). */
    public static function findForPlayer(DB $db, int $uid, string $provider, string $providerRef): ?PaymentOrder
    {
        $statement = $db->run('SELECT * FROM paymentOrder WHERE uid=? AND provider=? AND providerRef=? LIMIT 1', [$uid, $provider, $providerRef]);
        $row = $statement ? $statement->get_result()->fetch_assoc() : null;

        return is_array($row) ? PaymentOrder::fromRow($row) : null;
    }

    public static function setProviderRef(DB $db, PaymentOrder $order, string $providerRef): void
    {
        $db->run('UPDATE paymentOrder SET providerRef=?, updated=? WHERE id=?', [$providerRef, time(), $order->id]);
    }

    public static function fail(DB $db, PaymentOrder $order): void
    {
        $db->run('UPDATE paymentOrder SET status=\'failed\', updated=? WHERE id=? AND status=\'pending\'', [time(), $order->id]);
    }

    private static function find(DB $db, string $column, string $value): ?PaymentOrder
    {
        $statement = $db->run('SELECT * FROM paymentOrder WHERE ' . ($column === 'id' ? 'id' : 'token') . '=? LIMIT 1', [$value]);
        $row = $statement ? $statement->get_result()->fetch_assoc() : null;

        return is_array($row) ? PaymentOrder::fromRow($row) : null;
    }
}
