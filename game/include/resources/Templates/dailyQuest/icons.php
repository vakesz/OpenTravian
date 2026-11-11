<?php

declare(strict_types=1);

if (!function_exists('daily_quest_svg')) {
    /**
     * Inline SVG chrome of the daily-quests dialog.
     * IDs are uniquified per render so repeated icons do not collide.
     * Only id= / url(#) / href="#… references are rewritten — class names stay intact.
     */
    function daily_quest_svg(string $name, ?string $classOverride = null): string
    {
        static $seq = 0;
        static $cache = [];

        $file = match ($name) {
            'decoration_start' => 'decoration-start.svg',
            'decoration_end' => 'decoration-end.svg',
            'laurel' => 'laurel.svg',
            'laurelIcon' => 'laurel-icon.svg',
            'below' => 'below.svg',
            'above' => 'above.svg',
            'check' => 'check.svg',
            default => '',
        };
        if ($file === '') {
            return '';
        }

        if (!isset($cache[$file])) {
            $path = __DIR__ . '/svg/' . $file;
            $raw = file_get_contents($path);
            $cache[$file] = $raw === false ? '' : $raw;
        }

        $svg = $cache[$file];
        if ($svg === '') {
            return '';
        }

        ++$seq;
        $suffix = 'dq' . $seq;
        $ids = [];
        if (preg_match_all('/\\bid="([^"]+)"/', $svg, $matches) > 0) {
            $ids = array_unique($matches[1]);
            // Longer ids first so prefixes do not partially rewrite siblings.
            usort($ids, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        }

        foreach ($ids as $id) {
            $newId = $id . '-' . $suffix;
            $svg = str_replace('id="' . $id . '"', 'id="' . $newId . '"', $svg);
            $svg = str_replace('url(#' . $id . ')', 'url(#' . $newId . ')', $svg);
            $svg = str_replace('href="#' . $id . '"', 'href="#' . $newId . '"', $svg);
            $svg = str_replace('xlink:href="#' . $id . '"', 'xlink:href="#' . $newId . '"', $svg);
        }

        if ($classOverride !== null && $classOverride !== '') {
            $svg = (string)preg_replace(
                '/\\bclass="[^"]*"/',
                'class="' . htmlspecialchars($classOverride, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"',
                $svg,
                1
            );
        }

        return $svg;
    }
}
