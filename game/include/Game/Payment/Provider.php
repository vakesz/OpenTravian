<?php

declare(strict_types=1);

namespace Game\Payment;

interface Provider
{
    /** Stable id stored on orders: "stripe" or "paypal". */
    public function id(): string;

    /**
     * Starts a hosted checkout for the order. `$description` is what the
     * provider's page shows as the item.
     *
     * @return array{url:string,ref:string} where to send the player, and the provider's id for this checkout
     * @throws PaymentException when the provider refuses or is unreachable
     */
    public function createCheckout(PaymentOrder $order, string $description, string $successUrl, string $cancelUrl): array;

    /**
     * Verifies the notification's signature and maps it. Returns null for
     * event types this shop does not act on.
     *
     * @param array<string,string> $headers lower-case header names
     * @throws PaymentException when the signature is missing, stale or wrong
     */
    public function parseWebhook(string $rawBody, array $headers): ?PaymentEvent;
}
