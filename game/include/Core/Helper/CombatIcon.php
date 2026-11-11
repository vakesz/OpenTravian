<?php

declare(strict_types=1);

namespace Core\Helper;

/**
 * The combat artwork (attack/defend/loot/skull) as inline SVG, the way the reference prints it in reports:
 * the shared Gpack file with the caller's class and icon name, plus an invisible titled rectangle as tooltip.
 */
final class CombatIcon
{
    /** File below img/svg/combat/ of an icon: "attackLost" is svg-attack-lost.svg. */
    public static function fileName(string $name): string
    {
        return 'svg-' . strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $name)) . '.svg';
    }

    public static function svg(string $file, string $class, ?string $title = null, ?string $icon = null): string
    {
        $source = file_get_contents(PUBLIC_INTERNAL_PATH . 'img/svg/combat/' . $file);
        if ($source === false) {
            return '';
        }
        $attributes = ($class !== '' ? ' class="' . $class . '"' : '') . ($icon !== null ? ' icon="' . $icon . '"' : '');
        $svg = trim((string) preg_replace('/ class="[^"]*"/', $attributes, $source, 1));
        if ($title !== null && preg_match('/viewBox="0 0 ([\d.]+) ([\d.]+)"/', $svg, $box)) {
            $rect = '<rect x="0" y="0" width="' . $box[1] . '" height="' . $box[2] . '" fill="#FF1493" fill-opacity="0"><title>'
                . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title></rect>';
            $svg = (string) preg_replace('#</svg>$#', $rect . '</svg>', $svg);
        }

        return $svg;
    }
}
