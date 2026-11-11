<?php

declare(strict_types=1);

// Contract: the one transparent pixel is served as /img/spacer.gif. The reference's
// /img/x.gif name must not be requested by local code, templates, scripts or tests
// (it returns 404 here). Recorded reference captures keep the name as data.

$root = dirname(__DIR__, 2);
$needle = '/img/x' . '.gif';
$skip = ['/assets/i18n/reference/', '/.git/', '/node_modules/', '/vendor/'];
$allowedFiles = ['/assets/gpack/travian/mainPage/NAME_MAP.json', '/assets/gpack/travian/mainPage/ASSET_PROVENANCE.md', '/tests/contracts/test-no-transparent-pixel-alias-contract.php'];
$hits = [];

$iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    static function (SplFileInfo $file) use ($root, $skip): bool {
        $relative = substr($file->getPathname(), strlen($root)) . ($file->isDir() ? '/' : '');
        foreach ($skip as $part) {
            if (str_starts_with($relative, $part)) {
                return false;
            }
        }

        return true;
    }
));
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getSize() > 5_000_000) {
        continue;
    }
    $relative = substr($file->getPathname(), strlen($root));
    if (in_array($relative, $allowedFiles, true) || !preg_match('/\.(php|js|css|html|json|py|sh|md)$/', $relative)) {
        continue;
    }
    if (str_contains((string)file_get_contents($file->getPathname()), $needle)) {
        $hits[] = $relative;
    }
}

if ($hits !== []) {
    fwrite(STDERR, "Use /img/spacer.gif, not the missing x.gif, in: " . implode(', ', $hits) . PHP_EOL);
    exit(1);
}

echo "No transparent pixel alias contract passed.\n";
