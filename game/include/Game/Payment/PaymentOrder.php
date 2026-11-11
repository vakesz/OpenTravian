<?php

declare(strict_types=1);

namespace Game\Payment;

/** One checkout: who buys how much gold in which world, for what price. */
final class PaymentOrder
{
    public function __construct(
        public readonly int $id,
        public readonly string $token,
        public readonly int $worldUniqueId,
        public readonly int $uid,
        public readonly int $gold,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly string $provider,
        public readonly ?string $paymentRef,
        public readonly string $status,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['token'],
            (int) $row['worldUniqueId'],
            (int) $row['uid'],
            (int) $row['gold'],
            (int) $row['amountMinor'],
            (string) $row['currency'],
            (string) $row['provider'],
            $row['paymentRef'] === null ? null : (string) $row['paymentRef'],
            (string) $row['status'],
        );
    }
}
