<?php

declare(strict_types=1);

namespace Game\Admin;

/**
 * A small local QR Code encoder (ISO/IEC 18004): byte mode, error correction
 * level M, versions 1 to 10 (up to 213 bytes), which is plenty for an otpauth
 * URI. It renders inline SVG so enrolment needs no external service or image
 * library.
 */
final class QrCode
{
    private const MAX_VERSION = 10;

    /** Version => [data codewords per block group..., EC codewords per block]. Level M. */
    private const BLOCKS = [
        1 => [[1, 16]],
        2 => [[1, 28]],
        3 => [[1, 44]],
        4 => [[2, 32]],
        5 => [[2, 43]],
        6 => [[4, 27]],
        7 => [[4, 31]],
        8 => [[2, 38], [2, 39]],
        9 => [[3, 36], [2, 37]],
        10 => [[4, 43], [1, 44]],
    ];
    private const EC_PER_BLOCK = [1 => 10, 2 => 16, 3 => 26, 4 => 18, 5 => 24, 6 => 16, 7 => 18, 8 => 22, 9 => 22, 10 => 26];
    private const ALIGNMENT = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    private function __construct()
    {
    }

    /**
     * The module matrix, true = dark. Rows are top to bottom.
     *
     * @return list<list<bool>>
     */
    public static function matrix(string $text): array
    {
        $length = strlen($text);
        $version = 0;
        for ($candidate = 1; $candidate <= self::MAX_VERSION; ++$candidate) {
            $countBits = $candidate >= 10 ? 16 : 8;
            if ((4 + $countBits + 8 * $length) <= 8 * self::dataCodewords($candidate)) {
                $version = $candidate;
                break;
            }
        }
        if ($version === 0) {
            throw new \InvalidArgumentException('The text is too long for the supported QR versions.');
        }

        $codewords = self::interleave($version, self::dataBytes($text, $version));
        $size = 17 + 4 * $version;
        [$modules, $function] = self::functionPatterns($version, $size);
        self::placeCodewords($modules, $function, $codewords, $size);

        $best = null;
        $bestPenalty = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; ++$mask) {
            $candidate = self::applyMask($modules, $function, $mask, $size);
            self::drawFormat($candidate, $mask, $size);
            $penalty = self::penalty($candidate, $size);
            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $best = $candidate;
            }
        }

        return $best;
    }

    /** Inline SVG with a quiet zone, dark modules as one path. */
    public static function svg(string $text, int $scale = 4, int $quietZone = 4): string
    {
        $matrix = self::matrix($text);
        $size = count($matrix);
        $total = $size + 2 * $quietZone;
        $path = '';
        foreach ($matrix as $y => $row) {
            foreach ($row as $x => $dark) {
                if ($dark) {
                    $path .= 'M' . ($x + $quietZone) . ' ' . ($y + $quietZone) . 'h1v1h-1z';
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" role="img" aria-label="QR code" width="' . ($total * $scale)
            . '" height="' . ($total * $scale) . '" viewBox="0 0 ' . $total . ' ' . $total . '" shape-rendering="crispEdges">'
            . '<rect width="' . $total . '" height="' . $total . '" fill="#fff"/><path d="' . $path . '" fill="#000"/></svg>';
    }

    /** Reed-Solomon remainder over GF(256), polynomial 0x11D. @param list<int> $data @return list<int> */
    public static function reedSolomon(array $data, int $degree): array
    {
        [$exp, $log] = self::galois();
        $generator = [1];
        for ($i = 0; $i < $degree; ++$i) {
            $next = array_fill(0, count($generator) + 1, 0);
            foreach ($generator as $j => $coefficient) {
                $next[$j] ^= $coefficient;
                $next[$j + 1] ^= $coefficient === 0 ? 0 : $exp[($log[$coefficient] + $i) % 255];
            }
            $generator = $next;
        }
        $remainder = array_fill(0, $degree, 0);
        foreach ($data as $byte) {
            $factor = $byte ^ array_shift($remainder);
            $remainder[] = 0;
            if ($factor !== 0) {
                foreach (array_slice($generator, 1) as $j => $coefficient) {
                    if ($coefficient !== 0) {
                        $remainder[$j] ^= $exp[($log[$coefficient] + $log[$factor]) % 255];
                    }
                }
            }
        }

        return $remainder;
    }

    /** The 15 format bits for level M and a mask pattern. */
    public static function formatBits(int $mask): int
    {
        $data = $mask; // level M is 00 in the two high bits
        $remainder = $data;
        for ($i = 0; $i < 10; ++$i) {
            $remainder = ($remainder << 1) ^ (($remainder >> 9) * 0x537);
        }

        return (($data << 10) | $remainder) ^ 0x5412;
    }

    /** The 18 version bits (versions 7 and up). */
    public static function versionBits(int $version): int
    {
        $remainder = $version;
        for ($i = 0; $i < 12; ++$i) {
            $remainder = ($remainder << 1) ^ (($remainder >> 11) * 0x1F25);
        }

        return ($version << 12) | $remainder;
    }

    private static function dataCodewords(int $version): int
    {
        $total = 0;
        foreach (self::BLOCKS[$version] as [$blocks, $perBlock]) {
            $total += $blocks * $perBlock;
        }

        return $total;
    }

    /** @return list<int> */
    private static function dataBytes(string $text, int $version): array
    {
        $bits = '0100' . str_pad(decbin(strlen($text)), $version >= 10 ? 16 : 8, '0', STR_PAD_LEFT);
        foreach (str_split($text) as $character) {
            $bits .= str_pad(decbin(ord($character)), 8, '0', STR_PAD_LEFT);
        }
        $capacity = 8 * self::dataCodewords($version);
        $bits .= str_repeat('0', min(4, $capacity - strlen($bits)));
        $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
        $bytes = array_map('bindec', str_split($bits, 8));
        for ($pad = 0xEC; count($bytes) < self::dataCodewords($version); $pad = $pad === 0xEC ? 0x11 : 0xEC) {
            $bytes[] = $pad;
        }

        return $bytes;
    }

    /**
     * Splits the data into blocks, adds each block's EC codewords and
     * interleaves them into the final bit stream (as bytes).
     *
     * @param list<int> $data
     * @return list<int>
     */
    private static function interleave(int $version, array $data): array
    {
        $blocks = [];
        $offset = 0;
        foreach (self::BLOCKS[$version] as [$count, $perBlock]) {
            for ($i = 0; $i < $count; ++$i) {
                $blocks[] = array_slice($data, $offset, $perBlock);
                $offset += $perBlock;
            }
        }
        $ec = array_map(static fn (array $block): array => self::reedSolomon($block, self::EC_PER_BLOCK[$version]), $blocks);
        $out = [];
        $longest = max(array_map('count', $blocks));
        for ($i = 0; $i < $longest; ++$i) {
            foreach ($blocks as $block) {
                if (isset($block[$i])) {
                    $out[] = $block[$i];
                }
            }
        }
        for ($i = 0; $i < self::EC_PER_BLOCK[$version]; ++$i) {
            foreach ($ec as $block) {
                $out[] = $block[$i];
            }
        }

        return $out;
    }

    /** @return array{0: list<list<bool>>, 1: list<list<bool>>} modules and the function-pattern mask */
    private static function functionPatterns(int $version, int $size): array
    {
        $modules = array_fill(0, $size, array_fill(0, $size, false));
        $function = array_fill(0, $size, array_fill(0, $size, false));
        $set = static function (int $x, int $y, bool $dark) use (&$modules, &$function, $size): void {
            if ($x >= 0 && $x < $size && $y >= 0 && $y < $size) {
                $modules[$y][$x] = $dark;
                $function[$y][$x] = true;
            }
        };
        for ($i = 0; $i < $size; ++$i) {
            $set(6, $i, $i % 2 === 0);
            $set($i, 6, $i % 2 === 0);
        }
        foreach ([[3, 3], [$size - 4, 3], [3, $size - 4]] as [$cx, $cy]) {
            for ($dy = -4; $dy <= 4; ++$dy) {
                for ($dx = -4; $dx <= 4; ++$dx) {
                    $distance = max(abs($dx), abs($dy));
                    $set($cx + $dx, $cy + $dy, $distance !== 2 && $distance !== 4);
                }
            }
        }
        $positions = self::ALIGNMENT[$version];
        $last = count($positions) - 1;
        foreach ($positions as $i => $cx) {
            foreach ($positions as $j => $cy) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $last) || ($i === $last && $j === 0)) {
                    continue;
                }
                for ($dy = -2; $dy <= 2; ++$dy) {
                    for ($dx = -2; $dx <= 2; ++$dx) {
                        $set($cx + $dx, $cy + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }
        // Reserve the format areas; the real bits are written per mask.
        for ($i = 0; $i < 9; ++$i) {
            if ($i === 6) {
                continue; // the timing pattern runs through the format strip
            }
            $set(8, $i, false);
            $set($i, 8, false);
        }
        for ($i = 0; $i < 8; ++$i) {
            $set($size - 1 - $i, 8, false);
            $set(8, $size - 1 - $i, false);
        }
        $set(8, $size - 8, true);
        if ($version >= 7) {
            $bits = self::versionBits($version);
            for ($i = 0; $i < 18; ++$i) {
                $dark = (($bits >> $i) & 1) === 1;
                $a = $size - 11 + $i % 3;
                $b = intdiv($i, 3);
                $set($a, $b, $dark);
                $set($b, $a, $dark);
            }
        }

        return [$modules, $function];
    }

    /** @param list<list<bool>> $modules @param list<list<bool>> $function @param list<int> $codewords */
    private static function placeCodewords(array &$modules, array $function, array $codewords, int $size): void
    {
        $bitIndex = 0;
        $total = count($codewords) * 8;
        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vertical = 0; $vertical < $size; ++$vertical) {
                for ($j = 0; $j < 2; ++$j) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $size - 1 - $vertical : $vertical;
                    if (!$function[$y][$x] && $bitIndex < $total) {
                        $modules[$y][$x] = (($codewords[$bitIndex >> 3] >> (7 - ($bitIndex & 7))) & 1) === 1;
                        ++$bitIndex;
                    }
                }
            }
        }
    }

    /** @param list<list<bool>> $modules @param list<list<bool>> $function @return list<list<bool>> */
    private static function applyMask(array $modules, array $function, int $mask, int $size): array
    {
        for ($y = 0; $y < $size; ++$y) {
            for ($x = 0; $x < $size; ++$x) {
                if ($function[$y][$x]) {
                    continue;
                }
                $invert = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => $x * $y % 2 + $x * $y % 3 === 0,
                    6 => ($x * $y % 2 + $x * $y % 3) % 2 === 0,
                    default => (($x + $y) % 2 + $x * $y % 3) % 2 === 0,
                };
                if ($invert) {
                    $modules[$y][$x] = !$modules[$y][$x];
                }
            }
        }

        return $modules;
    }

    /** @param list<list<bool>> $modules */
    private static function drawFormat(array &$modules, int $mask, int $size): void
    {
        $bits = self::formatBits($mask);
        $bit = static fn (int $i): bool => (($bits >> $i) & 1) === 1;
        for ($i = 0; $i <= 5; ++$i) {
            $modules[$i][8] = $bit($i);
        }
        $modules[7][8] = $bit(6);
        $modules[8][8] = $bit(7);
        $modules[8][7] = $bit(8);
        for ($i = 9; $i < 15; ++$i) {
            $modules[8][14 - $i] = $bit($i);
        }
        for ($i = 0; $i < 8; ++$i) {
            $modules[8][$size - 1 - $i] = $bit($i);
        }
        for ($i = 8; $i < 15; ++$i) {
            $modules[$size - 15 + $i][8] = $bit($i);
        }
        $modules[$size - 8][8] = true;
    }

    /** @param list<list<bool>> $modules */
    private static function penalty(array $modules, int $size): int
    {
        $penalty = 0;
        $lines = [];
        for ($i = 0; $i < $size; ++$i) {
            $row = '';
            $column = '';
            for ($j = 0; $j < $size; ++$j) {
                $row .= $modules[$i][$j] ? '1' : '0';
                $column .= $modules[$j][$i] ? '1' : '0';
            }
            $lines[] = $row;
            $lines[] = $column;
        }
        foreach ($lines as $line) {
            preg_match_all('/0{5,}|1{5,}/', $line, $runs);
            foreach ($runs[0] as $run) {
                $penalty += 3 + strlen($run) - 5;
            }
            $penalty += 40 * (preg_match_all('/(?=10111010000|00001011101)/', $line));
        }
        for ($y = 0; $y < $size - 1; ++$y) {
            for ($x = 0; $x < $size - 1; ++$x) {
                if ($modules[$y][$x] === $modules[$y][$x + 1] && $modules[$y][$x] === $modules[$y + 1][$x] && $modules[$y][$x] === $modules[$y + 1][$x + 1]) {
                    $penalty += 3;
                }
            }
        }
        $dark = 0;
        foreach ($modules as $row) {
            $dark += count(array_filter($row));
        }
        $percent = $dark * 100 / ($size * $size);
        $penalty += 10 * intdiv((int) abs($percent - 50), 5);

        return $penalty;
    }

    /** @return array{0: list<int>, 1: list<int>} exp and log tables */
    private static function galois(): array
    {
        static $tables = null;
        if ($tables === null) {
            $exp = [];
            $log = array_fill(0, 256, 0);
            $value = 1;
            for ($i = 0; $i < 255; ++$i) {
                $exp[$i] = $value;
                $log[$value] = $i;
                $value <<= 1;
                if ($value & 0x100) {
                    $value ^= 0x11D;
                }
            }
            $tables = [$exp, $log];
        }

        return $tables;
    }
}
