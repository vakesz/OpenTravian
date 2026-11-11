<?php

declare(strict_types=1);

namespace Game;

/**
 * Renders operator-written Markdown for the legal pages. Everything is escaped
 * first, then only these constructs are turned into markup: headings (# to ###),
 * paragraphs, bullet and numbered lists, **bold**, *italic*, and links whose
 * target is a local path or an https URL.
 */
final class LegalMarkdown
{
    private function __construct()
    {
    }

    public static function render(string $markdown): string
    {
        $html = '';
        $paragraph = [];
        $list = null;
        $flush = static function () use (&$html, &$paragraph, &$list): void {
            if ($paragraph !== []) {
                $html .= '<p>' . self::inline(implode(' ', $paragraph)) . "</p>\n";
                $paragraph = [];
            }
            if ($list !== null) {
                $html .= '<' . $list['tag'] . '>' . implode('', $list['items']) . '</' . $list['tag'] . ">\n";
                $list = null;
            }
        };

        foreach (preg_split('/\R/', trim($markdown)) ?: [] as $line) {
            $line = rtrim($line);
            if ($line === '') {
                $flush();
            } elseif (preg_match('/^(#{1,3})\s+(.+)$/', $line, $m) === 1) {
                $flush();
                $level = strlen($m[1]) + 1;
                $html .= "<h{$level}>" . self::inline($m[2]) . "</h{$level}>\n";
            } elseif (preg_match('/^(?:([-*])|\d+\.)\s+(.+)$/', $line, $m) === 1) {
                $tag = $m[1] !== '' ? 'ul' : 'ol';
                if ($list === null || $list['tag'] !== $tag) {
                    $flush();
                    $list = ['tag' => $tag, 'items' => []];
                }
                $list['items'][] = '<li>' . self::inline($m[2]) . '</li>';
            } else {
                if ($list !== null) {
                    $flush();
                }
                $paragraph[] = trim($line);
            }
        }
        $flush();

        return $html;
    }

    private static function inline(string $text): string
    {
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = (string) preg_replace_callback(
            '/\[([^\]]+)\]\((https:\/\/[^\s)]+|\/(?!\/)[^\s)]*)\)/',
            static function (array $m): string {
                $external = str_starts_with($m[2], 'https://');

                return '<a href="' . $m[2] . '"'
                    . ($external ? ' target="_blank" rel="noopener noreferrer"' : '') . '>' . $m[1] . '</a>';
            },
            $text,
        );
        $text = (string) preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);

        return (string) preg_replace('/(?<!\*)\*(?![\s*])(.+?)(?<![\s*])\*(?!\*)/', '<em>$1</em>', $text);
    }
}
