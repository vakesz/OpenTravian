<?php

declare(strict_types=1);

namespace Game\SocialAuth;

interface HttpClient
{
    /**
     * @param array<string,string> $headers
     * @throws \RuntimeException on transport failure
     */
    public function get(string $url, array $headers = []): HttpResponse;

    /**
     * @param array<string,string> $fields application/x-www-form-urlencoded body
     * @throws \RuntimeException on transport failure
     */
    public function postForm(string $url, array $fields): HttpResponse;
}
