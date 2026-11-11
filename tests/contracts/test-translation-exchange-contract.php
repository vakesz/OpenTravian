<?php

declare(strict_types=1);

use Game\Translation\Catalogue;
use Game\Translation\Formats;
use Game\Translation\HandFile;
use Game\Translation\Placeholders;

$root = dirname(__DIR__, 2);
foreach (['Placeholders', 'Catalogue', 'HandFile', 'Formats'] as $class) {
    require_once $root . '/game/include/Game/Translation/' . $class . '.php';
}

function expectTranslation(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

// One placeholder pattern, in one file, read by the importer.
expectTranslation(Placeholders::pattern() === '%\d*\$?[sd]|\[[A-Z_]+\]|\{[a-zA-Z_]+\}', 'The shared pattern is the established placeholder pattern.');

// Placeholder validation.
foreach ([
    ['Hello %s, you have %d items', 'Hallo %s, du hast %d Dinge', null],
    ['Hello %s, you have %d items', 'Hallo %s', 'expected'],
    ['Hello %s', 'Hallo %s und %s', 'expected'],
    ['Hello %s, %d', 'Anzahl %2$d, Name %1$s', null],
    ['Hello %s, %d', 'Anzahl %1$d, Name %1$s', 'positional'],
    ['Hello %s, %d', 'Anzahl %d, Name %1$s', 'positional'],
    ['[NUMBER] hours', '[NUMBER] Stunden', null],
    ['[NUMBER] hours', '[ZAHL] Stunden', 'expected'],
    ['Hi {name}', 'Hallo {name}', null],
    ['Hi {name}', 'Hallo', 'expected'],
    ['No placeholders', 'Keine', null],
    ['No placeholders', 'Keine %s', 'expected'],
] as [$english, $translation, $expected]) {
    $result = Placeholders::mismatch($english, $translation);
    expectTranslation($expected === null ? $result === null : ($result !== null && str_contains($result, $expected)), "Placeholders '$english' -> '$translation' gave " . var_export($result, true));
}

// Paths and leaves.
expectTranslation(Catalogue::decodePath(Catalogue::encodePath(['A', 'b c.d', 3, 'ü'])) === ['A', 'b c.d', 3, 'ü'], 'A path must survive JSON encoding.');
expectTranslation(Catalogue::decodePath('"x"') === null && Catalogue::decodePath('[]') === null && Catalogue::decodePath('[1.5]') === null && Catalogue::decodePath('not json') === null, 'Anything but a list of keys is not a path.');
$leaves = Catalogue::leaves(['A' => ['k' => 'v', 'rows' => ['one', 'two'], 'deep' => ['x' => ['y' => 'z']]], 'B' => 'top']);
$byPath = [];
foreach ($leaves as $leaf) {
    $byPath[Catalogue::encodePath($leaf['path'])] = $leaf['list'];
}
expectTranslation($byPath === ['["A","k"]' => false, '["A","rows",0]' => true, '["A","rows",1]' => true, '["A","deep","x","y"]' => false, '["B"]' => false], 'Leaves must be found at every depth and entries of a list marked.');

// Where generated entries came from.
$sources = Catalogue::generatedSources(<<<'GENERATED'
<?php
// Imported translation overlay; maintained by hand.
return [
    'Alliance' => [
        'Actions' => 'Aktionen', // bundle:admin.aktionen,allianz.optionsActions
        'It\'s' => 'Es ist مرحبا', // page:/respawn
        'numbers' => [
            '1' => [
                'deep' => 'tief', // bundle:x.y
            ],
        ],
    ],
    'Top' => [
        'Other' => 'Anderes', // page:/map
    ],
];
GENERATED);
expectTranslation($sources['["Alliance","Actions"]'] === ['kind' => 'bundle', 'reference' => 'admin.aktionen,allianz.optionsActions'], 'A bundle source must be read from the line comment.');
expectTranslation($sources['["Alliance","It\'s"]']['kind'] === 'capture' && $sources['["Alliance","It\'s"]']['reference'] === '/respawn', 'A page source is a capture, and quoted keys are unescaped.');
expectTranslation(($sources['["Alliance","numbers",1,"deep"]']['kind'] ?? '') === 'bundle' && ($sources['["Top","Other"]']['reference'] ?? '') === '/map', 'Nested sections and numeric keys must be tracked.');

// CSV and PO round trips (the Arabic text holds the byte 0x85, which a non-UTF-8 \R would split lines on).
$rows = [
    ['path' => ['Alliance', 'Actions'], 'key' => 'Actions', 'english' => 'Actions, "all" of them', 'value' => "Aktionen,\n\"alle\" ü€ مرحبا", 'source' => 'bundle', 'reference' => 'admin.aktionen'],
    ['path' => ['Alliance', 'rows', 0], 'key' => 'rows / 0', 'english' => 'Hello %s \\ back\\slash', 'value' => '', 'source' => 'missing', 'reference' => ''],
    ['path' => ['T', "it's"], 'key' => "it's", 'english' => "tab\there", 'value' => "tab\tda", 'source' => 'hand', 'reference' => ''],
];
foreach ([false, true] as $bom) {
    $decoded = Formats::parseCsv(Formats::csv($rows, $bom));
    expectTranslation(count($decoded) === 3 && $decoded[0]['path'] === Catalogue::encodePath($rows[0]['path']) && $decoded[0]['english'] === $rows[0]['english'] && $decoded[0]['value'] === $rows[0]['value'] && $decoded[1]['value'] === '' && $decoded[2]['value'] === "tab\tda", 'CSV must round trip quotes, commas, newlines, tabs and UTF-8 (BOM ' . var_export($bom, true) . ').');
}
expectTranslation(str_starts_with(Formats::csv($rows, true), "\xEF\xBB\xBF") && !str_starts_with(Formats::csv($rows, false), "\xEF"), 'The BOM is only written on request.');
$threw = false;
try {
    Formats::parseCsv("a,b\n1,2\n");
} catch (RuntimeException $exception) {
    $threw = str_contains($exception->getMessage(), 'path');
}
expectTranslation($threw, 'A CSV without the path column is refused.');
$po = Formats::po($rows, 'de-DE');
expectTranslation(str_contains($po, '#, php-format') && str_contains($po, '#. source: bundle') && str_contains($po, '#. reference: admin.aktionen') && str_contains($po, '"Language: de-DE\n"'), 'The PO carries flags, source comments and the language header.');
$decoded = Formats::parsePo($po);
expectTranslation(count($decoded) === 3 && $decoded[0]['value'] === $rows[0]['value'] && $decoded[0]['english'] === $rows[0]['english'] && $decoded[1]['english'] === $rows[1]['english'] && $decoded[2]['value'] === "tab\tda" && !$decoded[0]['fuzzy'], 'PO must round trip.');
$wrapped = "msgid \"\"\nmsgstr \"\"\n\"Language: de\\n\"\n\n#, fuzzy\nmsgctxt \"[\\\"A\\\",\\\"b\\\"]\"\nmsgid \"Hello \"\n\"world\"\nmsgstr \"Hallo \"\n\"Welt\"\n\n#~ msgid \"obsolete\"\n";
$decoded = Formats::parsePo($wrapped);
expectTranslation(count($decoded) === 1 && $decoded[0]['english'] === 'Hello world' && $decoded[0]['value'] === 'Hallo Welt' && $decoded[0]['fuzzy'] === true && $decoded[0]['path'] === '["A","b"]', 'An editor that wraps long strings and marks entries fuzzy must be understood.');

// Hand files: read plain literals, leave everything else alone, rewrite safely.
$hand = <<<'HAND'
<?php
use Core\Config;
global $Definition;
// a comment that must survive
$Definition['A']['single'] = 'It\'s here';
$Definition['A']['double'] = "Say \"hi\"\n";
$Definition['A']['concat'] = 'one' . ' two';
$Definition['A']['call'] = sprintf('%s', strtoupper('x'));
$x = 1;
$Definition['A']['interpolated'] = "Value $x";
$Definition['A']['append'] = 'one';
$Definition['A']['append'] .= ' two';
$Definition['E']['k'] = 'old';
$Definition['E'] = ['k2' => 'new'];
$Definition['B'] = [
    "zoomIn" => "Zoom in",
    'rows' => ['first', 'second'],
    'nested' => [
        'deep' => 'Deep', // trailing comment
        'calc' => 'a' . 'b',
    ],
    7 => 'seven',
];
$Definition['A']['single'] = 'Last one wins';

return $Definition;
HAND;
$literals = HandFile::literals($hand);
$values = array_map(static fn (array $literal): string => $literal['value'], $literals);
expectTranslation($values == [
    '["E","k2"]' => 'new', '["A","single"]' => 'Last one wins', '["A","double"]' => "Say \"hi\"\n", '["B","zoomIn"]' => 'Zoom in', '["B","rows",0]' => 'first', '["B","rows",1]' => 'second',
    '["B","nested","deep"]' => 'Deep', '["B",7]' => 'seven',
], 'Only plain literals are read (concatenation, calls and interpolation are computed): ' . json_encode($values));
$changed = HandFile::apply($hand, [[['B', 'nested', 'deep'], "Tief 'x' \\ ü"], [['A', 'single'], 'Neu']]);
expectTranslation(str_contains($changed, "'deep' => 'Tief \\'x\\' \\\\ ü', // trailing comment") && str_contains($changed, '// a comment that must survive') && str_contains($changed, "\$Definition['A']['concat'] = 'one' . ' two';"), 'A literal is replaced in place and everything around it is untouched.');
expectTranslation(substr_count($changed, "'It\\'s here'") === 1, 'A shadowed earlier assignment is left as it was; only the effective (last) literal changes.');
$added = HandFile::apply($changed, [[['C', 'new key', 'x'], 'Neu'], [['A', 'fresh'], "Zeile\nzwei"]]);
expectTranslation(substr_count($added, '// --- Imported by tools/translations.php') === 1 && str_contains($added, "\$Definition['C']['new key']['x'] = 'Neu';") && strpos($added, '// --- Imported') < strpos($added, 'return $Definition;'), 'New keys go into one managed block before the return.');
$again = HandFile::apply($added, [[['D', 'more'], 'Mehr']]);
expectTranslation(substr_count($again, '// --- Imported by tools/translations.php') === 1 && strpos($again, "\$Definition['D']['more']") < strpos($again, '// --- End of imported translations ---'), 'A second import extends the same block.');
$file = tempnam(sys_get_temp_dir(), 'hand');
file_put_contents($file, $again);
$tree = Catalogue::load($file);
unlink($file);
expectTranslation($tree['B']['nested']['deep'] === "Tief 'x' \\ ü" && $tree['A']['single'] === 'Neu' && $tree['A']['fresh'] === "Zeile\nzwei" && $tree['C']['new key']['x'] === 'Neu' && $tree['D']['more'] === 'Mehr' && $tree['B']['rows'] === ['first', 'second'] && $tree['B'][7] === 'seven', 'The rewritten file must evaluate to the expected catalogue.');
$created = HandFile::create([[['A', 'k'], 'v'], [['A', 'it\'s'], "q'q"]]);
$file = tempnam(sys_get_temp_dir(), 'hand');
file_put_contents($file, $created);
$tree = Catalogue::load($file);
unlink($file);
expectTranslation($tree === ['A' => ['k' => 'v', 'it\'s' => "q'q"]], 'A new hand file holds exactly the entries given.');
$threw = false;
try {
    HandFile::apply("<?php\n\$Definition['A']['k'] = 'v';\n", [[['B', 'x'], 'y']]);
} catch (RuntimeException $exception) {
    $threw = str_contains($exception->getMessage(), 'return');
}
expectTranslation($threw, 'A hand file without its final return is refused rather than guessed at.');

echo "Translation exchange contract passed.\n";
