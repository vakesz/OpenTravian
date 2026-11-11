#!/usr/bin/env php
<?php

declare(strict_types=1);

// Translation export and import for one locale (see docs/translations.md).
//
//   php tools/translations.php export <locale> [--format=csv|po] [--out=FILE] [--bom]
//   php tools/translations.php import <locale> <file> [--format=csv|po] [--apply] [--create-hand-file] [--strict]
//   php tools/translations.php status <locale>
//
// export lists every en-US string with the locale's current value and where it comes from. import
// reads an edited file back into the locale's hand catalogue (Translation/<locale>.php); it only
// plans unless --apply is given, never writes reference-sourced entries, and refuses en-US.

use Core\Locale;
use Game\Translation\Catalogue;
use Game\Translation\Formats;
use Game\Translation\Importer;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/game/deployment/include/env.php';
require dirname(__DIR__) . '/game/include/bootstrap.php';

$fail = static function (string $message, int $code = 2): never {
    fwrite(STDERR, $message . "\n");
    exit($code);
};
$option = static function (string $name) use ($argv): ?string {
    foreach ($argv as $argument) {
        if (str_starts_with($argument, '--' . $name . '=')) {
            return substr($argument, strlen($name) + 3);
        }
    }

    return null;
};
$flag = static fn (string $name): bool => in_array('--' . $name, $argv, true);
$short = static fn (string $text): string => mb_strlen($text) > 70 ? mb_substr($text, 0, 67) . '...' : $text;

$command = $argv[1] ?? '';
$locale = $argv[2] ?? '';
if (!in_array($command, ['export', 'import', 'status'], true) || !isset(Locale::manifest()[$locale])) {
    $fail("Usage: translations.php export <locale> [--format=csv|po] [--out=FILE] [--bom]\n"
        . "       translations.php import <locale> <file> [--format=csv|po] [--apply] [--create-hand-file] [--strict]\n"
        . "       translations.php status <locale>\n<locale> is an installed locale such as de-DE.");
}
$rows = Catalogue::rows($locale);

if ($command === 'status') {
    $counts = array_count_values(array_column($rows, 'source'));
    ksort($counts);
    fwrite(STDOUT, $locale . ': ' . count($rows) . " strings\n");
    foreach ($counts as $source => $count) {
        fwrite(STDOUT, sprintf("  %-9s %d\n", $source, $count));
    }
    exit(0);
}

if ($command === 'export') {
    $out = $option('out');
    $format = $option('format') ?? ($out !== null && str_ends_with($out, '.po') ? 'po' : 'csv');
    if (!in_array($format, ['csv', 'po'], true)) {
        $fail('The format is csv or po.');
    }
    $text = $format === 'po' ? Formats::po($rows, $locale) : Formats::csv($rows, $flag('bom'));
    if ($out === null) {
        fwrite(STDOUT, $text);
    } elseif (file_put_contents($out, $text) === false) {
        $fail('Cannot write ' . $out . '.');
    } else {
        fwrite(STDOUT, 'Wrote ' . count($rows) . ' strings to ' . $out . ".\n");
    }
    exit(0);
}

if ($locale === 'en-US') {
    $fail('en-US defines the keys every locale falls back to and cannot be imported; export it as a template instead.');
}
$file = $argv[3] ?? '';
if ($file === '' || str_starts_with($file, '--') || !is_file($file)) {
    $fail('Give the CSV or PO file to import.');
}
$format = $option('format') ?? (str_ends_with($file, '.po') ? 'po' : 'csv');
try {
    $edited = $format === 'po' ? Formats::parsePo((string) file_get_contents($file)) : Formats::parseCsv((string) file_get_contents($file));
} catch (\RuntimeException $exception) {
    $fail($exception->getMessage());
}
$plan = Importer::plan($edited, $rows);
foreach ($plan['accepted'] as [$path, $value]) {
    fwrite(STDOUT, 'ACCEPT   ' . Catalogue::encodePath($path) . '  ' . $short($value) . "\n");
}
foreach ($plan['conflicts'] as [$path, $reason]) {
    fwrite(STDOUT, 'SKIPPED  ' . $path . '  ' . $reason . "\n");
}
foreach ($plan['rejected'] as [$path, $reason]) {
    fwrite(STDOUT, 'REJECTED ' . $path . '  ' . $reason . "\n");
}
fwrite(STDOUT, sprintf("%d to write, %d skipped (reference-sourced), %d rejected, %d unchanged%s.\n", count($plan['accepted']), count($plan['conflicts']), count($plan['rejected']), $plan['unchanged'], $plan['fuzzy'] > 0 ? ', ' . $plan['fuzzy'] . ' fuzzy ignored' : ''));
if ($plan['accepted'] !== [] && $flag('apply')) {
    try {
        Importer::apply(Catalogue::handFile($locale), $plan['accepted'], $flag('create-hand-file'));
    } catch (\RuntimeException $exception) {
        $fail($exception->getMessage());
    }
    fwrite(STDOUT, 'Wrote ' . count($plan['accepted']) . ' strings to ' . basename(Catalogue::handFile($locale)) . ".\n");
} elseif ($plan['accepted'] !== []) {
    fwrite(STDOUT, "Nothing written: add --apply to write these.\n");
}
exit($flag('strict') && ($plan['conflicts'] !== [] || $plan['rejected'] !== []) ? 1 : 0);
