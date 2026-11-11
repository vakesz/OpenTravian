<?php

declare(strict_types=1);

namespace Game\Translation;

/**
 * Decides what an edited export may change and writes the accepted rows into the locale's hand
 * catalogue. Reference-sourced entries (generated from bundles or captures) are never written:
 * the generator owns them, and a generated value wins over a hand value at runtime anyway.
 */
final class Importer
{
    private function __construct()
    {
    }

    /**
     * @param list<array{path: string, english: string, value: string, fuzzy: bool}> $edited rows parsed from a CSV or PO
     * @param list<array{path: list<int|string>, key: string, english: string, value: string, source: string, reference: string}> $current Catalogue::rows()
     * @return array{accepted: list<array{0: list<int|string>, 1: string}>, rejected: list<array{0: string, 1: string}>, conflicts: list<array{0: string, 1: string}>, unchanged: int, fuzzy: int}
     */
    public static function plan(array $edited, array $current): array
    {
        $byPath = [];
        foreach ($current as $row) {
            $byPath[Catalogue::encodePath($row['path'])] = $row;
        }
        $plan = ['accepted' => [], 'rejected' => [], 'conflicts' => [], 'unchanged' => 0, 'fuzzy' => 0];
        foreach ($edited as $row) {
            $path = Catalogue::decodePath($row['path']);
            $known = $byPath[$row['path']] ?? null;
            if ($path === null || $known === null) {
                $plan['rejected'][] = [$row['path'], $path === null ? 'not a valid path' : 'not a key of the en-US catalogue'];
            } elseif ($row['english'] !== $known['english']) {
                $plan['rejected'][] = [$row['path'], 'the English text changed since the export; export again'];
            } elseif ($row['fuzzy']) {
                ++$plan['fuzzy'];
            } elseif ($row['value'] === '' || $row['value'] === $known['value']) {
                ++$plan['unchanged'];
            } elseif ($known['source'] === Catalogue::SOURCE_LIST) {
                $plan['rejected'][] = [$row['path'], 'inside a list, which an overlay replaces as a whole (read-only)'];
            } elseif ($known['source'] === Catalogue::SOURCE_COMPUTED) {
                $plan['rejected'][] = [$row['path'], 'computed by PHP in the hand catalogue (read-only)'];
            } elseif ($known['source'] === Catalogue::SOURCE_BUNDLE || $known['source'] === Catalogue::SOURCE_CAPTURE) {
                $plan['conflicts'][] = [$row['path'], 'generated from ' . $known['source'] . ' ' . $known['reference'] . '; the generated entry wins at runtime, so change it in the generated overlay'];
            } elseif (($mismatch = Placeholders::mismatch($known['english'], $row['value'])) !== null) {
                $plan['rejected'][] = [$row['path'], $mismatch];
            } else {
                $plan['accepted'][$row['path']] = [$path, $row['value']];
            }
        }
        $plan['accepted'] = array_values($plan['accepted']);

        return $plan;
    }

    /**
     * Writes the accepted rows into a hand catalogue file, atomically and with its mode kept.
     *
     * @param list<array{0: list<int|string>, 1: string}> $changes
     */
    public static function apply(string $file, array $changes, bool $createFile): void
    {
        $exists = is_file($file);
        if (!$exists && !$createFile) {
            throw new \RuntimeException(basename($file) . ' does not exist; pass --create-hand-file to create the hand catalogue for this locale.');
        }
        $before = $exists ? sha1((string) file_get_contents($file)) : null;
        $source = $exists ? HandFile::apply((string) file_get_contents($file), $changes) : HandFile::create($changes);
        $temporary = $file . '.import';
        if (@file_put_contents($temporary, $source) === false) {
            throw new \RuntimeException('Cannot write next to ' . basename($file) . '; run this in a writable checkout (the dev compose layer).');
        }
        @chmod($temporary, $exists ? (int) (fileperms($file) & 0777) : 0644);
        if ($before !== null && sha1((string) @file_get_contents($file)) !== $before) {
            @unlink($temporary);
            throw new \RuntimeException(basename($file) . ' changed while the import ran; nothing was written. Run it again.');
        }
        if (!@rename($temporary, $file)) {
            @unlink($temporary);
            throw new \RuntimeException('Cannot replace ' . basename($file) . '.');
        }
    }
}
