<?php

declare(strict_types=1);

namespace Core\Helper;

/**
 * messages and surrounding reports paint first/prev/current/next/last, including a
 * single-page disabled chrome. The report lists use the previous/next-only pager (`$simple`).
 */
class PageNavigator
{
    private int $currentPage;
    /** @var array<string, mixed> */
    private array $linkPrefix;
    private string $target;
    private string $currentPageClass;
    private int $totalPagesNum;
    private string $HTML = '';

    /**
     * @param array<string, mixed> $linkPrefix query parameters carried by every page link
     */
    public function __construct(
        int $currentPage,
        int $totalSize,
        int $pageSize,
        array $linkPrefix,
        string $target,
        bool $simple = false,
        string $currentPageClass = 'number currentPage'
    ) {
        $this->currentPageClass = $currentPageClass;
        $this->currentPage = $currentPage;
        $this->linkPrefix = $linkPrefix;
        $this->target = $target;
        $this->totalPagesNum = (int) ceil($totalSize / $pageSize);
        if ($simple) {
            $this->totalPagesNum = max(1, $this->totalPagesNum);
        }
        if ($this->totalPagesNum === 0) {
            return;
        }
        if ($this->currentPage > $this->totalPagesNum) {
            $this->currentPage = $this->totalPagesNum;
        }
        if ($this->currentPage <= 0) {
            $this->currentPage = 1;
        }
        if ($simple) {
            $this->HTML = '<div class="paginator">' . $this->createPreviousNavigator() . $this->createNextNavigator() . '</div>';

            return;
        }
        $HTML = '<div class="paginator">';
        $HTML .= $this->createFirstNavigator() . ' ';
        $HTML .= $this->createPreviousNavigator() . ' ';
        $beforeCount = 1;
        $afterCount = 1;
        if ($this->currentPage == $this->totalPagesNum) {
            $beforeCount = 3;
            $afterCount = 0;
        }
        if ($this->currentPage == 1) {
            $afterCount = 1;
            $beforeCount = 0;
        }
        $HTML .= $this->createPage(1);
        if ($this->currentPage - $beforeCount - 1 > 1) {
            $HTML .= ' ... ';
        }
        for ($i = $this->currentPage - $beforeCount; $i <= $this->currentPage; $i++) {
            if ($i <= 1 || $i >= $this->totalPagesNum) {
                continue;
            }
            $HTML .= $this->createPage($i);
        }
        $HTML .= '<span class="' . $this->currentPageClass . '">' . $this->currentPage . ' </span> ';
        for ($i = $this->currentPage; $i <= $this->currentPage + $afterCount; $i++) {
            if ($i >= $this->totalPagesNum) {
                continue;
            }
            $HTML .= $this->createPage($i);
        }
        if ($this->currentPage + $afterCount + 1 < $this->totalPagesNum) {
            $HTML .= ' ... ';
        }
        if ($this->totalPagesNum > 1) {
            $HTML .= $this->createPage($this->totalPagesNum);
        }
        $HTML .= $this->createNextNavigator() . ' ';
        $HTML .= $this->createLastNavigator() . '  ';
        $HTML .= '</div>';
        $this->HTML = $HTML;
    }

    private function createFirstNavigator(): string
    {
        $HTML = '';
        $disabled = $this->currentPage == 1 || $this->totalPagesNum == 1;
        if (!$disabled) {
            $HTML .= '<a href="' . $this->getUrl(1) . '" class="first">';
        }
        $HTML .= '<img alt="first page" src="/img/spacer.gif"' . ($disabled ? ' class="first disabled"' : '') . '>';
        if (!$disabled) {
            $HTML .= '</a>';
        }

        return $HTML;
    }

    private function getUrl(int $page): string
    {
        $this->linkPrefix['page'] = $page;

        return $this->target . "?" . http_build_query($this->linkPrefix);
    }

    private function createPreviousNavigator(): string
    {
        $HTML = '';
        $disabled = ($this->currentPage - 1) < 1;
        if (!$disabled) {
            $HTML .= '<a href="' . $this->getUrl($this->currentPage - 1) . '" class="previous">';
        }
        $HTML .= '<img alt="previous page" src="/img/spacer.gif"' . ($disabled ? ' class="previous disabled"' : '') . '>';
        if (!$disabled) {
            $HTML .= '</a>';
        }

        return $HTML;
    }

    private function createPage(int $page): string
    {
        if ($page < 1 || $page > $this->totalPagesNum || $page == $this->currentPage) {
            return '';
        }

        return '<a class="number" href="' . $this->getUrl($page) . '">' . $page . '</a>';
    }

    private function createNextNavigator(): string
    {
        $HTML = '';
        $disabled = ($this->currentPage + 1) > $this->totalPagesNum;
        if (!$disabled) {
            $HTML .= '<a href="' . $this->getUrl($this->currentPage + 1) . '" class="next">';
        }
        $HTML .= '<img alt="next page" src="/img/spacer.gif"' . ($disabled ? ' class="next disabled"' : '') . '>';
        if (!$disabled) {
            $HTML .= '</a>';
        }

        return $HTML;
    }

    private function createLastNavigator(): string
    {
        $HTML = '';
        $disabled = $this->currentPage == $this->totalPagesNum || $this->totalPagesNum == 1;
        if (!$disabled) {
            $HTML .= '<a href="' . $this->getUrl($this->totalPagesNum) . '" class="last">';
        }
        $HTML .= '<img alt="last page" src="/img/spacer.gif"' . ($disabled ? ' class="last disabled"' : '') . '>';
        if (!$disabled) {
            $HTML .= '</a>';
        }

        return $HTML;
    }

    /**
     * @param bool $no (No navigator div)
     */
    public function get(bool $no = false): string
    {
        if ($no) {
            $HTML = $this->HTML;
            $HTML = str_replace('<div class="paginator">', '', $HTML);
            $HTML = str_replace('</div>', '', $HTML);
            return $HTML;
        }
        return $this->HTML;
    }
}