<?php

declare(strict_types=1);

namespace Game\Translation;

/**
 * Reads and rewrites a hand catalogue (Translation/<locale>.php) through the PHP tokenizer. Hand
 * catalogues are code: this understands the two shapes they use for text, `$Definition['A']['b'] =
 * 'text';` and array literals ("key" => "text"), and treats every other value (concatenation,
 * helper calls, interpolated strings) as computed, which it never touches.
 */
final class HandFile
{
    private const BEGIN = '// --- Imported by tools/translations.php ---';
    private const END = '// --- End of imported translations ---';

    private function __construct()
    {
    }

    /**
     * The plain string literals of a hand catalogue by JSON path (the last assignment wins).
     *
     * @return array<string, array{start: int, end: int, value: string}> byte span of the literal, with its decoded value
     */
    public static function literals(string $source): array
    {
        $tokens = [];
        $offset = 0;
        foreach (token_get_all($source) as $token) {
            $text = is_array($token) ? $token[1] : $token;
            if (!is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $tokens[] = [is_array($token) ? $token[0] : $token, $text, $offset];
            }
            $offset += strlen($text);
        }
        $literals = [];
        $count = count($tokens);
        for ($i = 0; $i < $count; ++$i) {
            if ($tokens[$i][0] !== T_VARIABLE || $tokens[$i][1] !== '$Definition' || ($tokens[$i + 1][0] ?? null) !== '[') {
                continue;
            }
            $path = [];
            $j = $i + 1;
            while (($tokens[$j][0] ?? null) === '[' && in_array($tokens[$j + 1][0] ?? null, [T_CONSTANT_ENCAPSED_STRING, T_LNUMBER], true) && ($tokens[$j + 2][0] ?? null) === ']') {
                $key = self::key($tokens[$j + 1]);
                if ($key === null) {
                    break;
                }
                $path[] = $key;
                $j += 3;
            }
            if ($path === []) {
                continue;
            }
            // Assigning to a path replaces everything below it, and "x .= ..." (or any compound
            // assignment) makes the value computed, so earlier literals there no longer hold.
            $prefix = substr(Catalogue::encodePath($path), 0, -1);
            foreach (array_keys($literals) as $known) {
                if ($known === $prefix . ']' || str_starts_with($known, $prefix . ',')) {
                    unset($literals[$known]);
                }
            }
            if (($tokens[$j][0] ?? null) !== '=') {
                continue;
            }
            $i = self::value($tokens, $j + 1, $path, $literals) - 1;
        }

        return $literals;
    }

    /**
     * Replaces the text of existing literals in place and adds the rest in one managed block just
     * before the final `return $Definition;`. Throws when the result would not parse or would not
     * read back as given.
     *
     * @param list<array{0: list<int|string>, 1: string}> $changes path and new text
     */
    public static function apply(string $source, array $changes): string
    {
        $literals = self::literals($source);
        $replace = [];
        $add = [];
        foreach ($changes as [$path, $text]) {
            $json = Catalogue::encodePath($path);
            if (isset($literals[$json])) {
                $replace[$literals[$json]['start']] = [$literals[$json]['end'], self::quote($text)];
                unset($add[$json]);
            } else {
                $add[$json] = [$path, $text];
            }
        }
        krsort($replace);
        foreach ($replace as $start => [$end, $quoted]) {
            $source = substr($source, 0, $start) . $quoted . substr($source, $end);
        }
        if ($add !== []) {
            $lines = '';
            foreach ($add as [$path, $text]) {
                $target = '$Definition';
                foreach ($path as $key) {
                    $target .= '[' . (is_int($key) ? (string) $key : self::quote($key)) . ']';
                }
                $lines .= $target . ' = ' . self::quote($text) . ";\n";
            }
            $end = strrpos($source, self::END);
            if ($end !== false && str_contains($source, self::BEGIN)) {
                $source = substr($source, 0, $end) . $lines . substr($source, $end);
            } else {
                $return = strrpos($source, 'return $Definition;');
                if ($return === false) {
                    throw new \RuntimeException('The hand catalogue has no final "return $Definition;" to insert before.');
                }
                $source = substr($source, 0, $return) . self::BEGIN . "\n" . $lines . self::END . "\n\n" . substr($source, $return);
            }
        }
        try {
            token_get_all($source, TOKEN_PARSE);
        } catch (\ParseError $error) {
            throw new \RuntimeException('The rewritten catalogue would not parse: ' . $error->getMessage());
        }
        $read = self::literals($source);
        foreach ($changes as [$path, $text]) {
            if (($read[Catalogue::encodePath($path)]['value'] ?? null) !== $text) {
                throw new \RuntimeException('The rewritten catalogue does not read back ' . Catalogue::encodePath($path) . ' as given.');
            }
        }

        return $source;
    }

