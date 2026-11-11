<?php

declare(strict_types=1);

namespace Game\Payment;

/** Stripe Checkout (hosted page) with signed webhooks. */
final class StripeProvider implements Provider
{
    private const API = 'https://api.stripe.com/v1/';
    private const TOLERANCE_SECONDS = 300;

    private readonly \Closure $now;

    public function __construct(
        private readonly string $secretKey,
        private readonly string $webhookSecret,
        private readonly HttpTransport $http,
        ?\Closure $now = null,
    ) {
        $this->now = $now ?? static fn (): int => time();
    }

    public function id(): string
    {
        return 'stripe';
    }

    public function createCheckout(PaymentOrder $order, string $description, string $successUrl, string $cancelUrl): array
    {
        $body = http_build_query([
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => $order->token,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($order->currency),
                    'unit_amount' => $order->amountMinor,
                    'product_data' => ['name' => $description],
                ],
            ]],
        ], '', '&', PHP_QUERY_RFC3986);
        try {
            $response = $this->http->send('POST', self::API . 'checkout/sessions', [
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Idempotency-Key' => 'checkout-' . $order->token,
            ], $body);
        } catch (\RuntimeException) {
            throw new PaymentException('Stripe could not be reached.');
        }
        $session = $response->json();
        if ($response->status !== 200 || !is_string($session['id'] ?? null) || !is_string($session['url'] ?? null)
            || !str_starts_with($session['url'], 'https://')) {
            throw new PaymentException('Stripe refused the checkout.');
        }

        return ['url' => $session['url'], 'ref' => $session['id']];
    }

    public function parseWebhook(string $rawBody, array $headers): ?PaymentEvent
    {
        $this->verify($rawBody, $headers['stripe-signature'] ?? '');
        $event = json_decode($rawBody, true, 32);
        $object = is_array($event) ? ($event['data']['object'] ?? null) : null;
        if (!is_array($event) || !is_string($event['id'] ?? null) || !is_string($event['type'] ?? null) || !is_array($object)) {
            throw new PaymentException('Malformed Stripe event.');
        }
        $string = static fn (mixed $value): ?string => is_string($value) && $value !== '' ? $value : null;
        $currency = strtoupper((string) ($object['currency'] ?? ''));

        return match ($event['type']) {
            'checkout.session.completed', 'checkout.session.async_payment_succeeded'
                => ($object['payment_status'] ?? '') === 'paid'
                    ? new PaymentEvent('stripe', $event['id'], PaymentEventType::Paid, $string($object['client_reference_id'] ?? null),
                        $string($object['payment_intent'] ?? null), (int) ($object['amount_total'] ?? 0), $currency)
                    : null,
            'charge.refunded' => new PaymentEvent('stripe', $event['id'], PaymentEventType::Refunded, null,
                $string($object['payment_intent'] ?? null), (int) ($object['amount_refunded'] ?? 0), $currency, true),
            'charge.dispute.created' => new PaymentEvent('stripe', $event['id'], PaymentEventType::Disputed, null,
                $string($object['payment_intent'] ?? null), (int) ($object['amount'] ?? 0), $currency),
            default => null,
        };
    }

    private function verify(string $rawBody, string $header): void
    {
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }
        if ($timestamp === null || $signatures === [] || abs(($this->now)() - $timestamp) > self::TOLERANCE_SECONDS) {
            throw new PaymentException('Missing or stale Stripe signature.');
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $this->webhookSecret);
        foreach ($signatures as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return;
            }
        }
        throw new PaymentException('Invalid Stripe signature.');
    }
}
