<?php

declare(strict_types=1);

/**
 * Inline SVG glyphs used by the Farm List page and its dialogs.
 */
if (!function_exists('farm_list_svg')) {
    /** @return array<string, array{0: string, 1: string}> viewBox and inner markup per glyph */
    function farm_list_glyphs(): array
    {
        return [
            'edit' => ['0 0 12.8 18.8', '<path d="M5.5 16.6.8 18.8 0 13.6c0-.1.1-.1.1-.1l5.4 2.9c.1.1.1.2 0 .2zm6.7-14.4L8.4.1C7.9-.1 7.3 0 7 .5L1.2 11.2c-.3.5-.1 1.1.3 1.4h.1l3.9 2.1c.5.3 1.1.1 1.4-.4l5.8-10.7c.3-.4.1-1.1-.5-1.4.1 0 .1 0 0 0z"/>'],
            'chevron' => ['0 0 12 7.41', '<path d="M1.41 0L6 4.58 10.59 0 12 1.41l-6 6-6-6z"/>'],
            'drag' => ['0 0 7 13', '<circle cx="1.5" cy="1.5" r="1.5"/><circle cx="5.5" cy="1.5" r="1.5"/><circle cx="1.5" cy="6.5" r="1.5"/><circle cx="5.5" cy="6.5" r="1.5"/><circle cx="1.5" cy="11.5" r="1.5"/><circle cx="5.5" cy="11.5" r="1.5"/>'],
            'menu' => ['0 0 100 75', '<path d="M96.96 15H3.04C1.36 15 0 13.64 0 11.96V3.04C0 1.36 1.36 0 3.04 0h93.93c1.68 0 3.04 1.36 3.04 3.04v8.93c0 1.68-1.36 3.04-3.04 3.04ZM100 41.96v-8.93c0-1.68-1.36-3.04-3.04-3.04H3.04A3.054 3.054 0 0 0 0 33.04v8.93C0 43.64 1.36 45 3.04 45h93.93c1.68 0 3.04-1.36 3.04-3.04Zm0 30v-8.93c0-1.68-1.36-3.04-3.04-3.04H3.04C1.36 59.99 0 61.35 0 63.03v8.93C0 73.64 1.36 75 3.04 75h93.93c1.68 0 3.04-1.36 3.04-3.04Z"/>'],
            'sort' => ['0 0 16 20', '<path d="M8 4h3v15h2V4h3l-4-4zM8 16H5V1H3v15H0l4 4z"/>'],
            'arrow' => ['0 0 10 5', '<path d="M0 5l5-5 5 5z"/>'],
            'delete' => ['0 0 86.29 100', '<path d="M75 100H11.32C8.9 93.78 7 89.76 6.89 82.9Q6.73 73 6.72 63c0-13.43.05-26.86.05-40.29H79.5c0 14-.22 28-.13 42C79.46 77.58 80.5 88.09 75 100zM25.8 86V32h-9v54zm22 0V32h-9v54zm13-54v54h9V32z"/><path d="M0 4.66C10.8 4 21.12 7 29.64 0h27c8.51 7 18.87 4 29.66 4.66L85.76 18H.53z"/>'],
            'deactivate' => ['0 0 46.4 50', '<path d="M36.56 18.61a.888.888 0 0 1 0-1.29l9.57-9.58c.62-.62.13-1.61-.8-1.61H13.42l19 21.15 2.27 2.52h10.64c.93 0 1.42-.99.8-1.61l-9.57-9.58ZM8.32 6.13 2.92.12 2.82 0 0 2.53l7.49 8.33v37.06h5.52V29.8H24.5L42.65 50l1.52-1.37 1.29-1.16L29.59 29.8 8.32 6.13z"/>'],
            'activate' => ['0 0 46 50', '<path d="M34.36 14.93c-.43-.44-.43-1.11 0-1.55L45.67 1.93c.73-.74.15-1.93-.94-1.93H0v50h7V28.32h37.73c1.1 0 1.68-1.18.94-1.93L34.36 14.93Z"/>'],
            'duplicate' => ['0 0 50 50', '<path d="M45.21 14.28h-7.67V4.9c0-2.7-2.2-4.9-4.9-4.9H4.9C2.2 0 0 2.2 0 4.9v27.74c0 2.7 2.2 4.9 4.9 4.9h9.38v7.67c0 2.64 2.15 4.79 4.79 4.79h26.14c2.64 0 4.79-2.15 4.79-4.79V19.07c0-2.64-2.15-4.79-4.79-4.79ZM6 31.54V6h25.53v8.27H19.06c-2.64 0-4.79 2.15-4.79 4.79v12.47H6Z"/>'],
            'plus' => ['0 0 18 18', '<path d="M16 10h-6v6H8v-6H2V8h6V2h2v6h6z"/>'],
            'error' => ['0 0 20 20', '<path d="M10 5a1 1 0 011 1v4a1 1 0 01-2 0V6a1 1 0 011-1zm0-5a10 10 0 1010 10A10 10 0 0010 0zm0 18a8 8 0 118-8 8 8 0 01-8 8zm1-3H9v-2h2z"/>'],
            'average' => ['0 0 82 100', '<path d="M82 23V0H0v17l28 33L0 83v17h82V76H65v7H22l27-33-27-33h43v6h17z"/>'],
            'success' => ['0 0 20 20', '<path d="M14.59 5.58L8 12.17 4.41 8.59 3 10l5 5 8-8zM10 0a10 10 0 1010 10A10 10 0 0010 0zm0 18a8 8 0 118-8 8 8 0 01-8 8z"/>'],
            'close' => ['0 0 20 20', '<path d="M0 17.01L7.01 10 .14 3.13 3.13.14 10 7.01 17.01 0 20 2.99 12.99 10l6.87 6.87-2.99 2.99L10 12.99 2.99 20 0 17.01z"/>'],
            'checkbox' => ['0 0 18 18', '<path class="box" d="M16 0H2a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V2a2 2 0 00-2-2z"/><rect class="indeterminate" width="10" height="2" x="4" y="8"/><path class="checkmark" d="M15.28 4.28l-8.27 8.27-4.3-4.26h0"/>'],
        ];
    }

    function farm_list_svg(string $name, string $class = ''): string
    {
        $glyph = farm_list_glyphs()[$name] ?? null;
        if ($glyph === null) {
            return '';
        }

        return '<svg' . ($class !== '' ? ' class="' . $class . '"' : '') . ' viewBox="' . $glyph[0] . '">' . $glyph[1] . '</svg>';
    }

    /** Outer svg wrapping the outline and icon layers the framed icon buttons expect. */
    function farm_list_layered_svg(string $name): string
    {
        return '<svg>' . farm_list_svg($name, 'outline') . farm_list_svg($name, 'icon') . '</svg>';
    }

    /** @return array<string, string> glyph markup handed to the client for context menus */
    function farm_list_client_glyphs(): array
    {
        $glyphs = [];
        foreach (array_keys(farm_list_glyphs()) as $name) {
            $glyphs[$name] = farm_list_svg($name);
        }

        return $glyphs;
    }
}
