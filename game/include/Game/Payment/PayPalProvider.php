<?php

declare(strict_types=1);

namespace Game\Payment;

/**
 * PayPal Orders v2. The player approves on PayPal, the return route captures
 * the order, and the verified webhook (checked through PayPal's
 * verify-webhook-signature call) is what credits gold.
 */
final class PayPalProvider implements Provider
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $webhookId,
        private readonly bool $live,
        private readonly HttpTransport $http,
    ) {
    }

    public function id(): string
    {
        return 'paypal';
    }

    public function createCheckout(PaymentOrder $order, string $description, string $successUrl, string $cancelUrl): array
    {
        $created = $this->api('POST', 'v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'custom_id' => $order->token,
                'description' => $description,
                'amount' => ['currency_code' => $order->currency, 'value' => self::decimal($order->amountMinor)],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => [
                'return_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'user_action' => 'PAY_NOW',
            ]]],
        ], ['PayPal-Request-Id' => 'checkout-' . $order->token]);
        $approve = null;
        foreach ((array) ($created['links'] ?? []) as $link) {
            if (is_array($link) && in_array($link['rel'] ?? '', ['payer-action', 'approve'], true) && is_string($link['href'] ?? null)) {
                $approve = $link['href'];
                break;
            }
        }
        if (!is_string($created['id'] ?? null) || $approve === null || !str_starts_with($approve, 'https://')) {
            throw new PaymentException('PayPal refused the checkout.');
        }

        return ['url' => $approve, 'ref' => $created['id']];
    }

    /** Captures an order the player approved; safe to repeat. */
    public function capture(string $providerRef): void
    {
        $this->api('POST', 'v2/checkout/orders/' . rawurlencode($providerRef) . '/capture', null, [
            'PayPal-Request-Id' => 'capture-' . $providerRef,
        ], [200, 201, 422]);
    }

    public function parseWebhook(string $rawBody, array $headers): ?PaymentEvent
    {
        $event = json_decode($rawBody, true, 32);
        if (!is_array($event) || !is_string($event['id'] ?? null) || !is_string($event['event_type'] ?? null)
            || !is_array($event['resource'] ?? null)) {
            throw new PaymentException('Malformed PayPal event.');
        }
        $this->verify($rawBody, $headers);
        $resource = $event['resource'];
        $string = static fn (mixed $value): ?string => is_string($value) && $value !== '' ? $value : null;
        $currency = strtoupper((string) ($resource['amount']['currency_code'] ?? ''));
        $minor = self::minor((string) ($resource['amount']['value'] ?? '0'));

        switch ($event['event_type']) {
            case 'PAYMENT.CAPTURE.COMPLETED':
                if (($resource['status'] ?? '') !== 'COMPLETED') {
                    return null;
                }

                return new PaymentEvent('paypal', $event['id'], PaymentEventType::Paid, $string($resource['custom_id'] ?? null),
                    $string($resource['id'] ?? null), $minor, $currency);
            case 'PAYMENT.CAPTURE.REFUNDED':
                $capture = null;
                foreach ((array) ($resource['links'] ?? []) as $link) {
                    if (is_array($link) && ($link['rel'] ?? '') === 'up' && is_string($link['href'] ?? null)
                        && preg_match('#/payments/captures/([A-Za-z0-9]+)$#', $link['href'], $match) === 1) {
                        $capture = $match[1];
                    }
                }

                return new PaymentEvent('paypal', $event['id'], PaymentEventType::Refunded, null, $capture, $minor, $currency);
            case 'CUSTOMER.DISPUTE.CREATED':
                $transaction = $resource['disputed_transactions'][0] ?? [];
                $amount = $resource['dispute_amount'] ?? [];

                return new PaymentEvent('paypal', $event['id'], PaymentEventType::Disputed, null,
                    $string($transaction['seller_transaction_id'] ?? null),
                    self::minor((string) ($amount['value'] ?? '0')), strtoupper((string) ($amount['currency_code'] ?? '')));
        }

        return null;
    }

    /**
     * @param array<string, string> $headers
     */
    private function verify(string $rawBody, array $headers): void
    {
        $required = ['paypal-auth-algo', 'paypal-cert-url', 'paypal-transmission-id', 'paypal-transmission-sig', 'paypal-transmission-time'];
        foreach ($required as $name) {
            if (($headers[$name] ?? '') === '') {
                throw new PaymentException('Missing PayPal signature headers.');
            }
        }
        // The event is embedded verbatim: PayPal signs the exact bytes it sent.
        $body = '{"auth_algo":' . json_encode($headers['paypal-auth-algo'])
            . ',"cert_url":' . json_encode($headers['paypal-cert-url'])
            . ',"transmission_id":' . json_encode($headers['paypal-transmission-id'])
            . ',"transmission_sig":' . json_encode($headers['paypal-transmission-sig'])
            . ',"transmission_time":' . json_encode($headers['paypal-transmission-time'])
            . ',"webhook_id":' . json_encode($this->webhookId)
            . ',"webhook_event":' . $rawBody . '}';
        try {
            $result = $this->api('POST', 'v1/notifications/verify-webhook-signature', $body);
        } catch (PaymentException) {
            throw new PaymentException('PayPal signature verification is unavailable.');
        }
        if (($result['verification_status'] ?? '') !== 'SUCCESS') {
            throw new PaymentException('Invalid PayPal signature.');
        }
    }

    /**
     * @param array<string,mixed>|string|null $payload array (encoded as JSON) or a ready JSON string
     * @param array<string,string> $headers
     * @param list<int> $accept
     * @return array<string,mixed>
     */
    private function api(string $method, string $path, array|string|null $payload, array $headers = [], array $accept = [200, 201]): array
    {
        $base = $this->live ? 'https://api-m.paypal.com/' : 'https://api-m.sandbox.paypal.com/';
        try {
            $token = $this->http->send('POST', $base . 'v1/oauth2/token', [
                'Authorization' => 'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ], 'grant_type=client_credentials')->json();
            if (!is_string($token['access_token'] ?? null)) {
                throw new PaymentException('PayPal rejected the credentials.');
            }
            $response = $this->http->send($method, $base . $path, $headers + [
                'Authorization' => 'Bearer ' . $token['access_token'],
                'Content-Type' => 'application/json',
            ], $payload === null ? '{}' : (is_string($payload) ? $payload : json_encode($payload, JSON_THROW_ON_ERROR)));
        } catch (\RuntimeException $exception) {
            if ($exception instanceof PaymentException) {
                throw $exception;
            }
            throw new PaymentException('PayPal could not be reached.');
        }
        if (!in_array($response->status, $accept, true)) {
            throw new PaymentException('PayPal returned an error.');
        }

        return $response->json() ?? [];
    }

    private static function decimal(int $minor): string
    {
        return sprintf('%d.%02d', intdiv($minor, 100), $minor % 100);
    }

    private static function minor(string $decimal): int
    {
        return (int) round(((float) $decimal) * 100);
    }
}
