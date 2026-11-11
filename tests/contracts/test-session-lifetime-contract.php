<?php

declare(strict_types=1);

function failSessionLifetime(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$ini = parse_ini_string((string)file_get_contents($root . '/docker/php/php.ini'));
if (!is_array($ini)) {
    failSessionLifetime('docker/php/php.ini must be parseable.');
}
if ((int)($ini['session.gc_maxlifetime'] ?? 0) < 30 * 86400) {
    failSessionLifetime('session.gc_maxlifetime must keep sessions for at least 30 days.');
}
if (($ini['session.save_handler'] ?? '') !== 'redis' || !str_contains((string)($ini['session.save_path'] ?? ''), 'prefix=travian_session:')) {
    failSessionLifetime('Sessions must use the Redis handler and the travian_session prefix.');
}
if (($ini['redis.session.locking_enabled'] ?? '') !== '1') {
    failSessionLifetime('Redis sessions must lock, so parallel requests from one player stay ordered.');
}
if (($ini['session.cookie_httponly'] ?? '') !== '1' || ($ini['session.cookie_samesite'] ?? '') !== 'Lax') {
    failSessionLifetime('Session cookie must stay HttpOnly and SameSite=Lax.');
}

putenv('TRAVIAN_BASE_URL=http://localhost:8080/');
require $root . '/game/deployment/include/env.php';
$_SERVER = ['HTTP_HOST' => 'localhost:8080'];
$params = travian_session_cookie_params();
if ($params['lifetime'] < 30 * 86400) {
    failSessionLifetime('The session cookie must be persistent (30 days), not browser-session only.');
}
if ($params['secure'] !== false || $params['httponly'] !== true || $params['samesite'] !== 'Lax') {
    failSessionLifetime('Cookie flags: HttpOnly, SameSite=Lax, Secure only over HTTPS.');
}

$entry = (string)file_get_contents($root . '/docker/php/entrypoint.sh');
$docker = (string)file_get_contents($root . '/docker/php/Dockerfile');
$env = (string)file_get_contents($root . '/game/deployment/include/env.php');
if (!str_contains($entry, 'session.save_path') || !str_contains($entry, 'auth=${redis_password}')
    || !str_contains($entry, 'umask 077')
    || !str_contains($docker, 'ENTRYPOINT ["travian-entrypoint"]') || !str_contains($docker, 'CMD ["php-fpm"]')) {
    failSessionLifetime('The entrypoint must write the Redis session password into a root-only ini, not the image.');
}
if (!str_contains($env, "ini_get('session.save_handler') !== 'files'")) {
    failSessionLifetime('The session directory helper must leave Redis sessions alone.');
}

$session = (string)file_get_contents($root . '/game/include/Core/Session.php');
if (!str_contains($session, 'RememberMe::LIFETIME') || str_contains($session, "max(600, Config::getProperty(\"settings\", \"session_timeout\"))) {\n                \$this->logout();\n                redirect(\"/dorf1.php\");\n                return;\n            }\n            if (\$isSitter")) {
    failSessionLifetime('The player idle timeout must not undercut the remember-me lifetime.');
}

$bootstrap = (string)file_get_contents($root . '/game/include/bootstrap.php');
if (!str_contains($bootstrap, "\$firstContact = !isset(\$_COOKIE[session_name()]);")
    || !str_contains($bootstrap, "session_start(\$firstContact ? ['gc_maxlifetime' => 86400] : [])")) {
    failSessionLifetime('A first request without a session cookie must keep its Redis session for one day, not the full lifetime.');
}

echo "Session lifetime contract passed.\n";
