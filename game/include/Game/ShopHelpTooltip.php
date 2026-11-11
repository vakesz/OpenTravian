<?php

declare(strict_types=1);

namespace Game;

/**
 * The help tooltips of the shop's advantage cards (the `tooltipHelpIcon` of OpenTravian Plus, the Gold club and the
 * four production bonuses). The reference puts this markup in a Travian.Tip, so the card carries it as the `title`
 * of the icon (HTML-escaped) and the tip bridge shows it as a `div.text`. The video bonus paragraph of the
 * reference is left out: there is no ad provider here.
 */
final class ShopHelpTooltip
{
    /** The info glyph of the icon, as the reference draws it. */
    private const ICON_SVG = '<svg viewBox="0 0 20 20"><path d="M9 15h2V9H9v6zm1-8c.3 0 .5-.1.7-.3s.3-.4.3-.7-.1-.5-.3-.7c-.2-.2-.4-.3-.7-.3s-.5.1-.7.3c-.2.2-.3.4-.3.7s.1.5.3.7.4.3.7.3zm0 13c-1.4 0-2.7-.3-3.9-.8-1.2-.5-2.3-1.2-3.2-2.1s-1.6-2-2.1-3.2S0 11.4 0 10s.3-2.7.8-3.9S2 3.8 2.9 2.9s2-1.6 3.2-2.1S8.6 0 10 0s2.7.3 3.9.8 2.3 1.2 3.2 2.1 1.6 2 2.1 3.2c.5 1.2.8 2.5.8 3.9s-.3 2.7-.8 3.9c-.5 1.2-1.2 2.3-2.1 3.2s-2 1.6-3.2 2.1c-1.2.5-2.5.8-3.9.8zm0-2c2.2 0 4.1-.8 5.7-2.3S18 12.2 18 10s-.8-4.1-2.3-5.7S12.2 2 10 2s-4.1.8-5.7 2.3S2 7.8 2 10s.8 4.1 2.3 5.7S7.8 18 10 18z"></path></svg>';

    /** Feature rows of the OpenTravian Plus tooltip: icon class => catalogue key under PaymentWizard.help.plus. */
    private const PLUS_ROWS = [
        'attackWarning' => 'attackWarning',
        'buildingQueue' => 'buildingQueue',
        'linkList' => 'linkList',
        'villageOverview' => 'villageOverview',
        'largerMap' => 'largerMap',
        'directLinks' => 'directLinks',
        'merchantsRunTwice' => 'merchantsRunTwice',
    ];

    /** Feature rows of the Gold club tooltip. */
    private const GOLD_CLUB_ROWS = [
        'capitalEvasion' => 'capitalEvasion',
        'farmLists' => 'farmLists',
        'merchantsRun3' => 'merchantsRun3',
        'automaticTrade' => 'automaticTrade',
        'cropFinder' => 'cropFinder',
        'messageArchive' => 'messageArchive',
    ];

    private function __construct()
    {
    }

    /** The icon the card ends with; its tooltip is `$html` (see the three builders below). */
    public static function icon(string $html): string
    {
        return '<div class="tooltipHelpIcon bonusExplanation" title="' . htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
            . self::ICON_SVG . '</div>';
    }

    public static function plus(): string
    {
        return self::features('plus', 'OpenTravian Plus', 'plus', self::PLUS_ROWS);
    }

    public static function goldClub(): string
    {
        return self::features('goldclub', (string)T('PaymentWizard', 'goldClub'), 'goldClub', self::GOLD_CLUB_ROWS);
    }

    /**
     * @param string $resource lumber, clay, iron or crop
     * @param string $durationText the card's duration, e.g. "24 hours"
     */
    public static function productionBoost(string $resource, string $heading, string $durationText): string
    {
        $help = static fn (string $key): string => (string)T('PaymentWizard', 'help.productionBoost.' . $key);
        $bonus = "\u{202D}+\u{202D}25\u{202C}%\u{202C}";

        return '<div class="contentV2"><div class="tooltipHelpContainer advantage productionBoost ' . $resource . 'ProductionBonus">'
            . '<h3>' . $heading . '</h3>'
            . '<span>' . $help($resource) . '<br>' . $help('appliesToAll') . '</span>'
            . '<div class="helpText"><span class="bonusIcon">'
            . ProductionBoostArrow::svg('advantageBonusArrow premiumFeature', 'svg-' . substr(bin2hex(random_bytes(4)), 0, 6)) . '</span>'
            . '<strong class="title">' . sprintf($help('title'), $bonus, $durationText) . '</strong>'
            . '<span class="desc">' . sprintf($help('gold'), $bonus) . '</span></div>'
            . '</div></div>';
    }

    /**
     * @param array<string, string> $rows
     */
    private static function features(string $class, string $heading, string $section, array $rows): string
    {
        $html = '<div class="contentV2"><div class="tooltipHelpContainer advantage ' . $class . '"><h3>' . $heading . '</h3>';
        foreach ($rows as $icon => $key) {
            $html .= '<div class="helpText"><span class="bonusIcon ' . $icon . '"></span>'
                . '<strong class="title">' . T('PaymentWizard', 'help.' . $section . '.' . $key . '.title') . '</strong>'
                . '<span class="desc">' . T('PaymentWizard', 'help.' . $section . '.' . $key . '.text') . '</span></div>';
        }

        return $html . '</div></div>';
    }
}
