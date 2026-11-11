<?php

declare(strict_types=1);

use Game\Translation\Catalogue;
use Game\Translation\Importer;

$root = dirname(__DIR__, 2);
foreach (['Placeholders', 'Catalogue', 'HandFile', 'Formats', 'Importer'] as $class) {
    require_once $root . '/game/include/Game/Translation/' . $class . '.php';
}

function expectTranslationImport(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$row = static fn (array $path, string $english, string $value, string $source, string $reference = ''): array => ['path' => $path, 'key' => '', 'english' => $english, 'value' => $value, 'source' => $source, 'reference' => $reference];
$current = [
    $row(['A', 'missing'], 'Hello %s', '', 'missing'),
    $row(['A', 'hand'], 'Open', 'Offen', 'hand'),
    $row(['A', 'generated'], 'Close', 'Schließen', 'bundle', 'allgemein.close'),
    $row(['A', 'captured'], 'Send', 'Senden', 'capture', '/respawn'),
    $row(['A', 'computed'], 'Total', 'Summe 10', 'computed'),
    $row(['A', 'rows', 0], 'First', 'Erste', 'list'),
    $row(['A', 'plain'], 'Plain', '', 'missing'),
];
$edit = static fn (array $path, string $english, string $value, bool $fuzzy = false): array => ['path' => Catalogue::encodePath($path), 'english' => $english, 'value' => $value, 'fuzzy' => $fuzzy];
$plan = Importer::plan([
    $edit(['A', 'missing'], 'Hello %s', 'Hallo %s'),
    $edit(['A', 'hand'], 'Open', 'Geöffnet'),
    $edit(['A', 'generated'], 'Close', 'Zumachen'),
    $edit(['A', 'captured'], 'Send', 'Schicken'),
    $edit(['A', 'computed'], 'Total', 'Gesamt'),
    $edit(['A', 'rows', 0], 'First', 'Zuerst'),
    $edit(['A', 'plain'], 'Plain', ''),
    $edit(['A', 'plain'], 'Plain', 'Schlicht', true),
    $edit(['X', 'unknown'], 'Nope', 'Nein'),
    ['path' => 'not json', 'english' => 'x', 'value' => 'y', 'fuzzy' => false],
    $edit(['A', 'hand'], 'Open (old text)', 'Auf'),
], $current);
expectTranslationImport(array_map(static fn (array $change): string => Catalogue::encodePath($change[0]), $plan['accepted']) === ['["A","missing"]', '["A","hand"]'], 'Only missing and hand rows with a changed, valid value are accepted: ' . json_encode($plan['accepted']));
expectTranslationImport(array_column($plan['conflicts'], 0) === ['["A","generated"]', '["A","captured"]'] && str_contains($plan['conflicts'][0][1], 'allgemein.close') && str_contains($plan['conflicts'][1][1], '/respawn'), 'Edits to reference-sourced rows are skipped with their source named.');
$reasons = [];
foreach ($plan['rejected'] as [$path, $reason]) {
    $reasons[$path] = $reason;
}
expectTranslationImport(str_contains($reasons['["A","computed"]'], 'computed') && str_contains($reasons['["A","rows",0]'], 'list') && str_contains($reasons['["X","unknown"]'], 'en-US') && str_contains($reasons['not json'], 'path') && str_contains($reasons['["A","hand"]'], 'English text changed'), 'Computed, list, unknown, malformed and stale rows are rejected with a reason: ' . json_encode($reasons));
expectTranslationImport($plan['unchanged'] === 1 && $plan['fuzzy'] === 1, 'An empty value is unchanged and a fuzzy entry is ignored.');
$bad = Importer::plan([$edit(['A', 'missing'], 'Hello %s', 'Hallo')], $current);
expectTranslationImport($bad['accepted'] === [] && str_contains($bad['rejected'][0][1], 'expected the placeholders'), 'A translation that loses a placeholder is rejected.');
expectTranslationImport(Importer::plan([$edit(['A', 'hand'], 'Open', 'Offen')], $current)['unchanged'] === 1, 'An unchanged value changes nothing.');

// Writing: atomic, mode kept, created only on request.
$directory = sys_get_temp_dir() . '/translation-import-' . getmypid();
mkdir($directory);
try {
    $file = $directory . '/xx-XX.php';
    $threw = false;
    try {
        Importer::apply($file, [[['A', 'k'], 'v']], false);
    } catch (RuntimeException $exception) {
        $threw = str_contains($exception->getMessage(), '--create-hand-file') && !file_exists($file);
    }
    expectTranslationImport($threw, 'A missing hand catalogue is only created on request.');
    Importer::apply($file, [[['A', 'k'], 'v']], true);
    expectTranslationImport(Catalogue::load($file) === ['A' => ['k' => 'v']] && glob($directory . '/*.import') === [] && (fileperms($file) & 0777) === 0644, 'A created catalogue holds the entries, with no temporary file.');
    chmod($file, 0640);
    Importer::apply($file, [[['A', 'k'], 'w'], [['B', 'n'], 'x']], false);
    expectTranslationImport(Catalogue::load($file) === ['A' => ['k' => 'w'], 'B' => ['n' => 'x']] && (fileperms($file) & 0777) === 0640, 'An existing catalogue is rewritten with its mode kept.');
    $before = (string) file_get_contents($file);
    $threw = false;
    try {
        Importer::apply($directory . '/gone/xx-YY.php', [[['A', 'k'], 'v']], true);
    } catch (RuntimeException $exception) {
        $threw = str_contains($exception->getMessage(), 'writable');
    }
    expectTranslationImport($threw && (string) file_get_contents($file) === $before, 'An unwritable location is reported, not guessed around.');
} finally {
    foreach (glob($directory . '/*') ?: [] as $leftover) {
        @unlink($leftover);
    }
    @rmdir($directory);
}

// The CLI refuses en-US and keeps the safety rails.
$tool = (string) file_get_contents($root . '/tools/translations.php');
expectTranslationImport(str_contains($tool, "\$locale === 'en-US'") && str_contains($tool, "flag('apply')") && str_contains($tool, "flag('create-hand-file')") && str_contains($tool, "flag('strict')"), 'The tool must refuse en-US, write only with --apply, create only with --create-hand-file and honour --strict.');

echo "Translation import contract passed.\n";
