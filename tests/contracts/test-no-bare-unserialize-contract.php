<?php

declare(strict_types=1);

// Contract: stored payloads (reports, messages, cache values) never instantiate objects.
// Every unserialize() in game/ and services/ (outside vendored code) passes allowed_classes => false.

$root = dirname(__DIR__, 2);
$violations = [];
foreach (['game', 'services'] as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        $path = $file->getPathname();
        if (!str_ends_with($path, '.php') || str_contains($path, '/vendor/') || str_contains($path, '/Plugins/')) {
            continue;
        }
        $source = (string)file_get_contents($path);
        if (!str_contains($source, 'unserialize')) {
            continue;
        }
        $lines = explode("\n", $source);
        foreach (token_get_all($source) as $token) {
            if (!is_array($token) || $token[0] !== T_STRING || strtolower($token[1]) !== 'unserialize') {
                continue;
            }
            $line = $lines[$token[2] - 1] ?? '';
            if (preg_match('/function\s+unserialize|->unserialize|::unserialize/', $line) === 1) {
                continue;
            }
            if (!str_contains($line, "'allowed_classes' => false")) {
                $violations[] = substr($path, strlen($root) + 1) . ':' . $token[2];
            }
        }
    }
}
if ($violations !== []) {
    fwrite(STDERR, "unserialize() without allowed_classes => false:\n" . implode("\n", $violations) . "\n");
    exit(1);
}

echo "No bare unserialize contract passed.\n";
