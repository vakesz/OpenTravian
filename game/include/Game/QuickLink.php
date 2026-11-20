<?php

namespace Game;

use Core\Session;
use Core\Village;
use Core\Database\DB;
use Game\AllianceBonus\AllianceBonus;
use Model\ResourceFieldsModel;
use Model\MarketModel;
use Model\TrainingModel;

/**
 * Village-list quick-link tooltips. The type keys, titles and copy follow the
 * reference `tooltip/quickLink` responses; the queue summaries reuse the local
 * building-page wording.
 */
class QuickLink
{
    private const TRAINING_GIDS = [
        'BarracksManagement' => Gid::BARRACKS,
        'GreatBarracksManagement' => Gid::GREAT_BARRACKS,
        'StableManagement' => Gid::STABLE,
        'GreatStableManagement' => Gid::GREAT_STABLE,
        'WorkshopManagement' => Gid::WORKSHOP,
        'TrapperManagement' => Gid::TRAPPER,
    ];

    private const FIXED_TITLES = [
        'MarketplaceBuy' => 'toMarketplaceBuy',
        'MarketplaceSendResources' => 'toMarketplaceSendResources',
        'MarketplaceTradeRoutes' => 'toMarketplaceTradeRoutes',
        'RallyPointFarmList' => 'toRallyPointFarmList',
        'RallyPointOverview' => 'toRallyPointOverview',
        'RallyPointSendTroops' => 'toRallyPointSendTroops',
    ];

    public static function isKnownType(string $type): bool
    {
        return array_key_exists($type, self::TRAINING_GIDS)
            || array_key_exists($type, self::FIXED_TITLES)
            || in_array($type, ['BlacksmithManagement', 'ExpansionTrain', 'HerosMansionManagement', 'HospitalManagement', 'TownHallCelebration'], true);
    }

    /** Tooltip title for a quick link whose building (gid) exists in the village; '' otherwise. */
    public static function title(string $type, int $gid): string
    {
        if ($gid <= 0) {
            return '';
        }
        if (isset(self::FIXED_TITLES[$type])) {
            return T('quickLinks', self::FIXED_TITLES[$type]);
        }
        $name = $type === 'BlacksmithManagement' ? T('quickLinks', 'blacksmith') : T('Buildings', $gid . '.title');

        return str_replace("'", "\u{2019}", sprintf(T('quickLinks', 'toBuilding'), $name));
    }

    /** @return array{title: string, text: string} */
    public static function tooltip(string $type): array
    {
        $village = Village::getInstance();
        $gid = self::gidFor($type, $village);
        if ($gid <= 0) {
            return ['title' => '', 'text' => ''];
        }
        $kid = (int) Session::getInstance()->getKid();
        $text = '';
        if (isset(self::TRAINING_GIDS[$type])) {
            $text = self::trainingText($kid, $gid);
        } elseif ($type === 'ExpansionTrain') {
            $text = self::trainingText($kid, $gid);
        } elseif ($type === 'BlacksmithManagement') {
            $end = (int) DB::getInstance()->fetchScalar("SELECT MAX(end_time) FROM research WHERE kid=$kid AND mode=" . ResearchMode::SMITHY);
            $text = $end > time()
                ? sprintf(T('quickLinks', 'upgradingTime'), secondsToString($end - time()))
                : T('quickLinks', 'notUpgrading');
        } elseif ($type === 'TownHallCelebration') {
            $end = (int) $village->getCelebration();
            $text = $end > time()
                ? sprintf(T('quickLinks', 'celebratingTime'), secondsToString($end - time()))
                : T('quickLinks', 'notCelebrating');
        } elseif ($type === 'MarketplaceBuy' || $type === 'MarketplaceSendResources') {
            $text = self::merchantsText();
        } elseif ($type === 'RallyPointFarmList') {
            $text = sprintf(T('quickLinks', 'attacksOnTheWay'), (int) (new ResourceFieldsModel())->getOutgoingAttacks($kid)['count']);
        } elseif ($type === 'RallyPointOverview') {
            $m = new ResourceFieldsModel();
            $incoming = (int) $m->getIncomingAttacksToMe($kid)['count'] + (int) $m->getIncomingReinforcementToMe($kid)['count'];
            $outgoing = (int) $m->getOutgoingAttacks($kid)['count'] + (int) $m->getOutGoingReinforcements($kid)['count'];
            $text = sprintf(T('quickLinks', 'currentlyIncoming'), $incoming) . "<br />\n"
                . sprintf(T('quickLinks', 'currentlyOutgoing'), $outgoing);
        }

        return ['title' => self::title($type, $gid), 'text' => $text];
    }

    /** "Available merchants: free/total" with the reference's bidi isolation marks. */
    public static function merchantsText(): string
    {
        $village = Village::getInstance();
        $session = Session::getInstance();
        $allianceBonus = 1;
        if ($session->getAllianceId() > 0) {
            $allianceBonus = AllianceBonus::getTradersBonus($session->getAllianceId(), $session->getAllianceJoinTime());
        }
        $cap = Formulas::merchantCAP($session->getRace(), max($village->getTypeLevel(Gid::TRADE_OFFICE)), $allianceBonus);
        $total = max($village->getTypeLevel(Gid::MARKETPLACE));
        $market = new MarketModel();
        $kid = $village->getKid();
        $free = $total - $market->getOnTheWayMerchantsCount($kid, $cap) - $market->getOfferingMerchantsCount($kid, $cap);

        return sprintf(T('quickLinks', 'availableMerchants'), "\u{202D}\u{202D}" . $free . "\u{202C}/\u{202D}" . $total . "\u{202C}\u{202C}");
    }

    private static function trainingText(int $kid, int $gid): string
    {
        $time = trainingTimeDisplaySeconds((new TrainingModel())->getTotalTrainingTime($kid, $gid));
        if ($time === 0) {
            return T('inGame', 'noTroopInBeingTrained');
        }

        return sprintf(T('inGame', 'TrainingTime'), secondsToString($time));
    }

    private static function gidFor(string $type, Village $village): int
    {
        $map = self::TRAINING_GIDS + [
            'BlacksmithManagement' => Gid::SMITHY,
            'RallyPointFarmList' => Gid::RALLY_POINT,
            'RallyPointOverview' => Gid::RALLY_POINT,
            'RallyPointSendTroops' => Gid::RALLY_POINT,
            'MarketplaceBuy' => Gid::MARKETPLACE,
            'MarketplaceSendResources' => Gid::MARKETPLACE,
            'MarketplaceTradeRoutes' => Gid::MARKETPLACE,
            'TownHallCelebration' => Gid::TOWN_HALL,
            'HerosMansionManagement' => Gid::HEROS_MANSION,
        ];
        if ($type === 'HospitalManagement') {
            foreach ([Gid::HOSPITAL, Gid::ASCLEPEION] as $candidate) {
                if ((int) $village->findBuildingByGid($candidate) > 0) {
                    return $candidate;
                }
            }

            return 0;
        }
        if ($type === 'ExpansionTrain') {
            foreach (Gid::EXPANSION as $candidate) {
                if ((int) $village->findBuildingByGid($candidate) > 0) {
                    return $candidate;
                }
            }

            return 0;
        }
        $gid = $map[$type] ?? 0;

        return $gid > 0 && (int) $village->findBuildingByGid($gid) > 0 ? $gid : 0;
    }
}
