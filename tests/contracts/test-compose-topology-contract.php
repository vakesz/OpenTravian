<?php

declare(strict_types=1);

// Contract: compose.yaml is the production configuration (published images,
// health-gated web startup, hardened services); layers add development
// bind mounts, a local build, or Caddy TLS. No layer needs Compose's !reset.

$root = dirname(__DIR__, 2);

function checkComposeTopology(bool $ok, string $message): void
{
    if (!$ok) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

$base = file_get_contents($root . '/compose.yaml');
$development = file_get_contents($root . '/compose.dev.yaml');
$build = file_get_contents($root . '/compose.build.yaml');
$tls = file_get_contents($root . '/compose.tls.yaml');
$phpDockerfile = file_get_contents($root . '/docker/php/Dockerfile');
$nginxDockerfile = file_get_contents($root . '/docker/nginx/Dockerfile');
$nginxConfig = file_get_contents($root . '/docker/nginx/default.conf.template');

checkComposeTopology($base !== false && $development !== false && $build !== false && $tls !== false, 'Compose layers must exist.');
checkComposeTopology($phpDockerfile !== false && $nginxDockerfile !== false && $nginxConfig !== false, 'Production Dockerfiles and nginx configuration must exist.');
foreach (['docker-compose.yml', 'compose.override.yaml', 'compose.production.yaml'] as $obsolete) {
    checkComposeTopology(!is_file($root . '/' . $obsolete), "The obsolete $obsolete path must stay removed.");
}
foreach ([$base, $development, $build, $tls] as $layer) {
    checkComposeTopology(!str_contains((string)$layer, '!reset') && !str_contains((string)$layer, '!override'), 'Layers must not depend on Compose reset/override tags.');
}
checkComposeTopology(!str_contains((string)$base, 'build:'), 'The production file pulls images; local builds live in compose.build.yaml.');
checkComposeTopology(str_contains((string)$base, 'ghcr.io/vakesz/opentravian-app:${TRAVIAN_VERSION:-latest}') && str_contains((string)$base, 'ghcr.io/vakesz/opentravian-web:${TRAVIAN_VERSION:-latest}'), 'Production must default to the published images selected by TRAVIAN_VERSION.');
checkComposeTopology(str_contains((string)$base, 'image: *app-image') && substr_count((string)$base, 'image: *app-image') === 3, 'php, engine and installer must share the one app image.');
checkComposeTopology(str_contains((string)$build, 'target: production') && str_contains((string)$build, 'docker/nginx/Dockerfile'), 'The build layer must build both production images.');
checkComposeTopology(str_contains((string)$development, 'target: runtime') && str_contains((string)$development, '.:/travian'), 'Development must use the runtime target and a source bind mount.');
checkComposeTopology(str_contains((string)$base, 'MARIADB_ROOT_PASSWORD') && substr_count((string)$base, 'TRAVIAN_INSTALL_DB_PASSWORD') === 1, 'Only the installer service may receive the database administrator password.');
checkComposeTopology(substr_count((string)$base, '<<: *service') >= 6, 'Every service must use the shared hardening anchor.');
checkComposeTopology(str_contains((string)$base, 'no-new-privileges:true') && str_contains((string)$base, 'cap_drop:'), 'Services must drop capabilities and forbid privilege escalation.');
checkComposeTopology(str_contains((string)$base, 'condition: service_healthy') && str_contains((string)$nginxConfig, 'location = /healthz'), 'Compose must health-gate startup and expose nginx readiness.');
checkComposeTopology(str_contains((string)$base, '@sha256:') && str_contains((string)$phpDockerfile, '@sha256:') && str_contains((string)$nginxDockerfile, '@sha256:'), 'Runtime images must be digest pinned.');
checkComposeTopology(
    preg_match('~nginx:stable-alpine@(sha256:[0-9a-f]{64})~', (string)$nginxDockerfile, $dockerfileNginx) === 1
        && preg_match('~nginx:stable-alpine@(sha256:[0-9a-f]{64})~', (string)$development, $developmentNginx) === 1
        && $dockerfileNginx[1] === $developmentNginx[1],
    'The nginx digest in docker/nginx/Dockerfile and compose.dev.yaml must match.'
);
checkComposeTopology(str_contains((string)$tls, 'caddy') && str_contains((string)$tls, 'TRAVIAN_DOMAIN'), 'The TLS layer must run Caddy for TRAVIAN_DOMAIN.');
checkComposeTopology(str_contains((string)$phpDockerfile, 'FROM runtime AS production') && str_contains((string)$nginxDockerfile, 'COPY assets/gpack/'), 'Production images must contain their required application files.');

echo "compose topology contract ok\n";
