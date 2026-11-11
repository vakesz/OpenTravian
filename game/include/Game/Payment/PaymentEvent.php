<?php

declare(strict_types=1);

namespace Game\Payment;

/**
 * A verified provider notification. The order is found by `orderToken` (sent
 * with the checkout) or, for refunds and disputes that do not carry it, by the
 * provider's own payment id.
 */
final class PaymentEvent
{
    public function __construct(
        public readonly string $provider,
        public readonly string $eventId,
        public readonly PaymentEventType $type,
        public readonly ?string $orderToken,
        public readonly ?string $paymentRef,
        public readonly int $amountMinor,
        public readonly string $currency,
        /** True when `amountMinor` is the running refund total (Stripe), false when it is this refund alone (PayPal). */
        public readonly bool $cumulative = false,
    ) {
    }
}
