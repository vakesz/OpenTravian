<?php

declare(strict_types=1);

namespace Game\Webhook;

/** HTTPS-only curl post to a Discord webhook: verified TLS, no redirects, a 3 second limit so a slow Discord cannot stall the engine. */
final class DiscordSender implements WebhookSender
{
    public const TIMEOUT_SECONDS = 3;

    public function send(string $url, string $json): array
    {
        if (!WebhookConfig::validUrl($url)) {
            return ['status' => 0, 'retryAfter' => 0];
        }
        $handle = curl_init($url);
        if ($handle === false) {
            return ['status' => 0, 'retryAfter' => 0];
        }
        $retryAfter = 0;
        curl_setopt_array($handle, [
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_USERAGENT => 'opentravian-webhook',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$retryAfter): int {
                if (stripos($line, 'retry-after:') === 0) {
                    $retryAfter = max(0, (int) ceil((float) trim(substr($line, 12))));
                }

                return strlen($line);
            },
        ]);
        $ok = curl_exec($handle);
        $status = $ok === false ? 0 : (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return ['status' => $status, 'retryAfter' => $retryAfter];
    }
}
