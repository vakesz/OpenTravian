<?php

declare(strict_types=1);

namespace Game;

/**
 * The arrow the reference draws for a production bonus: beside the resource bars (class `productionBoost`) and
 * in front of the "+25% for 24 hours" line of the shop cards (class `premiumFeature` paints it).
 */
final class ProductionBoostArrow
{
    private const PATHS = '<path class="border" d="M158.78 106.45 85.17 2.72C84.06 1.01 82.14 0 80.03 0S76 1.01 74.79 2.72L1.17 106.45c-1.41 1.91-1.51 4.53-.5 6.64 1.11 2.11 3.23 3.42 5.65 3.42h31.76v57.15c0 3.52 2.82 6.34 6.35 6.34h70.89c3.53 0 6.35-2.82 6.35-6.34v-57.15h31.97c2.42 0 4.54-1.31 5.65-3.42 1.11-2.11.91-4.63-.5-6.64Z"></path>'
        . '<path class="sideShadow" d="m20.6 103.48-14.07 6.78c.17.3.48.51.87.51h35.86c.55 0 1 .45 1 1v61.81c0 .55.45 1 1 1h69.48c.55 0 1-.45 1-1V111.8c0-.55.45-1 1-1l35.34.29c.41 0 .74-.23.9-.55l-14.1-7-118.28-.06Z"></path>'
        . '<path class="bottomShadow" d="m51.45 167.41-6.88 6.88c.18.18.42.28.69.28h69.48c.26 0 .49-.11.67-.27l-6.96-6.9H51.44Z"></path>'
        . '<path class="topShadow" d="m6.54 110.26 13.98-6.74 59.44-85.03 59.02 85.1 14.01 6.95c.15-.3.15-.68-.07-1.01L80.83 5.35c-.39-.57-1.24-.57-1.64 0L6.59 109.21c-.35.5-.16 1.14.3 1.42-.15-.09-.26-.22-.35-.37Z"></path>'
        . '<path class="inner" d="M108.45 167.41h-57v-63.89H20.52l59.44-85.03 59.02 85.1-30.53-.05v63.87z"></path>';

    private function __construct()
    {
    }

    /**
     * @param string $class the class attribute value, written as given
     */
    public static function svg(string $class, string $id = ''): string
    {
        return '<svg viewBox="0 0 160 180" class="' . $class . '"' . ($id !== '' ? ' id="' . $id . '"' : '') . '>' . self::PATHS . '</svg>';
    }

    /** The arrow of the resource bars: bare `.productionBoost` is hidden by the Gpack, `.premiumFeature` paints it. */
    public static function forResourceBar(bool $boosted): string
    {
        return self::svg(' advantageBonusArrow productionBoost' . ($boosted ? ' premiumFeature' : ''));
    }
}
