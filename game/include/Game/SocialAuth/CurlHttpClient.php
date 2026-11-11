<?php

declare(strict_types=1);

namespace Game\SocialAuth;

/** HTTPS-only curl client: verified TLS, no redirects, bounded time and size. */
final class CurlHttpClient implements HttpClient
{
    private const MAX_BYTES = 1048576;

    public function get(string $url, array $headers = []): HttpResponse
    {
        return $this->request($url, $headers, null);
    }

    public function postForm(string $url, array $fields): HttpResponse
    {
        return $this->request($url, [], http_build_query($fields, '', '&', PHP_QUERY_RFC3986));
    }

    /** @param array<string,string> $headers */
    private function request(string $url, array $headers, ?string $body): HttpResponse
    {
        if (!str_starts_with($url, 'https://')) {
            throw new \RuntimeException('Refusing a non-HTTPS provider URL.');
        }
        $handle = curl_init($url);
        if ($handle === false) {
            throw new \RuntimeException('Unable to initialise the HTTP client.');
        }
        $received = '';
        $maxAge = null;
        $headerLines = ['Accept: application/json'];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        curl_setopt_array($handle, [
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_USERAGENT => 'opentravian-social-signin',
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$maxAge): int {
                if (preg_match('/^cache-control:.*max-age=(\d{1,7})/i', $line, $match) === 1) {
                    $maxAge = (int) $match[1];
                }

                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$received): int {
                $received .= $chunk;

                return strlen($received) > self::MAX_BYTES ? 0 : strlen($chunk);
            },
        ]);
        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POST, true);
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }
        $ok = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if ($ok === false) {
            throw new \RuntimeException('Provider request failed.');
        }

        return new HttpResponse($status, $received, $maxAge);
    }
}
