<?php

declare(strict_types=1);

// nginx throttles auth POSTs, bounds bodies and runtime, and sends the baseline security headers;
// Compose rotates container logs and can enable the MariaDB slow-query log; the app log rotates.

$root = dirname(__DIR__, 2);
$fail = static function (string $message): void {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$read = static fn (string $path): string => (string)file_get_contents($root . '/' . $path);

$nginx = $read('docker/nginx/default.conf.template');
foreach ([
    'limit_req_zone $travian_auth_key zone=travian_auth:10m rate=20r/m;' => 'a request-rate zone for auth POSTs',
    'limit_req zone=travian_auth burst=10 nodelay;' => 'limit_req on the server',
    'client_max_body_size 1m;' => 'a small default body limit',
    'fastcgi_read_timeout 60s;' => 'a 60s default FastCGI timeout',
    'Permissions-Policy' => 'Permissions-Policy',
    'Content-Security-Policy' => 'a Content-Security-Policy',
    "frame-ancestors 'self'" => 'frame-ancestors',
    'X-Content-Type-Options "nosniff"' => 'nosniff',
    'Referrer-Policy "strict-origin-when-cross-origin"' => 'Referrer-Policy',
    'location = /admin.php' => 'a dedicated long-running admin location',
    'location = /setup.php' => 'a dedicated long-running installer location',
] as $needle => $what) {
    if (!str_contains($nginx, $needle)) {
        $fail("nginx template must define {$what}.");
    }
}
if (str_contains($nginx, 'client_max_body_size 40m') || substr_count($nginx, 'fastcgi_read_timeout 3600s;') !== 2) {
    $fail('Only the admin and installer locations may have the long timeout and large bodies must be gone.');
}
foreach (['/oauth/', '/support/ticket', '/homepage', 'POST'] as $needle) {
    if (!str_contains($nginx, $needle)) {
        $fail("The auth throttle map must cover {$needle}.");
    }
}

$ini = $read('docker/php/php.ini');
if (!str_contains($ini, 'file_uploads = Off') || str_contains($ini, '40M')) {
    $fail('php.ini must disable uploads and keep post_max_size small.');
}

$compose = $read('compose.yaml');
foreach (['x-logging: &default-logging', 'max-size: "10m"', '--slow-query-log=${TRAVIAN_MARIADB_SLOW_LOG:-0}', '--long-query-time=${TRAVIAN_MARIADB_SLOW_LOG_SECONDS:-1}'] as $needle) {
    if (!str_contains($compose, $needle)) {
        $fail("compose.yaml must contain {$needle}.");
    }
}
// Every service inherits the rotating log options through the shared x-service anchor.
preg_match_all('/^  [a-z]+:\n    <<: \*service$/m', $compose, $serviceBlocks);
preg_match_all('/^  [a-z]+:$/m', (string)substr($compose, (int)strpos($compose, "\nservices:"), (int)strpos($compose, "\n# frontend:") - (int)strpos($compose, "\nservices:")), $allServices);
if (substr_count($compose, 'logging: *default-logging') !== 1 || count($serviceBlocks[0]) !== count($allServices[0])) {
    $fail('Every compose service must use the rotating log driver options (via <<: *service).');
}
if (!str_contains($read('compose.tls.yaml'), 'max-size: "10m"')) {
    $fail('The Caddy service must rotate its logs too.');
}

if (!str_contains($read('game/include/Core/ErrorHandler.php'), 'MAX_LOG_BYTES')) {
    $fail('The application error log must rotate.');
}

// Version disclosure, HSTS and Redis authentication.
if (!preg_match('/^server_tokens off;$/m', $nginx)) {
    $fail('nginx must send no version (server_tokens off).');
}
if (!preg_match('/^expose_php = Off$/m', $read('docker/php/php.ini'))) {
    $fail('PHP must not send X-Powered-By (expose_php = Off).');
}
if (str_contains($nginx, 'Strict-Transport-Security') || !str_contains($read('docker/caddy/Caddyfile'), 'header Strict-Transport-Security "max-age=31536000"')) {
    $fail('HSTS belongs in the Caddyfile (HTTPS layer) only, with max-age=31536000.');
}
$redisConf = $read('docker/redis/redis.conf');
$redisEntrypoint = $read('docker/redis/entrypoint.sh');
if (!str_contains($redisConf, 'aclfile') || !str_contains($redisEntrypoint, '-@dangerous -@admin +eval') || str_contains($redisEntrypoint, '--requirepass')) {
    $fail('Redis must load an ACL file that denies dangerous commands, never a command-line password.');
}
if (!str_contains($compose, 'REDIS_PASSWORD: ${REDIS_PASSWORD:?') || !str_contains($compose, 'TRAVIAN_REDIS_PASSWORD: ${REDIS_PASSWORD:?') || !str_contains($compose, 'REDISCLI_AUTH')) {
    $fail('REDIS_PASSWORD must be required and reach redis, its healthcheck and the PHP services.');
}
if (!str_contains($read('config/bootstrap-env.sh'), 'set_value REDIS_PASSWORD')) {
    $fail('bootstrap-env.sh must generate REDIS_PASSWORD.');
}
if (!str_contains($compose, "networks:\n  frontend:\n  backend:") || preg_match('/^    networks: \[frontend\]$/m', $compose) !== 1 || substr_count($compose, 'networks: [backend]') !== 4) {
    $fail('web must sit on the frontend network only; db, redis, engine and installer on the backend only.');
}

echo "Ops hardening contract passed.\n";
