<?php

declare(strict_types=1);

namespace Core\Helper;

use Core\Caching\Caching;
use Core\Config;
use Core\Session;
use function json_decode;
use function json_encode;

class PreferencesHelper
{
    /**
     * Ships four ActiveVillage quick links above the village name
     * (Marketplace send, Barracks, Stable, Workshop). Five slots exist; the
     * fifth stays empty until the player customizes via the ⋯ dialog.
     */
    public const DEFAULT_VILLAGE_QUICK_LINKS = '["MarketplaceSendResources","BarracksManagement","StableManagement","WorkshopManagement"]';

    private static bool $populated = false;
    /** @var array<string, mixed> */
    private static array $currentPreferences = [
        'QuestDialogAchievementPosition' => -1,
        'QuestDialogPosition' => -1,
        'travian_toggle_hero' => 'expanded',
        'travian_toggle_infobox' => 'expanded',
        'travian_toggle_alliance' => 'expanded',
        'mobileUnitDisplay' => 'collapsed',
        'allianceBonusesOverview' => [
            'bonusInfo0' => false,
            'bonusInfo1' => false,
            'bonusInfo2' => false,
            'bonusInfo3' => false
        ],
        'WMBlueprints' => "[]",
        'villageQuickLinks' => self::DEFAULT_VILLAGE_QUICK_LINKS,
        'buildingDescriptionCollapsed' => false,
        'markLayer-player-alliance-expanded' => false,
        'markLayer-player-player-expanded' => false,
        'minimap-expanded' => false,
        'outline-expanded' => false
    ];
    /** @var array<string, mixed> */
    private static array $preferences = [
        'travian_toggle_hero' => 'expanded',
        'travian_toggle_infobox' => 'expanded',
        'travian_toggle_alliance' => 'expanded',
        'mobileUnitDisplay' => 'collapsed',
        'allianceBonusesOverview' => [
            'bonusInfo0' => false,
            'bonusInfo1' => false,
            'bonusInfo2' => false,
            'bonusInfo3' => false
        ],
        'WMBlueprints' => "[]",
        'villageQuickLinks' => self::DEFAULT_VILLAGE_QUICK_LINKS,
        'buildingDescriptionCollapsed' => false,
        'markLayer-player-alliance-expanded' => false,
        'markLayer-player-player-expanded' => false,
        'minimap-expanded' => false,
        'outline-expanded' => false
    ];

