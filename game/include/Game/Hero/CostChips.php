<?php

declare(strict_types=1);

namespace Game\Hero;

use Core\Session;
use Model\HeroResourceInventory;

/**
 * Resource cost chips of the build pages. While the hero carries resources
 * (inventory items 145-148) the four resource chips become the reference
 * `.inlineIcon.resource.transfer` buttons that open the resource transfer
 * dialog preset with the cost.
 */
final class CostChips
{
    private static ?bool $heroCarries = null;

    /** @param array<int, int|float|string> $cost lumber, clay, iron, crop (raw numbers) */
    public static function render(array $cost, string|int $freeCrop): string
    {
        $transfer = self::heroCarriesResources();
        $onclick = '';
        if ($transfer) {
            $target = ['lumber' => (int)$cost[0], 'clay' => (int)$cost[1], 'iron' => (int)$cost[2], 'crop' => (int)$cost[3]];
            $onclick = ' onclick="window.Travian.React.Hero.openResourceTransfer({ targetResourceAmount: { '
                . 'lumber: ' . $target['lumber'] . ', clay: ' . $target['clay'] . ', iron: ' . $target['iron'] . ', crop: ' . $target['crop']
                . ' }, onTransferFinish: Travian.Autoreload.autoreload })"';
        }

        $html = '<div class="inlineIconList resourceWrapper' . ($transfer ? ' charges' : '') . '">';
        $resources = ['lumber', 'clay', 'iron', 'crop'];
        foreach (['r1Big', 'r2Big', 'r3Big', 'r4Big'] as $index => $icon) {
            $title = $transfer ? T('Buildings', 'transferFromHero.' . $resources[$index]) : T('inGame', 'resources.r' . ($index + 1));
            $html .= '<div class="inlineIcon resource' . ($transfer ? ' transfer' : '') . '" title="' . htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8') . '"' . $onclick . '>'
                . '<i class="' . $icon . '"></i><span class="value value">' . self::wrapLtr(number_format_x($cost[$index])) . '</span></div>';
        }
        $html .= '<div class="inlineIcon resource" title="' . htmlspecialchars((string)T('Manual', 'freeCrop'), ENT_QUOTES, 'UTF-8')
            . '"><i class="cropConsumptionBig"></i><span class="value value">' . self::wrapLtr((string)$freeCrop) . '</span></div></div>';

        return $html;
    }

    /** The reference isolates every cost number with left-to-right embedding marks. */
    private static function wrapLtr(string|int|float $number): string
    {
        return "\u{202D}" . $number . "\u{202C}";
    }

    private static function heroCarriesResources(): bool
    {
        if (self::$heroCarries === null) {
            $session = Session::getInstance();
            self::$heroCarries = $session->isValid() && !$session->isSitter()
                && array_sum((new HeroResourceInventory())->get((int)$session->getPlayerId())) > 0;
        }

        return self::$heroCarries;
    }
}
