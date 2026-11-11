<?php

declare(strict_types=1);

// Against the real catalogues: every installed locale exports and re-imports without a single
// change, the hand-file reader agrees with PHP's own evaluation of the hand catalogues, and the
// tool refuses what it must.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Translation tool runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use Core\Locale;
use Game\Translation\Catalogue;
use Game\Translation\Formats;
use Game\Translation\HandFile;
use Game\Translation\Importer;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$english = Catalogue::leaves(Catalogue::load(Catalogue::handFile('en-US')));

foreach (array_keys(Locale::manifest()) as $locale) {
    $rows = Catalogue::rows($locale);
    if (count($rows) !== count($english)) {
        $fail("$locale: the export must have one row per en-US string (" . count($rows) . ' vs ' . count($english) . ').');
    }
    foreach (['csv' => Formats::parseCsv(Formats::csv($rows, false)), 'po' => Formats::parsePo(Formats::po($rows, $locale))] as $format => $parsed) {
        $plan = Importer::plan($parsed, $rows);
        if ($plan['accepted'] !== [] || $plan['rejected'] !== [] || $plan['conflicts'] !== [] || $plan['unchanged'] !== count($rows)) {
            $fail("$locale: re-importing the unchanged $format export must change nothing: " . json_encode(['accepted' => count($plan['accepted']), 'rejected' => array_slice($plan['rejected'], 0, 2), 'conflicts' => count($plan['conflicts']), 'unchanged' => $plan['unchanged']]));
        }
    }
}

// The tokenizer reader must read exactly what PHP evaluates for every plain literal.
foreach (['en-US', 'ar-AE', 'el-GR', 'fa-IR'] as $locale) {
    $literals = HandFile::literals((string) file_get_contents(Catalogue::handFile($locale)));
    $rows = Catalogue::rows($locale);
    $hand = 0;
    foreach ($rows as $row) {
        if ($row['source'] === 'hand') {
            ++$hand;
            if (($literals[Catalogue::encodePath($row['path'])]['value'] ?? null) !== $row['value']) {
                $fail("$locale: the reader disagrees with PHP about " . Catalogue::encodePath($row['path']));
            }
        }
    }
    if ($hand < 100) {
        $fail("$locale: expected many hand-written strings, found $hand.");
    }
}

// The command line.
$run = static function (string $arguments): array {
    $output = [];
    exec(escapeshellarg(PHP_BINARY) . ' /travian/tools/translations.php ' . $arguments . ' 2>&1', $output, $code);

    return [$code, implode("\n", $output)];
};
$bare = null;
foreach (array_keys(Locale::manifest()) as $locale) {
    if ($locale !== 'en-US' && !is_file(Catalogue::handFile($locale))) {
        $bare = $locale;
        break;
    }
}
if ($bare === null) {
    $fail('Expected an installed locale without a hand catalogue.');
}
[$code, $out] = $run('status ' . $bare);
if ($code !== 0 || !str_contains($out, 'strings') || !str_contains($out, 'bundle')) {
    $fail("status must count strings by source:\n$out");
}
$export = sys_get_temp_dir() . '/translations-' . getmypid() . '.csv';
[$code] = $run('export ' . $bare . ' --out=' . escapeshellarg($export));
if ($code !== 0) {
    $fail('export must succeed.');
}
[$code, $out] = $run('import en-US ' . escapeshellarg($export));
if ($code !== 2 || !str_contains($out, 'en-US')) {
    $fail("import must refuse en-US:\n$out");
}
$rows = Catalogue::rows($bare);
$edited = $rows;
$picked = ['missing' => null, 'placeholder' => null, 'bundle' => null];
foreach ($edited as $index => $row) {
    if ($row['source'] === 'missing' && $picked['missing'] === null && !preg_match('/[%<&{\[]/', $row['english'])) {
        $picked['missing'] = $index;
    } elseif ($row['source'] === 'missing' && $picked['placeholder'] === null && str_contains($row['english'], '%s')) {
        $picked['placeholder'] = $index;
    } elseif ($row['source'] === 'bundle' && $picked['bundle'] === null) {
        $picked['bundle'] = $index;
    }
}
if (in_array(null, $picked, true)) {
    $fail('The locale ' . $bare . ' needs a missing string, one with %s and a bundle one to test with: ' . json_encode($picked));
}
$edited[$picked['missing']]['value'] = 'TEST ' . $edited[$picked['missing']]['english'];
$edited[$picked['placeholder']]['value'] = 'TEST without a placeholder';
$edited[$picked['bundle']]['value'] = 'TEST ' . $edited[$picked['bundle']]['value'];
file_put_contents($export, Formats::csv($edited, true));
[$code, $out] = $run('import ' . $bare . ' ' . escapeshellarg($export));
if ($code !== 0 || !str_contains($out, 'ACCEPT') || !str_contains($out, 'SKIPPED') || !str_contains($out, 'REJECTED') || !str_contains($out, '1 to write, 1 skipped (reference-sourced), 1 rejected') || !str_contains($out, 'Nothing written')) {
    $fail("A dry run must plan one write, skip the reference row, reject the placeholder row and write nothing:\n$out");
}
[$code] = $run('import ' . $bare . ' ' . escapeshellarg($export) . ' --strict');
if ($code !== 1) {
    $fail('--strict must exit 1 when rows were skipped or rejected.');
}
[$code, $out] = $run('import ' . $bare . ' ' . escapeshellarg($export) . ' --apply');
if ($code !== 2 || !str_contains($out, '--create-hand-file') || is_file(Catalogue::handFile($bare))) {
    $fail("--apply must not create a hand catalogue without --create-hand-file:\n$out");
}
@unlink($export);

echo "Translation tool runtime passed.\n";
