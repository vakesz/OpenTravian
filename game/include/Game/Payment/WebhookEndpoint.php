<?php

declare(strict_types=1);

namespace Game\Payment;

use Core\Database\GlobalDB;

/**
 * The provider-facing webhook: raw body in, HTTP status out. It runs outside
 * the player session and CSRF; the provider's signature is the only credential.
 */
final class WebhookEndpoint
{
    public function __construct(
        private readonly PaymentConfig $config,
        private readonly HttpTransport $http,
    ) {
    }

    /**
     * @param array<string,string> $headers lower-case names
     * @return array{0:int,1:string} HTTP status and a short plain-text body
     */
    public function handle(string $providerId, string $method, string $rawBody, array $headers): array
    {
        if ($method !== 'POST') {
            return [405, 'Method not allowed.'];
        }
        $provider = $this->config->providers($this->http)[$providerId] ?? null;
        if ($provider === null || strlen($rawBody) > 262144) {
            return [404, 'Not found.'];
        }
        try {
            $event = $provider->parseWebhook($rawBody, $headers);
        } catch (PaymentException) {
            return [400, 'Invalid signature.'];
        }
        if ($event === null) {
            return [200, 'Ignored.'];
        }
        try {
            $outcome = (new PaymentCredit(GlobalDB::getInstance(), static fn (int $world) => WorldDatabase::forWorld($world)))->apply($event);
        } catch (\Throwable $exception) {
            error_log('Payment event ' . $event->provider . ':' . $event->eventId . ' failed: ' . $exception->getMessage());

            return [500, 'Try again.'];
        }

        return [200, $outcome];
    }
}
