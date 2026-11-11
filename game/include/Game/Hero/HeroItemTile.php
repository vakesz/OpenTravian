<?php

declare(strict_types=1);

namespace Game\Hero;

/**
 * The hero item tile the reference draws with its StandaloneItem component, for places that show one item
 * outside the inventory (the bounty of an adventure report): the tile with its corner arrows, the item
 * picture and its amount, also a single one (report 22909367, bounty Artwork, shows the count 1).
 */
final class HeroItemTile
{
    public const ARROWS_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 62 62" class="arrows" aria-hidden="true">'
        . '<path class="top" d="M31 4l2-4h-4l2 4z"></path><path class="bottom" d="M29 62h4l-2-4-2 4z"></path>'
        . '<path class="from" d="M0 33l4-2-4-2v4z"></path><path class="to" d="M58 31l4 2v-4l-4 2z"></path></svg>';

    private function __construct()
    {
    }

    public static function standalone(int $btype, int $type, int $amount): string
    {
        return '<div id="standaloneItem"><div class="heroItem ' . ($btype >= 7 ? 'consumable' : 'tier1') . ' neutral '
            . HeroItems::tileSlot($btype, 'bag') . ' heroItemV1">' . self::ARROWS_SVG
            . '<div class="item item' . $type . ' neutral"></div>'
            . '<div class="count">' . $amount . '</div>'
            . '</div></div>';
    }
}
