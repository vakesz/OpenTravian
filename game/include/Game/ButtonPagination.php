<?php

declare(strict_types=1);

namespace Game;

/**
 * The reference's button pager (`.pagination`): previous, one button per page with its index
 * label (the first seven and the last when there are more than eight), next. Every button
 * carries its tooltip and either navigates to the URL the caller builds for its page (`render`)
 * or names its page for a script that refreshes the list in place (`renderForScript`).
 */
final class ButtonPagination
{
    /** @param callable(int): string $urlFor HTML-escaped URL of a page */
    public static function render(int $page, int $pages, callable $urlFor): string
    {
        return self::build($page, $pages, static fn (int $target): string => ' onclick="window.location.href=\'' . $urlFor($target) . '\';"');
    }

    /** Buttons that carry `data-auction-page` and the list they page, for auction-lists.js. */
    public static function renderForScript(int $page, int $pages, string $list): string
    {
        $list = htmlspecialchars($list, ENT_QUOTES, 'UTF-8');

        return self::build($page, $pages, static fn (int $target): string => ' data-auction-page="' . $target . '" data-auction-list="' . $list . '"');
    }

    /** @param callable(int): string $actionFor attributes that make a button act on its page */
    private static function build(int $page, int $pages, callable $actionFor): string
    {
        $pages = max(1, $pages);
        $page = min(max(1, $page), $pages);
        $escape = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $button = static function (string $class, int $target, string $label, bool $disabled = false) use ($escape, $actionFor): string {
            return '<button type="button" class="' . $class . '" aria-label="' . $escape($label) . '" title="' . $escape($label) . '"'
                . ($disabled ? ' disabled></button>' : $actionFor($target) . '></button>');
        };
        $previousLabel = T('Tasks', 'previous');
        $nextLabel = T('Tasks', 'next');

        $html = '<div class="pagination">';
        $html .= $page === 1
            ? $button('back disabled', 1, $previousLabel, true)
            : $button('back', $page - 1, $previousLabel);
        $visible = $pages <= 8 ? range(1, $pages) : array_merge(range(1, 7), [$pages]);
        foreach ($visible as $index => $number) {
            if ($pages > 8 && $index === 7) {
                $html .= '<div class="emptyPage">..</div>';
            }
            $html .= $button('page' . ($number === $page ? ' selected' : ''), $number, sprintf(T('Tasks', 'page'), $number));
            $html .= '<div class="pageIndex">' . $number . '</div>';
        }
        $html .= $page === $pages
            ? $button('forward disabled', $pages, $nextLabel, true)
            : $button('forward', $page + 1, $nextLabel);

        return $html . '</div>';
    }
}
