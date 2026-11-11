<?php

declare(strict_types=1);

namespace Game\Translation;

/**
 * Placeholder rules for translated strings. The pattern lives in one file,
 * Translation/placeholders.regex, read by this class, so the
 * importer and the validator can never disagree about what a placeholder is.
 */
final class Placeholders
{
    private function __construct()
    {
    }

    private static ?string $pattern = null;

    /** The shared pattern, without delimiters. */
    public static function pattern(): string
    {
        return self::$pattern ??= trim((string) file_get_contents(dirname(__DIR__, 2) . '/resources/Translation/placeholders.regex'));
    }

    /**
     * Why a translation's placeholders do not fit the English text, or null when they do: the same
     * printf conversions (%s and %d) in the same number, positional ones (%1$s) forming a
     * permutation of 1..n, and the same {name} and [TOKEN] placeholders.
     */
    public static function mismatch(string $english, string $translation): ?string
    {
        [$expectedPrintf, $expectedOther] = self::split($english);
        [$actualPrintf, $actualOther, $positions] = self::split($translation);
        if ($expectedPrintf !== $actualPrintf || $expectedOther !== $actualOther) {
            return 'expected the placeholders ' . self::describe($expectedPrintf, $expectedOther) . ' but found ' . self::describe($actualPrintf, $actualOther);
        }
        if ($positions !== []) {
            sort($positions);
            if ($positions !== range(1, count($actualPrintf))) {
                return 'the positional placeholders must number every %s or %d once, from 1 to ' . count($actualPrintf);
            }
        }

        return null;
    }

    /**
     * @return array{0: list<string>, 1: list<string>, 2: list<int>} printf conversions (as %s / %d), other placeholders, positions used
     */
    private static function split(string $text): array
    {
        preg_match_all('~' . self::pattern() . '~', $text, $matches);
        $printf = [];
        $other = [];
        $positions = [];
        foreach ($matches[0] as $token) {
            if ($token[0] === '%' && preg_match('/^%(\d*)(\$?)([sd])$/', $token, $parts) === 1) {
                $printf[] = '%' . $parts[3];
                if ($parts[2] === '$' && $parts[1] !== '') {
                    $positions[] = (int) $parts[1];
                }
            } else {
                $other[] = $token;
            }
        }
        sort($printf);
        sort($other);

        return [$printf, $other, $positions];
    }

    /**
     * @param list<string> $printf
     * @param list<string> $other
     */
    private static function describe(array $printf, array $other): string
    {
        $all = array_merge($printf, $other);

        return $all === [] ? 'none' : implode(' ', $all);
    }
}
