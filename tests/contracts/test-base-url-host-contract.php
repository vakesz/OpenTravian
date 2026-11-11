<?php

declare(strict_types=1);

// Links in mail come from the configured world URL, never the request Host header,
// and nginx drops requests whose Host is not one of the configured names.

$root = dirname(__DIR__, 2);
$fail = static function (string $message): void {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$read = static fn (string $path): string => (string)file_get_contents($root . '/' . $path);

$web = $read('game/include/Core/Helper/WebService.php');
if (str_contains($web, 'get_real_base_url') || str_contains($web, "'HTTP_HOST'")) {
    $fail('WebService must not derive URLs from HTTP_HOST.');
}
foreach (['game/include/Game/EmailVerification.php', 'game/include/Controller/ActivateCtrl.php'] as $file) {
    $source = $read($file);
    if (str_contains($source, 'HTTP_HOST') || str_contains($source, 'get_real_base_url')) {
        $fail("{$file} must not use the request Host.");
    }
    if (!str_contains($source, 'WebService::get_base_url()')) {
        $fail("{$file} must build the verification URL from WebService::get_base_url().");
    }
}

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/game/include', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->getExtension() === 'php' && str_contains((string)file_get_contents($file->getPathname()), 'get_real_base_url')) {
        $fail('get_real_base_url() must stay deleted: ' . $file->getPathname());
    }
}

$nginx = $read('docker/nginx/default.conf.template');
if (!preg_match('/listen 80 default_server;.*?server_name _;\s*return 444;/s', $nginx)
    || !str_contains($nginx, 'server_name ${TRAVIAN_ALLOWED_HOSTS};')) {
    $fail('nginx must answer unknown Host headers with 444 and serve only ${TRAVIAN_ALLOWED_HOSTS}.');
}
$envsh = $read('docker/nginx/15-travian-allowed-hosts.envsh');
foreach (['localhost 127.0.0.1', 'TRAVIAN_BASE_URL', 'TRAVIAN_EXTRA_HOSTS', 'export TRAVIAN_ALLOWED_HOSTS'] as $needle) {
    if (!str_contains($envsh, $needle)) {
        $fail("15-travian-allowed-hosts.envsh must contain {$needle}.");
    }
}
foreach (['docker/nginx/Dockerfile', 'compose.dev.yaml'] as $file) {
    if (!str_contains($read($file), '15-travian-allowed-hosts.envsh')) {
        $fail("{$file} must ship the allowed-hosts script.");
    }
}
if (!str_contains($read('compose.yaml'), 'TRAVIAN_BASE_URL: ${TRAVIAN_BASE_URL:-http://localhost:${APP_PORT:-8080}/}')) {
    $fail('compose.yaml must pass TRAVIAN_BASE_URL to the web service.');
}

echo "Base URL / Host contract passed.\n";
