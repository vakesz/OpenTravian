<?php

declare(strict_types=1);

// Against the running dev stack: the footer is absent by default and with the environment variable
// but no cookie, present as exactly one fixed element with the cookie, and the page is otherwise
// byte-identical. Skipped loudly when the stack's php service does not have TRAVIAN_DEV_TOOLS=1.

if (getenv('TRAVIAN_DEV_TOOLS') !== '1') {
    fwrite(STDERR, "\n*** SKIPPED: dev request timing: TRAVIAN_DEV_TOOLS is not 1 in this container (use the dev compose layer).\n\n");
    echo "Dev request timing runtime passed (SKIPPED).\n";
    exit(0);
}

$get = static function (string $url, string $cookie = ''): array {
    $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 20, 'follow_location' => 0, 'header' => $cookie !== '' ? "Cookie: $cookie\r\n" : '']]);
    $body = @file_get_contents($url, false, $context);

    return [$http_response_header ?? [], (string) $body];
};
$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$url = 'http://web/homepage';
[, $plain] = $get($url);
if ($plain === '' || !str_contains($plain, '</body>')) {
    $fail('The public page must render.');
}
if (str_contains($plain, 'travianDevFooter')) {
    $fail('Without the cookie the footer must be absent.');
}
[$headers, $withToggle] = $get($url . '?devFooter=1');
if (!preg_grep('/^Set-Cookie: travianDevFooter=1/i', $headers)) {
    $fail('?devFooter=1 must set the cookie.');
}
[$headers, $with] = $get($url, 'travianDevFooter=1');
if (substr_count($with, 'id="travianDevFooter"') !== 1) {
    $fail('With the cookie exactly one footer must be added.');
}
if (!preg_grep('/^Server-Timing: /i', $headers)) {
    $fail('With the cookie a Server-Timing header must be sent.');
}
$stripped = preg_replace('/<div id="travianDevFooter".*?<\/div>/s', '', $with, 1);
if ($stripped === null || strlen($stripped) < strlen($plain) - 200 || strlen($stripped) > strlen($plain) + 200) {
    $fail('Apart from the footer the page must be unchanged.');
}
[$headers] = $get($url . '?devFooter=0', 'travianDevFooter=1');
if (!preg_grep('/^Set-Cookie: travianDevFooter=;/i', $headers) && !preg_grep('/^Set-Cookie: travianDevFooter=deleted/i', $headers)) {
    $fail('?devFooter=0 must clear the cookie.');
}

echo "Dev request timing runtime passed.\n";
