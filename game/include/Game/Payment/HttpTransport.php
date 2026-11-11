<?php

declare(strict_types=1);

namespace Game\Payment;

use Game\SocialAuth\HttpResponse;

interface HttpTransport
{
    /**
     * @param array<string,string> $headers
     * @throws \RuntimeException on transport failure
     */
    public function send(string $method, string $url, array $headers, ?string $body): HttpResponse;
}
