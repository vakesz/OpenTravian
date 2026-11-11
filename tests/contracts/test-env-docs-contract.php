<?php

declare(strict_types=1);

// Contract: .env.example, the compose files and docs/configuration.md describe the
// same variables. Adding a knob in one place without the others fails here.

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string)file_get_contents($root . '/' . $path);

function expectEnvDocs(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$example = $read('.env.example');
preg_match_all('/^#?\s*([A-Z][A-Z0-9_]*)=/m', $example, $matches);
$exampleVars = array_values(array_unique($matches[1]));
expectEnvDocs(count($exampleVars) > 20, 'Could not read the variables from .env.example.');

$configuration = $read('docs/configuration.md');
$start = strpos($configuration, '## Environment reference');
$end = strpos($configuration, "\n## ", (int)$start + 1);
expectEnvDocs($start !== false && $end !== false, 'docs/configuration.md needs an "Environment reference" section.');
preg_match_all('/^\| `([A-Z][A-Z0-9_]*)` \|/m', substr($configuration, $start, $end - $start), $rows);
$documented = $rows[1];

foreach ($exampleVars as $name) {
    expectEnvDocs(in_array($name, $documented, true), ".env.example defines $name but docs/configuration.md has no row for it.");
}
foreach ($documented as $name) {
    expectEnvDocs(in_array($name, $exampleVars, true), "docs/configuration.md documents $name but .env.example does not define it.");
}
expectEnvDocs(count($documented) === count(array_unique($documented)), 'docs/configuration.md lists a variable twice.');

// Every variable a compose file reads from .env must be offered in .env.example.
foreach (['compose.yaml', 'compose.dev.yaml', 'compose.build.yaml', 'compose.tls.yaml'] as $file) {
    preg_match_all('/\$\{([A-Z][A-Z0-9_]*)[:}?-]/', $read($file), $used);
    foreach (array_unique($used[1]) as $name) {
        expectEnvDocs(in_array($name, $exampleVars, true), "$file reads $name, which .env.example does not define.");
    }
}

// The values bootstrap-env.sh fills must be the empty required ones. CI calls that
// script instead of listing secrets itself; the release job has no .env, so it sets
// every variable Compose requires before `docker compose config`.
$bootstrap = $read('config/bootstrap-env.sh');
expectEnvDocs(str_contains($read('.github/workflows/ci.yml'), 'sh config/bootstrap-env.sh --dev'), 'CI must write .env with config/bootstrap-env.sh --dev.');
foreach (['MARIADB_ROOT_PASSWORD', 'REDIS_PASSWORD', 'TRAVIAN_SETUP_TOKEN', 'APP_UID', 'APP_GID'] as $name) {
    expectEnvDocs(preg_match('/^' . $name . '=$/m', $example) === 1, ".env.example must define $name empty for config/bootstrap-env.sh to fill.");
    expectEnvDocs(str_contains($bootstrap, 'set_value ' . $name . ' '), "config/bootstrap-env.sh must generate $name.");
}
$release = $read('.github/workflows/release.yml');
preg_match_all('/\$\{([A-Z][A-Z0-9_]*):\?/', $read('compose.yaml') . "\n" . $read('compose.tls.yaml'), $required);
foreach (array_unique($required[1]) as $name) {
    expectEnvDocs(preg_match('/^[[:space:]]+' . $name . ':/m', $release) === 1, "The release workflow must set $name before docker compose config.");
}

echo "Env docs contract passed.\n";
