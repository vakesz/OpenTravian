<?php

declare(strict_types=1);

// There is one development switch, TRAVIAN_DEV_TOOLS (set only by compose.dev.yaml), and it only
// enables developer tooling. The former IS_DEV constant was hard-coded false and gated gameplay
// and error display; it is gone, and nothing may bring a second dev constant back.

$root = dirname(__DIR__, 2);
$offenders = [];
$iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    static fn (SplFileInfo $file): bool => !in_array($file->getFilename(), ['.git', 'node_modules', 'vendor', 'assets', 'runtime'], true)
));
foreach ($iterator as $file) {
    if ($file->isFile() && in_array($file->getExtension(), ['php', 'md', 'yaml', 'sh'], true) && $file->getPathname() !== __FILE__) {
        if (preg_match('/\bIS_DEV\b/', (string) file_get_contents($file->getPathname())) === 1) {
            $offenders[] = substr($file->getPathname(), strlen($root) + 1);
        }
    }
}
if ($offenders !== []) {
    fwrite(STDERR, 'IS_DEV must not return; found in: ' . implode(', ', $offenders) . PHP_EOL);
    exit(1);
}
$errors = (string) file_get_contents($root . '/game/include/Core/ErrorHandler.php');
if (!str_contains($errors, "ini_set('display_errors', '0')")) {
    fwrite(STDERR, "The error handler must never display errors to the browser.\n");
    exit(1);
}

echo "No dev constant contract passed.\n";