    /**
     * @param mixed $value raw request value; each preference validates its own shape
     */
    public static function setPreference(string $key, mixed $value): void
    {
        self::getPreferences();
        $session_cookie_value = &self::$currentPreferences;
        switch ($key) {
            case 'allianceBonusesOverview':
                $value = json_decode(is_string($value) ? $value : '', true);
                $session_cookie_value[$key] = [
                    'bonusInfo0' => isset($value['bonusInfo0']) && $value['bonusInfo0'],
                    'bonusInfo1' => isset($value['bonusInfo1']) && $value['bonusInfo1'],
                    'bonusInfo2' => isset($value['bonusInfo2']) && $value['bonusInfo2'],
                    'bonusInfo3' => isset($value['bonusInfo3']) && $value['bonusInfo3'],
                ];
                break;
            case 'tradeRoutesOrder':
                if (in_array($value, ['1a', '1d', '2a', '2d', '3a', '3d'], true)) {
                    $session_cookie_value[$key] = $value;
                }
                break;
            case 'QuestDialogAchievementPosition':
            case 'QuestDialogPosition':
            case 'WMBlueprints':
                if (self::isJSON($value)) {
                    $session_cookie_value[$key] = (string)json_encode(json_decode((string) $value, true),
                        JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
                }
                break;
            case 'villageQuickLinks':
                $allowedQuickLinks = [
                    'BlacksmithManagement',
                    'RallyPointOverview',
                    'RallyPointSendTroops',
                    'RallyPointFarmList',
                    'MarketplaceSendResources',
                    'MarketplaceTradeRoutes',
                    'MarketplaceBuy',
                    'BarracksManagement',
                    'GreatBarracksManagement',
                    'StableManagement',
                    'GreatStableManagement',
                    'WorkshopManagement',
                    'TownHallCelebration',
                    'TrapperManagement',
                    'HerosMansionManagement',
                    'HospitalManagement',
                    'ExpansionTrain',
                ];
                $links = json_decode((string) $value, true);
                if (is_array($links)) {
                    $links = array_values(array_unique(array_filter(
                        $links,
                        static fn ($link): bool => is_string($link) && in_array($link, $allowedQuickLinks, true)
                    )));
                    // Five `.quickLinkSlot` rings on ActiveVillage / village list.
                    $session_cookie_value[$key] = json_encode(array_slice($links, 0, 5));
                }
                break;
            case 'reports_tab_offensive':
            case 'reports_tab0':
            case 'reports_tab1':
            case 'reports_tab2':
            case 'reports_tab3':
            case 'reports_tab6':
                break;
            case 'travian_toggle_hero':
            case 'travian_toggle_infobox':
            case 'travian_toggle_alliance':
            case 'mobileUnitDisplay':
                $session_cookie_value[$key] = $value === 'expanded' ? 'expanded' : 'collapsed';
                break;
            case 'buildingDescriptionCollapsed':
            case 'markLayer-player-alliance-expanded':
            case 'markLayer-player-player-expanded':
            case 'minimap-expanded':
            case 'outline-expanded':
            	$session_cookie_value[$key] = (bool)$value;
                break;
            case 'lastProductID':
            case 'lastUsedPaymentMethod':
            	$session_cookie_value[$key] = (int)$value;
                break;
            default:
                // Auction lists: the layout of each list, the grouped buy view and "show more" (reference keys).
                if (preg_match('/^(?:auctionsBuy|auctionsBuyItemTypeDetail|ongoingSellList|auctionsOverviewBids|auctionsOverviewSold)_listLayout$/', $key) === 1) {
                    if (in_array($value, ['detailed', 'compact'], true)) {
                        $session_cookie_value[$key] = $value;
                    }
                    break;
                }
                // The time the player ticked "Don't show this message again" on the auction fee confirmation.
                if ($key === \Game\Hero\AuctionFee::PREFERENCE_CONFIRMED) {
                    if (is_numeric($value) && (int)$value > 0) {
                        $session_cookie_value[$key] = (string)(int)$value;
                    } else {
                        unset($session_cookie_value[$key]);
                    }
                    break;
                }
                // The stack size of an item's auction list; clearing it (null) shows every stack again.
                if ($key === \Game\Hero\AuctionListState::PREFERENCE_BATCH_SIZE) {
                    if (in_array((int)$value, \Game\Hero\AuctionListState::BATCH_SIZES, true)) {
                        $session_cookie_value[$key] = (string)(int)$value;
                    } else {
                        unset($session_cookie_value[$key]);
                    }
                    break;
                }
                if (preg_match('/^(?:auctionsBuy_viewGroupedByItemType|auctionsSell_showMore|auctionsOverviewBids_showMore|auctionsOverviewSold_showMore)$/', $key) === 1) {
                    $session_cookie_value[$key] = $value === 'true' || $value === true;
                    break;
                }
                // Collapsed state of one village group: only the collapsed state is kept.
                if (preg_match('/^villageListGroup[1-9][0-9]{0,9}_collapsed$/', $key) === 1) {
                    if ($value === null || $value === '' || $value === 'null' || $value === 'false') {
                        unset($session_cookie_value[$key]);
                    } else {
                        $session_cookie_value[$key] = true;
                    }
                }
        }
        Caching::getInstance()->set("preferences:" . self::getCacheKey(), $session_cookie_value, 86400);
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPreferences(): array
    {
        if (!Session::getInstance()->isValid()) {
            return self::$preferences;
        }
        if (!self::$populated) {
            self::$populated = true;
            $cache = Caching::getInstance()->get('preferences:' . self::getCacheKey());
            if (is_array($cache) && $cache !== []) {
                self::$currentPreferences = $cache;
            } else {
                self::reSetT3E();
                self::$currentPreferences = self::$preferences;
                Caching::getInstance()->set("preferences:" . self::getCacheKey(), self::$currentPreferences, 3 * 86400);
            }
        }
        return self::$currentPreferences;
    }

    private static function getCacheKey(): string
    {
        return Session::getInstance()->getPlayerId() . ':' . self::getRandKey();
    }

    public static function getRandKey(): int
    {
        $cookie = self::getKey();
        $int = is_scalar($_COOKIE[$cookie] ?? null) ? (int) $_COOKIE[$cookie] : 0;
        if ($int > 9999999 || $int < 1111111) {
            $int = mt_rand(1111111, 9999999);
            $_COOKIE[$cookie] = (string) $int;
            setcookie($cookie, (string) $int, time() + 3 * 86400);
        }

        return $int;
    }

    private static function getKey(): string
    {
        return WebService::fixSessionPrefix("T3E");
    }

    public static function reSetT3E(): void
    {
        Caching::getInstance()->delete("preferences:" . self::getCacheKey());
        Caching::getInstance()->set("preferences:" . self::getCacheKey(), self::$preferences, 3 * 86400);
    }

    public static function isJSON(mixed $string): bool
    {
        return is_string($string) && is_array(json_decode($string, true));
    }

    public static function getPreference(string $key): mixed
    {
        self::getPreferences();
        if ($key === 'tradeRoutesOrder' && !isset(self::$currentPreferences[$key])){
            return '2d';
        }
        if ($key === 'villageQuickLinks') {
            $stored = self::$currentPreferences[$key] ?? null;
            $decoded = is_string($stored) ? json_decode($stored, true) : null;
            // Legacy caches and the old helper default used "[]", which left the
            // ActiveVillage header empty. Treat unset/empty as the default set.
            if (!is_array($decoded) || $decoded === []) {
                return self::DEFAULT_VILLAGE_QUICK_LINKS;
            }

            return json_encode(array_values($decoded));
        }
        return self::$currentPreferences[$key] ?? '';
    }
}