    /** A new hand catalogue holding only the given entries. */
    public static function create(array $changes): string
    {
        return self::apply("<?php\n\nglobal \$Definition;\n\nreturn \$Definition;\n", $changes);
    }

    public static function quote(string $text): string
    {
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $text) . "'";
    }

    /**
     * Parses one value starting at $i and returns the index after it; plain literals are recorded.
     *
     * @param list<array{0: int|string, 1: string, 2: int}> $tokens
     * @param list<int|string> $path
     * @param array<string, array{start: int, end: int, value: string}> $literals
     */
    private static function value(array $tokens, int $i, array $path, array &$literals): int
    {
        $type = $tokens[$i][0] ?? null;
        if ($type === T_CONSTANT_ENCAPSED_STRING) {
            $value = self::decode($tokens[$i][1]);
            if ($value !== null && in_array($tokens[$i + 1][0] ?? null, [',', ';', ']', ')'], true)) {
                $literals[Catalogue::encodePath($path)] = ['start' => $tokens[$i][2], 'end' => $tokens[$i][2] + strlen($tokens[$i][1]), 'value' => $value];

                return $i + 1;
            }
        }
        $open = $type === '[' ? $i : ($type === T_ARRAY && ($tokens[$i + 1][0] ?? null) === '(' ? $i + 1 : null);
        if ($open === null) {
            return self::skip($tokens, $i);
        }
        $close = $tokens[$open][0] === '[' ? ']' : ')';
        $i = $open + 1;
        $auto = 0;
        while (($tokens[$i][0] ?? $close) !== $close) {
            if (($tokens[$i][0] ?? null) === ',') {
                ++$i;
                continue;
            }
            $key = $auto;
            if (in_array($tokens[$i][0], [T_CONSTANT_ENCAPSED_STRING, T_LNUMBER], true) && ($tokens[$i + 1][0] ?? null) === T_DOUBLE_ARROW) {
                $parsed = self::key($tokens[$i]);
                if ($parsed === null) {
                    $i = self::skip($tokens, $i);
                    continue;
                }
                $key = $parsed;
                $i += 2;
            }
            if (is_int($key) && $key >= $auto) {
                $auto = $key + 1;
            }
            $i = self::value($tokens, $i, array_merge($path, [$key]), $literals);
        }

        return $i + 1;
    }

    /**
     * Skips an expression we do not interpret, up to the "," ";" or closing bracket that ends it.
     *
     * @param list<array{0: int|string, 1: string, 2: int}> $tokens
     */
    private static function skip(array $tokens, int $i): int
    {
        $depth = 0;
        for ($count = count($tokens); $i < $count; ++$i) {
            $type = $tokens[$i][0];
            if (in_array($type, ['(', '[', '{', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)) {
                ++$depth;
            } elseif (in_array($type, [')', ']', '}'], true)) {
                if ($depth === 0) {
                    return $i;
                }
                --$depth;
            } elseif ($depth === 0 && ($type === ',' || $type === ';')) {
                return $i;
            }
        }

        return $i;
    }

    /** @param array{0: int|string, 1: string, 2: int} $token */
    private static function key(array $token): int|string|null
    {
        $key = $token[0] === T_LNUMBER ? (int) $token[1] : self::decode($token[1]);
        if (is_string($key) && preg_match('/^(0|[1-9]\d*)$/', $key) === 1) {
            return (int) $key;
        }

        return $key;
    }

    /** The text of a quoted PHP string literal, or null for escapes this does not interpret. */
    private static function decode(string $literal): ?string
    {
        $inner = substr($literal, 1, -1);
        if ($literal[0] === "'") {
            return (string) preg_replace_callback('/\\\\([\\\\\'])/', static fn (array $m): string => $m[1], $inner);
        }
        if (preg_match('/\\\\(?:x|u|[0-7])/', $inner) === 1) {
            return null;
        }
        $map = ['n' => "\n", 'r' => "\r", 't' => "\t", 'v' => "\v", 'e' => "\e", 'f' => "\f", '\\' => '\\', '"' => '"', '$' => '$'];

        return (string) preg_replace_callback('/\\\\(.)/s', static fn (array $m): string => $map[$m[1]] ?? $m[0], $inner);
    }
}
