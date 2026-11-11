<?php

declare(strict_types=1);

/**
 * Contract: redirects and form actions survive nested clean routes.
 *
 * <base href="/"> resolves relative links and form actions, but never HTTP
 * Location headers. A relative redirect("dorf1.php") issued from /hero/attributes
 * lands on /hero/dorf1.php, so every player-facing redirect must be root-absolute
 * (the helpers also normalize any stray relative target). The public login form
 * also posts to the root, never a nested route.
 */

$root = dirname(__DIR__, 2);
$failures = [];

require_once $root . '/game/include/functions.general.php';

foreach ([
    ['dorf1.php', '/dorf1.php'],
    ['login.php?registered=true', '/login.php?registered=true'],
    ['/hero/attributes', '/hero/attributes'],
    ['?refresh', '?refresh'],
    ['#top', '#top'],
    ['https://example.test/x', 'https://example.test/x'],
] as [$in, $out]) {
    if (normalizeRedirectTarget($in) !== $out) {
        $failures[] = "normalizeRedirectTarget('{$in}') must be '{$out}'";
    }
}

foreach ([
    'game/include/Core/Helper/WebService.php',
    'game/include/Controller/AbstractCtrl.php',
    'game/include/functions.general.php',
] as $helper) {
    if (!str_contains((string)file_get_contents($root . '/' . $helper), 'normalizeRedirectTarget($url)')) {
        $failures[] = "{$helper} must send Location through normalizeRedirectTarget()";
    }
}

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/game/include', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    $path = $file->getPathname();
    $rel = substr($path, strlen($root) + 1);
    if (!$file->isFile() || !str_ends_with($path, '.php') || str_contains($path, '/admin/') || str_contains($path, '/vendor/')) {
        continue;
    }
    $src = (string)file_get_contents($path);
    if (preg_match_all('/redirect\(\s*[\'"]([a-z][a-z0-9_]*\.php[^\'"]*)[\'"]/i', $src, $m)) {
        foreach ($m[1] as $target) {
            $failures[] = "{$rel}: relative redirect target '{$target}' (use /{$target})";
        }
    }
    if (preg_match('/Location:\s*[\'"]?[a-z]/i', $src) && !str_contains($src, 'normalizeRedirectTarget')) {
        $failures[] = "{$rel}: relative Location header";
    }
    if (!str_contains($rel, 'system/install') && !str_contains($rel, 'Templates/admin/')
        && preg_match_all('/action=["\']([a-z][a-z0-9_]*\.php[^"\']*)["\']/i', $src, $m)) {
        foreach ($m[1] as $action) {
            $failures[] = "{$rel}: relative form action '{$action}'";
        }
    }
}

$login = (string)file_get_contents($root . '/game/include/resources/Templates/system/login.php');
if (!str_contains($login, "'/admin.php' : \$publicLoginUrl;")
    || !str_contains($login, "\$vars['publicLoginUrl'] : '/';")) {
    $failures[] = 'The public login form action must remain root-absolute.';
}
$loginCtrl = (string)file_get_contents($root . '/game/include/Controller/LoginCtrl.php');
if (str_contains($loginCtrl, 'basename($path)') || !str_contains($loginCtrl, '$target = $path .')) {
    $failures[] = 'redirectAfterLogin must preserve the full same-origin return path, not its basename.';
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "Nested route redirect contract passed.\n";
