<?php

declare(strict_types=1);

namespace Game\Payment;

use Game\SocialAuth\HttpResponse;

/** HTTPS-only curl client for provider APIs: verified TLS, no redirects, bounded time and size. */
final class CurlTransport implements HttpTransport
{
    private const MAX_BYTES = 1048576;

    public function send(string $method, string $url, array $headers, ?string $body): HttpResponse
    {
        if (!str_starts_with($url, 'https://')) {
            throw new \RuntimeException('Refusing a non-HTTPS payment provider URL.');
        }
        $handle = curl_init($url);
        if ($handle === false) {
            throw new \RuntimeException('Unable to initialise the HTTP client.');
        }
        $lines = ['Accept: application/json'];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        $received = '';
        curl_setopt_array($handle, [
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_USERAGENT => 'opentravian-payments',
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$received): int {
                $received .= $chunk;

                return strlen($received) > self::MAX_BYTES ? 0 : strlen($chunk);
            },
        ]);
        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }
        $ok = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if ($ok === false) {
            throw new \RuntimeException('The payment provider could not be reached.');
        }

        return new HttpResponse($status, $received);
    }
}
