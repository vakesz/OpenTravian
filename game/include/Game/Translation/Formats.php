<?php

declare(strict_types=1);

namespace Game\Translation;

/**
 * The exchange formats: CSV (for spreadsheets) and PO (for translation editors). Both carry the
 * path of each string as JSON, the English text and the locale's value, and decode to the same
 * rows: path, english, value and whether an editor marked the entry fuzzy.
 */
final class Formats
{
    private const COLUMNS = ['path', 'section', 'key', 'source', 'reference', 'en-US', 'value'];

    private function __construct()
    {
    }

    /**
     * @param list<array{path: list<int|string>, key: string, english: string, value: string, source: string, reference: string}> $rows
     */
    public static function csv(array $rows, bool $bom): string
    {
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, self::COLUMNS, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, [Catalogue::encodePath($row['path']), (string) $row['path'][0], $row['key'], $row['source'], $row['reference'], $row['english'], $row['value']], ',', '"', '');
        }
        rewind($stream);

        return ($bom ? "\xEF\xBB\xBF" : '') . (string) stream_get_contents($stream);
    }

    /**
     * @return list<array{path: string, english: string, value: string, fuzzy: bool}>
     */
    public static function parseCsv(string $text): array
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, str_starts_with($text, "\xEF\xBB\xBF") ? substr($text, 3) : $text);
        rewind($stream);
        $header = fgetcsv($stream, null, ',', '"', '');
        $columns = is_array($header) ? array_flip(array_map('strval', $header)) : [];
        foreach (['path', 'en-US', 'value'] as $required) {
            if (!isset($columns[$required])) {
                throw new \RuntimeException('The CSV has no "' . $required . '" column.');
            }
        }
        $rows = [];
        while (($line = fgetcsv($stream, null, ',', '"', '')) !== false) {
            if ($line === [null]) {
                continue;
            }
            $rows[] = ['path' => (string) ($line[$columns['path']] ?? ''), 'english' => (string) ($line[$columns['en-US']] ?? ''), 'value' => (string) ($line[$columns['value']] ?? ''), 'fuzzy' => false];
        }

        return $rows;
    }

    /**
     * @param list<array{path: list<int|string>, key: string, english: string, value: string, source: string, reference: string}> $rows
     */
    public static function po(array $rows, string $locale): string
    {
        $po = "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\"Language: " . $locale . "\\n\"\n\"X-Generator: tools/translations.php\\n\"\n";
        foreach ($rows as $row) {
            $po .= "\n#. source: " . $row['source'] . "\n";
            if ($row['reference'] !== '') {
                $po .= '#. reference: ' . str_replace("\n", ' ', $row['reference']) . "\n";
            }
            if (preg_match('~' . Placeholders::pattern() . '~', $row['english']) === 1) {
                $po .= "#, php-format\n";
            }
            $po .= 'msgctxt ' . self::poString(Catalogue::encodePath($row['path'])) . "\nmsgid " . self::poString($row['english']) . "\nmsgstr " . self::poString($row['value']) . "\n";
        }

        return $po;
    }

    /**
     * @return list<array{path: string, english: string, value: string, fuzzy: bool}>
     */
    public static function parsePo(string $text): array
    {
        $rows = [];
        $entry = ['msgctxt' => null, 'msgid' => null, 'msgstr' => null, 'fuzzy' => false];
        $field = null;
        $flush = static function () use (&$entry, &$rows): void {
            if ($entry['msgctxt'] !== null && $entry['msgid'] !== null && $entry['msgstr'] !== null) {
                $rows[] = ['path' => $entry['msgctxt'], 'english' => $entry['msgid'], 'value' => $entry['msgstr'], 'fuzzy' => $entry['fuzzy']];
            }
            $entry = ['msgctxt' => null, 'msgid' => null, 'msgstr' => null, 'fuzzy' => false];
        };
        foreach (preg_split('/\r\n|\n|\r/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                $flush();
                $field = null;
            } elseif (str_starts_with($line, '#,')) {
                $entry['fuzzy'] = $entry['fuzzy'] || str_contains($line, 'fuzzy');
            } elseif ($line[0] === '#') {
                continue;
            } elseif (preg_match('/^(msgctxt|msgid|msgstr)\s+"(.*)"$/', $line, $match) === 1) {
                if ($match[1] === 'msgctxt' && $entry['msgstr'] !== null) {
                    $flush();
                }
                $field = $match[1];
                $entry[$field] = self::poUnquote($match[2]);
            } elseif ($field !== null && preg_match('/^"(.*)"$/', $line, $match) === 1) {
                $entry[$field] .= self::poUnquote($match[1]);
            }
        }
        $flush();

        return array_values(array_filter($rows, static fn (array $row): bool => $row['path'] !== ''));
    }

    private static function poString(string $text): string
    {
        return '"' . str_replace(['\\', '"', "\n", "\r", "\t"], ['\\\\', '\\"', '\\n', '\\r', '\\t'], $text) . '"';
    }

    private static function poUnquote(string $quoted): string
    {
        return (string) preg_replace_callback('/\\\\(.)/', static fn (array $m): string => ['n' => "\n", 'r' => "\r", 't' => "\t"][$m[1]] ?? $m[1], $quoted);
    }
}
