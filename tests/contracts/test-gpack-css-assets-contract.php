<?php

declare(strict_types=1);

function failGpackCssAssetsContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

/**
 * True only when every path segment exists with exactly this spelling. realpath() and
 * is_file() are case-insensitive on macOS and on bind mounts of a macOS checkout, but the
 * production image is case-sensitive, so a mismatched spelling is a 404 there.
 */
function gpackPathExistsExactCase(string $root, string $relative): bool
{
    static $listings = [];
    $directory = $root;
    $segments = [];
    foreach (explode('/', $relative) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            if ($segments === []) {
                return false;
            }
            array_pop($segments);
            continue;
        }
        $segments[] = $segment;
    }
    $directory = $root;
    foreach ($segments as $index => $segment) {
        $listings[$directory] ??= scandir($directory) ?: [];
        if (!in_array($segment, $listings[$directory], true)) {
            return false;
        }
        $directory .= '/' . $segment;
        if ($index < count($segments) - 1 && !is_dir($directory)) {
            return false;
        }
    }
    return $segments !== [] && is_file($directory);
}

$mainPage = dirname(__DIR__, 2) . '/assets/gpack/travian/mainPage';
$directories = [
    $mainPage . '/css_ltr',
    $mainPage . '/css_rtl',
    $mainPage . '/lang',
];
$missing = [];
$references = 0;

foreach ($directories as $directory) {
    foreach (glob($directory . '/*.css') ?: [] as $stylesheet) {
        $source = file_get_contents($stylesheet);
        if ($source === false) {
            failGpackCssAssetsContract('GPack stylesheet must be readable: ' . $stylesheet);
        }
        preg_match_all('~url\(\s*["\']?([^"\')]+)["\']?\s*\)~i', $source, $matches);
        foreach ($matches[1] as $url) {
            $url = trim($url);
            if ($url === '' || str_starts_with($url, 'data:') || preg_match('~^(?:https?:|//|#)~', $url)) {
                continue;
            }
            $assetPath = preg_replace('~[?#].*$~', '', $url);
            if ($assetPath === '') {
                continue;
            }

            ++$references;
            $relative = substr(dirname($stylesheet), strlen($mainPage) + 1) . '/' . $assetPath;
            if (!gpackPathExistsExactCase($mainPage, $relative)) {
                $missing[] = str_replace($mainPage . '/', '', $stylesheet) . ' => ' . $url;
            }
        }
    }
}

if ($missing !== []) {
    failGpackCssAssetsContract(
        "Every relative GPack CSS asset must exist with exactly the spelling the stylesheet uses (case-sensitive).\n" . implode("\n", $missing)
    );
}

echo sprintf("GPack CSS asset contract passed (%d references).\n", $references);
