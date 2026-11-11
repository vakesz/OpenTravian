#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Every maintained /api/v1 route is consumed by Travian.api(), whose default
 * transport is JSON. Keep that safe transport central: only a client that
 * explicitly asks for raw HTML may opt out.
 */
function jsonDialogTransportCommandToFile(string $command): string
{
    return lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $command)))) . '.php';
}

$root = dirname(__DIR__, 2);
$routes = require $root . '/game/deployment/include/api_legacy_routes.php';
$clients = [
    file_get_contents($root . '/game/deployment/public/game-client.js'),
];
foreach ($clients as $client) {
    if (!is_string($client)
        || preg_match('~Travian\\.api\\(\\s*n,\\s*\\{.*?\\},\\s*"GET",\\s*"html"~s', $client) !== 1) {
        fwrite(STDERR, "profile-languages must remain the explicit raw-HTML API exception.\n");
        exit(1);
    }
}
$rawHtmlEndpoints = ['profile-languages'];
$unexpectedRawHtml = [];
$htmlResponsePattern = '~response\s*\[\s*[\'\"]html[\'\"]\s*\]~';
$rawHtmlPattern = '~response\s*\[\s*[\'\"]_rawHtml[\'\"]\s*\]~';
$ajaxControllerDirectory = $root . '/game/include/Controller/Ajax';
$rawHtmlControllers = [realpath($ajaxControllerDirectory . '/profileLanguages.php')];
$ajaxController = file_get_contents($root . '/game/include/Controller/AjaxCtrl.php');
if ($ajaxController === false
    || !str_contains($ajaxController, '$rawHtml = !empty($response[\'_rawHtml\']);')
    || !str_contains($ajaxController, 'unset($response[\'_rawHtml\']);')
    || !str_contains($ajaxController, 'if ($rawHtml && isset($response[\'html\'])')) {
    fwrite(STDERR, "Ajax responses must default to JSON and require an explicit _rawHtml opt-out.\n");
    exit(1);
}
if (str_contains($ajaxController, '_jsonHtml')) {
    fwrite(STDERR, "The retired _jsonHtml transport override must not remain in AjaxCtrl.\n");
    exit(1);
}

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ajaxControllerDirectory)) as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $controller = file_get_contents($file->getPathname());
    if ($controller === false || preg_match($htmlResponsePattern, $controller) !== 1) {
        continue;
    }
    if (in_array($file->getRealPath(), $rawHtmlControllers, true)) {
        if (preg_match($rawHtmlPattern, $controller) !== 1) {
            $unexpectedRawHtml[] = str_replace($root . '/', '', $file->getPathname()) . ' is missing its explicit raw-HTML opt-out';
        }
        continue;
    }
    if (preg_match($rawHtmlPattern, $controller) === 1) {
        $unexpectedRawHtml[] = str_replace($root . '/', '', $file->getPathname());
    }
}

foreach ($routes['routes'] as $endpoint => [$section, $command]) {
    if (in_array($endpoint, $rawHtmlEndpoints, true)) {
        continue;
    }
    $path = $root . '/game/include/Controller/Ajax/'
        . ($section === null ? '' : lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $section)))) . '/')
        . jsonDialogTransportCommandToFile($command);
    $controller = file_get_contents($path);
    if ($controller === false || preg_match($htmlResponsePattern, $controller) !== 1) {
        continue;
    }
    if (preg_match($rawHtmlPattern, $controller) === 1) {
        $unexpectedRawHtml[] = $endpoint . ' (' . str_replace($root . '/', '', $path) . ')';
    }
}

$legacyJsonHtml = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ajaxControllerDirectory)) as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $controller = file_get_contents($file->getPathname());
    if ($controller !== false && str_contains($controller, '_jsonHtml')) {
        $legacyJsonHtml[] = str_replace($root . '/', '', $file->getPathname());
    }
}

$unexpectedRawHtml = array_values(array_unique(array_merge($unexpectedRawHtml, $legacyJsonHtml)));
if ($unexpectedRawHtml !== []) {
    fwrite(
        STDERR,
        "Only profile-languages may opt out of default JSON dialog transport; _jsonHtml is retired:\n  "
        . implode("\n  ", $unexpectedRawHtml) . "\n"
    );
    exit(1);
}

echo "JSON dialog transport contract passed.\n";
