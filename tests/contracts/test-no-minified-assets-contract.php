<?php

declare(strict_types=1);

/**
 * The repository stores readable assets only; the production web image minifies
 * them at build time (docker/assets). No first-party or vendored CSS/JS/JSON/SVG may be
 * committed minified, and no source maps may be committed.
 *
 * Heuristics (bytes per line):
 *   css/js  larger than 4 KiB with an average line longer than 200 bytes
 *   json    larger than 4 KiB with fewer than ten lines. Long string values legitimately
 *           make pretty-printed lines long, so the structure is checked, not line length (package-lock.json is
 *           tool-generated and exempt)
 *   svg     larger than 500 bytes with fewer than five newlines
 *   *.map   never
 *   names   no *.min.* and no *_compressed* file names (a stored file is readable, so its name must not claim otherwise)
 */
function failNoMinifiedAssets(string $message): void
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

$root = dirname(__DIR__, 2);
$skipDirs = ['.git', 'node_modules', 'vendor', 'runtime'];
$offenders = [];
$checked = 0;

foreach (['assets', 'game', 'services', 'docker', 'dev', 'tools', 'config', 'tests'] as $top) {
    $base = $root . '/' . $top;
    if (!is_dir($base)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
            static fn (SplFileInfo $file): bool => !($file->isDir() && in_array($file->getFilename(), $skipDirs, true))
        )
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->isLink()) {
            continue;
        }
        $path = $file->getPathname();
        $relative = substr($path, strlen($root) + 1);
        $extension = strtolower($file->getExtension());
        if (preg_match('/\.min\.|_compressed/i', $file->getFilename()) === 1) {
            $offenders[] = "{$relative}: file name claims minified/compressed content";
            continue;
        }
        if ($extension === 'map') {
            $offenders[] = "{$relative}: source maps must not be committed";
            continue;
        }
        if (!in_array($extension, ['css', 'js', 'json', 'svg'], true) || $file->getFilename() === 'package-lock.json') {
            continue;
        }
        $size = $file->getSize();
        $contents = (string) file_get_contents($path);
        $newlines = substr_count($contents, "\n");
        $checked++;
        if ($extension === 'json') {
            if ($size > 4096 && $newlines < 10) {
                $offenders[] = sprintf('%s: %d bytes in %d line(s)', $relative, $size, $newlines + 1);
            }
            continue;
        }
        if ($extension === 'svg') {
            if ($size > 500 && $newlines < 5) {
                $offenders[] = sprintf('%s: %d bytes in %d line(s)', $relative, $size, $newlines + 1);
            }
            continue;
        }
        if ($size > 4096 && $size / ($newlines + 1) > 200) {
            $offenders[] = sprintf('%s: %d bytes, average line %d bytes', $relative, $size, intdiv($size, $newlines + 1));
        }
    }
}

if ($offenders !== []) {
    failNoMinifiedAssets(
        "Minified assets must not be committed (store the readable source; the image build minifies):\n  "
        . implode("\n  ", $offenders)
    );
}

echo sprintf("No minified assets contract passed (%d css/js/json/svg files).\n", $checked);
