<?php

declare(strict_types=1);

namespace Game\Translation;

/**
 * The layers of a locale's catalogue, read for export and import: the en-US keys, the locale's hand
 * catalogue (Translation/<locale>.php) and the generated reference overlay
 * (Translation/generated/<locale>.php, whose lines end in "// bundle:..." or "// page:..." naming
 * their source). Paths are lists of keys, written as JSON where one string is needed.
 */
final class Catalogue
{
    public const SOURCE_BUNDLE = 'bundle';
    public const SOURCE_CAPTURE = 'capture';
    public const SOURCE_HAND = 'hand';
    public const SOURCE_MISSING = 'missing';
    public const SOURCE_COMPUTED = 'computed';
    public const SOURCE_LIST = 'list';

    private function __construct()
    {
    }

    public static function directory(): string
    {
        return dirname(__DIR__, 2) . '/resources/Translation/';
    }

    public static function handFile(string $locale): string
    {
        return self::directory() . $locale . '.php';
    }

    /**
     * Evaluates a catalogue file the way Core\Locale does (hand files assign into the global
     * $Definition and return it; the generated overlay returns an array).
     *
     * @return array<int|string, mixed>
     */
    public static function load(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }
        global $Definition;
        $Definition = [];
        $catalogue = require $file;

        return is_array($catalogue) ? $catalogue : [];
    }

    /**
     * Every string leaf of a tree, with whether it sits inside a list (an overlay list replaces the
     * English list as a whole, so list entries cannot be translated one by one).
     *
     * @param array<int|string, mixed> $tree
     * @return list<array{path: list<int|string>, value: string, list: bool}>
     */
    public static function leaves(array $tree, array $path = [], bool $inList = false): array
    {
        $leaves = [];
        $isList = array_is_list($tree) && $tree !== [];
        foreach ($tree as $key => $value) {
            $here = array_merge($path, [$key]);
            if (is_array($value)) {
                array_push($leaves, ...self::leaves($value, $here, $inList || $isList));
            } elseif (is_string($value)) {
                $leaves[] = ['path' => $here, 'value' => $value, 'list' => $inList || $isList];
            }
        }

        return $leaves;
    }

    /**
     * The origin of each generated entry, from the comment at the end of its line.
     *
     * @return array<string, array{kind: string, reference: string}> keyed by the JSON path
     */
    public static function generatedSources(string $php): array
    {
        $sources = [];
        $stack = [];
        $unquote = static function (string $quoted): int|string {
            $key = str_replace(["\\'", '\\\\'], ["'", '\\'], $quoted);

            return preg_match('/^(0|[1-9]\d*)$/', $key) === 1 ? (int) $key : $key;
        };
        foreach (preg_split('/\r\n|\n|\r/', $php) ?: [] as $line) {
            if (preg_match("/^\s*'((?:[^'\\\\]|\\\\.)*)' => \[\s*$/", $line, $match) === 1) {
                $stack[] = $unquote($match[1]);
            } elseif (preg_match('/^\s*\],\s*$/', $line) === 1) {
                array_pop($stack);
            } elseif (preg_match("/^\s*'((?:[^'\\\\]|\\\\.)*)' => '.*', \/\/ (bundle|page):(.*)$/", $line, $match) === 1) {
                $sources[self::encodePath(array_merge($stack, [$unquote($match[1])]))] = [
                    'kind' => $match[2] === 'bundle' ? self::SOURCE_BUNDLE : self::SOURCE_CAPTURE,
                    'reference' => trim($match[3]),
                ];
            }
        }

        return $sources;
    }

    /** @param list<int|string> $path */
    public static function encodePath(array $path): string
    {
        return (string) json_encode($path, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return list<int|string>|null
     */
    public static function decodePath(string $json): ?array
    {
        $path = json_decode($json, true);

        return is_array($path) && $path !== [] && array_is_list($path) && array_filter($path, static fn ($part): bool => !is_int($part) && !is_string($part)) === [] ? $path : null;
    }

    /**
     * One row per en-US string leaf for a locale: the English text, the locale's effective value
     * (generated overlay over hand catalogue; empty when the locale falls back to English) and where
     * that value comes from.
     *
     * @return list<array{path: list<int|string>, key: string, english: string, value: string, source: string, reference: string}>
     */
    public static function rows(string $locale): array
    {
        $english = self::load(self::handFile('en-US'));
        $isEnglish = $locale === 'en-US';
        $hand = $isEnglish ? $english : self::load(self::handFile($locale));
        $generatedFile = self::directory() . 'generated/' . $locale . '.php';
        $generated = $isEnglish ? [] : self::load($generatedFile);
        $generatedSources = $isEnglish || !is_file($generatedFile) ? [] : self::generatedSources((string) file_get_contents($generatedFile));
        $handLiterals = is_file(self::handFile($locale)) ? HandFile::literals((string) file_get_contents(self::handFile($locale))) : [];
        $generatedValues = [];
        foreach (self::leaves($generated) as $leaf) {
            $generatedValues[self::encodePath($leaf['path'])] = $leaf['value'];
        }
        $handValues = [];
        foreach (self::leaves($hand) as $leaf) {
            $handValues[self::encodePath($leaf['path'])] = $leaf['value'];
        }

        $rows = [];
        foreach (self::leaves($english) as $leaf) {
            $json = self::encodePath($leaf['path']);
            if (isset($generatedValues[$json])) {
                $value = $generatedValues[$json];
                $source = $generatedSources[$json]['kind'] ?? self::SOURCE_BUNDLE;
                $reference = $generatedSources[$json]['reference'] ?? '';
            } elseif (isset($handValues[$json])) {
                $value = $handValues[$json];
                $source = $leaf['list'] ? self::SOURCE_LIST : (isset($handLiterals[$json]) ? self::SOURCE_HAND : self::SOURCE_COMPUTED);
                $reference = '';
            } else {
                $value = '';
                $source = $leaf['list'] ? self::SOURCE_LIST : self::SOURCE_MISSING;
                $reference = '';
            }
            $rows[] = [
                'path' => $leaf['path'],
                'key' => implode(' / ', array_slice(array_map('strval', $leaf['path']), 1)),
                'english' => $leaf['value'],
                'value' => $value,
                'source' => $leaf['list'] && $source !== self::SOURCE_BUNDLE && $source !== self::SOURCE_CAPTURE ? self::SOURCE_LIST : $source,
                'reference' => $reference,
            ];
        }

        return $rows;
    }
}
