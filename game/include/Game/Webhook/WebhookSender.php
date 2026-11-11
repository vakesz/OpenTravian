<?php

declare(strict_types=1);

namespace Game\Webhook;

interface WebhookSender
{
    /** @return array{status:int,retryAfter:int} HTTP status (0 on a transport failure) and the seconds Discord asks to wait. */
    public function send(string $url, string $json): array;
}
