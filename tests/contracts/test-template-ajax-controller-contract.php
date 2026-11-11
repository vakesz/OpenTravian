<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$templateRoots = [
    $root . '/game/include/resources/Templates',
    $root . '/game/include/resources/View',
];
$legacyRoutes = require $root . '/game/deployment/include/api_legacy_routes.php';
$registeredRoutes = $legacyRoutes['routes'] ?? null;
if (!is_array($registeredRoutes)) {
    fwrite(STDERR, "Legacy Ajax routes must be readable.\n");
    exit(1);
}

/** @return string */
function ajaxDispatcherSegment(string $segment): string
{
    return lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $segment))));
}

/** @return never */
function failTemplateAjaxControllerContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$endpoints = [];
foreach ($templateRoots as $templateRoot) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($templateRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $source = file_get_contents($file->getPathname());
        if ($source === false) {
            failTemplateAjaxControllerContract('Could not read template ' . $file->getPathname() . '.');
        }
        if (preg_match_all("/Travian\\.api\\(\\s*['\"]([^'\"]+)['\"]/", $source, $matches) !== 1 && empty($matches[1])) {
            continue;
        }
        foreach ($matches[1] as $endpoint) {
            $endpoints[$endpoint] = true;
        }
    }
}

if ($endpoints === []) {
    failTemplateAjaxControllerContract('No template Ajax endpoints were discovered.');
}

ksort($endpoints);
foreach (array_keys($endpoints) as $endpoint) {
    $endpointPath = parse_url($endpoint, PHP_URL_PATH);
    if (!is_string($endpointPath) || $endpointPath === '') {
        failTemplateAjaxControllerContract("Invalid template Ajax endpoint '{$endpoint}'.");
    }
    // A route key may carry a verb prefix ("POST village-list").
    $registered = array_filter(
        array_keys($registeredRoutes),
        static fn (string $key): bool => $key === $endpointPath || preg_match('/^[A-Z]+ /', $key) === 1 && substr($key, strpos($key, ' ') + 1) === $endpointPath
    );
    if ($registered === []) {
        failTemplateAjaxControllerContract(
            "Template Ajax endpoint '{$endpoint}' is not registered by the public legacy route adapter."
        );
    }

    $parts = explode('/', $endpointPath);
    if (count($parts) > 2 || in_array('', $parts, true)) {
        failTemplateAjaxControllerContract("Invalid template Ajax endpoint '{$endpoint}'.");
    }

    $section = count($parts) === 2 ? ajaxDispatcherSegment($parts[0]) : null;
    $command = ajaxDispatcherSegment($parts[count($parts) - 1]);
    $controller = $root . '/game/include/Controller/Ajax/'
        . ($section === null ? '' : $section . '/')
        . $command . '.php';

    if (!is_file($controller)) {
        failTemplateAjaxControllerContract(
            "Template Ajax endpoint '{$endpoint}' has no dispatcher controller at '{$controller}'."
        );
    }
}

printf("Template Ajax-controller contract passed (%d endpoints).\n", count($endpoints));
