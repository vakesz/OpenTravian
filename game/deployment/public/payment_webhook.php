<?php

declare(strict_types=1);

// Payment provider webhook front controller (nginx: /payments/{stripe|paypal}/webhook).
// It boots the game runtime but never the player session: the provider's
// signature over the raw body is the only credential.

define('TRAVIAN_WITHOUT_SESSION', true);
require dirname(__DIR__) . '/include/env.php';
require dirname(__DIR__, 2) . '/include/bootstrap.php';

$headers = [];
foreach ($_SERVER as $name => $value) {
    if (is_string($value) && str_starts_with($name, 'HTTP_')) {
        $headers[strtolower(str_replace('_', '-', substr($name, 5)))] = $value;
    }
}
$endpoint = new Game\Payment\WebhookEndpoint(Game\Payment\PaymentConfig::fromEnvironment(), new Game\Payment\CurlTransport());
[$status, $body] = $endpoint->handle(
    is_string($_GET['provider'] ?? null) ? $_GET['provider'] : '',
    (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
    (string) file_get_contents('php://input'),
    $headers
);
while (ob_get_level() > 0) {
    ob_end_clean();
}
http_response_code($status);
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
echo $body;
