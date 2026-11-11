<?php

declare(strict_types=1);

function failSessionCookiePolicyContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

putenv('TRAVIAN_BASE_URL=https://game.example.test/');
require dirname(__DIR__, 2) . '/game/deployment/include/env.php';

$_SERVER = ['HTTP_HOST' => 'localhost:8080'];
if (travian_session_cookie_params()['secure'] !== false) {
    failSessionCookiePolicyContract('A plain localhost request must not receive a Secure session cookie for another public host.');
}

$_SERVER = ['HTTP_HOST' => 'game.example.test'];
if (travian_session_cookie_params()['secure'] !== true) {
    failSessionCookiePolicyContract('The configured HTTPS host must retain Secure cookies behind TLS termination.');
}

$_SERVER = ['HTTP_HOST' => 'localhost:8080', 'HTTPS' => 'on'];
if (travian_session_cookie_params()['secure'] !== true) {
    failSessionCookiePolicyContract('A direct HTTPS request must retain Secure cookies.');
}

echo "Session cookie policy contract passed.\n";
