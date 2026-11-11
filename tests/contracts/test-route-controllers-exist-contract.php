<?php

declare(strict_types=1);

// Controllers are looked up by class name at request time (the route table in mainInclude.php, the
// literal dispatch() calls, the autoloader's path for a class), so a class that was renamed or moved
// without every reference only fails when somebody opens that route. This pins the lookups statically.

$root = dirname(__DIR__, 2);
$controllers = $root . '/game/include/Controller';

function expectRouteController(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$source = (string) file_get_contents($root . '/game/include/mainInclude.php');
$start = strpos($source, '$pages = [');
$end = strpos($source, "\n];", (int) $start);
expectRouteController($start !== false && $end !== false, 'The route table in mainInclude.php was not found.');
preg_match_all("~=>\s*(?:[^'\n]*\?\s*)?'([A-Z][A-Za-z0-9_]*)'(?:\s*:\s*'([A-Z][A-Za-z0-9_]*)')?~", substr($source, (int) $start, (int) $end - (int) $start), $matches);
$routed = array_values(array_unique(array_filter(array_merge($matches[1], $matches[2]))));
expectRouteController(count($routed) >= 40, 'The route table must name its controllers (found ' . count($routed) . ').');
// admin.php is answered by the admin panel's own dispatcher before the route table is consulted.
foreach (array_diff($routed, ['AdminCtrl']) as $name) {
    expectRouteController(is_file("$controllers/$name.php"), "Route table names $name, but Controller/$name.php does not exist.");
}

$php = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/game', FilesystemIterator::SKIP_DOTS));
foreach ($php as $file) {
    $path = $file->getPathname();
    if (!$file->isFile() || !str_ends_with($path, '.php') || str_contains($path, '/Plugins/') || str_contains($path, '/Translation/')) {
        continue;
    }
    $code = (string) file_get_contents($path);
    // dispatch("X") and dispatch('X') name a controller class below Controller\.
    if (preg_match_all('~->dispatch\(\s*[\'"]([A-Za-z0-9_\\\\]+)[\'"]~', $code, $dispatched) > 0) {
        foreach ($dispatched[1] as $name) {
            expectRouteController(is_file($controllers . '/' . ucfirst(str_replace('\\', '/', $name)) . '.php'), substr($path, strlen($root) + 1) . " dispatches $name, which is not a controller.");
        }
    }
    // Every imported Controller\ or Model\ class has its file.
    if (preg_match_all('~^use (Controller|Model)\\\\([A-Za-z0-9_\\\\]+)(?: as \w+)?;~m', $code, $imports, PREG_SET_ORDER) > 0) {
        foreach ($imports as [, $namespace, $class]) {
            $dir = $namespace === 'Controller' ? $controllers : $root . '/game/include/Model';
            expectRouteController(is_file($dir . '/' . str_replace('\\', '/', $class) . '.php'), substr($path, strlen($root) + 1) . " imports $namespace\\$class, which does not exist.");
        }
    }
}

// Each controller file below Controller/ (the ajax commands excepted) declares the class its file is named after.
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($controllers, FilesystemIterator::SKIP_DOTS)) as $file) {
    $path = $file->getPathname();
    if (!$file->isFile() || !str_ends_with($path, '.php') || str_contains($path, '/Controller/Ajax/')) {
        continue;
    }
    $class = basename($path, '.php');
    expectRouteController(preg_match('~^(?:abstract |final )?(?:class|interface|trait) ' . preg_quote($class, '~') . '\b~m', (string) file_get_contents($path)) === 1, substr($path, strlen($root) + 1) . " must declare $class.");
}

echo "Route controllers contract passed.\n";
