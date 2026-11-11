<?php

declare(strict_types=1);

namespace Game\Payment;

use Core\Database\DB;

/**
 * Applies verified provider events to gold balances.
 *
 * Idempotency has two layers because the account database (events, orders)
 * and the world database (balances) cannot share a transaction:
 *  - paymentEvent has UNIQUE(provider, eventId): a redelivered event is a no-op;
 *  - the world's paymentCredits has UNIQUE(creditKey) per order and direction,
 *    so even concurrent or retried deliveries move the balance at most once.
 * An event is only marked "applied" after the balance change committed, so a
 * crash in between is repaired by the provider's next delivery.
 */
final class PaymentCredit
{
    public const APPLIED = 'applied';
    public const DUPLICATE = 'duplicate';
    public const REJECTED = 'rejected';

    /** @param \Closure(int):?DB $worldFor resolves a world's database; null when unreachable */
    public function __construct(private readonly DB $account, private readonly \Closure $worldFor)
    {
    }

    /**
     * @return string one of APPLIED, DUPLICATE, REJECTED
     * @throws PaymentException when the order's world cannot be reached (the provider should retry)
     */
    public function apply(PaymentEvent $event, ?int $now = null): string
    {
        $now ??= time();
        $inserted = $this->account->run(
            'INSERT IGNORE INTO paymentEvent (provider, eventId, type, amountMinor, currency, time) VALUES (?, ?, ?, ?, ?, ?)',
            [$event->provider, $event->eventId, $event->type->value, max(0, $event->amountMinor), $event->currency, $now]
        );
        if (!$inserted) {
            throw new PaymentException('The payment event could not be recorded.');
        }
        if ($inserted->affected_rows === 0) {
            $previous = $this->account->run('SELECT outcome FROM paymentEvent WHERE provider=? AND eventId=?', [$event->provider, $event->eventId]);
            $row = $previous ? $previous->get_result()->fetch_assoc() : null;
            if (is_array($row) && $row['outcome'] !== 'received') {
                return self::DUPLICATE;
            }
        }

        $order = $event->orderToken !== null ? PaymentOrders::findByToken($this->account, $event->orderToken) : null;
        if ($order === null && $event->paymentRef !== null) {
            $order = PaymentOrders::findByPaymentRef($this->account, $event->provider, $event->paymentRef);
        }
        if ($order === null || $order->provider !== $event->provider) {
            return $this->finish($event, null, self::REJECTED, 'unknown order');
        }
        if ($event->currency !== '' && $event->currency !== $order->currency) {
            return $this->finish($event, $order, self::REJECTED, 'currency mismatch');
        }

        return $event->type === PaymentEventType::Paid ? $this->credit($event, $order, $now) : $this->reverse($event, $order, $now);
    }

    private function credit(PaymentEvent $event, PaymentOrder $order, int $now): string
    {
        if ($event->amountMinor !== $order->amountMinor) {
            return $this->finish($event, $order, self::REJECTED, 'amount mismatch');
        }
        if (!in_array($order->status, ['pending', 'paid'], true)) {
            return $this->finish($event, $order, self::REJECTED, 'order is ' . $order->status);
        }
        $world = $this->world($order);
        $world->begin_transaction();
        try {
            $fresh = $world->run(
                'INSERT IGNORE INTO paymentCredits (creditKey, uid, gold, time) VALUES (?, ?, ?, ?)',
                [self::creditKey($order), $order->uid, $order->gold, $now]
            );
            if ($fresh && $fresh->affected_rows === 1) {
                $user = $world->run('UPDATE users SET bought_gold=bought_gold+? WHERE id=?', [$order->gold, $order->uid]);
                if (!$user || $user->affected_rows !== 1) {
                    $world->rollback();

                    return $this->finish($event, $order, self::REJECTED, 'player not found');
                }
                // type 1 makes Automation mail the player the standard "gold bought" message.
                $world->run('INSERT INTO buyGoldMessages (uid, gold, type, trackingCode) VALUES (?, ?, 1, ?)', [$order->uid, $order->gold, 'order-' . $order->id]);
            }
            $world->commit();
        } catch (\Throwable $exception) {
            $world->rollback();
            throw $exception instanceof PaymentException ? $exception : new PaymentException('The gold could not be credited.', 0, $exception);
        }
        $this->account->run(
            'UPDATE paymentOrder SET status=\'paid\', paymentRef=COALESCE(paymentRef, ?), updated=? WHERE id=? AND status IN (\'pending\', \'paid\')',
            [$event->paymentRef, $now, $order->id]
        );

        return $this->finish($event, $order, self::APPLIED, '');
    }

