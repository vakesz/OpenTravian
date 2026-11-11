<?php

declare(strict_types=1);

// An image that is byte-identical in both text directions lives once, in img_common, and nothing
// assembles an img_ltr or img_rtl path by hand: gpack_image_path() picks the directory.

$root = dirname(__DIR__, 2);
$main = $root . '/assets/gpack/travian/mainPage';

function expectImgCommon(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$files = static function (string $directory): array {
    $found = [];
    $base = strlen($directory) + 1;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile()) {
            $found[substr($file->getPathname(), $base)] = $file->getPathname();
        }
    }

    return $found;
};
$common = $files($main . '/img_common');
$ltr = $files($main . '/img_ltr');
$rtl = $files($main . '/img_rtl');
expectImgCommon(count($common) >= 200, 'img_common must hold the merged images.');
foreach (array_keys($common) as $relative) {
    expectImgCommon(!isset($ltr[$relative]) && !isset($rtl[$relative]), "$relative exists in img_common and in a direction directory.");
}
foreach ($ltr as $relative => $path) {
    expectImgCommon(!isset($rtl[$relative]) || sha1_file($path) !== sha1_file($rtl[$relative]), "$relative is identical in img_ltr and img_rtl and must live in img_common.");
}

// No hand-built direction directory outside the helper.
$offenders = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/game', FilesystemIterator::SKIP_DOTS)) as $file) {
    $path = $file->getPathname();
    if (!$file->isFile() || !str_ends_with($path, '.php') || str_contains($path, '/Translation/') || str_contains($path, '/Plugins/') || str_ends_with($path, '/functions.general.php')) {
        continue;
    }
    if (preg_match('~\'img_\'\s*\.|"img_"\s*\.|img_\{|img_\$|\? \'img_(?:rtl|ltr)\' : \'img_(?:ltr|rtl)\'~', (string) file_get_contents($path)) === 1) {
        $offenders[] = substr($path, strlen($root) + 1);
    }
}
expectImgCommon($offenders === [], 'Build image paths with gpack_image_path(), not by hand: ' . implode(', ', $offenders));
$functions = (string) file_get_contents($root . '/game/include/functions.general.php');
expectImgCommon(str_contains($functions, 'function gpack_image_path(') && str_contains($functions, "'img_common/'"), 'gpack_image_path() must send shared images to img_common.');

// The name map keeps one entry per merged file, with its old path and, where the file came from the reference CDN, the reference paths.
$map = json_decode((string) file_get_contents($main . '/NAME_MAP.json'), true, 512, JSON_THROW_ON_ERROR)['files'];
$merged = array_filter($map, static fn (array $entry): bool => $entry['category'] === 'gpack-image-merged');
expectImgCommon(count($merged) === count($common), 'NAME_MAP.json must have one gpack-image-merged entry per img_common file.');
foreach ($merged as $path => $entry) {
    expectImgCommon(is_file($root . '/' . $path) && isset($entry['old']), "NAME_MAP entry for $path is incomplete.");
}

echo "Gpack img_common contract passed.\n";