    /**
     * Refund or dispute: take purchased gold back (the balance may go negative) and flag the account.
     * A refund deducts ceil(gold x refunded / paid) in total across all its partial refunds, never more
     * than the order's gold; a dispute reverses the whole order.
     */
    private function reverse(PaymentEvent $event, PaymentOrder $order, int $now): string
    {
        if ($order->status === 'pending' || $order->status === 'failed') {
            return $this->finish($event, $order, self::REJECTED, 'order was never paid');
        }
        $world = $this->world($order);
        $prefix = 'order:' . $order->id . ':reversal:';
        $totals = static function () use ($world, $prefix): array {
            $row = $world->run('SELECT COALESCE(SUM(amountMinor), 0) AS refunded, COALESCE(-SUM(gold), 0) AS deducted FROM paymentCredits WHERE creditKey LIKE ?', [$prefix . '%'])
                ->get_result()->fetch_assoc();

            return [(int) $row['refunded'], (int) $row['deducted']];
        };
        $world->begin_transaction();
        try {
            // Locking the credit row serialises concurrent refund events for one order.
            $credited = $world->run('SELECT 1 FROM paymentCredits WHERE creditKey=? FOR UPDATE', [self::creditKey($order)]);
            if (!$credited || $credited->get_result()->num_rows === 0) {
                $world->rollback();

                return $this->finish($event, $order, self::REJECTED, 'gold was never credited');
            }
            [$refunded, $deducted] = $totals();
            $total = match (true) {
                $event->type === PaymentEventType::Disputed => $order->amountMinor,
                $event->cumulative => max($refunded, $event->amountMinor),
                default => $refunded + $event->amountMinor,
            };
            $total = min($total, $order->amountMinor);
            $target = min($order->gold, intdiv($order->gold * $total + $order->amountMinor - 1, $order->amountMinor));
            $delta = max(0, $target - $deducted);
            $fresh = $world->run(
                'INSERT IGNORE INTO paymentCredits (creditKey, uid, gold, amountMinor, time) VALUES (?, ?, ?, ?, ?)',
                [$prefix . substr(sha1($event->provider . ':' . $event->eventId), 0, 16), $order->uid, -$delta, max(0, $total - $refunded), $now]
            );
            if ($fresh && $fresh->affected_rows === 1 && $delta > 0) {
                $world->run('UPDATE users SET bought_gold=bought_gold-? WHERE id=?', [$delta, $order->uid]);
            }
            [$refundedTotal, $deductedTotal] = $totals();
            $world->commit();
        } catch (\Throwable $exception) {
            $world->rollback();
            throw $exception instanceof PaymentException ? $exception : new PaymentException('The gold could not be deducted.', 0, $exception);
        }
        $status = $event->type === PaymentEventType::Disputed || $order->status === 'disputed'
            ? 'disputed'
            : ($refundedTotal >= $order->amountMinor ? 'refunded' : 'partial');
        $this->account->run(
            'UPDATE paymentOrder SET status=?, updated=? WHERE id=? AND status IN (\'paid\', \'partial\', \'refunded\', \'disputed\')',
            [$status, $now, $order->id]
        );
        $this->account->run(
            'INSERT INTO paymentFlag (uid, worldUniqueId, orderId, reason, goldDeducted, time) VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE clearedAt=IF(VALUES(goldDeducted) > goldDeducted, NULL, clearedAt), reason=IF(reason=\'disputed\', reason, VALUES(reason)), goldDeducted=VALUES(goldDeducted)',
            [$order->uid, $order->worldUniqueId, $order->id, $event->type->value, $deductedTotal, $now]
        );

        return $this->finish($event, $order, self::APPLIED, '');
    }

    private function world(PaymentOrder $order): DB
    {
        return ($this->worldFor)($order->worldUniqueId) ?? throw new PaymentException('The order\'s world is not reachable.');
    }

    private function finish(PaymentEvent $event, ?PaymentOrder $order, string $outcome, string $detail): string
    {
        $this->account->run(
            'UPDATE paymentEvent SET orderId=?, outcome=?, detail=? WHERE provider=? AND eventId=?',
            [$order?->id, $outcome, $detail, $event->provider, $event->eventId]
        );

        return $outcome;
    }

    private static function creditKey(PaymentOrder $order): string
    {
        return 'order:' . $order->id . ':credit';
    }
}
